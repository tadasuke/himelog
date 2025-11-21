<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;
use App\Models\LoginHistory;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function mockLogin(Request $request): JsonResponse
    {
        return response()->json([
            'loggedIn' => true
        ]);
    }

    /**
     * Google認証のリダイレクトURLを取得
     */
    public function googleRedirect(): JsonResponse
    {
        try {
            $url = Socialite::driver('google')
                ->redirect()
                ->getTargetUrl();
            
            return response()->json([
                'redirectUrl' => $url
            ]);
        } catch (\Exception $e) {
            Log::error('Google redirect error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to generate redirect URL'
            ], 500);
        }
    }

    /**
     * Google認証のコールバック処理
     */
    public function googleCallback(Request $request): JsonResponse
    {
        try {
            $user = Socialite::driver('google')->user();
            
            return response()->json([
                'loggedIn' => true,
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'avatar' => $user->getAvatar(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Google callback error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * トークンからユーザー情報を取得（フロントエンドから直接トークンを送信する場合）
     * Google Identity Services の ID トークン（JWT）を検証
     */
    public function googleLogin(Request $request): JsonResponse
    {
        // リクエストが到達しているか確認
        Log::info('Google login: Request received', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'input' => $request->all(),
        ]);

        try {
            $token = $request->input('token');
            
            if (!$token) {
                Log::error('Google login: Token is missing');
                return response()->json([
                    'error' => 'Token is required',
                    'loggedIn' => false
                ], 400);
            }

            Log::info('Google login: Token received', ['token_length' => strlen($token)]);

            // IDトークン（JWT）をデコード
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                Log::error('Google login: Invalid token format', ['parts_count' => count($parts)]);
                throw new \Exception('Invalid token format');
            }

            // ペイロードをデコード（base64urlデコード）
            $payloadBase64 = str_replace(['-', '_'], ['+', '/'], $parts[1]);
            // パディングを追加
            $padding = strlen($payloadBase64) % 4;
            if ($padding) {
                $payloadBase64 .= str_repeat('=', 4 - $padding);
            }
            
            $payloadJson = base64_decode($payloadBase64);
            if (!$payloadJson) {
                Log::error('Google login: Failed to decode base64');
                throw new \Exception('Failed to decode token base64');
            }
            
            $payload = json_decode($payloadJson, true);
            if (!$payload || json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Google login: Failed to decode JSON', ['json_error' => json_last_error_msg()]);
                throw new \Exception('Failed to decode token JSON: ' . json_last_error_msg());
            }

            Log::info('Google login: Token decoded', ['payload_keys' => array_keys($payload)]);

            // トークンの有効期限を確認
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                Log::error('Google login: Token expired', ['exp' => $payload['exp'], 'now' => time()]);
                throw new \Exception('Token expired');
            }

            // 発行者を確認
            if (!isset($payload['iss']) || $payload['iss'] !== 'https://accounts.google.com') {
                Log::error('Google login: Invalid issuer', ['iss' => $payload['iss'] ?? 'not set']);
                throw new \Exception('Invalid token issuer: ' . ($payload['iss'] ?? 'not set'));
            }

            // ユーザー情報を取得
            $userId = $payload['sub'] ?? null;
            $userEmail = $payload['email'] ?? null;
            $userName = $payload['name'] ?? null;
            $userPicture = $payload['picture'] ?? null;

            if (!$userId) {
                Log::error('Google login: User ID not found', ['payload_keys' => array_keys($payload)]);
                throw new \Exception('User ID not found in token');
            }

            Log::info('Google login: Success', ['user_id' => $userId, 'email' => $userEmail]);
            
            // ログイン履歴をDBに保存
            try {
                LoginHistory::create([
                    'user_id' => $userId,
                    'user_email' => $userEmail,
                    'user_name' => $userName,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'logged_in_at' => now(),
                ]);
                Log::info('Login history saved', ['user_id' => $userId]);
            } catch (\Exception $e) {
                // ログイン履歴の保存に失敗してもログインは成功とする
                Log::error('Failed to save login history: ' . $e->getMessage());
            }
            
            return response()->json([
                'loggedIn' => true,
                'user' => [
                    'id' => $userId,
                    'name' => $userName,
                    'email' => $userEmail,
                    'avatar' => $userPicture,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Google login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage(),
                'loggedIn' => false
            ], 401);
        }
    }

    /**
     * ログアウト処理
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            Log::info('Logout: Request received', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // 将来的にセッション管理を実装する場合の処理をここに追加
            // 例: セッションの無効化、トークンの削除など

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'success' => true, // エラーが発生してもログアウトは成功とする
                'message' => 'Logged out'
            ]);
        }
    }
}

