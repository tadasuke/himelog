<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;

class S3Service
{
    private ?S3Client $s3Client;
    private string $bucket;
    private string $environment;
    private string $appUrl;
    private string $storageType;
    private string $s3Prefix;

    public function __construct()
    {
        $this->environment = config('app.env', 'production');
        $this->appUrl = config('app.url', 'http://localhost');
        
        // PUBLIC_REVIEW_STORAGE_TYPE環境変数で保存先を指定（local または s3）
        // 設定されていない場合は、APP_ENVがlocalの場合はlocal、それ以外はs3
        $storageType = config('aws.public_review.storage_type');
        if ($storageType === null || $storageType === '') {
            $storageType = ($this->environment === 'local') ? 'local' : 's3';
        }
        $this->storageType = strtolower($storageType);
        
        // PUBLIC_REVIEW_S3_PREFIX環境変数でS3のプレフィックスを指定（デフォルト: review/）
        $s3Prefix = config('aws.public_review.s3_prefix', 'review/');
        // 末尾にスラッシュがない場合は追加
        $this->s3Prefix = rtrim($s3Prefix, '/') . '/';
        
        // S3を使用する場合のみS3クライアントを初期化
        if ($this->shouldUseS3()) {
            $this->bucket = config('aws.s3.bucket', '');

            // S3Clientの設定を構築
            $s3Config = [
                'version' => 'latest',
                'region' => config('aws.s3.region', 'ap-northeast-1'),
            ];

            // 環境変数で認証情報が指定されている場合のみ追加
            // IAMロールを使用する場合は指定しない（SDKが自動的に取得）
            $accessKeyId = config('aws.s3.access_key_id', '');
            $secretAccessKey = config('aws.s3.secret_access_key', '');
            
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
     * S3のプレフィックスを取得
     */
    public function getS3Prefix(): string
    {
        return $this->s3Prefix;
    }

    /**
     * 公開URLを生成（環境に応じて適切なURLを返す）
     *
     * @param string $filename ファイル名（例: abc123def456.html）
     * @return string 公開URL
     */
    public function getPublicUrl(string $filename): string
    {
        // PUBLIC_REVIEW_BASE_URL環境変数が設定されている場合はそれを使用
        $publicBaseUrl = config('aws.public_review.base_url');
        if ($publicBaseUrl) {
            // 公開URLにはs3Prefixを含めない（PUBLIC_REVIEW_BASE_URL + ファイル名のみ）
            return rtrim($publicBaseUrl, '/') . '/' . $filename;
        }

        if ($this->isLocalEnvironment()) {
            // ローカル環境の場合
            $baseUrl = $this->appUrl;
            if (!str_contains($baseUrl, ':8000') && str_contains($baseUrl, 'localhost')) {
                $baseUrl = str_replace('http://localhost', 'http://localhost:8000', $baseUrl);
                $baseUrl = str_replace('http://127.0.0.1', 'http://127.0.0.1:8000', $baseUrl);
            }
            // ローカル環境では public-reviews ディレクトリを使用
            return rtrim($baseUrl, '/') . '/public-reviews/' . $filename;
        } else {
            // 本番環境の場合（PUBLIC_REVIEW_BASE_URLが設定されていない場合はS3の直接URLを生成）
            // S3の直接URLにはs3Prefixを含める（S3の実際のパス）
            $key = $this->s3Prefix . $filename;
            return $this->s3Client->getObjectUrl($this->bucket, $key);
        }
    }

    /**
     * HTMLファイルをアップロード（環境に応じてS3またはローカルに保存）
     *
     * @param string $content HTMLコンテンツ
     * @param string $filename ファイル名（例: abc123def456.html）
     * @return string 公開URL（PUBLIC_REVIEW_BASE_URL、S3 URL、またはローカルURL）
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

            // 公開URLを返す
            return $this->getPublicUrl($filename);
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
     * @return string 公開URL（PUBLIC_REVIEW_BASE_URLまたはS3 URL）
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

            // 公開URLを返す
            return $this->getPublicUrl($filename);
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

