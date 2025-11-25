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

    public function __construct()
    {
        $this->environment = env('APP_ENV', 'production');
        $this->appUrl = env('APP_URL', 'http://localhost');
        
        // ローカル環境以外の場合のみS3クライアントを初期化
        if ($this->isLocalEnvironment()) {
            $this->s3Client = null;
            $this->bucket = '';
            $this->cloudFrontUrl = null;
        } else {
            $this->bucket = env('AWS_S3_BUCKET', '');
            $this->cloudFrontUrl = env('AWS_CLOUDFRONT_URL', null);

            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION', 'ap-northeast-1'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID', ''),
                    'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
                ],
            ]);
        }
    }

    /**
     * ローカル環境かどうかを判定
     */
    public function isLocalEnvironment(): bool
    {
        return $this->environment === 'local';
    }

    /**
     * CloudFront URLを取得
     */
    public function getCloudFrontUrl(): ?string
    {
        return $this->cloudFrontUrl;
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
        if ($this->isLocalEnvironment()) {
            return $this->uploadHtmlToLocal($content, $filename);
        } else {
            return $this->uploadHtmlToS3($content, $filename);
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
            $key = 'public-reviews/' . $filename;

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
        if ($this->isLocalEnvironment()) {
            $this->deleteHtmlFromLocal($filename);
        } else {
            $this->deleteHtmlFromS3($filename);
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
            $key = 'public-reviews/' . $filename;

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

