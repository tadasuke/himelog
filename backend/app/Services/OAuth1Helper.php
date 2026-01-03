<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * OAuth 1.0a認証用のヘルパークラス
 * X APIのOAuth 1.0a署名を生成
 */
class OAuth1Helper
{
    /**
     * OAuth 1.0a署名を生成
     *
     * @param string $method HTTPメソッド
     * @param string $url リクエストURL
     * @param array $params パラメータ
     * @param string $consumerSecret Consumer Secret
     * @param string|null $tokenSecret Token Secret（リクエストトークン取得時はnull）
     * @return string 署名
     */
    public static function generateSignature(
        string $method,
        string $url,
        array $params,
        string $consumerSecret,
        ?string $tokenSecret = null
    ): string {
        // パラメータをURLエンコードしてソート
        ksort($params);
        $signatureBaseString = self::buildSignatureBaseString($method, $url, $params);
        
        // 署名キーを生成
        $signingKey = self::buildSigningKey($consumerSecret, $tokenSecret);
        
        // デバッグ用ログ（本番環境では無効化推奨）
        Log::debug('OAuth 1.0a signature generation', [
            'method' => $method,
            'url' => $url,
            'params_keys' => array_keys($params),
            'signature_base_string_length' => strlen($signatureBaseString),
            'signing_key_length' => strlen($signingKey),
        ]);
        
        // HMAC-SHA1で署名
        $signature = base64_encode(hash_hmac('sha1', $signatureBaseString, $signingKey, true));
        
        return $signature;
    }

    /**
     * 署名ベース文字列を構築
     *
     * @param string $method HTTPメソッド
     * @param string $url リクエストURL
     * @param array $params パラメータ
     * @return string 署名ベース文字列
     */
    private static function buildSignatureBaseString(string $method, string $url, array $params): string
    {
        // URLからクエリパラメータを分離
        $parsedUrl = parse_url($url);
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        if (isset($parsedUrl['port'])) {
            $baseUrl .= ':' . $parsedUrl['port'];
        }
        if (isset($parsedUrl['path'])) {
            $baseUrl .= $parsedUrl['path'];
        }
        
        // クエリパラメータとパラメータをマージ
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            $params = array_merge($params, $queryParams);
        }
        
        // パラメータをURLエンコード
        $encodedParams = [];
        foreach ($params as $key => $value) {
            $encodedParams[self::encode($key)] = self::encode($value);
        }
        
        ksort($encodedParams);
        
        // パラメータ文字列を構築
        $parameterString = '';
        foreach ($encodedParams as $key => $value) {
            if ($parameterString !== '') {
                $parameterString .= '&';
            }
            $parameterString .= $key . '=' . $value;
        }
        
        // 署名ベース文字列を構築
        $signatureBaseString = strtoupper($method) . '&' . self::encode($baseUrl) . '&' . self::encode($parameterString);
        
        // デバッグ用ログ
        Log::debug('OAuth 1.0a signature base string', [
            'method' => $method,
            'base_url' => $baseUrl,
            'parameter_string' => $parameterString,
            'signature_base_string' => $signatureBaseString,
        ]);
        
        return $signatureBaseString;
    }

    /**
     * 署名キーを構築
     *
     * @param string $consumerSecret Consumer Secret
     * @param string|null $tokenSecret Token Secret
     * @return string 署名キー
     */
    private static function buildSigningKey(string $consumerSecret, ?string $tokenSecret): string
    {
        $signingKey = self::encode($consumerSecret) . '&';
        if ($tokenSecret !== null) {
            $signingKey .= self::encode($tokenSecret);
        }
        return $signingKey;
    }

    /**
     * OAuth 1.0a Authorizationヘッダーを生成
     *
     * @param array $params OAuthパラメータ
     * @return string Authorizationヘッダー
     */
    public static function buildAuthorizationHeader(array $params): string
    {
        // パラメータをキーでソート（OAuth 1.0a仕様に準拠）
        ksort($params);
        
        $header = 'OAuth ';
        $first = true;
        
        foreach ($params as $key => $value) {
            if (!$first) {
                $header .= ', ';
            }
            // Authorizationヘッダーでは、値もエンコードする必要がある
            $header .= self::encode($key) . '="' . self::encode($value) . '"';
            $first = false;
        }
        
        return $header;
    }

    /**
     * RFC 3986に準拠したURLエンコード
     *
     * @param string $value エンコードする値
     * @return string エンコードされた値
     */
    public static function encode(string $value): string
    {
        return str_replace('+', ' ', str_replace('%7E', '~', rawurlencode($value)));
    }

    /**
     * OAuth 1.0aパラメータを生成
     *
     * @param string $consumerKey Consumer Key
     * @param string|null $token Token（リクエストトークン取得時はnull）
     * @param string|null $nonce Nonce（指定しない場合は自動生成）
     * @param string|null $timestamp Timestamp（指定しない場合は現在時刻）
     * @return array OAuthパラメータ
     */
    public static function generateOAuthParams(
        string $consumerKey,
        ?string $token = null,
        ?string $nonce = null,
        ?string $timestamp = null
    ): array {
        $params = [
            'oauth_consumer_key' => $consumerKey,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_version' => '1.0',
        ];
        
        if ($token !== null) {
            $params['oauth_token'] = $token;
        }
        
        $params['oauth_nonce'] = $nonce ?? bin2hex(random_bytes(16));
        $params['oauth_timestamp'] = $timestamp ?? (string)time();
        
        return $params;
    }
}

