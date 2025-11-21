<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class LoggingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // アプリケーション起動時にDB接続情報をログに記録
        $this->logDatabaseConnectionInfo();

        // SQLクエリをログに記録（開発環境のみ）
        if (config('app.debug')) {
            DB::listen(function ($query) {
                Log::debug('SQL Query', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                ]);
            });
        }
    }

    /**
     * データベース接続情報をログに記録
     */
    private function logDatabaseConnectionInfo(): void
    {
        try {
            // 環境変数から直接取得
            $dbConnection = env('DB_CONNECTION', 'mysql');
            $dbHost = env('DB_HOST', '127.0.0.1');
            $dbPort = env('DB_PORT', '3306');
            $dbDatabase = env('DB_DATABASE', '');
            $dbUsername = env('DB_USERNAME', '');
            $dbPassword = env('DB_PASSWORD', '');
            
            // パスワードをマスク
            $maskedPassword = $dbPassword ? str_repeat('*', min(strlen($dbPassword), 8)) : 'not set';
            
            Log::info('=== Database Connection Information ===', [
                'connection' => $dbConnection,
                'host' => $dbHost,
                'port' => $dbPort,
                'database' => $dbDatabase,
                'username' => $dbUsername,
                'password' => $maskedPassword,
                'full_dsn' => sprintf(
                    '%s://%s:%s@%s:%s/%s',
                    $dbConnection,
                    $dbUsername,
                    $maskedPassword,
                    $dbHost,
                    $dbPort,
                    $dbDatabase
                ),
            ]);

            // 設定ファイルからも取得を試みる
            try {
                $defaultConnection = config('database.default', $dbConnection);
                $connections = config('database.connections', []);
                
                if (!empty($connections)) {
                    Log::info('Database Configuration from Config', [
                        'default_connection' => $defaultConnection,
                        'available_connections' => array_keys($connections),
                        'connection_details' => array_map(function ($connection) {
                            $masked = $connection;
                            if (isset($masked['password'])) {
                                $masked['password'] = str_repeat('*', min(strlen($masked['password']), 8));
                            }
                            return $masked;
                        }, $connections),
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Could not read database config', [
                    'error' => $e->getMessage(),
                ]);
            }

            // 実際の接続を試みて、接続情報を確認
            try {
                $pdo = DB::connection()->getPdo();
                $serverInfo = $pdo->getAttribute(\PDO::ATTR_SERVER_INFO);
                $serverVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
                
                Log::info('Database connection successful', [
                    'server_info' => $serverInfo,
                    'server_version' => $serverVersion,
                    'connection_status' => 'connected',
                ]);
            } catch (\Exception $e) {
                Log::error('Database connection failed', [
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to log database connection info', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

