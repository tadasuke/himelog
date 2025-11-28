<?php

namespace App\Services;

use App\Models\Record;
use App\Models\ShopType;
use App\Models\Girl;
use Illuminate\Support\Facades\Log;
use App\Services\S3Service;

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
     * 各記録にヒメの画像URL（最初の1枚目）を含める
     */
    public function getRecords(string $userId)
    {
        $records = Record::where('user_id', $userId)
            ->with('shopType')
            ->orderBy('created_at', 'desc')
            ->get();

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl_name) {
                $girl = Girl::where('user_id', $userId)
                    ->where('girl_name', $record->girl_name)
                    ->with(['girlImageUrls' => function ($query) {
                        $query->orderBy('display_order')->limit(1);
                    }])
                    ->first();
                
                if ($girl && $girl->girlImageUrls && $girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $girl->girlImageUrls->first()->image_url;
                }
            }
            $record->girl_image_url = $girlImageUrl;
        }

        return $records;
    }

    /**
     * レビューを検索
     * 各記録にヒメの画像URL（最初の1枚目）を含める
     *
     * @param string $userId
     * @param array $filters 検索条件
     *   - shop_type_ids: array お店の種類IDの配列（複数選択可）
     *   - overall_rating_min: int 総合評価の最小値
     *   - overall_rating_max: int 総合評価の最大値
     *   - visit_date_from: string 利用日の開始日（Y-m-d形式）
     *   - visit_date_to: string 利用日の終了日（Y-m-d形式）
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function searchRecords(string $userId, array $filters = [])
    {
        $query = Record::where('user_id', $userId)
            ->with('shopType');

        // お店の種類でフィルタ（複数選択可）
        if (!empty($filters['shop_type_ids']) && is_array($filters['shop_type_ids'])) {
            $shopTypeIds = array_filter(array_map('intval', $filters['shop_type_ids']));
            if (!empty($shopTypeIds)) {
                $query->whereIn('shop_type_id', $shopTypeIds);
            }
        }

        // 総合評価の範囲でフィルタ
        if (isset($filters['overall_rating_min']) && $filters['overall_rating_min'] !== '') {
            $minRating = (int) $filters['overall_rating_min'];
            $query->where('overall_rating', '>=', $minRating);
        }
        if (isset($filters['overall_rating_max']) && $filters['overall_rating_max'] !== '') {
            $maxRating = (int) $filters['overall_rating_max'];
            $query->where('overall_rating', '<=', $maxRating);
        }

        // 利用日の範囲でフィルタ
        if (!empty($filters['visit_date_from'])) {
            $fromDate = $filters['visit_date_from'];
            $query->where(function ($q) use ($fromDate) {
                $q->where('visit_date', '>=', $fromDate)
                  ->orWhere(function ($subQ) use ($fromDate) {
                      $subQ->whereNull('visit_date')
                           ->where('created_at', '>=', $fromDate);
                  });
            });
        }
        if (!empty($filters['visit_date_to'])) {
            $toDate = $filters['visit_date_to'];
            $query->where(function ($q) use ($toDate) {
                $q->where('visit_date', '<=', $toDate)
                  ->orWhere(function ($subQ) use ($toDate) {
                      $subQ->whereNull('visit_date')
                           ->where('created_at', '<=', $toDate);
                  });
            });
        }

        $records = $query->orderByRaw('COALESCE(visit_date, created_at) DESC')
            ->get();

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl_name) {
                $girl = Girl::where('user_id', $userId)
                    ->where('girl_name', $record->girl_name)
                    ->with(['girlImageUrls' => function ($query) {
                        $query->orderBy('display_order')->limit(1);
                    }])
                    ->first();
                
                if ($girl && $girl->girlImageUrls && $girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $girl->girlImageUrls->first()->image_url;
                }
            }
            $record->girl_image_url = $girlImageUrl;
        }

        return $records;
    }

    /**
     * グラフ表示用に過去10件の記録を取得
     * 来店日の降順（最新が右側）でソートし、総合評価を含める
     */
    public function getRecentRecordsForChart(string $userId, int $limit = 10)
    {
        // 最新のレコードを取得（来店日または作成日の降順）
        $records = Record::where('user_id', $userId)
            ->orderByRaw('COALESCE(visit_date, created_at) DESC')
            ->limit($limit)
            ->get();

        // 来店日の昇順にソート（左から右へ古い順→新しい順）
        // visit_dateがない場合はcreated_atを使用
        $sortedRecords = $records->sortBy(function ($record) {
            return $record->visit_date ? $record->visit_date->timestamp : $record->created_at->timestamp;
        })->values();

        return $sortedRecords;
    }

    /**
     * お店のタイプごとの集計を取得（円グラフ用）
     */
    public function getShopTypeStatistics(string $userId): array
    {
        $records = Record::where('user_id', $userId)
            ->with('shopType')
            ->get();

        // お店のタイプごとに集計
        $statistics = [];
        foreach ($records as $record) {
            $shopTypeName = $record->shop_type ?? '不明';
            
            if (!isset($statistics[$shopTypeName])) {
                $statistics[$shopTypeName] = 0;
            }
            $statistics[$shopTypeName]++;
        }

        // 配列形式に変換（ラベルと値のペア）
        $result = [];
        foreach ($statistics as $shopTypeName => $count) {
            $result[] = [
                'label' => $shopTypeName,
                'value' => $count
            ];
        }

        // 件数の降順でソート
        usort($result, function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });

        return $result;
    }

    /**
     * 総合評価ごとの集計を取得（円グラフ用）
     */
    public function getOverallRatingStatistics(string $userId): array
    {
        $records = Record::where('user_id', $userId)
            ->whereNotNull('overall_rating')
            ->where('overall_rating', '>', 0)
            ->get();

        // 総合評価ごとに集計（1〜10星）
        $statistics = [];
        for ($rating = 1; $rating <= 10; $rating++) {
            $count = $records->where('overall_rating', $rating)->count();
            if ($count > 0) {
                $statistics[] = [
                    'label' => $rating . '星',
                    'value' => $count
                ];
            }
        }

        // 評価の降順でソート（10星から1星の順）
        usort($statistics, function ($a, $b) {
            // ラベルから数値を抽出して比較
            $ratingA = (int) str_replace('星', '', $a['label']);
            $ratingB = (int) str_replace('星', '', $b['label']);
            return $ratingB <=> $ratingA;
        });

        return $statistics;
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
     * ユーザーが登録した全お店の一覧を取得
     * 各お店について利用回数、総合評価の平均、最終利用日を含める
     * 最終利用日の降順でソート
     */
    public function getShops(string $userId): array
    {
        $records = Record::where('user_id', $userId)
            ->with('shopType')
            ->get();

        $shopsMap = [];
        
        // お店ごとにグループ化（shop_type_idとshop_nameの組み合わせで一意に識別）
        $groupedRecords = $records->groupBy(function ($record) {
            return $record->shop_type_id . '_' . $record->shop_name;
        });

        foreach ($groupedRecords as $shopRecords) {
            $firstRecord = $shopRecords->first();
            $visitCount = $shopRecords->count();
            
            $ratings = $shopRecords
                ->pluck('overall_rating')
                ->filter(function ($rating) {
                    return $rating !== null && $rating > 0;
                });
            
            $averageRating = $ratings->count() > 0 
                ? round($ratings->avg(), 1) 
                : 0;

            // 最終利用日を取得（visit_dateが存在する場合はそれを使用、なければcreated_atを使用）
            $lastVisitDate = $shopRecords
                ->map(function ($record) {
                    return $record->visit_date ? $record->visit_date->format('Y-m-d') : $record->created_at->format('Y-m-d');
                })
                ->sort()
                ->last();

            // ソート用の日付（タイムスタンプ）
            $lastVisitTimestamp = $shopRecords
                ->map(function ($record) {
                    return $record->visit_date ? $record->visit_date->timestamp : $record->created_at->timestamp;
                })
                ->max();

            $shopsMap[] = [
                'name' => $firstRecord->shop_name,
                'shop_type' => $firstRecord->shop_type,
                'visit_count' => $visitCount,
                'average_rating' => $averageRating,
                'last_visit_date' => $lastVisitDate,
                'last_visit_timestamp' => $lastVisitTimestamp,
            ];
        }

        // 最終利用日の降順でソート
        usort($shopsMap, function ($a, $b) {
            return $b['last_visit_timestamp'] <=> $a['last_visit_timestamp'];
        });

        // ソート用のタイムスタンプを削除
        $shops = array_map(function ($shop) {
            unset($shop['last_visit_timestamp']);
            return $shop;
        }, $shopsMap);

        return $shops;
    }

    /**
     * 特定のお店の記録一覧を取得
     * 各記録にヒメの画像URL（最初の1枚目）を含める
     */
    public function getShopRecords(string $userId, string $shopType, string $shopName)
    {
        $shopTypeId = $this->convertShopTypeToId($shopType);
        
        $records = Record::where('user_id', $userId)
            ->where('shop_type_id', $shopTypeId)
            ->where('shop_name', $shopName)
            ->with('shopType')
            ->orderBy('visit_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl_name) {
                $girl = Girl::where('user_id', $userId)
                    ->where('girl_name', $record->girl_name)
                    ->with(['girlImageUrls' => function ($query) {
                        $query->orderBy('display_order')->limit(1);
                    }])
                    ->first();
                
                if ($girl && $girl->girlImageUrls && $girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $girl->girlImageUrls->first()->image_url;
                }
            }
            $record->girl_image_url = $girlImageUrl;
        }

        return $records;
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

    /**
     * レビューを公開用HTMLとして生成し、S3にアップロード
     *
     * @param Record $record
     * @param string $userId
     * @param bool $includeShopName お店の名前を含めるか
     * @param bool $includeGirlName ヒメの名前を含めるか
     * @param string $publicReview 公開用の感想（DBには保存しない）
     * @return string 公開URL
     */
    public function publishRecord(Record $record, string $userId, bool $includeShopName = true, bool $includeGirlName = true, string $publicReview = ''): string
    {
        // 所有者チェック
        if ($record->user_id !== $userId) {
            Log::warning('Unauthorized record publish attempt', [
                'record_id' => $record->id,
                'record_user_id' => $record->user_id,
                'authenticated_user_id' => $userId
            ]);
            throw new \Exception('この記録を公開する権限がありません');
        }

        // 公開用トークンを生成（既に存在する場合は再利用）
        if (!$record->public_token) {
            $record->generatePublicToken();
        }

        // HTMLを生成
        $html = $this->generatePublicHtml($record, $includeShopName, $includeGirlName, $publicReview);

        // S3にアップロード
        $s3Service = new S3Service();
        $filename = $record->public_token . '.html';
        $publicUrl = $s3Service->uploadHtml($html, $filename);

        Log::info('Record published', [
            'record_id' => $record->id,
            'public_token' => $record->public_token,
            'public_url' => $publicUrl,
            'include_shop_name' => $includeShopName,
            'include_girl_name' => $includeGirlName,
        ]);

        return $publicUrl;
    }

    /**
     * 公開ページを削除
     *
     * @param Record $record
     * @param string $userId
     */
    public function unpublishRecord(Record $record, string $userId): void
    {
        // 所有者チェック
        if ($record->user_id !== $userId) {
            Log::warning('Unauthorized record unpublish attempt', [
                'record_id' => $record->id,
                'record_user_id' => $record->user_id,
                'authenticated_user_id' => $userId
            ]);
            throw new \Exception('この記録の公開を削除する権限がありません');
        }

        // 公開用トークンが存在しない場合は何もしない
        if (!$record->public_token) {
            return;
        }

        // S3からHTMLファイルを削除
        $s3Service = new S3Service();
        $filename = $record->public_token . '.html';
        $s3Service->deleteHtml($filename);

        // public_tokenを削除
        $record->public_token = null;
        $record->save();

        Log::info('Record unpublished', [
            'record_id' => $record->id,
        ]);
    }

    /**
     * 公開用HTMLを生成
     *
     * @param Record $record
     * @param bool $includeShopName お店の名前を含めるか
     * @param bool $includeGirlName ヒメの名前を含めるか
     * @param string $publicReview 公開用の感想
     */
    private function generatePublicHtml(Record $record, bool $includeShopName = true, bool $includeGirlName = true, string $publicReview = ''): string
    {
        $record->load('shopType');
        $shopTypeName = $record->shop_type ?? '';

        // 公開用の感想を使用（指定されていない場合は元の感想を使用）
        $reviewText = $publicReview !== '' ? $publicReview : ($record->review ?? '');
        
        // レビューの改行を<br>に変換
        $reviewHtml = $reviewText 
            ? nl2br(htmlspecialchars($reviewText, ENT_QUOTES, 'UTF-8'))
            : '';

        // タイトルと説明を生成（含める情報に基づく）
        $titleParts = [];
        if ($includeGirlName && $record->girl_name) {
            $titleParts[] = "{$record->girl_name}さんのレビュー";
        }
        if ($includeShopName && $record->shop_name) {
            $titleParts[] = $record->shop_name;
        }
        if (empty($titleParts)) {
            $title = "レビュー - ヒメログ";
        } else {
            $title = implode(' - ', $titleParts) . ' - ヒメログ';
        }
        
        // 総合評価の文字列を生成
        $overallRatingText = '';
        if ($record->overall_rating && $record->overall_rating > 0) {
            $overallRatingText = "【総合:星{$record->overall_rating}】";
        }
        
        // descriptionを生成（総合評価 + 感想）
        $description = '';
        if ($reviewText) {
            $reviewTextForDescription = strip_tags($reviewHtml);
            $description = $overallRatingText . mb_substr($reviewTextForDescription, 0, 120);
            if (mb_strlen($reviewTextForDescription) > 120) {
                $description .= '...';
            }
        } else {
            // 感想がない場合は総合評価のみ（総合評価がある場合）
            $description = $overallRatingText ? rtrim($overallRatingText, '】') . '】レビューです。' : "レビューです。";
        }

        // お店の名前とヒメの名前の表示値を準備
        $shopNameDisplay = ($includeShopName && $record->shop_name) 
            ? htmlspecialchars($record->shop_name, ENT_QUOTES, 'UTF-8') 
            : '内緒';
        
        $girlNameDisplay = ($includeGirlName && $record->girl_name) 
            ? htmlspecialchars($record->girl_name, ENT_QUOTES, 'UTF-8') 
            : '内緒';
        
        // h1タグ用のタイトル（リンク付き）
        $footerLinkUrl = config('app.public_review_footer_link_url');
        if (!$footerLinkUrl) {
            throw new \Exception('PUBLIC_REVIEW_FOOTER_LINK_URL環境変数が設定されていません');
        }
        $h1Title = '<a href="' . htmlspecialchars($footerLinkUrl, ENT_QUOTES, 'UTF-8') . '">ヒメログ</a>';

        // 星評価のHTMLを生成（1-10段階評価に対応）
        $starHtml = function($rating) {
            $stars = '';
            for ($i = 1; $i <= 10; $i++) {
                if ($i <= $rating) {
                    $stars .= '<span class="star filled">★</span>';
                } else {
                    $stars .= '<span class="star">☆</span>';
                }
            }
            return $stars;
        };

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="https://hime-log.jp/favicon.svg" type="image/svg+xml">
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="{$title}">
    <meta property="og:description" content="{$description}">
    <meta property="og:image" content="https://hime-log.jp/favicon.png">
    <meta property="og:site_name" content="ヒメログ">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{$title}">
    <meta name="twitter:description" content="{$description}">
    <meta name="twitter:image" content="https://hime-log.jp/favicon.png">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
            color: #e0e0e0;
            line-height: 1.6;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(26, 26, 46, 0.8);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #ffffff;
            border-bottom: 2px solid #4a90e2;
            padding-bottom: 12px;
        }
        h1 a {
            color: #ffffff;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        h1 a:hover {
            color: #6ba3ff;
            text-decoration: underline;
        }
        .info-section {
            margin-bottom: 24px;
        }
        .info-item {
            margin-bottom: 16px;
            padding: 12px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border-left: 3px solid #4a90e2;
        }
        .info-label {
            font-size: 12px;
            color: #a0a0a0;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-size: 16px;
            color: #ffffff;
            font-weight: 500;
        }
        .ratings {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .rating-item {
            background: rgba(0, 0, 0, 0.3);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }
        .rating-label {
            font-size: 12px;
            color: #a0a0a0;
            margin-bottom: 8px;
        }
        .stars {
            font-size: 18px;
            color: #ffd700;
        }
        .star.filled {
            color: #ffd700;
        }
        .star {
            color: #666;
        }
        .review-section {
            margin-top: 24px;
            padding: 16px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border-left: 3px solid #4a90e2;
        }
        .review-text {
            color: #e0e0e0;
            line-height: 1.8;
            white-space: pre-wrap;
        }
        .footer {
            margin-top: 32px;
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #888;
            font-size: 14px;
        }
        .footer a {
            color: #4a90e2;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .footer a:hover {
            color: #6ba3ff;
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .container {
                padding: 16px;
            }
            h1 {
                font-size: 20px;
            }
            .ratings {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{$h1Title}</h1>
        
        <div class="info-section">
            <div class="info-item">
                <div class="info-label">お店の種類</div>
                <div class="info-value">{$shopTypeName}</div>
            </div>
            <div class="info-item">
                <div class="info-label">お店の名前</div>
                <div class="info-value">{$shopNameDisplay}</div>
            </div>
HTML;

        if ($record->girl_name) {
            $html .= <<<HTML
            <div class="info-item">
                <div class="info-label">ヒメの名前</div>
                <div class="info-value">{$girlNameDisplay}</div>
            </div>
HTML;
        }

        if ($record->course) {
            $html .= <<<HTML
            <div class="info-item">
                <div class="info-label">コース</div>
                <div class="info-value">{$record->course}</div>
            </div>
HTML;
        }

        if ($record->price) {
            $price = number_format($record->price);
            $html .= <<<HTML
            <div class="info-item">
                <div class="info-label">利用料金</div>
                <div class="info-value">¥{$price}</div>
            </div>
HTML;
        }

        $html .= <<<HTML
        </div>
        
        <div class="ratings">
HTML;

        if ($record->overall_rating) {
            $stars = $starHtml($record->overall_rating);
            $html .= <<<HTML
            <div class="rating-item">
                <div class="rating-label">総合評価</div>
                <div class="stars">{$stars}</div>
            </div>
HTML;
        }

        if ($record->face_rating) {
            $stars = $starHtml($record->face_rating);
            $html .= <<<HTML
            <div class="rating-item">
                <div class="rating-label">顔</div>
                <div class="stars">{$stars}</div>
            </div>
HTML;
        }

        if ($record->style_rating) {
            $stars = $starHtml($record->style_rating);
            $html .= <<<HTML
            <div class="rating-item">
                <div class="rating-label">スタイル</div>
                <div class="stars">{$stars}</div>
            </div>
HTML;
        }

        if ($record->service_rating) {
            $stars = $starHtml($record->service_rating);
            $html .= <<<HTML
            <div class="rating-item">
                <div class="rating-label">接客</div>
                <div class="stars">{$stars}</div>
            </div>
HTML;
        }

        $html .= <<<HTML
        </div>
        
HTML;

        if ($reviewHtml) {
            $html .= <<<HTML
        <div class="review-section">
            <div class="info-label" style="margin-bottom: 12px;">レビュー</div>
            <div class="review-text">{$reviewHtml}</div>
        </div>
HTML;
        }

        // フッターのリンクURLは既に取得済み（h1タグ生成時に取得）
        $footerText = 'ヒメログ - あなたの出会いを記録';
        $footerHtml = '<a href="' . htmlspecialchars($footerLinkUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') . '</a>';

        $html .= <<<HTML
        
        <div class="footer">
            <p>{$footerHtml}</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * 総合評価ランキング（上位5件）を取得
     * overall_ratingが高い順にソート
     * 同じヒメのレビューが複数ある場合は、最も評価が高いものだけを表示
     */
    public function getOverallRatingRanking(string $userId, int $limit = 5)
    {
        $allRecords = Record::where('user_id', $userId)
            ->whereNotNull('overall_rating')
            ->where('overall_rating', '>', 0)
            ->with('shopType')
            ->orderBy('overall_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 同じヒメのレビューが複数ある場合、最も評価が高いものだけを残す
        $uniqueRecords = [];
        $girlNamesSeen = [];
        foreach ($allRecords as $record) {
            $girlName = $record->girl_name ?? '';
            if (empty($girlName)) {
                // ヒメ名がない場合はそのまま追加
                $uniqueRecords[] = $record;
            } else {
                // ヒメ名がある場合、まだ見ていないヒメ名なら追加
                if (!isset($girlNamesSeen[$girlName])) {
                    $girlNamesSeen[$girlName] = true;
                    $uniqueRecords[] = $record;
                }
            }
        }

        // 上位5件に制限
        $records = collect($uniqueRecords)->take($limit);

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl_name) {
                $girl = Girl::where('user_id', $userId)
                    ->where('girl_name', $record->girl_name)
                    ->with(['girlImageUrls' => function ($query) {
                        $query->orderBy('display_order')->limit(1);
                    }])
                    ->first();
                
                if ($girl && $girl->girlImageUrls && $girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $girl->girlImageUrls->first()->image_url;
                }
            }
            $record->girl_image_url = $girlImageUrl;
        }

        return $records;
    }

    /**
     * 顔の評価ランキング（上位5件）を取得
     * face_ratingが高い順にソート
     * 同じヒメのレビューが複数ある場合は、最も評価が高いものだけを表示
     */
    public function getFaceRatingRanking(string $userId, int $limit = 5)
    {
        $allRecords = Record::where('user_id', $userId)
            ->whereNotNull('face_rating')
            ->where('face_rating', '>', 0)
            ->with('shopType')
            ->orderBy('face_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 同じヒメのレビューが複数ある場合、最も評価が高いものだけを残す
        $uniqueRecords = [];
        $girlNamesSeen = [];
        foreach ($allRecords as $record) {
            $girlName = $record->girl_name ?? '';
            if (empty($girlName)) {
                // ヒメ名がない場合はそのまま追加
                $uniqueRecords[] = $record;
            } else {
                // ヒメ名がある場合、まだ見ていないヒメ名なら追加
                if (!isset($girlNamesSeen[$girlName])) {
                    $girlNamesSeen[$girlName] = true;
                    $uniqueRecords[] = $record;
                }
            }
        }

        // 上位5件に制限
        $records = collect($uniqueRecords)->take($limit);

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl_name) {
                $girl = Girl::where('user_id', $userId)
                    ->where('girl_name', $record->girl_name)
                    ->with(['girlImageUrls' => function ($query) {
                        $query->orderBy('display_order')->limit(1);
                    }])
                    ->first();
                
                if ($girl && $girl->girlImageUrls && $girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $girl->girlImageUrls->first()->image_url;
                }
            }
            $record->girl_image_url = $girlImageUrl;
        }

        return $records;
    }

    /**
     * スタイルの評価ランキング（上位5件）を取得
     * style_ratingが高い順にソート
     * 同じヒメのレビューが複数ある場合は、最も評価が高いものだけを表示
     */
    public function getStyleRatingRanking(string $userId, int $limit = 5)
    {
        $allRecords = Record::where('user_id', $userId)
            ->whereNotNull('style_rating')
            ->where('style_rating', '>', 0)
            ->with('shopType')
            ->orderBy('style_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 同じヒメのレビューが複数ある場合、最も評価が高いものだけを残す
        $uniqueRecords = [];
        $girlNamesSeen = [];
        foreach ($allRecords as $record) {
            $girlName = $record->girl_name ?? '';
            if (empty($girlName)) {
                // ヒメ名がない場合はそのまま追加
                $uniqueRecords[] = $record;
            } else {
                // ヒメ名がある場合、まだ見ていないヒメ名なら追加
                if (!isset($girlNamesSeen[$girlName])) {
                    $girlNamesSeen[$girlName] = true;
                    $uniqueRecords[] = $record;
                }
            }
        }

        // 上位5件に制限
        $records = collect($uniqueRecords)->take($limit);

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl_name) {
                $girl = Girl::where('user_id', $userId)
                    ->where('girl_name', $record->girl_name)
                    ->with(['girlImageUrls' => function ($query) {
                        $query->orderBy('display_order')->limit(1);
                    }])
                    ->first();
                
                if ($girl && $girl->girlImageUrls && $girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $girl->girlImageUrls->first()->image_url;
                }
            }
            $record->girl_image_url = $girlImageUrl;
        }

        return $records;
    }

    /**
     * 接客の評価ランキング（上位5件）を取得
     * service_ratingが高い順にソート
     * 同じヒメのレビューが複数ある場合は、最も評価が高いものだけを表示
     */
    public function getServiceRatingRanking(string $userId, int $limit = 5)
    {
        $allRecords = Record::where('user_id', $userId)
            ->whereNotNull('service_rating')
            ->where('service_rating', '>', 0)
            ->with('shopType')
            ->orderBy('service_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 同じヒメのレビューが複数ある場合、最も評価が高いものだけを残す
        $uniqueRecords = [];
        $girlNamesSeen = [];
        foreach ($allRecords as $record) {
            $girlName = $record->girl_name ?? '';
            if (empty($girlName)) {
                // ヒメ名がない場合はそのまま追加
                $uniqueRecords[] = $record;
            } else {
                // ヒメ名がある場合、まだ見ていないヒメ名なら追加
                if (!isset($girlNamesSeen[$girlName])) {
                    $girlNamesSeen[$girlName] = true;
                    $uniqueRecords[] = $record;
                }
            }
        }

        // 上位5件に制限
        $records = collect($uniqueRecords)->take($limit);

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl_name) {
                $girl = Girl::where('user_id', $userId)
                    ->where('girl_name', $record->girl_name)
                    ->with(['girlImageUrls' => function ($query) {
                        $query->orderBy('display_order')->limit(1);
                    }])
                    ->first();
                
                if ($girl && $girl->girlImageUrls && $girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $girl->girlImageUrls->first()->image_url;
                }
            }
            $record->girl_image_url = $girlImageUrl;
        }

        return $records;
    }

    /**
     * 利用回数ランキング（上位5件）を取得
     * 同じshop_nameまたはgirl_nameの出現回数が多い順にソート
     */
    public function getVisitCountRanking(string $userId, int $limit = 5)
    {
        $records = Record::where('user_id', $userId)
            ->with('shopType')
            ->get();

        // お店名とヒメ名の組み合わせごとに集計
        $visitCounts = [];
        foreach ($records as $record) {
            $key = ($record->shop_name ?? '') . '_' . ($record->girl_name ?? '');
            if (!isset($visitCounts[$key])) {
                $visitCounts[$key] = [
                    'count' => 0,
                    'records' => []
                ];
            }
            $visitCounts[$key]['count']++;
            $visitCounts[$key]['records'][] = $record;
        }

        // 訪問回数の降順でソート
        uasort($visitCounts, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        // 上位5件の最初の記録を取得
        $topRecords = [];
        $count = 0;
        foreach ($visitCounts as $visitData) {
            if ($count >= $limit) {
                break;
            }
            // 各グループから最新の記録を取得
            $latestRecord = collect($visitData['records'])->sortByDesc(function ($record) {
                return $record->visit_date ? $record->visit_date->timestamp : $record->created_at->timestamp;
            })->first();
            
            // 訪問回数を記録に追加
            $latestRecord->visit_count = $visitData['count'];
            $topRecords[] = $latestRecord;
            $count++;
        }

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($topRecords as $record) {
            $girlImageUrl = null;
            if ($record->girl_name) {
                $girl = Girl::where('user_id', $userId)
                    ->where('girl_name', $record->girl_name)
                    ->with(['girlImageUrls' => function ($query) {
                        $query->orderBy('display_order')->limit(1);
                    }])
                    ->first();
                
                if ($girl && $girl->girlImageUrls && $girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $girl->girlImageUrls->first()->image_url;
                }
            }
            $record->girl_image_url = $girlImageUrl;
        }

        return collect($topRecords);
    }
}

