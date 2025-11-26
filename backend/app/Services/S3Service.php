<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;

class S3Service
{
    private ?S3Client $s3Client;
    private string $bucket;
    private ?string $cloudFrontUrl;
    private string $environment;
    private string $appUrl;
    private string $storageType;
    private string $s3Prefix;

    public function __construct()
    {
        $this->environment = env('APP_ENV', 'production');
        $this->appUrl = env('APP_URL', 'http://localhost');
        
        // PUBLIC_REVIEW_STORAGE_TYPE環境変数で保存先を指定（local または s3）
        // 設定されていない場合は、APP_ENVがlocalの場合はlocal、それ以外はs3
        $storageType = env('PUBLIC_REVIEW_STORAGE_TYPE');
        if ($storageType === null || $storageType === '') {
            $storageType = ($this->environment === 'local') ? 'local' : 's3';
        }
        $this->storageType = strtolower($storageType);
        
        // PUBLIC_REVIEW_S3_PREFIX環境変数でS3のプレフィックスを指定（デフォルト: review/）
        $s3Prefix = env('PUBLIC_REVIEW_S3_PREFIX', 'review/');
        // 末尾にスラッシュがない場合は追加
        $this->s3Prefix = rtrim($s3Prefix, '/') . '/';
        
        // S3を使用する場合のみS3クライアントを初期化
        if ($this->shouldUseS3()) {
            $this->bucket = env('AWS_S3_BUCKET', '');
            $this->cloudFrontUrl = env('AWS_CLOUDFRONT_URL', null);

            // S3Clientの設定を構築
            $s3Config = [
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION', 'ap-northeast-1'),
            ];

            // 環境変数で認証情報が指定されている場合のみ追加
            // IAMロールを使用する場合は指定しない（SDKが自動的に取得）
            $accessKeyId = env('AWS_ACCESS_KEY_ID', '');
            $secretAccessKey = env('AWS_SECRET_ACCESS_KEY', '');
            
            if ($accessKeyId !== '' && $secretAccessKey !== '') {
                $s3Config['credentials'] = [
                    'key' => $accessKeyId,
                    'secret' => $secretAccessKey,
                ];
            }

            $this->s3Client = new S3Client($s3Config);
        } else {
            $this->s3Client = null;
            $this->bucket = '';
            $this->cloudFrontUrl = null;
        }
    }

    /**
     * S3を使用するかどうかを判定
     */
    private function shouldUseS3(): bool
    {
        return $this->storageType === 's3';
    }

    /**
     * ローカル環境かどうかを判定
     */
    public function isLocalEnvironment(): bool
    {
        return !$this->shouldUseS3();
    }

    /**
     * CloudFront URLを取得
     */
    public function getCloudFrontUrl(): ?string
    {
        return $this->cloudFrontUrl;
    }

    /**
     * S3のプレフィックスを取得
     */
    public function getS3Prefix(): string
    {
        return $this->s3Prefix;
    }

    /**
     * HTMLファイルをアップロード（環境に応じてS3またはローカルに保存）
     *
     * @param string $content HTMLコンテンツ
     * @param string $filename ファイル名（例: abc123def456.html）
     * @return string 公開URL（CloudFront URL、S3 URL、またはローカルURL）
     */
    public function uploadHtml(string $content, string $filename): string
    {
        if ($this->shouldUseS3()) {
            return $this->uploadHtmlToS3($content, $filename);
        } else {
            return $this->uploadHtmlToLocal($content, $filename);
        }
    }

    /**
     * HTMLファイルをローカルのpublicディレクトリに保存
     *
     * @param string $content HTMLコンテンツ
     * @param string $filename ファイル名
     * @return string 公開URL
     */
    private function uploadHtmlToLocal(string $content, string $filename): string
    {
        try {
            $directory = 'public-reviews';
            $filePath = $directory . '/' . $filename;
            
            // publicディレクトリに保存
            $publicPath = public_path($filePath);
            $directoryPath = dirname($publicPath);
            
            // ディレクトリが存在しない場合は作成
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }
            
            // ファイルを保存
            file_put_contents($publicPath, $content);
            
            // パーミッションを設定（読み取り可能にする）
            chmod($publicPath, 0644);

            Log::info('HTML file uploaded to local storage', [
                'path' => $publicPath,
                'filename' => $filename,
            ]);

            // 公開URLを返す（PUBLIC_REVIEW_BASE_URL環境変数を使用）
            $publicBaseUrl = env('PUBLIC_REVIEW_BASE_URL');
            if (!$publicBaseUrl) {
                // 環境変数が設定されていない場合は、ローカル環境の場合はポート番号8000を含める
                $baseUrl = $this->appUrl;
                if ($this->isLocalEnvironment() && !str_contains($baseUrl, ':8000')) {
                    // ポート番号が含まれていない場合は追加
                    $baseUrl = str_replace('http://localhost', 'http://localhost:8000', $baseUrl);
                    $baseUrl = str_replace('http://127.0.0.1', 'http://127.0.0.1:8000', $baseUrl);
                }
                $publicBaseUrl = $baseUrl;
            }
            return rtrim($publicBaseUrl, '/') . '/' . $filePath;
        } catch (\Exception $e) {
            Log::error('Failed to upload HTML to local storage', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);
            throw $e;
        }
    }

    /**
     * HTMLファイルをS3にアップロード
     *
     * @param string $content HTMLコンテンツ
     * @param string $filename ファイル名
     * @return string 公開URL（CloudFront URLまたはS3 URL）
     */
    private function uploadHtmlToS3(string $content, string $filename): string
    {
        try {
            $key = $this->s3Prefix . $filename;

            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $content,
                'ContentType' => 'text/html; charset=utf-8',
                'CacheControl' => 'public, max-age=3600',
            ]);

            Log::info('HTML file uploaded to S3', [
                'bucket' => $this->bucket,
                'key' => $key,
                'filename' => $filename,
            ]);

            // PUBLIC_REVIEW_BASE_URL環境変数が設定されている場合はそれを使用
            $publicBaseUrl = env('PUBLIC_REVIEW_BASE_URL');
            if ($publicBaseUrl) {
                return rtrim($publicBaseUrl, '/') . '/' . $key;
            }

            // CloudFront URLが設定されている場合はそれを使用
            if ($this->cloudFrontUrl) {
                return rtrim($this->cloudFrontUrl, '/') . '/' . $key;
            }

            return $this->s3Client->getObjectUrl($this->bucket, $key);
        } catch (\Exception $e) {
            Log::error('Failed to upload HTML to S3', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);
            throw $e;
        }
    }

    /**
     * HTMLファイルを削除（環境に応じてS3またはローカルから削除）
     *
     * @param string $filename ファイル名
     */
    public function deleteHtml(string $filename): void
    {
        if ($this->shouldUseS3()) {
            $this->deleteHtmlFromS3($filename);
        } else {
            $this->deleteHtmlFromLocal($filename);
        }
    }

    /**
     * ローカルのpublicディレクトリからHTMLファイルを削除
     *
     * @param string $filename ファイル名
     */
    private function deleteHtmlFromLocal(string $filename): void
    {
        try {
            $filePath = public_path('public-reviews/' . $filename);
            
            if (file_exists($filePath)) {
                unlink($filePath);
                
                Log::info('HTML file deleted from local storage', [
                    'path' => $filePath,
                    'filename' => $filename,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete HTML from local storage', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);
            throw $e;
        }
    }

    /**
     * S3からHTMLファイルを削除
     *
     * @param string $filename ファイル名
     */
    private function deleteHtmlFromS3(string $filename): void
    {
        try {
            $key = $this->s3Prefix . $filename;

            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            Log::info('HTML file deleted from S3', [
                'bucket' => $this->bucket,
                'key' => $key,
                'filename' => $filename,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete HTML from S3', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);
            throw $e;
        }
    }
}

