<?php

use App\Models\User;
use Kuteka\LaravelImport\Support\AutorizacaoImportePadrao;
use Kuteka\LaravelImport\Tenancy\ContextoInquilinoNulo;

return [

    /*
    |--------------------------------------------------------------------------
    | Fila
    |--------------------------------------------------------------------------
    |
    | Conexão e nome de fila padrão usados pelos jobs de importação.
    | Por importação, podem ser alterados via OpcoesImporte.
    |
    */

    'queue' => [
        'connection' => env('IMPORT_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'redis')),
        'queue' => env('IMPORT_QUEUE', 'imports'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Padrões de processamento
    |--------------------------------------------------------------------------
    */

    'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 1000),

    'tries' => (int) env('IMPORT_TRIES', 3),

    'timeout' => (int) env('IMPORT_TIMEOUT', 600),

    'backoff' => (int) env('IMPORT_BACKOFF', 3),

    /*
    |--------------------------------------------------------------------------
    | Limites
    |--------------------------------------------------------------------------
    */

    'max_file_size' => (int) env('IMPORT_MAX_FILE_SIZE', 100 * 1024 * 1024),

    'max_rows' => (int) env('IMPORT_MAX_ROWS', 1_000_000),

    'max_errors' => (int) env('IMPORT_MAX_ERRORS', 100_000),

    'max_concurrent_imports' => (int) env('IMPORT_MAX_CONCURRENT', 0),

    /*
    |--------------------------------------------------------------------------
    | Armazenamento
    |--------------------------------------------------------------------------
    */

    'disk' => env('IMPORT_DISK', 'local'),

    'path' => env('IMPORT_PATH', 'imports'),

    /*
    |--------------------------------------------------------------------------
    | Validação de ficheiros
    |--------------------------------------------------------------------------
    */

    'allowed_extensions' => ['csv', 'xlsx', 'xls'],

    'allowed_mime_types' => [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/octet-stream',
        'application/zip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tratamento de CSV
    |--------------------------------------------------------------------------
    */

    'csv' => [
        'delimiter' => env('IMPORT_CSV_DELIMITER'),

        'encoding' => env('IMPORT_CSV_ENCODING'),

        'auto_detect' => true,

        'candidate_delimiters' => [',', ';', "\t", '|'],

        'candidate_encodings' => ['UTF-8', 'Windows-1252', 'ISO-8859-1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transmissão
    |--------------------------------------------------------------------------
    |
    | Os eventos de progresso são transmitidos no canal privado
    | "importes.{uuid}" via Broadcasting do Laravel (Reverb, Pusher, Soketi, ...).
    |
    */

    'broadcast' => [
        'enabled' => (bool) env('IMPORT_BROADCAST', true),
        'throttle_seconds' => (float) env('IMPORT_BROADCAST_THROTTLE_SECONDS', 1),
        'throttle_records' => (int) env('IMPORT_BROADCAST_THROTTLE_RECORDS', 250),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notificações
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'enabled' => true,
        'channels' => ['database', 'mail'],
        'link_route' => env('IMPORT_NOTIFICATION_LINK_ROUTE', 'importes.show'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modelo de utilizador
    |--------------------------------------------------------------------------
    |
    | Modelo usado para resolver o utilizador notificável de um dado user_id.
    |
    */

    'user' => [
        'model' => env('IMPORT_USER_MODEL', User::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inquilinato
    |--------------------------------------------------------------------------
    |
    | Adaptador responsável por restaurar o contexto de inquilino dentro dos
    | workers de fila. Opções: null (inquilino único), Kuteka\LaravelImport\
    | Tenancy\ContextoInquilinoSpatie::class (spatie/laravel-multitenancy) ou
    | uma implementação própria.
    |
    */

    'tenancy' => [
        'adapter' => env('IMPORT_TENANCY_ADAPTER', ContextoInquilinoNulo::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Autorização
    |--------------------------------------------------------------------------
    |
    | Implementação de AutorizacaoImporte que controla as permissões de
    | criar/ver/cancelar/baixar/excluir.
    |
    */

    'authorization' => AutorizacaoImportePadrao::class,

    /*
    |--------------------------------------------------------------------------
    | Registo de importadores
    |--------------------------------------------------------------------------
    |
    | Mapeia chaves curtas para classes de importador. As chaves são validadas
    | ao criar importações através do controller HTTP do package. Passar uma
    | string de classe diretamente a GerenciadorImporte::criar() ignora este
    | registo.
    |
    */

    'importers' => [],

    /*
    |--------------------------------------------------------------------------
    | Rotas
    |--------------------------------------------------------------------------
    */

    'routes' => [
        'enabled' => (bool) env('IMPORT_ROUTES', true),
        'prefix' => 'importes',
        'middleware' => ['web', 'auth'],
        'as' => 'importes.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retenção
    |--------------------------------------------------------------------------
    |
    | Usado pelo comando "php artisan importes:podar".
    |
    */

    'retention' => [
        'days' => (int) env('IMPORT_RETENTION_DAYS', 30),
        'schedule' => false,
        'schedule_time' => '02:30',
    ],

    /*
    |--------------------------------------------------------------------------
    | Observabilidade
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'channel' => env('IMPORT_LOG_CHANNEL'),
        'log_row_data' => false,
    ],
];
