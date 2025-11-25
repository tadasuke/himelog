<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\GirlService;
use App\Traits\LogsMethodExecution;

class GirlController extends Controller
{
    use LogsMethodExecution;

    protected GirlService $girlService;

    public function __construct(GirlService $girlService)
    {
        $this->girlService = $girlService;
    }

    /**
     * ヒメ情報を取得
     */
    public function getGirl(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        try {
            // 認証されたユーザーIDを取得
            $authenticatedUserId = $request->input('authenticated_user_id');
            if (!$authenticatedUserId) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => '認証が必要です'
                ], 401);
            }

            $girlName = $request->query('girl_name');
            
            if (!$girlName) {
                return response()->json([
                    'error' => 'girl_name is required'
                ], 400);
            }

            $girl = $this->girlService->getGirl($authenticatedUserId, $girlName);

            $result = response()->json([
                'success' => true,
                'girl' => $girl
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Girl fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch girl',
                'message' => $e->getMessage()
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * ヒメ情報を作成または更新
     */
    public function createOrUpdateGirl(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        try {
            // 認証されたユーザーIDを取得
            $authenticatedUserId = $request->input('authenticated_user_id');
            if (!$authenticatedUserId) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => '認証が必要です'
                ], 401);
            }

            $request->validate([
                'girl_name' => 'required|string',
                'memo' => 'nullable|string',
                'urls' => 'nullable|array',
                'urls.*' => 'nullable|string',
                'image_urls' => 'nullable|array',
                'image_urls.*' => 'nullable|string|url',
            ]);

            $girlName = $request->input('girl_name');
            $memo = $request->input('memo');
            $urls = $request->input('urls', []);
            $imageUrls = $request->input('image_urls', []);

            $girl = $this->girlService->createOrUpdateGirl(
                $authenticatedUserId,
                $girlName,
                $memo,
                $urls,
                $imageUrls
            );

            $result = response()->json([
                'success' => true,
                'girl' => $girl
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Girl create or update error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to create or update girl',
                'message' => $e->getMessage()
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * ヒメ一覧を取得
     */
    public function getGirlList(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        try {
            // 認証されたユーザーIDを取得
            $authenticatedUserId = $request->input('authenticated_user_id');
            if (!$authenticatedUserId) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => '認証が必要です'
                ], 401);
            }

            $girlList = $this->girlService->getGirlList($authenticatedUserId);

            $result = response()->json([
                'success' => true,
                'girls' => $girlList
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Girl list fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch girl list',
                'message' => $e->getMessage()
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * URLのタイトルを取得
     */
    public function getUrlTitle(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        try {
            // 認証されたユーザーIDを取得
            $authenticatedUserId = $request->input('authenticated_user_id');
            if (!$authenticatedUserId) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => '認証が必要です'
                ], 401);
            }

            $url = $request->query('url');
            
            if (!$url) {
                return response()->json([
                    'error' => 'url is required'
                ], 400);
            }

            // URLのバリデーション
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'error' => 'Invalid URL format'
                ], 400);
            }

            $title = $this->fetchUrlTitle($url);

            $result = response()->json([
                'success' => true,
                'url' => $url,
                'title' => $title
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('URL title fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch URL title',
                'message' => $e->getMessage()
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * URLからタイトルを取得
     */
    private function fetchUrlTitle(string $url): string
    {
        try {
            // curlを使用してHTMLを取得（より確実）
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_ENCODING, ''); // 自動的にgzip/deflateを処理
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($html === false || $httpCode !== 200) {
                return $url;
            }

            // Content-Typeヘッダーからcharsetを取得
            $charset = 'UTF-8';
            if (preg_match('/charset=([^;]+)/i', $contentType, $matches)) {
                $charset = trim($matches[1]);
            }

            // HTMLのmeta charsetを検出
            if (preg_match('/<meta[^>]+charset=["\']?([^"\'\s>]+)/i', $html, $matches)) {
                $charset = trim($matches[1]);
            }

            // HTMLを正しいエンコーディングに変換
            if (strtoupper($charset) !== 'UTF-8') {
                $html = mb_convert_encoding($html, 'UTF-8', $charset);
            }

            // HTMLからタイトルを抽出
            if (preg_match('/<title[^>]*>([^<]+)<\/title>/is', $html, $matches)) {
                $title = trim($matches[1]);
                
                // HTMLエンティティをデコード
                $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                // 余分な空白を削除
                $title = preg_replace('/\s+/', ' ', $title);
                $title = trim($title);
                
                // 空の場合はURLを返す
                if (empty($title)) {
                    return $url;
                }
                
                return $title;
            }

            // タイトルが見つからない場合はURLを返す
            return $url;
        } catch (\Exception $e) {
            Log::error('Failed to fetch URL title: ' . $e->getMessage());
            return $url;
        }
    }
}
