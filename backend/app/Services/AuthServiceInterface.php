<?php

namespace App\Services;

/**
 * 認証サービスインターフェース
 * 複数の認証プロバイダー（Google、Xなど）に対応するための共通インターフェース
 */
interface AuthServiceInterface
{
    /**
     * トークンを検証し、ユーザー情報を取得
     *
     * @param string $token 認証トークン
     * @return array|null ユーザー情報
     *  [
     *      'user_id' => string,          // 内部ユーザUUID（users.id）
     *      'provider_user_id' => string, // プロバイダ側のユーザID（Google sub / X user id など）
     *      'email' => string|null,
     *      'name' => string|null,
     *      'username' => string|null,
     *      'avatar' => string|null,
     *  ] または null
     */
    public function verifyToken(string $token): ?array;

    /**
     * この認証サービスがサポートするプロバイダー名を取得
     *
     * @return string プロバイダー名（例: 'google', 'x'）
     */
    public function getProviderName(): string;
}









