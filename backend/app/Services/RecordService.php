<?php

namespace App\Services;

use App\Models\Record;
use App\Models\Shop;
use App\Models\ShopType;
use App\Models\Girl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Services\S3Service;
use MatthiasMullie\Minify\HTML;

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
        return DB::transaction(function () use ($userId, $data): Record {
            $shopTypeId = $this->convertShopTypeToId($data['shop_type'] ?? $data['shop_type_id'] ?? null);

            // レビュー投稿時にお店・ヒメのデータを作成（または取得）する
            $shop = null;
            if (!empty($data['shop_name']) && $shopTypeId !== null) {
                // 競合状態を避けるため、まず検索してから作成を試みる
                $shop = Shop::where('internal_user_id', $userId)
                    ->where('shop_type_id', $shopTypeId)
                    ->where('shop_name', $data['shop_name'])
                    ->first();
                
                if (!$shop) {
                    try {
                        $shop = Shop::create([
                            'internal_user_id' => $userId,
                            'shop_type_id' => $shopTypeId,
                            'shop_name' => $data['shop_name'],
                            'memo' => null,
                        ]);
                    } catch (QueryException $e) {
                        // 一意制約違反の場合、再検索する（別のプロセスが既に作成した可能性）
                        // SQLSTATE[23000] は一意制約違反のエラーコード
                        if ($e->getCode() === '23000' || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $shop = Shop::where('internal_user_id', $userId)
                                ->where('shop_type_id', $shopTypeId)
                                ->where('shop_name', $data['shop_name'])
                                ->first();
                            
                            if (!$shop) {
                                // それでも見つからない場合は元の例外を再スロー
                                Log::warning('Shop not found after duplicate entry error', [
                                    'user_id' => $userId,
                                    'shop_type_id' => $shopTypeId,
                                    'shop_name' => $data['shop_name'],
                                    'error' => $e->getMessage(),
                                ]);
                                throw $e;
                            }
                            
                            Log::info('Shop found after duplicate entry error (race condition handled)', [
                                'shop_id' => $shop->id,
                                'user_id' => $userId,
                                'shop_type_id' => $shopTypeId,
                                'shop_name' => $data['shop_name'],
                            ]);
                        } else {
                            throw $e;
                        }
                    }
                }
            }

            $girl = null;
            if (!empty($data['girl_name'])) {
                // 競合状態を避けるため、まず検索してから作成を試みる
                $girl = Girl::where('internal_user_id', $userId)
                    ->where('shop_id', $shop ? $shop->id : null)
                    ->where('girl_name', $data['girl_name'])
                    ->first();
                
                if (!$girl) {
                    try {
                        $girl = Girl::create([
                            'internal_user_id' => $userId,
                            'shop_id' => $shop ? $shop->id : null,
                            'girl_name' => $data['girl_name'],
                            'memo' => null,
                        ]);
                    } catch (QueryException $e) {
                        // 一意制約違反の場合、再検索する（別のプロセスが既に作成した可能性）
                        // SQLSTATE[23000] は一意制約違反のエラーコード
                        if ($e->getCode() === '23000' || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $girl = Girl::where('internal_user_id', $userId)
                                ->where('shop_id', $shop ? $shop->id : null)
                                ->where('girl_name', $data['girl_name'])
                                ->first();
                            
                            if (!$girl) {
                                // それでも見つからない場合は元の例外を再スロー
                                Log::warning('Girl not found after duplicate entry error', [
                                    'user_id' => $userId,
                                    'shop_id' => $shop ? $shop->id : null,
                                    'girl_name' => $data['girl_name'],
                                    'error' => $e->getMessage(),
                                ]);
                                throw $e;
                            }
                            
                            Log::info('Girl found after duplicate entry error (race condition handled)', [
                                'girl_id' => $girl->id,
                                'user_id' => $userId,
                                'shop_id' => $shop ? $shop->id : null,
                                'girl_name' => $data['girl_name'],
                            ]);
                        } else {
                            throw $e;
                        }
                    }
                }
            }

            $record = Record::create([
                // アプリ内のユーザ識別は internal_user_id（users.id）を使用
                'internal_user_id' => $userId,
                'shop_id' => $shop ? $shop->id : null,
                'girl_id' => $girl ? $girl->id : null,
                'visit_date' => $data['visit_date'],
                'face_rating' => $data['face_rating'] ?? null,
                'style_rating' => $data['style_rating'] ?? null,
                'service_rating' => $data['service_rating'] ?? null,
                'overall_rating' => $data['overall_rating'] ?? null,
                'review' => $data['review'] ?? null,
                'price' => $data['price'] ?? null,
                'course' => $data['course'] ?? null,
            ]);

            Log::info('Record created', [
                'record_id' => $record->id,
                'internal_user_id' => $record->internal_user_id,
            ]);

            // お店・お店の種類情報・ヒメ情報も含めて返す
            return $record->load(['shop', 'shop.shopType', 'girl']);
        });
    }

    /**
     * ユーザーの記録一覧を取得
     * 各記録にヒメの画像URL（最初の1枚目）を含める
     */
    public function getRecords(string $userId)
    {
        $records = Record::where('internal_user_id', $userId)
            ->with(['shop', 'shop.shopType', 'girl'])
            ->orderBy('created_at', 'desc')
            ->get();

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl) {
                $girl = $record->girl->loadMissing(['girlImageUrls' => function ($query) {
                    $query->orderBy('display_order')->limit(1);
                }]);

                if ($girl->girlImageUrls && $girl->girlImageUrls->count() > 0) {
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
        $query = Record::where('internal_user_id', $userId)
            ->with(['shop', 'shop.shopType', 'girl']);

        // お店の種類でフィルタ（複数選択可）
        if (!empty($filters['shop_type_ids']) && is_array($filters['shop_type_ids'])) {
            $shopTypeIds = array_filter(array_map('intval', $filters['shop_type_ids']));
            if (!empty($shopTypeIds)) {
                $query->whereHas('shop', function ($q) use ($shopTypeIds) {
                    $q->whereIn('shop_type_id', $shopTypeIds);
                });
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
            if ($record->girl) {
                $record->girl->loadMissing(['girlImageUrls' => function ($query) {
                    $query->orderBy('display_order')->limit(1);
                }]);
                
                if ($record->girl->girlImageUrls && $record->girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $record->girl->girlImageUrls->first()->image_url;
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
        $records = Record::where('internal_user_id', $userId)
            ->with(['shop', 'shop.shopType', 'girl'])
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
        $records = Record::where('internal_user_id', $userId)
            ->with(['shop', 'shop.shopType'])
            ->get();

        // お店のタイプごとに集計
        $statistics = [];
        foreach ($records as $record) {
            // Recordモデルのアクセサ（shop_type）を利用して種別名を取得
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
        $records = Record::where('internal_user_id', $userId)
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
        return DB::transaction(function () use ($record, $userId, $data): Record {
            // 所有者チェック
            if ($record->internal_user_id !== $userId) {
                Log::warning('Unauthorized record update attempt', [
                    'record_id' => $record->id,
                    'record_internal_user_id' => $record->internal_user_id,
                    'authenticated_user_id' => $userId
                ]);
                throw new \Exception('この記録を更新する権限がありません');
            }

            $shopTypeId = $this->convertShopTypeToId($data['shop_type'] ?? $data['shop_type_id'] ?? null);

            // お店の情報を更新または取得
            $shop = null;
            $girl = null;
            $shopNameChanged = false;
            
            if (!empty($data['shop_name']) && $shopTypeId !== null) {
                // 既存のお店を検索
                $existingShop = Shop::where('internal_user_id', $userId)
                    ->where('shop_type_id', $shopTypeId)
                    ->where('shop_name', $data['shop_name'])
                    ->first();
                
                // お店の名前が変更されたかどうかをチェック
                $currentShopName = $record->shop ? $record->shop->shop_name : null;
                $shopNameChanged = ($currentShopName !== $data['shop_name']);
                
                if (!$existingShop) {
                    // 同じお店の種類で同じ名前のお店が存在しなかった場合
                    // shopsデータを作成
                    $shop = Shop::create([
                        'internal_user_id' => $userId,
                        'shop_type_id' => $shopTypeId,
                        'shop_name' => $data['shop_name'],
                        'memo' => null,
                    ]);
                    
                    // ヒメの名前が指定されている場合、girlsデータも作成
                    // firstOrCreateを使用して、既に存在する場合は取得、存在しない場合は作成
                    if (!empty($data['girl_name'])) {
                        $girl = Girl::firstOrCreate(
                            [
                                'internal_user_id' => $userId,
                                'shop_id' => $shop->id,
                                'girl_name' => $data['girl_name'],
                            ],
                            [
                                'memo' => null,
                            ]
                        );
                    }
                } else {
                    // 同じお店の種類で同じ名前のお店が存在した場合
                    $shop = $existingShop;
                    
                    // そのお店に同じ名前のヒメが存在するかチェック
                    if (!empty($data['girl_name'])) {
                        $existingGirl = Girl::where('internal_user_id', $userId)
                            ->where('shop_id', $shop->id)
                            ->where('girl_name', $data['girl_name'])
                            ->first();
                        
                        if (!$existingGirl) {
                            // そのお店に同じ名前のヒメが存在しなかった場合
                            // ヒメデータを作成
                            $girl = Girl::create([
                                'internal_user_id' => $userId,
                                'shop_id' => $shop->id,
                                'girl_name' => $data['girl_name'],
                                'memo' => null,
                            ]);
                        } else {
                            $girl = $existingGirl;
                        }
                    }
                }
            } elseif ($record->shop_id) {
                // shop_nameが変更されていない場合は既存のshopを使用
                $shop = Shop::find($record->shop_id);
                
                // ヒメの情報を更新または取得（既存のロジック）
                if (!empty($data['girl_name'])) {
                    $girl = Girl::firstOrCreate(
                        [
                            'internal_user_id' => $userId,
                            'shop_id' => $shop ? $shop->id : null,
                            'girl_name' => $data['girl_name'],
                        ],
                        [
                            'memo' => null,
                        ]
                    );
                }
            } else {
                // shop_nameが指定されていない場合でも、girl_nameが指定されている場合は処理
                if (!empty($data['girl_name'])) {
                    $girl = Girl::firstOrCreate(
                        [
                            'internal_user_id' => $userId,
                            'shop_id' => null,
                            'girl_name' => $data['girl_name'],
                        ],
                        [
                            'memo' => null,
                        ]
                    );
                }
            }

            // レコードを更新
            $updateData = [
                'visit_date' => $data['visit_date'],
                'face_rating' => $data['face_rating'] ?? null,
                'style_rating' => $data['style_rating'] ?? null,
                'service_rating' => $data['service_rating'] ?? null,
                'overall_rating' => $data['overall_rating'] ?? null,
                'review' => $data['review'] ?? null,
                'price' => $data['price'] ?? null,
                'course' => $data['course'] ?? null,
            ];

            // shop_idとgirl_idを更新
            if ($shop) {
                $updateData['shop_id'] = $shop->id;
            }
            if ($girl) {
                $updateData['girl_id'] = $girl->id;
            } elseif (empty($data['girl_name'])) {
                // girl_nameが空の場合はgirl_idをnullに設定
                $updateData['girl_id'] = null;
            }

            $record->update($updateData);

            Log::info('Record updated', [
                'record_id' => $record->id,
                'internal_user_id' => $record->internal_user_id,
                'shop_id' => $shop ? $shop->id : null,
                'girl_id' => $girl ? $girl->id : null,
            ]);

            return $record->load(['shop', 'shop.shopType', 'girl']);
        });
    }

    /**
     * 記録を削除
     */
    public function deleteRecord(Record $record, string $userId): void
    {
        DB::transaction(function () use ($record, $userId): void {
            // 所有者チェック
            if ($record->internal_user_id !== $userId) {
                Log::warning('Unauthorized record deletion attempt', [
                    'record_id' => $record->id,
                    'record_internal_user_id' => $record->internal_user_id,
                    'authenticated_user_id' => $userId
                ]);
                throw new \Exception('この記録を削除する権限がありません');
            }

            $recordId = $record->id;
            $recordInternalUserId = $record->internal_user_id;
            $record->delete();

            Log::info('Record deleted', [
                'record_id' => $recordId,
                'internal_user_id' => $recordInternalUserId,
            ]);
        });
    }

    /**
     * お店の種類とユーザーIDに基づいて登録済みのお店名を取得
     */
    public function getShopNames(string $userId, string $shopType)
    {
        $shopTypeId = $this->convertShopTypeToId($shopType);
        
        // Recordからshop_idを取得し、そのshop_idでShopを取得して重複を排除
        $shopIds = Record::where('internal_user_id', $userId)
            ->whereNotNull('shop_id')
            ->whereHas('shop', function ($q) use ($shopTypeId) {
                $q->where('shop_type_id', $shopTypeId);
            })
            ->distinct()
            ->pluck('shop_id')
            ->filter();
        
        return Shop::whereIn('id', $shopIds)
            ->orderBy('shop_name')
            ->pluck('shop_name')
            ->values();
    }

    /**
     * お店の種類、お店の名前、ユーザーIDに基づいて登録済みの女の子の名前を取得
     */
    public function getGirlNames(string $userId, string $shopType, string $shopName)
    {
        $shopTypeId = $this->convertShopTypeToId($shopType);
        
        return Record::where('internal_user_id', $userId)
            ->whereHas('shop', function ($q) use ($shopTypeId, $shopName) {
                $q->where('shop_type_id', $shopTypeId)
                  ->where('shop_name', $shopName);
            })
            ->whereNotNull('girl_id')
            ->with('girl')
            ->get()
            ->pluck('girl.girl_name')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * ユーザーが投稿したことがある全ヒメ（女の子）の一覧を取得
     */
    public function getAllGirlNames(string $userId)
    {
        return Record::where('internal_user_id', $userId)
            ->whereNotNull('girl_id')
            ->with('girl')
            ->get()
            ->pluck('girl.girl_name')
            ->filter()
            ->unique()
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
        $records = Record::where('internal_user_id', $userId)
            ->with(['shop', 'shop.shopType'])
            ->get();

        $shopsMap = [];
        
        // お店ごとにグループ化（shop_idで一意に識別）
        $groupedRecords = $records->groupBy(function ($record) {
            return $record->shop_id ?: 'unknown_' . $record->shop_name;
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
                'name' => $firstRecord->shop && $firstRecord->shop->shop_name
                    ? $firstRecord->shop->shop_name
                    : null,
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
        
        $records = Record::where('internal_user_id', $userId)
            ->whereHas('shop', function ($q) use ($shopTypeId, $shopName) {
                $q->where('shop_type_id', $shopTypeId)
                  ->where('shop_name', $shopName);
            })
            ->with(['shop', 'shop.shopType', 'girl'])
            ->orderBy('visit_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl) {
                $record->girl->loadMissing(['girlImageUrls' => function ($query) {
                    $query->orderBy('display_order')->limit(1);
                }]);
                
                if ($record->girl->girlImageUrls && $record->girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $record->girl->girlImageUrls->first()->image_url;
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
        return Record::where('internal_user_id', $userId)
            ->whereHas('girl', function ($q) use ($girlName) {
                $q->where('girl_name', $girlName);
            })
            ->with(['shop', 'shop.shopType', 'girl'])
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
     * @param bool $includeCourse コースを含めるか
     * @param bool $includePrice 料金を含めるか
     * @param string $metDate 出会った日（テキスト、DBには保存しない）
     * @return string 公開URL
     */
    public function publishRecord(Record $record, string $userId, bool $includeShopName = true, bool $includeGirlName = true, string $publicReview = '', bool $includeCourse = false, bool $includePrice = false, string $metDate = ''): string
    {
        // DB更新部分のみトランザクションで保護し、S3アップロードは外側で実行
        return DB::transaction(function () use ($record, $userId, $includeShopName, $includeGirlName, $publicReview, $includeCourse, $includePrice, $metDate): string {
            // 所有者チェック
            if ($record->internal_user_id !== $userId) {
                Log::warning('Unauthorized record publish attempt', [
                    'record_id' => $record->id,
                    'record_internal_user_id' => $record->internal_user_id,
                    'authenticated_user_id' => $userId
                ]);
                throw new \Exception('この記録を公開する権限がありません');
            }

            // 公開用トークンを生成（既に存在する場合は再利用）
            if (!$record->public_token) {
                $record->generatePublicToken();
            }

            // HTMLを生成
            $html = $this->generatePublicHtml($record, $includeShopName, $includeGirlName, $publicReview, $includeCourse, $includePrice, $metDate);

            // S3にアップロード（外部サービスだが、ここで失敗した場合は例外でロールバックされる）
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
        });
    }

    /**
     * 公開ページを削除
     *
     * @param Record $record
     * @param string $userId
     */
    public function unpublishRecord(Record $record, string $userId): void
    {
        DB::transaction(function () use ($record, $userId): void {
            // 所有者チェック
            if ($record->internal_user_id !== $userId) {
                Log::warning('Unauthorized record unpublish attempt', [
                    'record_id' => $record->id,
                    'record_internal_user_id' => $record->internal_user_id,
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
        });
    }

    /**
     * 公開用HTMLを生成
     *
     * @param Record $record
     * @param bool $includeShopName お店の名前を含めるか
     * @param bool $includeGirlName ヒメの名前を含めるか
     * @param string $publicReview 公開用の感想
     * @param bool $includeCourse コースを含めるか
     * @param bool $includePrice 料金を含めるか
     * @param string $metDate 出会った日（テキスト）
     */
    private function generatePublicHtml(Record $record, bool $includeShopName = true, bool $includeGirlName = true, string $publicReview = '', bool $includeCourse = false, bool $includePrice = false, string $metDate = ''): string
    {
        $record->load(['shop', 'shop.shopType', 'girl']);
        // Recordモデルのアクセサからお店の種類名を取得
        $shopTypeName = $record->shop_type ?? '';

        // 公開用の感想を使用（指定されていない場合は元の感想を使用）
        $reviewText = $publicReview !== '' ? $publicReview : ($record->review ?? '');
        
        // レビューの改行を<br>に変換
        $reviewHtml = $reviewText 
            ? nl2br(htmlspecialchars($reviewText, ENT_QUOTES, 'UTF-8'))
            : '';

        // タイトルと説明を生成（含める情報に基づく）
        $titleParts = [];
        $girlName = $record->girl_name;
        if ($includeGirlName && $girlName) {
            $titleParts[] = "{$girlName}さんのレビュー";
        }
        $shopName = $record->shop && $record->shop->shop_name 
            ? $record->shop->shop_name 
            : null;
        if ($includeShopName && $shopName) {
            $titleParts[] = $shopName;
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
        $shopNameDisplay = ($includeShopName && $shopName) 
            ? htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') 
            : '内緒';
        
        $girlNameDisplay = ($includeGirlName && $girlName) 
            ? htmlspecialchars($girlName, ENT_QUOTES, 'UTF-8') 
            : '内緒';
        
        // h1タグ用のタイトル（リンク付き）
        $footerLinkUrl = 'https://hime-log.jp';
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
    <link rel="icon" type="image/svg+xml" href="https://hime-log.jp/favicon.svg">
    <link rel="alternate icon" href="https://hime-log.jp/favicon.png">
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

        if ($girlName) {
            $html .= <<<HTML
            <div class="info-item">
                <div class="info-label">ヒメの名前</div>
                <div class="info-value">{$girlNameDisplay}</div>
            </div>
HTML;
        }

        // 出会った日を表示
        if ($metDate) {
            $metDateEscaped = htmlspecialchars($metDate, ENT_QUOTES, 'UTF-8');
            $html .= <<<HTML
            <div class="info-item">
                <div class="info-label">出会った日</div>
                <div class="info-value">{$metDateEscaped}</div>
            </div>
HTML;
        }

        // コースを表示（includeCourseがtrueかつコースが存在する場合）
        if ($includeCourse && $record->course) {
            $courseEscaped = htmlspecialchars($record->course, ENT_QUOTES, 'UTF-8');
            $html .= <<<HTML
            <div class="info-item">
                <div class="info-label">コース</div>
                <div class="info-value">{$courseEscaped}</div>
            </div>
HTML;
        }

        // 料金を表示（includePriceがtrueかつ料金が存在する場合）
        if ($includePrice && $record->price) {
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

        // 本番環境のみでHTMLをminify
        if (config('app.env') === 'production') {
            $html = $this->minifyHtml($html);
        }

        return $html;
    }

    /**
     * HTMLをminify（本番環境用）
     * 
     * @param string $html
     * @return string
     */
    private function minifyHtml(string $html): string
    {
        try {
            $minifier = new HTML($html);
            return $minifier->minify();
        } catch (\Exception $e) {
            // minifyに失敗した場合は元のHTMLを返す（安全のため）
            Log::warning('HTML minify failed', [
                'error' => $e->getMessage(),
            ]);
            return $html;
        }
    }

    /**
     * 総合評価ランキング（上位5件）を取得
     * overall_ratingが高い順にソート
     * 同じヒメのレビューが複数ある場合は、最も評価が高いものだけを表示
     */
    public function getOverallRatingRanking(string $userId, int $limit = 5)
    {
        $allRecords = Record::where('internal_user_id', $userId)
            ->whereNotNull('overall_rating')
            ->where('overall_rating', '>', 0)
            ->with(['shop', 'shop.shopType', 'girl'])
            ->orderBy('overall_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 同じヒメのレビューが複数ある場合、最も評価が高いものだけを残す
        $uniqueRecords = [];
        $girlIdsSeen = [];
        foreach ($allRecords as $record) {
            $girlId = $record->girl_id ?? null;
            if (empty($girlId)) {
                // ヒメIDがない場合はそのまま追加
                $uniqueRecords[] = $record;
            } else {
                // ヒメIDがある場合、まだ見ていないヒメIDなら追加
                if (!isset($girlIdsSeen[$girlId])) {
                    $girlIdsSeen[$girlId] = true;
                    $uniqueRecords[] = $record;
                }
            }
        }

        // 上位5件に制限
        $records = collect($uniqueRecords)->take($limit);

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl) {
                $record->girl->loadMissing(['girlImageUrls' => function ($query) {
                    $query->orderBy('display_order')->limit(1);
                }]);
                
                if ($record->girl->girlImageUrls && $record->girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $record->girl->girlImageUrls->first()->image_url;
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
        $allRecords = Record::where('internal_user_id', $userId)
            ->whereNotNull('face_rating')
            ->where('face_rating', '>', 0)
            ->with(['shop', 'shop.shopType', 'girl'])
            ->orderBy('face_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 同じヒメのレビューが複数ある場合、最も評価が高いものだけを残す
        $uniqueRecords = [];
        $girlIdsSeen = [];
        foreach ($allRecords as $record) {
            $girlId = $record->girl_id ?? null;
            if (empty($girlId)) {
                // ヒメIDがない場合はそのまま追加
                $uniqueRecords[] = $record;
            } else {
                // ヒメIDがある場合、まだ見ていないヒメIDなら追加
                if (!isset($girlIdsSeen[$girlId])) {
                    $girlIdsSeen[$girlId] = true;
                    $uniqueRecords[] = $record;
                }
            }
        }

        // 上位5件に制限
        $records = collect($uniqueRecords)->take($limit);

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl) {
                $record->girl->loadMissing(['girlImageUrls' => function ($query) {
                    $query->orderBy('display_order')->limit(1);
                }]);
                
                if ($record->girl->girlImageUrls && $record->girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $record->girl->girlImageUrls->first()->image_url;
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
        $allRecords = Record::where('internal_user_id', $userId)
            ->whereNotNull('style_rating')
            ->where('style_rating', '>', 0)
            ->with(['shop', 'shop.shopType', 'girl'])
            ->orderBy('style_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 同じヒメのレビューが複数ある場合、最も評価が高いものだけを残す
        $uniqueRecords = [];
        $girlIdsSeen = [];
        foreach ($allRecords as $record) {
            $girlId = $record->girl_id ?? null;
            if (empty($girlId)) {
                // ヒメIDがない場合はそのまま追加
                $uniqueRecords[] = $record;
            } else {
                // ヒメIDがある場合、まだ見ていないヒメIDなら追加
                if (!isset($girlIdsSeen[$girlId])) {
                    $girlIdsSeen[$girlId] = true;
                    $uniqueRecords[] = $record;
                }
            }
        }

        // 上位5件に制限
        $records = collect($uniqueRecords)->take($limit);

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl) {
                $record->girl->loadMissing(['girlImageUrls' => function ($query) {
                    $query->orderBy('display_order')->limit(1);
                }]);
                
                if ($record->girl->girlImageUrls && $record->girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $record->girl->girlImageUrls->first()->image_url;
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
        $allRecords = Record::where('internal_user_id', $userId)
            ->whereNotNull('service_rating')
            ->where('service_rating', '>', 0)
            ->with(['shop', 'shop.shopType', 'girl'])
            ->orderBy('service_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 同じヒメのレビューが複数ある場合、最も評価が高いものだけを残す
        $uniqueRecords = [];
        $girlIdsSeen = [];
        foreach ($allRecords as $record) {
            $girlId = $record->girl_id ?? null;
            if (empty($girlId)) {
                // ヒメIDがない場合はそのまま追加
                $uniqueRecords[] = $record;
            } else {
                // ヒメIDがある場合、まだ見ていないヒメIDなら追加
                if (!isset($girlIdsSeen[$girlId])) {
                    $girlIdsSeen[$girlId] = true;
                    $uniqueRecords[] = $record;
                }
            }
        }

        // 上位5件に制限
        $records = collect($uniqueRecords)->take($limit);

        // 各記録にヒメの画像URL（最初の1枚目）を追加
        foreach ($records as $record) {
            $girlImageUrl = null;
            if ($record->girl) {
                $record->girl->loadMissing(['girlImageUrls' => function ($query) {
                    $query->orderBy('display_order')->limit(1);
                }]);
                
                if ($record->girl->girlImageUrls && $record->girl->girlImageUrls->count() > 0) {
                    $girlImageUrl = $record->girl->girlImageUrls->first()->image_url;
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
        $records = Record::where('internal_user_id', $userId)
            ->with(['shop', 'shop.shopType', 'girl'])
            ->get();

        // お店名とヒメ名の組み合わせごとに集計
        $visitCounts = [];
        foreach ($records as $record) {
            $shopName = $record->shop && $record->shop->shop_name 
                ? $record->shop->shop_name 
                : '';
            $girlName = $record->girl_name ?? '';
            $key = $shopName . '_' . $girlName;
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
                $girl = Girl::where('internal_user_id', $userId)
                    ->where('shop_id', $record->shop_id)
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

