<?php

namespace App\Services;

use App\Models\Girl;
use App\Models\GirlUrl;
use App\Models\GirlImageUrl;
use App\Models\Record;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GirlService
{
    /**
     * ヒメ情報を取得（URLも含む）
     */
    public function getGirl(string $userId, string $girlName, ?string $shopId = null): ?Girl
    {
        $query = Girl::where('internal_user_id', $userId)
            ->where('girl_name', $girlName);
        
        if ($shopId !== null) {
            $query->where('shop_id', $shopId);
        }
        
        return $query->with(['girlUrls', 'girlImageUrls'])
            ->first();
    }

    /**
     * ヒメ情報を作成または更新
     */
    public function createOrUpdateGirl(string $userId, string $girlName, ?string $memo, array $urls = [], array $imageUrls = [], ?string $shopId = null): Girl
    {
        DB::beginTransaction();
        try {
            // ヒメの詳細は、既にレビュー投稿時に作成されたgirlsレコードに対して更新のみ行う
            $query = Girl::where('internal_user_id', $userId)
                ->where('girl_name', $girlName);
            
            if ($shopId !== null) {
                $query->where('shop_id', $shopId);
            }
            
            $girl = $query->first();

            if (!$girl) {
                throw new \Exception('先にこのヒメのレビューを登録してください。');
            }

            // memoを更新
            $girl->memo = $memo;
            $girl->save();

            // 既存のURLを削除
            $girl->girlUrls()->delete();

            // 新しいURLを追加
            $displayOrder = 0;
            foreach ($urls as $url) {
                $trimmedUrl = trim($url);
                if (!empty($trimmedUrl)) {
                    // URL形式の簡易チェック
                    if (filter_var($trimmedUrl, FILTER_VALIDATE_URL)) {
                        GirlUrl::create([
                            'girl_id' => $girl->id,
                            'url' => $trimmedUrl,
                            'display_order' => $displayOrder++,
                        ]);
                    } else {
                        // URL形式でない場合は、http://を付けて再チェック
                        $urlWithProtocol = 'http://' . $trimmedUrl;
                        if (filter_var($urlWithProtocol, FILTER_VALIDATE_URL)) {
                            GirlUrl::create([
                                'girl_id' => $girl->id,
                                'url' => $urlWithProtocol,
                                'display_order' => $displayOrder++,
                            ]);
                        }
                    }
                }
            }

            // 既存の画像URLを削除
            $girl->girlImageUrls()->delete();

            // 新しい画像URLを追加
            $imageDisplayOrder = 0;
            foreach ($imageUrls as $imageUrl) {
                $trimmedImageUrl = trim($imageUrl);
                if (!empty($trimmedImageUrl)) {
                    // URL形式のチェック
                    if (filter_var($trimmedImageUrl, FILTER_VALIDATE_URL)) {
                        GirlImageUrl::create([
                            'girl_id' => $girl->id,
                            'image_url' => $trimmedImageUrl,
                            'display_order' => $imageDisplayOrder++,
                        ]);
                    }
                }
            }

            DB::commit();

            // リレーションを再読み込み
            $girl->load(['girlUrls', 'girlImageUrls']);

            Log::info('Girl created or updated', [
                'girl_id' => $girl->id,
                'user_id' => $userId,
                'girl_name' => $girlName,
            ]);

            return $girl;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ユーザーが登録したヒメ一覧を取得（重複排除、最終登録日時、平均評価を含む）
     */
    public function getGirlList(string $userId): array
    {
        // ユーザーが登録した記録から、girl_idがnullでないものを取得
        $records = Record::where('internal_user_id', $userId)
            ->whereNotNull('girl_id')
            ->with(['shop', 'shop.shopType', 'girl'])
            ->get();

        // girl_idでグループ化（同じ名前でもお店が違えば別のヒメとして扱う）
        $groupedRecords = $records->groupBy(function ($record) {
            return ($record->shop_id ?? 'no_shop') . '_' . ($record->girl_id ?? 'no_girl');
        });

        $girlList = [];
        foreach ($groupedRecords as $groupKey => $girlRecords) {
            // 最新の記録からgirl_nameを取得
            $latestRecord = $girlRecords->sortByDesc(function ($record) {
                return $record->visit_date ? $record->visit_date->format('Y-m-d H:i:s') : $record->created_at;
            })->first();
            
            $girlName = $latestRecord->girl_name ?? '';

            // 総合評価の平均値を計算（nullを除外）
            $overallRatings = $girlRecords->pluck('overall_rating')->filter(function ($rating) {
                return $rating !== null;
            });
            $averageRating = $overallRatings->count() > 0 
                ? round($overallRatings->avg(), 1) 
                : null;

            // 最終登録日時を取得（visit_dateがあればそれ、なければcreated_at）
            $lastRegisteredAt = $latestRecord->visit_date 
                ? $latestRecord->visit_date->format('Y-m-d H:i:s')
                : $latestRecord->created_at->format('Y-m-d H:i:s');

            // 登録回数を取得
            $recordCount = $girlRecords->count();

            // ヒメの画像URL（1枚目のみ）を取得
            $firstImageUrl = null;
            if ($latestRecord->girl) {
                $latestRecord->girl->loadMissing(['girlImageUrls' => function ($query) {
                    $query->orderBy('display_order')->limit(1);
                }]);
                
                if ($latestRecord->girl->girlImageUrls && $latestRecord->girl->girlImageUrls->count() > 0) {
                    $firstImageUrl = $latestRecord->girl->girlImageUrls->first()->image_url;
                }
            }

            $girlList[] = [
                'girl_name' => $girlName,
                'shop_type' => $latestRecord->shop_type, // アクセサで取得
                'shop_name' => $latestRecord->shop && $latestRecord->shop->shop_name 
                    ? $latestRecord->shop->shop_name 
                    : null,
                'last_registered_at' => $lastRegisteredAt,
                'average_overall_rating' => $averageRating,
                'record_count' => $recordCount,
                'first_image_url' => $firstImageUrl,
            ];
        }

        // 最終登録日時の降順でソート
        usort($girlList, function ($a, $b) {
            return strcmp($b['last_registered_at'], $a['last_registered_at']);
        });

        return $girlList;
    }
}
