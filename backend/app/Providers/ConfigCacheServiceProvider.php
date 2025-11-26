<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use App\Services\AwsSecretsManagerService;
use Illuminate\Support\Facades\Log;

class ConfigCacheServiceProvider extends ServiceProvider
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
        // config:cacheコマンド実行前にAWS Secrets ManagerからDB接続情報を取得
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if ($event->command === 'config:cache') {
                $this->loadDatabaseCredentialsFromSecretsManager();
            }
        });
    }

    /**
     * AWS Secrets ManagerからDB接続情報を取得して環境変数に設定
     */
    private function loadDatabaseCredentialsFromSecretsManager(): void
    {
        // ローカル環境の場合はスキップ（.envファイルから読み込む）
        if (app()->environment('local')) {
            return;
        }

        // AWS_SECRET_ARNが設定されていない場合はスキップ
        if (empty(env('AWS_SECRET_ARN'))) {
            Log::warning('AWS_SECRET_ARN is not set. Skipping database credentials loading from Secrets Manager.');
            return;
        }

        try {
            $secretsManager = new AwsSecretsManagerService();
            $secretsManager->setDatabaseCredentialsToEnv();
            
            Log::info('Database credentials loaded from AWS Secrets Manager before config:cache');
        } catch (\Exception $e) {
            Log::error('Failed to load database credentials from AWS Secrets Manager', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // エラーが発生しても処理を続行（.envファイルがある場合はそちらを使用）
            // ただし、develop環境でAWS_SECRET_ARNが設定されている場合は警告
            if (!empty(env('AWS_SECRET_ARN'))) {
                throw new \RuntimeException(
                    'Failed to load database credentials from AWS Secrets Manager. ' .
                    'Please check AWS_SECRET_ARN and IAM permissions. ' .
                    'Error: ' . $e->getMessage()
                );
            }
        }
    }
}
