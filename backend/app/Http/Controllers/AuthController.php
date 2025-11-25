<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;
use App\Models\LoginHistory;
use Illuminate\Support\Str;
use App\Traits\LogsMethodExecution;
use App\Services\AuthServiceManager;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    use LogsMethodExecution;
    public function mockLogin(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        try {
            $result = response()->json([
                'loggedIn' => true
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            $this->logMethodEnd(__FUNCTION__, null, __FILE__, __LINE__);
            throw $e;
        }
    }

    /**
     * Google認証のリダイレクトURLを取得
     */
    public function googleRedirect(): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, [], __FILE__, __LINE__);
        try {
            $url = Socialite::driver('google')
                ->redirect()
                ->getTargetUrl();
            
            $result = response()->json([
                'redirectUrl' => $url
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Google redirect error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to generate redirect URL'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * Google認証のコールバック処理
     */
    public function googleCallback(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        try {
            $user = Socialite::driver('google')->user();
            
            $result = response()->json([
                'loggedIn' => true,
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'avatar' => $user->getAvatar(),
                ]
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Google callback error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 401);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * トークンからユーザー情報を取得（フロントエンドから直接トークンを送信する場合）
     * Google Identity Services の ID トークン（JWT）を検証
     */
    public function googleLogin(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
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
            
            $result = response()->json([
                'loggedIn' => true,
                'user' => [
                    'id' => $userId,
                    'name' => $userName,
                    'email' => $userEmail,
                    'avatar' => $userPicture,
                ]
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Google login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $result = response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage(),
                'loggedIn' => false
            ], 401);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * X(Twitter)認証のコールバック処理
     * 認証コードをアクセストークンに交換
     */
    public function xCallback(Request $request): JsonResponse
    {
        // 最初にログを出力（メソッドが呼び出されているか確認）
        error_log('=== X CALLBACK METHOD CALLED ===');
        Log::info('=== X CALLBACK METHOD CALLED ===', [
            'timestamp' => now()->toDateTimeString(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
        ]);
        
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        Log::info('X callback: Request received', [
            'method' => $request->method(),
            'input' => $request->all(),
        ]);

        try {
            $code = $request->input('code');
            $codeVerifier = $request->input('code_verifier');
            $redirectUri = $request->input('redirect_uri');

            if (!$code || !$codeVerifier || !$redirectUri) {
                Log::error('X callback: Missing required parameters');
                return response()->json([
                    'error' => 'Missing required parameters',
                    'loggedIn' => false
                ], 400);
            }

            $clientId = config('services.x.client_id');
            $clientSecret = config('services.x.client_secret');

            if (!$clientId || !$clientSecret) {
                Log::error('X callback: X credentials not configured');
                return response()->json([
                    'error' => 'X credentials not configured',
                    'loggedIn' => false
                ], 500);
            }

            // 認証コードをアクセストークンに交換
            Log::info('X callback: Exchanging code for access token', [
                'code_length' => strlen($code),
                'code_verifier_length' => strlen($codeVerifier),
                'redirect_uri' => $redirectUri
            ]);

            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post('https://api.x.com/2/oauth2/token', [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'code_verifier' => $codeVerifier,
                ]);

            Log::info('X callback: Token exchange response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body_preview' => substr($response->body(), 0, 200)
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $errorJson = $response->json();
                Log::error('X callback: Token exchange failed', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'json' => $errorJson
                ]);
                return response()->json([
                    'error' => 'Token exchange failed',
                    'message' => $errorJson['error_description'] ?? $errorJson['error'] ?? 'Unknown error',
                    'loggedIn' => false
                ], 401);
            }

            $tokenData = $response->json();
            $accessToken = $tokenData['access_token'] ?? null;

            Log::info('X callback: Token data received', [
                'has_access_token' => !is_null($accessToken),
                'token_data_keys' => array_keys($tokenData)
            ]);

            if (!$accessToken) {
                Log::error('X callback: Access token not found in response', ['response' => $tokenData]);
                return response()->json([
                    'error' => 'Access token not found',
                    'loggedIn' => false
                ], 401);
            }

            // アクセストークンを使用してユーザー情報を取得
            Log::info('X callback: Verifying user with access token', [
                'access_token_length' => strlen($accessToken),
                'access_token_prefix' => substr($accessToken, 0, 20) . '...'
            ]);

            $authServiceManager = app(AuthServiceManager::class);
            
            try {
                $user = $authServiceManager->verifyToken($accessToken, 'x');
            } catch (\Exception $e) {
                Log::error('X callback: Token verification exception', [
                    'message' => $e->getMessage(),
                    'access_token_length' => strlen($accessToken),
                    'access_token_prefix' => substr($accessToken, 0, 20) . '...'
                ]);
                
                // レート制限エラーの場合
                if (strpos($e->getMessage(), 'rate limit') !== false) {
                    return response()->json([
                        'error' => 'Rate limit exceeded',
                        'message' => 'X APIのレート制限に達しました。しばらく待ってから再度お試しください。',
                        'loggedIn' => false
                    ], 429);
                }
                
                return response()->json([
                    'error' => 'User verification failed',
                    'message' => $e->getMessage(),
                    'loggedIn' => false
                ], 401);
            }

            if (!$user) {
                Log::error('X callback: User verification failed', [
                    'access_token_length' => strlen($accessToken),
                    'access_token_prefix' => substr($accessToken, 0, 20) . '...'
                ]);
                return response()->json([
                    'error' => 'User verification failed',
                    'loggedIn' => false
                ], 401);
            }

            Log::info('X callback: Success', ['user_id' => $user['user_id'], 'email' => $user['email'] ?? 'not provided']);
            
            // ログイン履歴をDBに保存
            try {
                LoginHistory::create([
                    'user_id' => $user['user_id'],
                    'user_email' => $user['email'] ?? null,
                    'user_name' => $user['name'] ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'logged_in_at' => now(),
                ]);
                Log::info('Login history saved', ['user_id' => $user['user_id']]);
            } catch (\Exception $e) {
                // ログイン履歴の保存に失敗してもログインは成功とする
                Log::error('Failed to save login history: ' . $e->getMessage());
            }
            
            $refreshToken = $tokenData['refresh_token'] ?? null;
            
            $result = response()->json([
                'loggedIn' => true,
                'user' => [
                    'id' => $user['user_id'],
                    'name' => $user['name'] ?? null,
                    'email' => $user['email'] ?? null,
                    'avatar' => $user['avatar'] ?? null,
                    'username' => $user['username'] ?? null,
                ],
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => $tokenData['expires_in'] ?? 7200, // デフォルト2時間
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('X callback error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $result = response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage(),
                'loggedIn' => false
            ], 401);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * X(Twitter)認証のログイン処理
     * OAuth 2.0アクセストークンを検証
     */
    public function xLogin(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        Log::info('X login: Request received', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'input' => $request->all(),
        ]);

        try {
            $token = $request->input('token');
            
            if (!$token) {
                Log::error('X login: Token is missing');
                return response()->json([
                    'error' => 'Token is required',
                    'loggedIn' => false
                ], 400);
            }

            Log::info('X login: Token received', ['token_length' => strlen($token)]);

            // AuthServiceManagerを使用してトークンを検証
            $authServiceManager = app(AuthServiceManager::class);
            $user = $authServiceManager->verifyToken($token, 'x');

            if (!$user) {
                Log::error('X login: Token verification failed');
                throw new \Exception('Token verification failed');
            }

            Log::info('X login: Success', ['user_id' => $user['user_id'], 'email' => $user['email'] ?? 'not provided']);
            
            // ログイン履歴をDBに保存
            try {
                LoginHistory::create([
                    'user_id' => $user['user_id'],
                    'user_email' => $user['email'] ?? null,
                    'user_name' => $user['name'] ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'logged_in_at' => now(),
                ]);
                Log::info('Login history saved', ['user_id' => $user['user_id']]);
            } catch (\Exception $e) {
                // ログイン履歴の保存に失敗してもログインは成功とする
                Log::error('Failed to save login history: ' . $e->getMessage());
            }
            
            $result = response()->json([
                'loggedIn' => true,
                'user' => [
                    'id' => $user['user_id'],
                    'name' => $user['name'] ?? null,
                    'email' => $user['email'] ?? null,
                    'avatar' => $user['avatar'] ?? null,
                    'username' => $user['username'] ?? null,
                ]
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('X login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $result = response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage(),
                'loggedIn' => false
            ], 401);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * X(Twitter)認証のリフレッシュトークン処理
     * リフレッシュトークンを使用して新しいアクセストークンを取得
     */
    public function xRefresh(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        Log::info('X refresh: Request received', [
            'method' => $request->method(),
            'input' => $request->all(),
        ]);

        try {
            $refreshToken = $request->input('refresh_token');
            
            if (!$refreshToken) {
                Log::error('X refresh: Refresh token is missing');
                return response()->json([
                    'error' => 'Refresh token is required',
                    'loggedIn' => false
                ], 400);
            }

            $clientId = config('services.x.client_id');
            $clientSecret = config('services.x.client_secret');

            if (!$clientId || !$clientSecret) {
                Log::error('X refresh: X credentials not configured');
                return response()->json([
                    'error' => 'X credentials not configured',
                    'loggedIn' => false
                ], 500);
            }

            Log::info('X refresh: Exchanging refresh token for access token', [
                'refresh_token_length' => strlen($refreshToken),
                'refresh_token_prefix' => substr($refreshToken, 0, 20) . '...'
            ]);

            // リフレッシュトークンで新しいアクセストークンを取得
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post('https://api.x.com/2/oauth2/token', [
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

            Log::info('X refresh: Token refresh response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body_preview' => substr($response->body(), 0, 200)
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $errorJson = $response->json();
                Log::error('X refresh: Token refresh failed', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'json' => $errorJson
                ]);
                return response()->json([
                    'error' => 'Token refresh failed',
                    'message' => $errorJson['error_description'] ?? $errorJson['error'] ?? 'Unknown error',
                    'loggedIn' => false
                ], 401);
            }

            $tokenData = $response->json();
            $accessToken = $tokenData['access_token'] ?? null;
            $newRefreshToken = $tokenData['refresh_token'] ?? $refreshToken; // 新しいリフレッシュトークンが返されない場合は既存のものを使用

            if (!$accessToken) {
                Log::error('X refresh: Access token not found in response', ['response' => $tokenData]);
                return response()->json([
                    'error' => 'Access token not found',
                    'loggedIn' => false
                ], 401);
            }

            Log::info('X refresh: Success', [
                'access_token_length' => strlen($accessToken),
                'access_token_prefix' => substr($accessToken, 0, 20) . '...'
            ]);

            $result = response()->json([
                'access_token' => $accessToken,
                'refresh_token' => $newRefreshToken,
                'expires_in' => $tokenData['expires_in'] ?? 7200, // デフォルト2時間
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('X refresh error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $result = response()->json([
                'error' => 'Token refresh failed',
                'message' => $e->getMessage(),
                'loggedIn' => false
            ], 401);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * ログアウト処理
     */
    public function logout(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        try {
            Log::info('Logout: Request received', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // 将来的にセッション管理を実装する場合の処理をここに追加
            // 例: セッションの無効化、トークンの削除など

            $result = response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            $result = response()->json([
                'success' => true, // エラーが発生してもログアウトは成功とする
                'message' => 'Logged out'
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }
}

