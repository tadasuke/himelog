<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * X(Twitter)認証サービス
 * OAuth 1.0aを使用
 */
class XAuthService implements AuthServiceInterface
{
    /**
     * トークンからユーザーIDへのマッピングのキャッシュ時間（秒）
     * 30日間キャッシュして、レート制限エラー時でもデータベースからユーザー情報を取得可能にする
     */
    private const TOKEN_TO_USER_ID_CACHE_TTL = 2592000; // 30日間

    /**
     * トークンを検証し、ユーザー情報を取得
     * OAuth 1.0aのアクセストークンとトークンシークレットを受け取る
     * 形式: JSON文字列 {"access_token": "...", "access_token_secret": "..."}
     *
     * @param string $tokenJson X OAuth 1.0aアクセストークンとトークンシークレットのJSON文字列
     * @return array|null ユーザー情報 または null
     */
    public function verifyToken(string $tokenJson): ?array
    {
        try {
            // JSON文字列をパース
            $tokenData = json_decode($tokenJson, true);
            if (!$tokenData || !isset($tokenData['access_token']) || !isset($tokenData['access_token_secret'])) {
                Log::warning('X auth: Invalid token format', ['token_json_length' => strlen($tokenJson)]);
                return null;
            }
            
            $accessToken = $tokenData['access_token'];
            $accessTokenSecret = $tokenData['access_token_secret'];
            
            Log::info('X auth: Starting token verification', [
                'access_token_length' => strlen($accessToken),
                'access_token_prefix' => substr($accessToken, 0, 20) . '...'
            ]);

            // 0. トークンからユーザーIDへのマッピングをキャッシュから確認
            $tokenCacheKey = 'x_token_to_user_id:' . hash('sha256', $accessToken . $accessTokenSecret);
            $cachedProviderUserId = Cache::get($tokenCacheKey);
            
            // 1. X API 1.1のaccount/verify_credentialsエンドポイントを使用
            $consumerKey = config('services.x.client_id');
            $consumerSecret = config('services.x.client_secret');
            
            if (!$consumerKey || !$consumerSecret) {
                Log::error('X auth: X credentials not configured');
                return null;
            }
            
            $url = 'https://api.x.com/1.1/account/verify_credentials.json';
            
            // OAuth 1.0aパラメータを生成
            $oauthParams = OAuth1Helper::generateOAuthParams($consumerKey, $accessToken);
            $oauthParams['oauth_signature'] = OAuth1Helper::generateSignature(
                'GET',
                $url,
                $oauthParams,
                $consumerSecret,
                $accessTokenSecret
            );
            
            // Authorizationヘッダーを生成
            $authHeader = OAuth1Helper::buildAuthorizationHeader($oauthParams);
            
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json',
            ])->get($url);

            Log::info('X auth: API response received', [
                'status' => $response->status(),
                'successful' => $response->successful()
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $errorJson = $response->json();
                
                // 認証エラー（401）の場合
                if ($response->status() === 401) {
                    Log::error('X auth: API request failed (Unauthorized)', [
                        'status' => $response->status(),
                        'body' => $errorBody,
                        'json' => $errorJson
                    ]);
                    return null;
                }
                
                // レート制限エラー（429）の場合
                if ($response->status() === 429) {
                    $rateLimitReset = $response->header('x-rate-limit-reset');
                    
                    Log::error('X auth: Rate limit exceeded', [
                        'access_token_prefix' => substr($accessToken, 0, 20) . '...',
                        'rate_limit_reset' => $rateLimitReset ?? 'unknown',
                        'body' => $errorBody,
                        'json' => $errorJson
                    ]);
                    
                    // キャッシュからユーザーIDを取得してデータベースからユーザー情報を取得
                    if ($cachedProviderUserId !== null) {
                        Log::info('X auth: Using cached user ID due to rate limit', [
                            'provider_user_id' => $cachedProviderUserId
                        ]);
                        
                        try {
                            $dbUser = User::where('provider', 'x')
                                ->where('provider_user_id', $cachedProviderUserId)
                                ->first();
                            
                            if ($dbUser !== null) {
                                // last_login_atを更新
                                $dbUser->update([
                                    'last_login_at' => now(),
                                ]);
                                
                                $userData = $dbUser->toAuthArray();
                                
                                Log::info('X auth: User data retrieved from database (rate limit)', [
                                    'provider_user_id' => $cachedProviderUserId,
                                    'user_id' => $dbUser->id,
                                ]);
                                
                                return $userData;
                            } else {
                                Log::warning('X auth: Cached user ID found but user not found in database', [
                                    'provider_user_id' => $cachedProviderUserId
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::warning('X auth: Failed to get user from database (rate limit)', [
                                'provider_user_id' => $cachedProviderUserId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    } else {
                        Log::warning('X auth: No cached user ID found, cannot retrieve user from database (rate limit)', [
                            'access_token_prefix' => substr($accessToken, 0, 20) . '...'
                        ]);
                    }
                    
                    // レート制限エラーの場合は例外を投げる
                    $message = 'X APIのレート制限に達しました。しばらく待ってから再度お試しください。';
                    if ($rateLimitReset) {
                        $resetTime = (int)$rateLimitReset;
                        $resetDate = date('Y-m-d H:i:s', $resetTime);
                        $message .= " リセット時刻: {$resetDate}";
                    }
                    throw new \Exception($message);
                } else {
                    Log::error('X auth: API request failed', [
                        'status' => $response->status(),
                        'body' => $errorBody,
                        'json' => $errorJson,
                        'headers' => $response->headers()
                    ]);
                }
                return null;
            }

            $userData = $response->json();
            
            // API 1.1のverify_credentialsは直接ユーザーデータを返す
            if (!is_array($userData) || !isset($userData['id_str'])) {
                Log::warning('X auth: Invalid response format', ['response' => $userData]);
                return null;
            }
            
            // ユーザー情報を取得
            $providerUserId = $userData['id_str'] ?? null;
            $userName = $userData['name'] ?? null;
            $userUsername = $userData['screen_name'] ?? null;
            $userEmail = $userData['email'] ?? null; // 認証済みアプリでのみ取得可能
            $userPicture = $userData['profile_image_url_https'] ?? null;

            if (!$providerUserId) {
                Log::warning('X auth: User ID not found', ['data' => $userData]);
                return null;
            }

            // トークンからユーザーIDへのマッピングをキャッシュに保存
            Cache::put($tokenCacheKey, $providerUserId, self::TOKEN_TO_USER_ID_CACHE_TTL);
            Log::info('X auth: Token to user ID mapping cached', [
                'provider_user_id' => $providerUserId
            ]);

            // usernameをnameとして使用（nameがない場合）
            $displayName = $userName ?: $userUsername;

            // 2. データベースに保存または更新（providerとprovider_user_idで既存ユーザーをチェック）
            try {
                $dbUser = User::updateOrCreate(
                    [
                        'provider' => 'x',
                        'provider_user_id' => $providerUserId,
                    ],
                    [
                        'name' => $displayName,
                        'email' => $userEmail,
                        'username' => $userUsername,
                        'avatar' => $userPicture,
                        'last_verified_at' => now(),
                        'last_login_at' => now(),
                        'status' => 'active',
                    ]
                );

                Log::info('X auth: User data saved/updated in users table', [
                    'provider_user_id' => $providerUserId,
                    'users_table_id' => $dbUser->id,
                    'was_recently_created' => $dbUser->wasRecentlyCreated,
                ]);
            } catch (\Exception $e) {
                Log::error('X auth: Failed to save user data to users table', [
                    'provider_user_id' => $providerUserId,
                    'error' => $e->getMessage()
                ]);
                return null;
            }

            // 3. 認証用の配列を作成
            $userData = [
                'user_id' => $dbUser->id,
                'provider_user_id' => $providerUserId,
                'email' => $userEmail,
                'name' => $displayName,
                'username' => $userUsername,
                'avatar' => $userPicture,
            ];

            Log::info('X auth: Token verified successfully', [
                'provider_user_id' => $providerUserId,
                'user_id' => $dbUser->id,
            ]);

            return $userData;
        } catch (\Exception $e) {
            Log::error('X auth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * プロバイダー名を取得
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return 'x';
    }
}

