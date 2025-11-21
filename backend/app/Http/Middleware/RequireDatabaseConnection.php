<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequireDatabaseConnection
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ヘルスチェックエンドポイントは除外
        if ($request->is('up') || $request->is('health')) {
            return $next($request);
        }
        
        try {
            // デフォルト接続がMySQLであることを確認
            $defaultConnection = config('database.default');
            
            if ($defaultConnection === 'sqlite') {
                Log::error('SQLite connection is not allowed', [
                    'default_connection' => $defaultConnection,
                    'request_path' => $request->path(),
                ]);
                
                return response()->json([
                    'error' => 'Database configuration error',
                    'message' => 'SQLite is not allowed. MySQL connection is required.'
                ], 500);
            }
            
            // MySQL接続をテスト
            try {
                $connection = DB::connection();
                $driverName = $connection->getDriverName();
                
                if ($driverName === 'sqlite') {
                    Log::error('SQLite connection detected', [
                        'driver' => $driverName,
                        'request_path' => $request->path(),
                    ]);
                    
                    return response()->json([
                        'error' => 'Database configuration error',
                        'message' => 'SQLite is not allowed. MySQL connection is required.'
                    ], 500);
                }
                
                // 接続が成功したことを確認
                if ($driverName !== 'mysql' && $driverName !== 'mariadb') {
                    Log::error('Invalid database driver', [
                        'driver' => $driverName,
                        'request_path' => $request->path(),
                    ]);
                    
                    return response()->json([
                        'error' => 'Database configuration error',
                        'message' => 'Only MySQL/MariaDB connections are allowed.'
                    ], 500);
                }
                
                // 接続をテスト（簡単なクエリを実行）
                $connection->getPdo();
                
            } catch (\Illuminate\Database\QueryException $e) {
                // データベース接続エラー
                Log::error('MySQL connection failed', [
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'request_path' => $request->path(),
                ]);
                
                return response()->json([
                    'error' => 'Database connection failed',
                    'message' => 'Unable to connect to MySQL database. Please check your database configuration.'
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::error('Database connection check failed', [
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'request_path' => $request->path(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Database connection failed',
                'message' => 'Unable to connect to MySQL database. Please check your database configuration.'
            ], 500);
        }
        
        return $next($request);
    }
}

