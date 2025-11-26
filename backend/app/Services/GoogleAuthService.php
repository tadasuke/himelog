<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Google認証サービス
 */
class GoogleAuthService implements AuthServiceInterface
{
    /**
     * トークンを検証し、ユーザー情報を取得
     *
     * @param string $token Google IDトークン（JWT）
     * @return array|null ユーザー情報 または null
     */
    public function verifyToken(string $token): ?array
    {
        try {
            // IDトークン（JWT）をデコード
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                Log::warning('Google auth: Invalid token format', ['parts_count' => count($parts)]);
                return null;
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
                Log::warning('Google auth: Failed to decode base64');
                return null;
            }

            $payload = json_decode($payloadJson, true);
            if (!$payload || json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Google auth: Failed to decode JSON', ['json_error' => json_last_error_msg()]);
                return null;
            }

            // トークンの有効期限を確認
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                Log::warning('Google auth: Token expired', ['exp' => $payload['exp'], 'now' => time()]);
                return null;
            }

            // 発行者を確認
            if (!isset($payload['iss']) || $payload['iss'] !== 'https://accounts.google.com') {
                Log::warning('Google auth: Invalid issuer', ['iss' => $payload['iss'] ?? 'not set']);
                return null;
            }

            // ユーザー情報を取得
            $userId = $payload['sub'] ?? null;
            $userEmail = $payload['email'] ?? null;
            $userName = $payload['name'] ?? null;

            if (!$userId) {
                Log::warning('Google auth: User ID not found', ['payload_keys' => array_keys($payload)]);
                return null;
            }

            return [
                'user_id' => $userId,
                'email' => $userEmail,
                'name' => $userName,
            ];
        } catch (\Exception $e) {
            Log::error('Google auth error: ' . $e->getMessage());
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
        return 'google';
    }
}









