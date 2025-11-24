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
            ]);

            $girlName = $request->input('girl_name');
            $memo = $request->input('memo');
            $urls = $request->input('urls', []);

            $girl = $this->girlService->createOrUpdateGirl(
                $authenticatedUserId,
                $girlName,
                $memo,
                $urls
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
}


