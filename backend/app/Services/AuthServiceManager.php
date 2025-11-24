<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * 認証サービスマネージャー
 * 複数の認証プロバイダーを管理し、適切な認証サービスを選択
 */
class AuthServiceManager
{
    /**
     * @var array<string, AuthServiceInterface>
     */
    private array $authServices = [];

    public function __construct()
    {
        // 認証サービスを登録
        $this->registerAuthService(new GoogleAuthService());
        // 将来的にX認証などを追加する場合はここに追加
        // $this->registerAuthService(new XAuthService());
    }

    /**
     * 認証サービスを登録
     *
     * @param AuthServiceInterface $authService
     * @return void
     */
    public function registerAuthService(AuthServiceInterface $authService): void
    {
        $this->authServices[$authService->getProviderName()] = $authService;
    }

    /**
     * トークンを検証
     * 各認証サービスを順番に試して、成功したものを返す
     *
     * @param string $token 認証トークン
     * @param string|null $provider プロバイダー名（指定された場合はそのプロバイダーのみ試行）
     * @return array|null ユーザー情報 または null
     */
    public function verifyToken(string $token, ?string $provider = null): ?array
    {
        $servicesToTry = $provider
            ? (isset($this->authServices[$provider]) ? [$this->authServices[$provider]] : [])
            : array_values($this->authServices);

        foreach ($servicesToTry as $authService) {
            $user = $authService->verifyToken($token);
            if ($user !== null) {
                Log::info('Token verified', [
                    'provider' => $authService->getProviderName(),
                    'user_id' => $user['user_id']
                ]);
                return $user;
            }
        }

        Log::warning('Token verification failed for all providers');
        return null;
    }

    /**
     * 登録されている認証プロバイダー名のリストを取得
     *
     * @return array<string>
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->authServices);
    }
}


