# Laravel Import

Package Laravel genérico e independente de domínio para **importações assíncronas de CSV/Excel**. É um
*framework* de importação, não um importador de Excel: o package trata do upload, leitura em streaming,
chunks, filas, transações, transmissão de progresso, erros, repetições, cancelamento, notificações,
multi-inquilino e observabilidade — a sua aplicação apenas implementa o importador do domínio.

Funciona com ficheiros pequenos, médios e grandes (milhões de linhas) com baixo consumo de memória.

## Funcionalidades

- CSV (`.csv`), Excel (`.xlsx`, `.xls`) com leitura em streaming/chunks — nunca carrega um ficheiro inteiro em memória
- Janelas de linhas via filtros de leitura (`FiltroJanelaLeitura`)
- Totalmente assíncrono: o pedido de upload apenas guarda o ficheiro e despacha um job
- Pipeline de chunks: `ProcessarImporte` → `ProcessarImporteChunk` (×N, sequencial) → `FinalizarImporte`
- Uma transação de base de dados por chunk (sem transações gigantes, sem locks prolongados)
- Modos de importação parcial e estrito; recolha de erros por linha em `import_errors`
- Processamento idempotente de chunks (guarda de estado + lock de linha + transação)
- Repetição automática de falhas transitórias (deadlocks, perda de conexão) via `ClassificadorRepeticao`
- Repetição manual de importações inteiras ou de chunks falhados
- Cancelamento gracioso (`cancelling` → `cancelled`, nunca a meio de uma transação)
- Progresso em tempo real via Broadcasting do Laravel (Reverb/Pusher/Soketi) em `private-importes.{uuid}`
- Transmissões limitadas (registos/segundos) — nunca uma transmissão por linha
- Notificações do Laravel (canais database, mail, broadcast) ao concluir
- Simulação / pré-visualização sem persistir
- Mapeamento de colunas e aliases, ficheiros sem cabeçalho, deteção automática de delimitador/codificação (UTF-8, UTF-8 BOM, Windows-1252, ISO-8859-1)
- Preparado para multi-inquilino (agnóstico; adaptador incluído para `spatie/laravel-multitenancy`); contexto de inquilino restaurado nos workers
- Abstração de autorização (`AutorizacaoImporte`)
- Exportação de erros para CSV/XLSX; comando de limpeza (`php artisan importes:podar`)
- Limites configuráveis (tamanho do ficheiro, linhas, erros, concorrência)
- Fila Redis recomendada em produção, com suporte ao Horizon

## Instalação

```bash
composer require kuteka/laravel-import
```

Publique a configuração e as migrações:

```bash
php artisan vendor:publish --tag=importe-config
php artisan vendor:publish --tag=importe-migrations   # opcional: as suas próprias cópias
```

Execute as migrações (cria `imports`, `import_chunks`, `import_errors` e, quando em falta, a
tabela padrão `notifications` para o canal de notificação database):

```bash
php artisan migrate
```

## Início rápido

```php
use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\Enums\EstrategiaDuplicado;
use Kuteka\LaravelImport\Enums\ModoImporte;
use Kuteka\LaravelImport\Services\GerenciadorImporte;

$importe = GerenciadorImporte::criar(
    arquivo: $pedido->file('arquivo'),
    importador: ImportadorProduto::class,
    opcoes: OpcoesImporte::criar()
        ->tamanhoChunk(1000)
        ->fila('imports')
        ->modo(ModoImporte::Parcial)
        ->estrategiaDuplicado(EstrategiaDuplicado::Upsert)
        ->transmitirProgresso(true),
);

// A importação é processada em background.
$status = GerenciadorImporte::status($importe->uuid);
```

API estática útil:

```php
GerenciadorImporte::criar(...)      // UploadedFile | caminho string + importador
GerenciadorImporte::simular(...)    // pré-visualização: cabeçalhos, amostras, erros — nada persistido
GerenciadorImporte::status($uuid)
GerenciadorImporte::cancelar($uuid)
GerenciadorImporte::repetir($uuid, EscopoRepeticao::Inteiro /* ou ChunksFalhados */)
GerenciadorImporte::excluir($uuid)
```

## Criar um importador

```php
use Kuteka\LaravelImport\Importers\ImportadorAbstrato;

class ImportadorPaciente extends ImportadorAbstrato
{
    public function regras(): array
    {
        return [
            'nome' => ['required', 'string'],
            'nif' => ['nullable', 'string'],
            'telefone' => ['nullable', 'string'],
        ];
    }

    public function processar(array $linha): void
    {
        Paciente::updateOrCreate(['nif' => $linha['nif']], $linha);
    }

    public function chaveUnica(array $linha): mixed
    {
        return $linha['nif'] ?? null;
    }

    public function cabecalhos(): array
    {
        return ['nome', 'nif', 'telefone'];
    }

    public function opcoes(): array
    {
        return [
            'tem_cabecalho' => true,
            'aliases' => ['nome' => ['Nome Completo', 'full_name', 'name']],
        ];
    }
}
```

**Processamento em lote** — implemente `LoteImportador` e o motor chama `processarLote(array $linhas)`
uma vez por chunk, em vez de linha a linha:

```php
use Kuteka\LaravelImport\Contracts\LoteImportador;
use Kuteka\LaravelImport\Importers\ImportadorAbstrato;

class ImportadorProduto extends ImportadorAbstrato implements LoteImportador
{
    public function processarLote(array $linhas): void
    {
        Produto::query()->upsert($linhas, ['sku'], ['nome', 'preco', 'stock']);
    }
}
```

Mantenha os importadores **idempotentes** (upsert/updateOrCreate pela `chaveUnica`): os jobs de chunk
podem ser repetidos após uma falha e a transação do chunk é reexecutada.

Hooks de ciclo de vida (opcionais): `preparar(array $linha)`, `aoIniciarImporte()`, `aoFinalizarImporte()`.

## Como funciona o pipeline

```text
Upload
   ↓ o pedido HTTP termina aqui
ProcessarImporte (fila)
   ├─ valida o ficheiro (extensão, MIME, tamanho, cabeçalhos, colunas obrigatórias)
   ├─ conta as linhas, aplica maximo_linhas
   ├─ cria os registos de import_chunks
   └─ despacha o chunk #1
ProcessarImporteChunk (por chunk, sequencial)
   ├─ ignora chunks concluídos (idempotência)
   ├─ BEGIN
   │    ├─ lê a janela de linhas (streaming)
   │    ├─ mapeia + valida + processa
   │    ├─ regista import_errors
   │    └─ atualiza os contadores do chunk
   ├─ COMMIT (ROLLBACK em erro crítico)
   ├─ atualiza os contadores da importação, transmite progresso limitado
   └─ despacha o próximo chunk ou FinalizarImporte
FinalizarImporte
   ├─ estado final: completed | completed_with_errors | cancelled
   ├─ dispara o evento final, notifica o utilizador, liberta o bloqueio da importação
```

- **Modo parcial** (padrão): erros por linha nunca interrompem a importação → `completed_with_errors`.
- **Modo estrito** (`modo(ModoImporte::Estrito)` ou `parar_em_erro(true)`): a primeira linha inválida
  aborta o chunk atual (revertido), os erros são persistidos e a importação falha.
- `maximo_erros` aborta a importação com `failed` quando excedido.

## Configuração da fila

Todo o processamento passa pela fila do Laravel. Configure globalmente em `config/importe.php`:

```php
'queue' => [
    'connection' => env('IMPORT_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'redis')),
    'queue' => env('IMPORT_QUEUE', 'imports'),
],
'tries' => 3, 'timeout' => 600, 'backoff' => 3,
```

Por importação: `OpcoesImporte::criar()->fila('imports')->conexaoFila('redis')->tentativas(5)->atraso(10)`.

Execute o worker para a fila dedicada:

```bash
php artisan queue:work redis --queue=imports --tries=3 --timeout=600
```

### Redis + Horizon (recomendado em produção)

```env
QUEUE_CONNECTION=redis
IMPORT_QUEUE_CONNECTION=redis
```

```php
// config/horizon.php
'environments' => [
    'production' => [
        'imports-worker' => [
            'connection' => 'redis',
            'queue' => ['imports'],
            'balance' => 'auto',
            'processes' => 1,
            'tries' => 3,
            'timeout' => 600,
            'memory' => 256,
        ],
    ],
],
```

O Horizon é opcional; as filas database/redis/sqs funcionam todas.

## Progresso em tempo real (Reverb / Broadcasting)

O progresso é transmitido no canal **privado** `importes.{uuid}`. O package depende apenas do
Broadcasting do Laravel; use Reverb, Pusher ou Soketi como driver.

```php
// config/importe.php
'broadcast' => [
    'enabled' => true,
    'throttle_seconds' => 1.0,
    'throttle_records' => 250,
],
```

O canal privado é registado automaticamente e autorizado através do seu
`AutorizacaoImporte::podeVer()` — os utilizadores só ouvem as suas próprias importações.

Eventos transmitidos: `ImporteIniciado`, `ImporteProgressoAtualizado`, `ImporteConcluido`,
`ImporteConcluidoComErros`, `ImporteFalhou`, `ImporteCancelado`. Payload de progresso:

```json
{
    "importe_id": "01J...",
    "status": "processing",
    "total_linhas": 100000,
    "linhas_processadas": 72000,
    "linhas_sucesso": 71800,
    "linhas_falhas": 200,
    "progresso": 72,
    "chunk_atual": 72,
    "total_chunks": 100
}
```

### Exemplo Vue + Laravel Echo

```bash
composer require laravel/reverb
npm install laravel-echo pusher-js
```

```ts
import Echo from 'laravel-echo'

const echo = new Echo({ broadcaster: 'reverb', key: import.meta.env.VITE_REVERB_APP_KEY, wsHost: import.meta.env.VITE_REVERB_HOST })

export function escutarImporte(importeUuid: string, handlers: Record<string, (dados: any) => void>) {
    return echo.private(`importes.${importeUuid}`)
        .listen('ImporteProgressoAtualizado', handlers.noProgresso ?? (() => {}))
        .listen('ImporteConcluido', handlers.noConcluido ?? (() => {}))
        .listen('ImporteConcluidoComErros', handlers.noConcluidoComErros ?? (() => {}))
        .listen('ImporteFalhou', handlers.noFalhou ?? (() => {}))
        .listen('ImporteCancelado', handlers.noCancelado ?? (() => {}))
}
```

```vue
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

const progresso = ref(0)
const processadas = ref(0)
const status = ref('')

onMounted(() => {
    canal = escutarImporte(props.importeUuid, {
        noProgresso: (dados) => { progresso.value = dados.progresso; processadas.value = dados.linhas_processadas; status.value = dados.status },
        noConcluido: () => status.value = 'completed',
        noFalhou: () => status.value = 'failed',
    })
})
</script>

<template>
    <div>
        <p>Importando produtos... {{ processadas }} registos</p>
        <div class="h-3 w-full rounded bg-gray-200">
            <div class="h-3 rounded bg-emerald-500" :style="{ width: progresso + '%' }"></div>
        </div>
        <p>{{ status }}</p>
    </div>
</template>
```

## Notificações

Quando `notificar_usuario` está ativo (padrão), o dono recebe uma `ImporteFinalizadoNotification` pelos
canais configurados (`database`, `mail`, `broadcast` — veja `config('importe.notifications.channels')`).

```php
OpcoesImporte::criar()->notificarUsuario(true)->canaisNotificacao(['database', 'mail'])
```

O mail usa o template markdown padrão de notificação do Laravel — não é necessária nenhuma view
personalizada. O canal `database` requer a tabela padrão `notifications` (o package cria-a quando falta).

## Erros

`GET /importes/{uuid}/erros` (JSON paginado) e `GET /importes/{uuid}/erros/exporte`
(`?format=csv|xlsx`) — a exportação inclui o número da linha original, os dados originais da linha, o
campo, o valor, o código do erro e a mensagem. Os erros nunca são guardados como JSON dentro de `imports`.

## Rotas HTTP (opcionais)

Ativas por padrão (`config('importe.routes.enabled')`), prefixo `/importes`, middleware `web`+`auth`
(configurável):

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/importes` | importações do utilizador (paginadas) |
| POST | `/importes` | upload + criação (`file`, `importer`, `options` opcional) |
| GET | `/importes/{uuid}` | estado/resultado |
| POST | `/importes/{uuid}/cancelar` | pedir cancelamento |
| POST | `/importes/{uuid}/repetir?scope=entire\|failed_chunks` | repetir |
| GET | `/importes/{uuid}/erros` | listar erros |
| GET | `/importes/{uuid}/erros/exporte` | baixar erros (csv/xlsx) |
| DELETE | `/importes/{uuid}` | excluir importação + ficheiro |

Em aplicações Inertia/Livewire/Blade pode desativar estas rotas e chamar `GerenciadorImporte` diretamente.

## Cancelamento

`GerenciadorImporte::cancelar($uuid)` define `cancelling`. Os workers verificam a flag entre chunks,
terminam a transação atual e param. Nunca mata uma transação a meio.

## Repetição

- Falhas transitórias (deadlock, lock wait timeout, perda de conexão) são repetidas automaticamente
  conforme as `tentativas`/`atraso` do job — erros de validação **nunca** são repetidos.
- Manual: `GerenciadorImporte::repetir($uuid)` (importação inteira) ou
  `GerenciadorImporte::repetir($uuid, EscopoRepeticao::ChunksFalhados)`.

## Multi-inquilino

O package é agnóstico em relação à biblioteca de inquilinos. Guarda `tenant_id` e restaura o contexto
de inquilino dentro de cada job através do contrato `ContextoInquilino` — nunca através da sessão.

```php
// config/importe.php
'tenancy' => [
    'adapter' => \Kuteka\LaravelImport\Tenancy\ContextoInquilinoSpatie::class, // ou o seu
],
```

O `ContextoInquilinoSpatie` incluído funciona com `spatie/laravel-multitenancy` e é um no-op seguro
sem ela. Aplicações de inquilino único usam o padrão `ContextoInquilinoNulo`.

## Autorização e modelo de utilizador

O package nunca assume `App\Models\User`:

```php
// config/importe.php
'user' => ['model' => \App\Models\User::class],
'authorization' => \Kuteka\LaravelImport\Support\AutorizacaoImportePadrao::class,
```

Implemente `Kuteka\LaravelImport\Contracts\AutorizacaoImporte` para regras próprias (papéis, planos, ...).
O padrão permite que os donos (e a CLI) vejam/cancelem/baixem/excluam as suas próprias importações.

## Segurança

- Extensão **e** tipo MIME validados; tamanho do ficheiro, máximo de linhas e de erros aplicados
- Ficheiros guardados com nomes ULID via o Filesystem do Laravel — a entrada do utilizador nunca é concatenada em caminhos
- Nenhum caminho físico é exposto através de APIs ou payloads WebSocket
- Canais de transmissão privados com autorização por utilizador
- Backpressure: `max_concurrent_imports` (por utilizador), fila dedicada

## Limites

```php
// config/importe.php
'max_file_size' => 100 * 1024 * 1024, // 100 MB
'max_rows' => 1_000_000,
'max_errors' => 100_000,
'chunk_size' => 1000,
'max_concurrent_imports' => 0, // 0 = ilimitado
```

## Retenção e limpeza

```bash
php artisan importes:podar --dias=30
```

Remove importações terminadas (mais chunks, erros e ficheiros) mais antigas que o período de retenção
(`config('importe.retention.days')`). Importações pendentes/em processamento nunca são tocadas. Ative o
agendamento opcional com `config('importe.retention.schedule' => true)`.

## Observabilidade

Os logs estruturados incluem `import_id`, `tenant_id`, `user_id`, `chunk_id`, estado, duração e
contadores de linhas (sem dados sensíveis de linhas). O evento `ImporteChunkProcessado` é disparado após
cada chunk para auditoria e métricas próprias; integre Telescope/Sentry/Grafana sem dependências extra.

## Notas de performance

- CSV/XLSX são lidos em modo streaming com filtros de leitura por janela de linhas; a memória mantém-se estável independentemente do tamanho do ficheiro
- `.xls` (BIFF) não pode ser transmitido — o ficheiro inteiro é carregado; prefira `.xlsx`/`.csv` para ficheiros grandes
- Use `LoteImportador` + upsert para o máximo de throughput
- `total_rows` de XLSX vem dos metadados da folha (`listWorksheetInfo`) — sem iterar células
- Linhas completamente vazias são contadas como ignoradas, não como falhas

## Testes

```bash
# suíte de testes do package (97 testes: unitários + funcional + fila + broadcasting + inquilinos)
php vendor/bin/pest --configuration ../laravel-import/phpunit.xml
```

Cobertos: leitores CSV/XLSX, deteção de delimitador/codificação, DTO de opções, mapeamento/validação,
transação por chunk, modo estrito, idempotência, limites máximos, cancelamento, repetição,
notificações, eventos de transmissão/canal privado, restauração de contexto de inquilino, endpoints HTTP
e exportação de erros.

## Resolução de problemas

| Problema | Solução |
|----------|---------|
| Importação presa em `pending` | Execute um worker para a fila configurada (`queue:work redis --queue=imports`) |
| `processing` nunca termina | Verifique `laravel.log` e a tabela de jobs falhados; o bloqueio expira automaticamente após 24h |
| Transmissões não chegam | Verifique `importe.broadcast.enabled`, a autorização do canal e a sua configuração Echo/Reverb |
| Notificação database em falta | Garanta que a tabela `notifications` existe (`php artisan migrate`) |
| `completed_with_errors` | Consulte `GET /importes/{uuid}/erros` e exporte o relatório para corrigir o ficheiro e reimportar |
