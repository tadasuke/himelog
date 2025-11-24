<?php

namespace App\Services;

use App\Models\Record;
use App\Models\ShopType;
use Illuminate\Support\Facades\Log;

class RecordService
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
            // 既に数値形式の場合、そのまま返す
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
     * 記録を作成
     */
    public function createRecord(string $userId, array $data): Record
    {
        $shopTypeId = $this->convertShopTypeToId($data['shop_type'] ?? $data['shop_type_id'] ?? null);

        $record = Record::create([
            'user_id' => $userId,
            'shop_type_id' => $shopTypeId,
            'shop_name' => $data['shop_name'],
            'girl_name' => $data['girl_name'] ?? null,
            'visit_date' => $data['visit_date'],
            'face_rating' => $data['face_rating'] ?? null,
            'style_rating' => $data['style_rating'] ?? null,
            'service_rating' => $data['service_rating'] ?? null,
            'overall_rating' => $data['overall_rating'] ?? null,
            'review' => $data['review'] ?? null,
            'price' => $data['price'] ?? null,
            'course' => $data['course'] ?? null,
        ]);

        Log::info('Record created', ['record_id' => $record->id, 'user_id' => $record->user_id]);

        return $record->load('shopType');
    }

    /**
     * ユーザーの記録一覧を取得
     */
    public function getRecords(string $userId)
    {
        return Record::where('user_id', $userId)
            ->with('shopType')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 記録を更新
     */
    public function updateRecord(Record $record, string $userId, array $data): Record
    {
        // 所有者チェック
        if ($record->user_id !== $userId) {
            Log::warning('Unauthorized record update attempt', [
                'record_id' => $record->id,
                'record_user_id' => $record->user_id,
                'authenticated_user_id' => $userId
            ]);
            throw new \Exception('この記録を更新する権限がありません');
        }

        $shopTypeId = $this->convertShopTypeToId($data['shop_type'] ?? $data['shop_type_id'] ?? null);

        $record->update([
            'shop_type_id' => $shopTypeId,
            'shop_name' => $data['shop_name'],
            'girl_name' => $data['girl_name'] ?? null,
            'visit_date' => $data['visit_date'],
            'face_rating' => $data['face_rating'] ?? null,
            'style_rating' => $data['style_rating'] ?? null,
            'service_rating' => $data['service_rating'] ?? null,
            'overall_rating' => $data['overall_rating'] ?? null,
            'review' => $data['review'] ?? null,
            'price' => $data['price'] ?? null,
            'course' => $data['course'] ?? null,
        ]);

        Log::info('Record updated', ['record_id' => $record->id, 'user_id' => $record->user_id]);

        return $record->load('shopType');
    }

    /**
     * 記録を削除
     */
    public function deleteRecord(Record $record, string $userId): void
    {
        // 所有者チェック
        if ($record->user_id !== $userId) {
            Log::warning('Unauthorized record deletion attempt', [
                'record_id' => $record->id,
                'record_user_id' => $record->user_id,
                'authenticated_user_id' => $userId
            ]);
            throw new \Exception('この記録を削除する権限がありません');
        }

        $recordId = $record->id;
        $recordUserId = $record->user_id;
        $record->delete();

        Log::info('Record deleted', ['record_id' => $recordId, 'user_id' => $recordUserId]);
    }

    /**
     * お店の種類とユーザーIDに基づいて登録済みのお店名を取得
     */
    public function getShopNames(string $userId, string $shopType)
    {
        $shopTypeId = $this->convertShopTypeToId($shopType);
        
        return Record::where('user_id', $userId)
            ->where('shop_type_id', $shopTypeId)
            ->distinct()
            ->pluck('shop_name')
            ->sort()
            ->values();
    }

    /**
     * お店の種類、お店の名前、ユーザーIDに基づいて登録済みの女の子の名前を取得
     */
    public function getGirlNames(string $userId, string $shopType, string $shopName)
    {
        $shopTypeId = $this->convertShopTypeToId($shopType);
        
        return Record::where('user_id', $userId)
            ->where('shop_type_id', $shopTypeId)
            ->where('shop_name', $shopName)
            ->distinct()
            ->pluck('girl_name')
            ->sort()
            ->values();
    }

    /**
     * ユーザーが投稿したことがある全ヒメ（女の子）の一覧を取得
     */
    public function getAllGirlNames(string $userId)
    {
        return Record::where('user_id', $userId)
            ->whereNotNull('girl_name')
            ->where('girl_name', '!=', '')
            ->distinct()
            ->pluck('girl_name')
            ->sort()
            ->values();
    }

    /**
     * ユーザーが登録した全お店の一覧を取得（shop_typeごとにグループ化）
     */
    public function getShops(string $userId): array
    {
        $shops = Record::where('user_id', $userId)
            ->with('shopType')
            ->select('shop_type_id', 'shop_name')
            ->distinct()
            ->get()
            ->groupBy(function ($record) {
                return $record->shop_type; // アクセサで取得したshop_type（名前）でグループ化
            })
            ->map(function ($group) {
                return $group->pluck('shop_name')
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();
            })
            ->toArray();

        return $shops;
    }

    /**
     * 特定のお店の記録一覧を取得
     */
    public function getShopRecords(string $userId, string $shopType, string $shopName)
    {
        $shopTypeId = $this->convertShopTypeToId($shopType);
        
        return Record::where('user_id', $userId)
            ->where('shop_type_id', $shopTypeId)
            ->where('shop_name', $shopName)
            ->with('shopType')
            ->orderBy('visit_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 特定のヒメ（女の子）の記録一覧を取得
     */
    public function getGirlRecords(string $userId, string $girlName)
    {
        return Record::where('user_id', $userId)
            ->where('girl_name', $girlName)
            ->with('shopType')
            ->orderBy('visit_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

