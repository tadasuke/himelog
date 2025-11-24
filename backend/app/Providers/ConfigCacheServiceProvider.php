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
        // ローカル環境の場合は何もしない
        if ($this->isLocalEnvironment()) {
            Log::info('Local environment detected. Skipping Secrets Manager lookup for config:cache.');
            return;
        }

        // AWS_SECRET_ARNが設定されていない場合はスキップ（エラーにはしない）
        if (empty(env('AWS_SECRET_ARN'))) {
            Log::warning('AWS_SECRET_ARN is not set. Skipping Secrets Manager lookup for config:cache.');
            return;
        }

        try {
            Log::info('Fetching database credentials from AWS Secrets Manager before config:cache...');
            
            $secretsService = new AwsSecretsManagerService();
            $secretsService->setDatabaseCredentialsToEnv();
            
            Log::info('Database credentials retrieved successfully from Secrets Manager.');
        } catch (\Exception $e) {
            Log::error('Failed to fetch database credentials from Secrets Manager during config:cache', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * ローカル環境かどうかを判定
     *
     * @return bool
     */
    private function isLocalEnvironment(): bool
    {
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

