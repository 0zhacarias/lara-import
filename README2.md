# Laravel Import — Guia rápido de utilização

Resumo prático. Para detalhes completos, consulte o `README.md`.

Package Laravel para **importações assíncronas de CSV/Excel** (streaming, chunks, filas, progresso em
tempo real, erros por linha, repetição, cancelamento e multi-inquilino). A sua aplicação só implementa o
importador do domínio.

## Requisitos

- **PHP** `^8.3`
- **Laravel** `13.x`
- `maatwebsite/excel` `^3.1` (dependência automática)
- Fila **Redis** recomendada em produção (`ext-redis`); database/sqs também funcionam
- Opcionais: `laravel/reverb` (progresso em tempo real), `laravel/horizon` (gestão da fila),
  `spatie/laravel-multitenancy` (multi-inquilino)

## Instalação

```bash
composer require milonga/laravel-import

php artisan vendor:publish --tag=importe-config
php artisan vendor:publish --tag=importe-migrations   # opcional

php artisan migrate
```

## Início rápido

```php
use Kuteka\LaravelImport\DTOs\OpcoesImporte;
use Kuteka\LaravelImport\Enums\ModoImporte;
use Kuteka\LaravelImport\Enums\EstrategiaDuplicado;
use Kuteka\LaravelImport\Services\GerenciadorImporte;

$importe = GerenciadorImporte::criar(
    arquivo: $pedido->file('arquivo'),
    importador: ImportadorProduto::class,
    opcoes: OpcoesImporte::criar()
        ->tamanhoChunk(1000)
        ->fila('imports')
        ->modo(ModoImporte::Parcial)
        ->estrategiaDuplicado(EstrategiaDuplicado::Upsert),
);

$status = GerenciadorImporte::status($importe->uuid);
```

API estática:

```php
GerenciadorImporte::criar(...)      // UploadedFile | caminho + importador
GerenciadorImporte::simular(...)    // pré-visualização sem persistir
GerenciadorImporte::status($uuid)
GerenciadorImporte::cancelar($uuid)
GerenciadorImporte::repetir($uuid, EscopoRepeticao::Inteiro /* ou ChunksFalhados */)
GerenciadorImporte::excluir($uuid)
```

## Criar um importador

```php
use Kuteka\LaravelImport\Importers\ImportadorAbstrato;
use Kuteka\LaravelImport\Contracts\LoteImportador;

class ImportadorProduto extends ImportadorAbstrato implements LoteImportador
{
    public function regras(): array
    {
        return ['nome' => ['required', 'string'], 'sku' => ['required', 'string']];
    }

    // Lote: chamado uma vez por chunk (recomendado para throughput)
    public function processarLote(array $linhas): void
    {
        Produto::query()->upsert($linhas, ['sku'], ['nome', 'preco']);
    }

    // Ou, linha a linha:
    // public function processar(array $linha): void
    // {
    //     Produto::updateOrCreate(['sku' => $linha['sku']], $linha);
    // }

    public function chaveUnica(array $linha): mixed
    {
        return $linha['sku'] ?? null;
    }

    public function cabecalhos(): array
    {
        return ['nome', 'sku', 'preco'];
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

Mantenha os importadores **idempotentes** (upsert/updateOrCreate pela `chaveUnica`).

## Fila

```env
QUEUE_CONNECTION=redis
IMPORT_QUEUE_CONNECTION=redis
```

```bash
php artisan queue:work redis --queue=imports --tries=3 --timeout=600
```

## Progresso em tempo real (opcional)

Canal privado `importes.{uuid}` (Reverb/Pusher/Soketi). Eventos: `ImporteIniciado`,
`ImporteProgressoAtualizado`, `ImporteConcluido`, `ImporteConcluidoComErros`, `ImporteFalhou`,
`ImporteCancelado`. Ative com `transmitirProgresso(true)` e `config('importe.broadcast.enabled')`.

## Erros

- `GET /importes/{uuid}/erros` — erros por linha (JSON paginado)
- `GET /importes/{uuid}/erros/exporte?format=csv|xlsx` — baixar relatório

## Rotas HTTP (opcionais)

Prefixo `/importes`, desativáveis via `config('importe.routes.enabled')`:

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/importes` | lista do utilizador |
| POST | `/importes` | upload + criação |
| GET | `/importes/{uuid}` | estado |
| POST | `/importes/{uuid}/cancelar` | cancelar |
| POST | `/importes/{uuid}/repetir?scope=entire\|failed_chunks` | repetir |
| GET | `/importes/{uuid}/erros` | listar erros |
| GET | `/importes/{uuid}/erros/exporte` | baixar erros |
| DELETE | `/importes/{uuid}` | excluir |

## Comando de limpeza

```bash
php artisan importes:podar --dias=30
```

## Resolução rápida

| Problema | Solução |
|----------|---------|
| Presa em `pending` | Inicie o worker (`queue:work redis --queue=imports`) |
| `processing` não termina | Ver `laravel.log` / jobs falhados |
| Sem transmissões | Ver `importe.broadcast.enabled` e a autorização do canal |
| `completed_with_errors` | Ver erros e reimportar o ficheiro corrigido |
