<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ShopType;
use App\Models\Record;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ShopTypeController extends Controller
{
    /**
     * お店の種類一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->query('user_id');
            
            // 全てのお店の種類を取得
            $allShopTypes = ShopType::orderBy('display_order', 'asc')
                ->orderBy('name', 'asc')
                ->get();

            // ユーザーIDが提供されていない場合は、デフォルトの並び順で返す
            if (!$userId) {
                return response()->json([
                    'success' => true,
                    'shop_types' => $allShopTypes
                ]);
            }

            // ユーザーの最新の記録のお店の種類を取得
            $latestRecord = Record::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->first();
            
            $latestShopType = $latestRecord ? $latestRecord->shop_type : null;

            // ユーザーの記録を種類ごとに集計
            $shopTypeCounts = Record::where('user_id', $userId)
                ->select('shop_type', DB::raw('count(*) as count'))
                ->groupBy('shop_type')
                ->orderBy('count', 'desc')
                ->pluck('count', 'shop_type')
                ->toArray();

            // 並び順を決定
            $sortedShopTypes = $allShopTypes->sort(function ($a, $b) use ($latestShopType, $shopTypeCounts) {
                $nameA = $a->name;
                $nameB = $b->name;
                
                // 最新の記録のお店の種類を最初に
                if ($latestShopType) {
                    if ($nameA === $latestShopType && $nameB !== $latestShopType) {
                        return -1; // Aを前に
                    }
                    if ($nameA !== $latestShopType && $nameB === $latestShopType) {
                        return 1; // Bを前に
                    }
                }
                
                // 両方とも最新の種類でない場合、合計数の降順で並べる
                $countA = $shopTypeCounts[$nameA] ?? 0;
                $countB = $shopTypeCounts[$nameB] ?? 0;
                
                if ($countA !== $countB) {
                    return $countB - $countA; // 降順
                }
                
                // 合計数が同じ場合は、display_orderで並べる
                return $a->display_order - $b->display_order;
            })->values();

            return response()->json([
                'success' => true,
                'shop_types' => $sortedShopTypes
            ]);
        } catch (\Exception $e) {
            Log::error('Shop type fetch error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch shop types',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
