<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\AuthServiceManager;
use Illuminate\Support\Facades\Log;

/**
 * ユーザー認証ミドルウェア
 * 複数の認証プロバイダー（Google、Xなど）に対応
 */
class AuthenticateUser
{
    public function __construct(
        private AuthServiceManager $authServiceManager
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Authorizationヘッダーからトークンを取得
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader) {
            Log::warning('Authentication failed: No Authorization header');
            return response()->json([
                'error' => 'Unauthorized',
                'message' => '認証トークンが必要です'
            ], 401);
        }

        // Bearerトークンの形式を確認
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            Log::warning('Authentication failed: Invalid Authorization header format');
            return response()->json([
                'error' => 'Unauthorized',
                'message' => '認証トークンの形式が正しくありません'
            ], 401);
        }

        $token = $matches[1];
        
        // オプション: X-Auth-Providerヘッダーでプロバイダーを指定
        $provider = $request->header('X-Auth-Provider');

        // トークンを検証
        $user = $this->authServiceManager->verifyToken($token, $provider);

        if (!$user) {
            Log::warning('Authentication failed: Token verification failed');
            return response()->json([
                'error' => 'Unauthorized',
                'message' => '認証に失敗しました'
            ], 401);
        }

        // 認証されたユーザー情報をリクエストに設定
        $request->merge(['authenticated_user_id' => $user['user_id']]);
        $request->setUserResolver(function () use ($user) {
            return (object) $user;
        });

        Log::info('User authenticated', [
            'user_id' => $user['user_id'],
            'provider' => $provider ?? 'auto'
        ]);

        return $next($request);
    }
}



