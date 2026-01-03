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
use App\Services\OAuth1Helper;
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
                'error' => 'Authentication failed'
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
            
            // ログイン履歴をDBに保存（Googleは provider_user_id ベースのまま）
            try {
                LoginHistory::create([
                    'user_id' => $userId,
                    // internal_user_id は users テーブルに該当レコードがある場合に後続マイグレーションで補完される
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
                'loggedIn' => false
            ], 401);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * X(Twitter)認証のリクエストトークン取得（OAuth 1.0a Step 1）
     */
    public function xRequestToken(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        Log::info('X request token: Request received', [
            'method' => $request->method(),
            'input' => $request->all(),
        ]);

        try {
            $callbackUrl = $request->input('callback_url');
            
            if (!$callbackUrl) {
                Log::error('X request token: Callback URL is missing');
                return response()->json([
                    'error' => 'Callback URL is required',
                ], 400);
            }

            $consumerKey = config('services.x.client_id');
            $consumerSecret = config('services.x.client_secret');

            if (!$consumerKey || !$consumerSecret) {
                Log::error('X request token: X credentials not configured');
                return response()->json([
                    'error' => 'X credentials not configured',
                ], 500);
            }

            Log::info('X request token: Credentials loaded', [
                'consumer_key_length' => strlen($consumerKey),
                'consumer_key_prefix' => substr($consumerKey, 0, 10) . '...',
                'consumer_secret_length' => strlen($consumerSecret),
                'consumer_secret_prefix' => substr($consumerSecret, 0, 10) . '...',
                'callback_url' => $callbackUrl,
            ]);

            // OAuth 1.0aパラメータを生成
            $oauthParams = OAuth1Helper::generateOAuthParams($consumerKey);
            $oauthParams['oauth_callback'] = $callbackUrl;

            // 署名を生成
            $url = 'https://api.x.com/oauth/request_token';
            
            Log::info('X request token: OAuth params before signature', [
                'oauth_params' => array_map(function($key, $value) {
                    // 機密情報は一部のみ表示
                    if (in_array($key, ['oauth_consumer_key'])) {
                        return substr($value, 0, 10) . '...';
                    }
                    return $value;
                }, array_keys($oauthParams), $oauthParams),
            ]);
            
            $oauthParams['oauth_signature'] = OAuth1Helper::generateSignature(
                'POST',
                $url,
                $oauthParams,
                $consumerSecret
            );

            // Authorizationヘッダーを生成
            $authHeader = OAuth1Helper::buildAuthorizationHeader($oauthParams);

            Log::info('X request token: Generated authorization header', [
                'auth_header_length' => strlen($authHeader),
                'auth_header_preview' => substr($authHeader, 0, 100) . '...',
                'signature_length' => strlen($oauthParams['oauth_signature']),
            ]);

            // リクエストトークンを取得
            // OAuth 1.0aでは、oauth_callbackはリクエストボディにも含める必要がある
            // 署名にはAuthorizationヘッダーに含まれるパラメータが使用される
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
            ])->asForm()->post($url, [
                'oauth_callback' => $callbackUrl,
            ]);

            Log::info('X request token: Response received', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $responseHeaders = $response->headers();
                
                Log::error('X request token: Failed to get request token', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'headers' => $responseHeaders,
                    'request_url' => $url,
                    'callback_url' => $callbackUrl,
                ]);
                
                // エラーレスポンスの詳細を返す
                return response()->json([
                    'error' => 'Failed to get request token',
                    'details' => $errorBody,
                    'status' => $response->status(),
                ], 401);
            }

            // レスポンスをパース（oauth_token=xxx&oauth_token_secret=yyy&oauth_callback_confirmed=true形式）
            parse_str($response->body(), $tokenData);
            
            if (!isset($tokenData['oauth_token']) || !isset($tokenData['oauth_token_secret'])) {
                Log::error('X request token: Invalid response format', ['response' => $response->body()]);
                return response()->json([
                    'error' => 'Invalid response from X API',
                ], 401);
            }

            if (!isset($tokenData['oauth_callback_confirmed']) || $tokenData['oauth_callback_confirmed'] !== 'true') {
                Log::error('X request token: Callback not confirmed', ['response' => $response->body()]);
                return response()->json([
                    'error' => 'Callback not confirmed',
                ], 401);
            }

            Log::info('X request token: Success', [
                'oauth_token' => substr($tokenData['oauth_token'], 0, 20) . '...',
            ]);

            $result = response()->json([
                'oauth_token' => $tokenData['oauth_token'],
                'oauth_token_secret' => $tokenData['oauth_token_secret'],
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('X request token error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $result = response()->json([
                'error' => 'Failed to get request token',
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * X(Twitter)認証のアクセストークン取得（OAuth 1.0a Step 3）
     */
    public function xAccessToken(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        Log::info('X access token: Request received', [
            'method' => $request->method(),
            'input' => $request->all(),
        ]);

        try {
            $oauthToken = $request->input('oauth_token');
            $oauthVerifier = $request->input('oauth_verifier');
            $oauthTokenSecret = $request->input('oauth_token_secret');

            if (!$oauthToken || !$oauthVerifier || !$oauthTokenSecret) {
                Log::error('X access token: Missing required parameters');
                return response()->json([
                    'error' => 'Missing required parameters',
                    'loggedIn' => false
                ], 400);
            }

            $consumerKey = config('services.x.client_id');
            $consumerSecret = config('services.x.client_secret');

            if (!$consumerKey || !$consumerSecret) {
                Log::error('X access token: X credentials not configured');
                return response()->json([
                    'error' => 'X credentials not configured',
                    'loggedIn' => false
                ], 500);
            }

            // OAuth 1.0aパラメータを生成
            $oauthParams = OAuth1Helper::generateOAuthParams($consumerKey, $oauthToken);
            $oauthParams['oauth_verifier'] = $oauthVerifier;

            // 署名を生成
            $url = 'https://api.x.com/oauth/access_token';
            $oauthParams['oauth_signature'] = OAuth1Helper::generateSignature(
                'POST',
                $url,
                $oauthParams,
                $consumerSecret,
                $oauthTokenSecret
            );

            // Authorizationヘッダーを生成
            $authHeader = OAuth1Helper::buildAuthorizationHeader($oauthParams);

            Log::info('X access token: Sending request to X API');

            // アクセストークンを取得
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
            ])->post($url, [
                'oauth_verifier' => $oauthVerifier,
            ]);

            Log::info('X access token: Response received', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                Log::error('X access token: Failed to get access token', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                ]);
                return response()->json([
                    'error' => 'Failed to get access token',
                    'loggedIn' => false
                ], 401);
            }

            // レスポンスをパース（oauth_token=xxx&oauth_token_secret=yyy形式）
            parse_str($response->body(), $tokenData);
            
            if (!isset($tokenData['oauth_token']) || !isset($tokenData['oauth_token_secret'])) {
                Log::error('X access token: Invalid response format', ['response' => $response->body()]);
                return response()->json([
                    'error' => 'Invalid response from X API',
                    'loggedIn' => false
                ], 401);
            }

            $accessToken = $tokenData['oauth_token'];
            $accessTokenSecret = $tokenData['oauth_token_secret'];

            Log::info('X access token: Success', [
                'access_token_prefix' => substr($accessToken, 0, 20) . '...',
            ]);

            // アクセストークンを使用してユーザー情報を取得
            $authServiceManager = app(AuthServiceManager::class);
            
            // トークンとトークンシークレットをJSON文字列に変換
            $tokenJson = json_encode([
                'access_token' => $accessToken,
                'access_token_secret' => $accessTokenSecret,
            ]);
            
            try {
                $user = $authServiceManager->verifyToken($tokenJson, 'x');
            } catch (\Exception $e) {
                Log::error('X access token: Token verification exception', [
                    'message' => $e->getMessage(),
                ]);
                
                if (strpos($e->getMessage(), 'rate limit') !== false) {
                    return response()->json([
                        'error' => 'Rate limit exceeded',
                        'loggedIn' => false
                    ], 429);
                }
                
                return response()->json([
                    'error' => 'User verification failed',
                    'loggedIn' => false
                ], 401);
            }

            if (!$user) {
                Log::error('X access token: User verification failed');
                return response()->json([
                    'error' => 'User verification failed',
                    'loggedIn' => false
                ], 401);
            }

            Log::info('X access token: Success', ['user_id' => $user['user_id'], 'email' => $user['email'] ?? 'not provided']);
            
            // ログイン履歴をDBに保存
            try {
                LoginHistory::create([
                    'user_id' => $user['provider_user_id'] ?? null,
                    'internal_user_id' => $user['user_id'],
                    'user_email' => $user['email'] ?? null,
                    'user_name' => $user['name'] ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'logged_in_at' => now(),
                ]);
                Log::info('Login history saved', ['user_id' => $user['user_id']]);
            } catch (\Exception $e) {
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
                ],
                'access_token' => $accessToken,
                'access_token_secret' => $accessTokenSecret,
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('X access token error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $result = response()->json([
                'error' => 'Authentication failed',
                'loggedIn' => false
            ], 401);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * X(Twitter)認証のコールバック処理
     * OAuth 1.0aのコールバック（oauth_tokenとoauth_verifierを受け取る）
     * フロントエンドからのリクエストで、アクセストークンを取得する
     */
    public function xCallback(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        Log::info('X callback: Request received', [
            'method' => $request->method(),
            'input' => $request->all(),
        ]);

        try {
            $oauthToken = $request->input('oauth_token');
            $oauthVerifier = $request->input('oauth_verifier');
            $oauthTokenSecret = $request->input('oauth_token_secret');

            if (!$oauthToken || !$oauthVerifier || !$oauthTokenSecret) {
                Log::error('X callback: Missing required parameters');
                return response()->json([
                    'error' => 'Missing required parameters',
                    'loggedIn' => false
                ], 400);
            }

            // アクセストークン取得エンドポイントを呼び出す
            $accessTokenRequest = new Request([
                'oauth_token' => $oauthToken,
                'oauth_verifier' => $oauthVerifier,
                'oauth_token_secret' => $oauthTokenSecret,
            ]);

            return $this->xAccessToken($accessTokenRequest);
        } catch (\Exception $e) {
            Log::error('X callback error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $result = response()->json([
                'error' => 'Authentication failed',
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
            
            // ログイン履歴をDBに保存（users.id を格納）
            try {
                LoginHistory::create([
                    'user_id' => $user['provider_user_id'] ?? null,
                    'internal_user_id' => $user['user_id'],
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

