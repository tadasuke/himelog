<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// PHPのエラーハンドラーを設定（Laravel起動前のエラーもキャッチ）
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
        $logFile = __DIR__ . '/../storage/logs/laravel.log';
        $message = sprintf(
            "[%s] PHP Fatal Error: %s in %s on line %d\nStack trace:\n%s\n",
            date('Y-m-d H:i:s'),
            $error['message'],
            $error['file'],
            $error['line'],
            isset($error['trace']) ? $error['trace'] : 'No trace available'
        );
        @file_put_contents($logFile, $message, FILE_APPEND);
    }
});

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
try {
    (require_once __DIR__.'/../bootstrap/app.php')
        ->handleRequest(Request::capture());
} catch (\Throwable $e) {
    // Laravel起動前のエラーもログに記録
    $logFile = __DIR__ . '/../storage/logs/laravel.log';
    $message = sprintf(
        "[%s] Bootstrap Error: %s\nException: %s\nFile: %s\nLine: %d\nTrace:\n%s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        get_class($e),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @file_put_contents($logFile, $message, FILE_APPEND);
    
    // エラーレスポンスを返す
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Server Error']);
    exit;
}

