<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\ShopService;
use App\Traits\LogsMethodExecution;

class ShopController extends Controller
{
    use LogsMethodExecution;

    protected ShopService $shopService;

    public function __construct(ShopService $shopService)
    {
        $this->shopService = $shopService;
    }

    /**
     * お店情報を取得
     */
    public function getShop(Request $request): JsonResponse
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

            $shopType = $request->query('shop_type');
            $shopName = $request->query('shop_name');
            
            if (!$shopType) {
                return response()->json([
                    'error' => 'shop_type is required'
                ], 400);
            }

            if (!$shopName) {
                return response()->json([
                    'error' => 'shop_name is required'
                ], 400);
            }

            $shop = $this->shopService->getShop($authenticatedUserId, $shopType, $shopName);

            $result = response()->json([
                'success' => true,
                'shop' => $shop
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Shop fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch shop',
                'message' => $e->getMessage()
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * お店情報を作成または更新
     */
    public function createOrUpdateShop(Request $request): JsonResponse
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
                'shop_type' => 'required|string',
                'shop_name' => 'required|string',
                'memo' => 'nullable|string',
                'urls' => 'nullable|array',
                'urls.*' => 'nullable|string',
            ]);

            $shopType = $request->input('shop_type');
            $shopName = $request->input('shop_name');
            $memo = $request->input('memo');
            $urls = $request->input('urls', []);

            $shop = $this->shopService->createOrUpdateShop(
                $authenticatedUserId,
                $shopType,
                $shopName,
                $memo,
                $urls
            );

            $result = response()->json([
                'success' => true,
                'shop' => $shop
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Shop create or update error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to create or update shop',
                'message' => $e->getMessage()
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }
}
