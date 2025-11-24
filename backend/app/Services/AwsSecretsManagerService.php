<?php

namespace App\Services;

use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;

class AwsSecretsManagerService
{
    private SecretsManagerClient $client;
    private ?string $secretArn = null;

    /**
     * ドメインからARNへのマッピング
     * 
     * @var array<string, string>
     */
    private array $domainArnMapping = [
        '4zklqklybqhu.madfaction.net' => 'arn:aws:secretsmanager:ap-northeast-1:034427362829:secret:himelog/rds/himelog_develop-UP4R6v',
    ];

    public function __construct(?string $domain = null)
    {
        // ドメインが指定されている場合は、ドメインからARNを決定
        if ($domain !== null) {
            $this->secretArn = $this->getArnByDomain($domain);
        }
        
        $config = [
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION', 'ap-northeast-1'),
        ];
        
        // 認証情報が設定されている場合は使用、そうでない場合はIAMロールを使用
        if (env('AWS_ACCESS_KEY_ID') && env('AWS_SECRET_ACCESS_KEY')) {
            $config['credentials'] = [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ];
        }
        
        $this->client = new SecretsManagerClient($config);
    }

    /**
     * ドメインからARNを取得
     * 
     * @param string $domain
     * @return string|null
     */
    private function getArnByDomain(string $domain): ?string
    {
        return $this->domainArnMapping[$domain] ?? null;
    }

    /**
     * 現在のドメインからARNを設定
     * 
     * @param string $domain
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setArnByDomain(string $domain): void
    {
        $arn = $this->getArnByDomain($domain);
        
        if (empty($arn)) {
            throw new \InvalidArgumentException(
                "No ARN mapping found for domain: {$domain}. Please add it to the domainArnMapping array."
            );
        }
        
        $this->secretArn = $arn;
    }

    /**
     * ARNが設定されているかどうかを確認
     * 
     * @return bool
     */
    public function hasArn(): bool
    {
        return !empty($this->secretArn);
    }

    /**
     * 現在のドメインを取得（HTTPリクエスト時）
     * 
     * @return string|null
     */
    public static function getCurrentDomain(): ?string
    {
        if (app()->runningInConsole()) {
            // CLI実行時はAPP_URLからドメインを抽出
            $appUrl = env('APP_URL', '');
            if (!empty($appUrl)) {
                $parsed = parse_url($appUrl);
                return $parsed['host'] ?? null;
            }
            return null;
        }
        
        // HTTPリクエスト時はリクエストから取得
        $request = request();
        if ($request) {
            return $request->getHost();
        }
        
        return $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null;
    }

    /**
     * Secrets ManagerからDB接続情報を取得
     * 
     * @return array DB接続情報の配列
     * @throws \Exception
     */
    public function getDatabaseCredentials(): array
    {
        if (empty($this->secretArn)) {
            throw new \InvalidArgumentException(
                'Secret ARN is not set. Please call setArnByDomain() or provide domain to constructor.'
            );
        }
        
        try {
            $result = $this->client->getSecretValue([
                'SecretId' => $this->secretArn,
            ]);

            $secret = $result['SecretString'];
            $credentials = json_decode($secret, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to decode secret JSON: ' . json_last_error_msg());
            }

            // RDSのシークレット形式に合わせてマッピング
            // 通常のRDSシークレット形式: username, password, host, port, dbname など
            return [
                'DB_HOST' => $credentials['host'] ?? $credentials['DB_HOST'] ?? null,
                'DB_PORT' => $credentials['port'] ?? $credentials['DB_PORT'] ?? '3306',
                'DB_DATABASE' => $credentials['dbname'] ?? $credentials['dbInstanceIdentifier'] ?? $credentials['DB_DATABASE'] ?? null,
                'DB_USERNAME' => $credentials['username'] ?? $credentials['DB_USERNAME'] ?? null,
                'DB_PASSWORD' => $credentials['password'] ?? $credentials['DB_PASSWORD'] ?? null,
            ];
        } catch (AwsException $e) {
            Log::error('AWS Secrets Manager error', [
                'message' => $e->getMessage(),
                'code' => $e->getAwsErrorCode(),
                'arn' => $this->secretArn,
            ]);
            throw new \Exception('Failed to retrieve database credentials from AWS Secrets Manager: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            Log::error('Failed to get database credentials from Secrets Manager', [
                'message' => $e->getMessage(),
                'arn' => $this->secretArn,
            ]);
            throw $e;
        }
    }

    /**
     * 取得したDB接続情報を環境変数に設定
     * 
     * @return void
     */
    public function setDatabaseCredentialsToEnv(): void
    {
        $credentials = $this->getDatabaseCredentials();

        foreach ($credentials as $key => $value) {
            if ($value !== null) {
                // 複数の方法で環境変数を設定して確実に反映されるようにする
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                
                // LaravelのDotenvが既に読み込まれている場合に備えて、.envファイルを更新
                // ただし、config:cache実行時は既に.envが読み込まれているため、putenvで十分
            }
        }
    }
}

