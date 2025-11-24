<?php

namespace App\Providers;

use App\Services\AwsSecretsManagerService;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class ConfigCacheServiceProvider extends EventServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        parent::boot();

        // config:cacheコマンド実行前にSecrets Managerから値を取得
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if ($event->command === 'config:cache') {
                $this->handleConfigCacheCommand();
            }
        });
    }

    /**
     * config:cacheコマンド実行時にSecrets ManagerからDB接続情報を取得
     */
    private function handleConfigCacheCommand(): void
    {
        // 現在のドメインを取得（CLI実行時はAPP_URLから取得）
        $domain = $this->getCurrentDomainForConfigCache();
        
        // ローカル環境の場合は何もしない
        if ($this->isLocalEnvironment($domain)) {
            Log::info('Local environment detected. Skipping Secrets Manager lookup for config:cache.', [
                'domain' => $domain,
            ]);
            return;
        }

        // ドメインが取得できない場合はエラー
        if (empty($domain)) {
            throw new \RuntimeException(
                'Domain could not be determined for config:cache command. ' .
                'Please set APP_URL environment variable to the domain name.'
            );
        }

        try {
            Log::info('Fetching database credentials from AWS Secrets Manager before config:cache...', [
                'domain' => $domain,
            ]);
            
            // ドメインからARNを決定してSecrets Managerサービスを作成
            $secretsService = new \App\Services\AwsSecretsManagerService($domain);
            
            // ARNが設定されていない場合はエラー
            if (!$secretsService->hasArn()) {
                throw new \InvalidArgumentException(
                    "No ARN mapping found for domain: {$domain}. " .
                    "Please add it to the domainArnMapping array in AwsSecretsManagerService."
                );
            }
            
            $secretsService->setDatabaseCredentialsToEnv();
            
            Log::info('Database credentials retrieved successfully from Secrets Manager.', [
                'domain' => $domain,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch database credentials from Secrets Manager during config:cache', [
                'error' => $e->getMessage(),
                'domain' => $domain,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * config:cacheコマンド実行時のドメインを取得
     * CLI実行時はAPP_URLからドメインを抽出
     * 
     * @return string|null
     */
    private function getCurrentDomainForConfigCache(): ?string
    {
        // CLI実行時はAPP_URLからドメインを抽出
        $appUrl = env('APP_URL', '');
        if (!empty($appUrl)) {
            $parsed = parse_url($appUrl);
            if (isset($parsed['host'])) {
                return $parsed['host'];
            }
        }
        
        return null;
    }

    /**
     * 現在のドメインを取得
     * 
     * @return string|null
     */
    private function getCurrentDomain(): ?string
    {
        return \App\Services\AwsSecretsManagerService::getCurrentDomain();
    }

    /**
     * ローカル環境かどうかを判定
     *
     * @param string|null $domain
     * @return bool
     */
    private function isLocalEnvironment(?string $domain = null): bool
    {
        // ドメインが指定されている場合、ローカルドメインかどうかを確認
        if ($domain !== null) {
            $localDomains = ['localhost', '127.0.0.1', 'localhost:8000', '127.0.0.1:8000'];
            if (in_array(strtolower($domain), $localDomains)) {
                return true;
            }
            // madfaction.netドメインでない場合はローカル環境とみなす
            if (!str_ends_with(strtolower($domain), '.madfaction.net')) {
                return true;
            }
        }
        
        $env = env('APP_ENV', 'production');
        
        // APP_ENVがlocalまたはdevelopmentの場合はローカル環境と判定
        if (in_array(strtolower($env), ['local', 'development'])) {
            return true;
        }
        
        // APP_ENVが設定されていない、かつローカルホストで実行されている場合
        if (empty($env) && (php_sapi_name() === 'cli' || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']))) {
            return true;
        }
        
        return false;
    }
}

