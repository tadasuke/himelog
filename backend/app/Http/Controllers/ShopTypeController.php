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
            // レビューを登録したことがあるお店の種類だけを返すかどうか
            $onlyReviewed = $request->input('only_reviewed', false);
            
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

            // ユーザーがレビューを書いたことがあるお店の種類IDを取得（shops経由）
            $userShopTypeIds = Record::where('internal_user_id', $userId)
                ->whereNotNull('shop_id')
                ->with('shop')
                ->get()
                ->pluck('shop.shop_type_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // only_reviewedがtrueの場合、レビューを登録したことがあるお店の種類だけを返す
            if ($onlyReviewed) {
                $reviewedShopTypes = $allShopTypes->filter(function ($shopType) use ($userShopTypeIds) {
                    return in_array($shopType->id, $userShopTypeIds);
                })->values();

                $result = response()->json([
                    'success' => true,
                    'shop_types' => $reviewedShopTypes
                ]);
                $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
                return $result;
            }

            // ユーザーの最新の記録のお店の種類を取得
            $latestRecord = Record::where('internal_user_id', $userId)
                ->whereNotNull('shop_id')
                ->with('shop')
                ->orderBy('created_at', 'desc')
                ->first();
            
            $latestShopTypeId = $latestRecord && $latestRecord->shop
                ? $latestRecord->shop->shop_type_id
                : null;

            // ユーザーの記録を種類ごとに集計（shops経由）
            $shopTypeCounts = Record::where('internal_user_id', $userId)
                ->whereNotNull('shop_id')
                ->with('shop')
                ->get()
                ->groupBy(function ($record) {
                    return $record->shop ? $record->shop->shop_type_id : null;
                })
                ->filter(function ($group, $key) {
                    return !is_null($key);
                })
                ->map(function ($group) {
                    return $group->count();
                })
                ->toArray();

            // 全てのお店の種類をユーザーの使用履歴に基づいて並び替え
            $sortedShopTypes = $allShopTypes->sort(function ($a, $b) use ($latestShopTypeId, $shopTypeCounts, $userShopTypeIds) {
                $idA = $a->id;
                $idB = $b->id;
                
                $hasRecordA = in_array($idA, $userShopTypeIds);
                $hasRecordB = in_array($idB, $userShopTypeIds);
                
                // レビューを書いたことがある種類を優先
                if ($hasRecordA && !$hasRecordB) {
                    return -1; // Aを前に
                }
                if (!$hasRecordA && $hasRecordB) {
                    return 1; // Bを前に
                }
                
                // 両方ともレビューを書いたことがある場合
                if ($hasRecordA && $hasRecordB) {
                    // 最新の記録のお店の種類を最初に
                    if ($latestShopTypeId) {
                        if ($idA === $latestShopTypeId && $idB !== $latestShopTypeId) {
                            return -1; // Aを前に
                        }
                        if ($idA !== $latestShopTypeId && $idB === $latestShopTypeId) {
                            return 1; // Bを前に
                        }
                    }
                    
                    // 合計数の降順で並べる
                    $countA = $shopTypeCounts[$idA] ?? 0;
                    $countB = $shopTypeCounts[$idB] ?? 0;
                    
                    if ($countA !== $countB) {
                        return $countB - $countA; // 降順
                    }
                }
                
                // 合計数が同じ場合、または両方ともレビューを書いたことがない場合は、display_orderで並べる
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
