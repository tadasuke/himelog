<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;

class DatabaseConnectionServiceProvider extends ServiceProvider
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
        // SQLite接続を完全に防止するためのチェック
        $this->preventSqliteConnection();
        
        // コマンド実行時にSQLite接続をチェック
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            $this->checkSqliteConnectionBeforeCommand($event->command);
        });
    }

    /**
     * SQLite接続を防止するチェック
     * アプリケーション起動時およびコマンド実行時に実行される
     */
    private function preventSqliteConnection(): void
    {
        // 設定ファイルでSQLiteが使用されていないことを確認
        $defaultConnection = config('database.default', env('DB_CONNECTION', 'mysql'));
        
        if ($defaultConnection === 'sqlite') {
            $this->throwSqliteNotAllowedException('Default database connection is set to SQLite in configuration.');
        }

        // データベース接続が既に確立されている場合は、SQLite接続でないことを確認
        try {
            if (app()->bound('db')) {
                $connection = DB::connection();
                $driverName = $connection->getDriverName();
                
                if ($driverName === 'sqlite') {
                    $this->throwSqliteNotAllowedException('Active database connection is using SQLite driver.');
                }
            }
        } catch (\Exception $e) {
            // 接続エラーの場合は無視（後でコマンド実行時にチェックされる）
        }
    }

    /**
     * コマンド実行前にSQLite接続をチェック
     */
    private function checkSqliteConnectionBeforeCommand(?string $command): void
    {
        // データベース接続を必要としないコマンドはスキップ
        $skipCommands = ['config:clear', 'config:cache', 'route:clear', 'view:clear', 'cache:clear', 'key:generate'];
        
        if ($command !== null && in_array($command, $skipCommands)) {
            // このコマンドではデータベース接続を使わないので、チェックはスキップ
            return;
        }

        // 設定でSQLiteが指定されていないことを確認
        $defaultConnection = config('database.default', env('DB_CONNECTION', 'mysql'));
        
        if ($defaultConnection === 'sqlite') {
            $this->throwSqliteNotAllowedException(
                "Command '{$command}' attempted with SQLite as default connection. SQLite is not allowed."
            );
        }

        // SQLiteファイルが存在する場合はエラー（データベース接続を必要とするコマンドの場合）
        $sqlitePath = database_path('database.sqlite');
        if (file_exists($sqlitePath)) {
            $this->throwSqliteNotAllowedException(
                "SQLite database file exists at: {$sqlitePath}. SQLite is not allowed. " .
                "Please delete this file immediately using: php artisan sqlite:remove"
            );
        }

        // 実際の接続を試みてSQLiteでないことを確認
        try {
            $connection = DB::connection();
            $driverName = $connection->getDriverName();
            
            if ($driverName === 'sqlite') {
                $this->throwSqliteNotAllowedException(
                    "Command '{$command}' attempted with active SQLite connection. SQLite is not allowed."
                );
            }
        } catch (\Exception $e) {
            // 接続エラーの場合は既にSQLiteファイルの存在チェックは完了している
        }
    }

    /**
     * SQLite使用禁止例外を投げる
     */
    private function throwSqliteNotAllowedException(string $reason): void
    {
        $message = "SQLite is absolutely not allowed in this project. {$reason} " .
                   "Please use MySQL connection only. " .
                   "Check your .env file and ensure DB_CONNECTION is set to 'mysql'.";
        
        Log::error('SQLite connection blocked', [
            'reason' => $reason,
            'message' => $message,
        ]);
        
        throw new \RuntimeException($message);
    }
}

