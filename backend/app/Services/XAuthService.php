<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * X(Twitter)認証サービス
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
     *
     * @param string $token X OAuth 2.0アクセストークン
     * @return array|null ユーザー情報 または null
     */
    public function verifyToken(string $token): ?array
    {
        try {
            Log::info('X auth: Starting token verification', [
                'token_length' => strlen($token),
                'token_prefix' => substr($token, 0, 20) . '...'
            ]);

            // 0. トークンからユーザーIDへのマッピングをキャッシュから確認
            $tokenCacheKey = 'x_token_to_user_id:' . hash('sha256', $token);
            $cachedProviderUserId = Cache::get($tokenCacheKey);
            
            // 1. X API v2を使用してユーザー情報を取得
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->get('https://api.x.com/2/users/me', [
                'user.fields' => 'id,name,username,profile_image_url'
            ]);

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
                    $userLimit24HourReset = $response->header('x-user-limit-24hour-reset');
                    $userLimit24HourRemaining = $response->header('x-user-limit-24hour-remaining');
                    $userLimit24HourLimit = $response->header('x-user-limit-24hour-limit');
                    
                    Log::error('X auth: Rate limit exceeded', [
                        'token_prefix' => substr($token, 0, 20) . '...',
                        'rate_limit_reset' => $rateLimitReset ?? 'unknown',
                        'user_limit_24hour_remaining' => $userLimit24HourRemaining ?? 'unknown',
                        'user_limit_24hour_limit' => $userLimit24HourLimit ?? 'unknown',
                        'user_limit_24hour_reset' => $userLimit24HourReset ?? 'unknown',
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
                            'token_prefix' => substr($token, 0, 20) . '...'
                        ]);
                    }
                    
                    // 24時間制限に達している場合のメッセージを生成
                    $message = 'X APIのレート制限に達しました。';
                    if ($userLimit24HourRemaining === '0' && $userLimit24HourReset) {
                        $resetTime = (int)$userLimit24HourReset;
                        $resetDate = date('Y-m-d H:i:s', $resetTime);
                        $message .= " 24時間制限（{$userLimit24HourLimit}リクエスト/24時間）に達しています。リセット時刻: {$resetDate}";
                    } else {
                        $message .= ' しばらく待ってから再度お試しください。';
                    }
                    
                    // レート制限エラーの場合は例外を投げる
                    // 新規ユーザーの場合、キャッシュにユーザーIDが存在しないため、データベースからユーザー情報を取得できない
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

            $data = $response->json();
            
            if (!isset($data['data'])) {
                Log::warning('X auth: Invalid response format', ['response' => $data]);
                return null;
            }

            $userData = $data['data'];
            
            // ユーザー情報を取得
            $providerUserId = $userData['id'] ?? null;
            $userName = $userData['name'] ?? null;
            $userUsername = $userData['username'] ?? null;
            $userEmail = null; // emailは認証済みアプリでのみ取得可能
            $userPicture = $userData['profile_image_url'] ?? null;

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

