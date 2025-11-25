<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ShopType;
use App\Models\Record;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Traits\LogsMethodExecution;

class ShopTypeController extends Controller
{
    use LogsMethodExecution;
    /**
     * お店の種類一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request], __FILE__, __LINE__);
        try {
            // 認証されたユーザーIDを取得（オプション）
            $userId = $request->input('authenticated_user_id');
            
            // 全てのお店の種類を取得
            $allShopTypes = ShopType::orderBy('display_order', 'asc')
                ->orderBy('name', 'asc')
                ->get();

            // ユーザーIDが提供されていない場合は、デフォルトの並び順で返す
            if (!$userId) {
                $result = response()->json([
                    'success' => true,
                    'shop_types' => $allShopTypes
                ]);
                $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
                return $result;
            }

            // ユーザーの最新の記録のお店の種類を取得
            $latestRecord = Record::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->first();
            
            // shop_type_idを直接使用（リレーションではなく）
            $latestShopTypeId = $latestRecord ? $latestRecord->shop_type_id : null;

            // ユーザーの記録を種類ごとに集計
            $shopTypeCounts = Record::where('user_id', $userId)
                ->select('shop_type_id', DB::raw('count(*) as count'))
                ->groupBy('shop_type_id')
                ->orderBy('count', 'desc')
                ->pluck('count', 'shop_type_id')
                ->toArray();

            // 並び順を決定
            $sortedShopTypes = $allShopTypes->sort(function ($a, $b) use ($latestShopTypeId, $shopTypeCounts) {
                $idA = $a->id;
                $idB = $b->id;
                
                // 最新の記録のお店の種類を最初に
                if ($latestShopTypeId) {
                    if ($idA === $latestShopTypeId && $idB !== $latestShopTypeId) {
                        return -1; // Aを前に
                    }
                    if ($idA !== $latestShopTypeId && $idB === $latestShopTypeId) {
                        return 1; // Bを前に
                    }
                }
                
                // 両方とも最新の種類でない場合、合計数の降順で並べる
                $countA = $shopTypeCounts[$idA] ?? 0;
                $countB = $shopTypeCounts[$idB] ?? 0;
                
                if ($countA !== $countB) {
                    return $countB - $countA; // 降順
                }
                
                // 合計数が同じ場合は、display_orderで並べる
                return $a->display_order - $b->display_order;
            })->values();

            $result = response()->json([
                'success' => true,
                'shop_types' => $sortedShopTypes
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Shop type fetch error', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId ?? null,
            ]);
            $result = response()->json([
                'error' => 'Failed to fetch shop types'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }
}
