<?php

namespace App\Services;

use App\Models\XUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * X(Twitter)認証サービス
 */
class XAuthService implements AuthServiceInterface
{
    /**
     * トークン検証結果のキャッシュ時間（秒）
     * 30日間キャッシュして、API呼び出しを大幅に削減
     * トークンが無効になった場合（401エラーなど）は、キャッシュを無効化して再検証
     */
    private const CACHE_TTL = 2592000; // 30日間

    /**
     * データベースからユーザー情報を取得する有効期限（日数）
     * この期間内に検証済みのユーザー情報は、DBから取得してAPI呼び出しを回避
     */
    private const DB_USER_VALID_DAYS = 30;

    /**
     * トークンを検証し、ユーザー情報を取得
     *
     * @param string $token X OAuth 2.0アクセストークン
     * @return array|null ユーザー情報 または null
     */
    public function verifyToken(string $token): ?array
    {
        try {
            // 1. トークンベースのキャッシュを確認
            $tokenCacheKey = 'x_auth_token:' . hash('sha256', $token);
            $cachedUser = Cache::get($tokenCacheKey);
            if ($cachedUser !== null) {
                Log::info('X auth: Using cached token verification', [
                    'token_prefix' => substr($token, 0, 20) . '...'
                ]);
                return $cachedUser;
            }

            // 2. データベースから最新のユーザー情報を取得を試みる
            // ただし、トークンからユーザーIDを取得するにはAPI呼び出しが必要なため、
            // ここではAPI呼び出しを行う
            Log::info('X auth: Starting token verification', [
                'token_length' => strlen($token),
                'token_prefix' => substr($token, 0, 20) . '...'
            ]);

            // 3. X API v2を使用してユーザー情報を取得
            // Bearer認証を使用（withTokenは自動的にBearerトークンとして設定）
            // emailフィールドは認証済みアプリでのみ取得可能で、通常のOAuth 2.0では取得できない
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
                
                // 認証エラー（401）の場合、トークンが無効になった可能性があるためキャッシュを削除
                if ($response->status() === 401) {
                    Log::warning('X auth: Unauthorized (401), removing cache', [
                        'token_prefix' => substr($token, 0, 20) . '...'
                    ]);
                    Cache::forget($tokenCacheKey);
                    // ユーザーIDが分からないため、ユーザーIDベースのキャッシュは削除できない
                    Log::error('X auth: API request failed (Unauthorized)', [
                        'status' => $response->status(),
                        'body' => $errorBody,
                        'json' => $errorJson
                    ]);
                    return null;
                }
                
                // レート制限エラー（429）の場合、キャッシュされた結果があれば使用
                if ($response->status() === 429) {
                    Log::warning('X auth: Rate limit exceeded, checking cache', [
                        'token_prefix' => substr($token, 0, 20) . '...',
                        'body' => $errorBody,
                        'json' => $errorJson,
                        'rate_limit_reset' => $response->header('x-rate-limit-reset') ?? 'unknown',
                        'rate_limit_remaining' => $response->header('x-rate-limit-remaining') ?? 'unknown',
                        'rate_limit_limit' => $response->header('x-rate-limit-limit') ?? 'unknown'
                    ]);
                    
                    // トークンベースのキャッシュから取得を試みる
                    $cachedUser = Cache::get($tokenCacheKey);
                    if ($cachedUser !== null) {
                        Log::info('X auth: Using cached result due to rate limit', [
                            'token_prefix' => substr($token, 0, 20) . '...'
                        ]);
                        return $cachedUser;
                    }
                    
                    // データベースからユーザー情報を取得を試みる（30日以内に検証済みの場合）
                    try {
                        // まずAPIレスポンスからユーザーIDを取得できないか試みる
                        // ただし、429エラーの場合はレスポンスにユーザー情報が含まれていない可能性が高い
                        // そのため、データベースから最新のユーザー情報を取得することはできない
                        // 代わりに、トークンからユーザーIDを推測することもできないため、
                        // ここではデータベースからの取得は行わない
                    } catch (\Exception $e) {
                        // データベースからの取得に失敗しても処理は続行
                    }
                    
                    $rateLimitReset = $response->header('x-rate-limit-reset');
                    $userLimit24HourReset = $response->header('x-user-limit-24hour-reset');
                    $userLimit24HourRemaining = $response->header('x-user-limit-24hour-remaining');
                    $userLimit24HourLimit = $response->header('x-user-limit-24hour-limit');
                    
                    Log::error('X auth: Rate limit exceeded and no cache available', [
                        'token_prefix' => substr($token, 0, 20) . '...',
                        'rate_limit_reset' => $rateLimitReset ?? 'unknown',
                        'rate_limit_remaining' => $response->header('x-rate-limit-remaining') ?? 'unknown',
                        'rate_limit_limit' => $response->header('x-rate-limit-limit') ?? 'unknown',
                        'user_limit_24hour_remaining' => $userLimit24HourRemaining ?? 'unknown',
                        'user_limit_24hour_limit' => $userLimit24HourLimit ?? 'unknown',
                        'user_limit_24hour_reset' => $userLimit24HourReset ?? 'unknown',
                        'body' => $errorBody,
                        'json' => $errorJson
                    ]);
                    
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
            $userId = $userData['id'] ?? null;
            $userName = $userData['name'] ?? null;
            $userUsername = $userData['username'] ?? null;
            // emailは認証済みアプリでのみ取得可能で、通常のOAuth 2.0では取得できない
            $userEmail = null;
            $userPicture = $userData['profile_image_url'] ?? null;

            if (!$userId) {
                Log::warning('X auth: User ID not found', ['data' => $userData]);
                return null;
            }

            // 4. ユーザーIDベースのキャッシュを確認（トークンが更新されても同じユーザーなら再利用可能）
            $userCacheKey = 'x_auth_user:' . $userId;
            $cachedUserByUserId = Cache::get($userCacheKey);
            if ($cachedUserByUserId !== null) {
                Log::info('X auth: Using cached user data by user ID', [
                    'user_id' => $userId
                ]);
                // トークンベースのキャッシュも更新
                Cache::put($tokenCacheKey, $cachedUserByUserId, self::CACHE_TTL);
                return $cachedUserByUserId;
            }

            // 5. データベースからユーザー情報を取得を試みる（30日以内に検証済みの場合）
            try {
                $dbUser = XUser::where('x_user_id', $userId)
                    ->where('last_verified_at', '>=', now()->subDays(self::DB_USER_VALID_DAYS))
                    ->first();
                
                if ($dbUser !== null) {
                    Log::info('X auth: Using user data from database', [
                        'user_id' => $userId,
                        'last_verified_at' => $dbUser->last_verified_at
                    ]);
                    $dbUserData = $dbUser->toAuthArray();
                    // キャッシュに保存
                    Cache::put($tokenCacheKey, $dbUserData, self::CACHE_TTL);
                    Cache::put($userCacheKey, $dbUserData, self::CACHE_TTL);
                    return $dbUserData;
                }
            } catch (\Exception $e) {
                // DB取得に失敗しても処理は続行（APIから取得）
                Log::warning('X auth: Failed to get user data from database', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }

            // usernameをnameとして使用（nameがない場合）
            $displayName = $userName ?: $userUsername;

            $userData = [
                'user_id' => $userId,
                'email' => $userEmail,
                'name' => $displayName,
                'username' => $userUsername,
                'avatar' => $userPicture,
            ];

            // 6. データベースに保存または更新
            try {
                XUser::updateOrCreate(
                    ['x_user_id' => $userId],
                    [
                        'name' => $displayName,
                        'username' => $userUsername,
                        'avatar' => $userPicture,
                        'last_verified_at' => now(),
                    ]
                );
                Log::info('X auth: User data saved to database', [
                    'user_id' => $userId
                ]);
            } catch (\Exception $e) {
                // DB保存に失敗しても処理は続行
                Log::warning('X auth: Failed to save user data to database', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }

            // 7. トークンベースとユーザーIDベースの両方でキャッシュに保存
            Cache::put($tokenCacheKey, $userData, self::CACHE_TTL);
            Cache::put($userCacheKey, $userData, self::CACHE_TTL);
            
            Log::info('X auth: Token verified and cached', [
                'user_id' => $userId,
                'cache_ttl' => self::CACHE_TTL
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

