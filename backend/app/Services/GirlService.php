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
    public function getGirl(string $userId, string $girlName): ?Girl
    {
        return Girl::where('user_id', $userId)
            ->where('girl_name', $girlName)
            ->with(['girlUrls', 'girlImageUrls'])
            ->first();
    }

    /**
     * ヒメ情報を作成または更新
     */
    public function createOrUpdateGirl(string $userId, string $girlName, ?string $memo, array $urls = [], array $imageUrls = []): Girl
    {
        DB::beginTransaction();
        try {
            // ヒメを取得または作成
            $girl = Girl::firstOrCreate(
                [
                    'user_id' => $userId,
                    'girl_name' => $girlName,
                ],
                [
                    'memo' => $memo,
                ]
            );

            // 既存のヒメの場合はmemoを更新
            if ($girl->wasRecentlyCreated === false) {
                $girl->memo = $memo;
                $girl->save();
            }

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
        // ユーザーが登録した記録から、girl_nameがnullでないものを取得
        $records = Record::where('user_id', $userId)
            ->whereNotNull('girl_name')
            ->where('girl_name', '!=', '')
            ->with('shopType')
            ->get();

        // girl_nameでグループ化
        $groupedRecords = $records->groupBy('girl_name');

        $girlList = [];
        foreach ($groupedRecords as $girlName => $girlRecords) {
            // 最新の記録を取得（visit_dateがあればそれで、なければcreated_atでソート）
            $latestRecord = $girlRecords->sortByDesc(function ($record) {
                return $record->visit_date ? $record->visit_date->format('Y-m-d H:i:s') : $record->created_at;
            })->first();

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

            $girlList[] = [
                'girl_name' => $girlName,
                'shop_type' => $latestRecord->shop_type, // アクセサで取得
                'shop_name' => $latestRecord->shop_name,
                'last_registered_at' => $lastRegisteredAt,
                'average_overall_rating' => $averageRating,
                'record_count' => $recordCount,
            ];
        }

        // 最終登録日時の降順でソート
        usort($girlList, function ($a, $b) {
            return strcmp($b['last_registered_at'], $a['last_registered_at']);
        });

        return $girlList;
    }
}
