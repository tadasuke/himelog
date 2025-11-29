<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopUrl;
use App\Models\ShopType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ShopService
{
    /**
     * shop_type（名前またはID）をshop_type_id（数値）に変換
     */
    private function convertShopTypeToId($shopType): ?int
    {
        if (!$shopType) {
            return null;
        }

        // 数値形式かどうかをチェック
        if (is_numeric($shopType)) {
            return (int) $shopType;
        }

        // 名前として扱い、shop_typesテーブルからIDを取得
        $shopTypeModel = ShopType::where('name', $shopType)->first();
        if (!$shopTypeModel) {
            throw new \Exception("お店の種類「{$shopType}」が見つかりません");
        }

        return $shopTypeModel->id;
    }

    /**
     * お店情報を取得（URLも含む）
     */
    public function getShop(string $userId, string $shopType, string $shopName): ?Shop
    {
        $shopTypeId = $this->convertShopTypeToId($shopType);
        
        return Shop::where('internal_user_id', $userId)
            ->where('shop_type_id', $shopTypeId)
            ->where('shop_name', $shopName)
            ->with(['shopType', 'shopUrls'])
            ->first();
    }

    /**
     * お店情報を作成または更新
     */
    public function createOrUpdateShop(string $userId, string $shopType, string $shopName, ?string $memo, array $urls = []): Shop
    {
        $shopTypeId = $this->convertShopTypeToId($shopType);
        
        DB::beginTransaction();
        try {
            // お店を取得または作成
            $shop = Shop::firstOrCreate(
                [
                    'internal_user_id' => $userId,
                    'shop_type_id' => $shopTypeId,
                    'shop_name' => $shopName,
                ],
                [
                    // 既存スキーマ互換性のため、user_id にも同じ値を保存（NOT NULL制約対応）
                    'user_id' => $userId,
                    'memo' => $memo,
                ]
            );

            // 既存のお店の場合はmemoを更新
            if ($shop->wasRecentlyCreated === false) {
                $shop->memo = $memo;
                $shop->save();
            }

            // 既存のURLを削除
            $shop->shopUrls()->delete();

            // 新しいURLを追加
            $displayOrder = 0;
            foreach ($urls as $url) {
                $trimmedUrl = trim($url);
                if (!empty($trimmedUrl)) {
                    // URL形式の簡易チェック
                    if (filter_var($trimmedUrl, FILTER_VALIDATE_URL)) {
                        ShopUrl::create([
                            'shop_id' => $shop->id,
                            'url' => $trimmedUrl,
                            'display_order' => $displayOrder++,
                        ]);
                    } else {
                        // URL形式でない場合は、http://を付けて再チェック
                        $urlWithProtocol = 'http://' . $trimmedUrl;
                        if (filter_var($urlWithProtocol, FILTER_VALIDATE_URL)) {
                            ShopUrl::create([
                                'shop_id' => $shop->id,
                                'url' => $urlWithProtocol,
                                'display_order' => $displayOrder++,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            // リレーションを再読み込み
            $shop->load(['shopType', 'shopUrls']);

            Log::info('Shop created or updated', [
                'shop_id' => $shop->id,
                'user_id' => $userId,
                'shop_type' => $shopType,
                'shop_name' => $shopName,
            ]);

            return $shop;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

