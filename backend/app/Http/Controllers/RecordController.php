<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Record;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\StoreRecordRequest;
use App\Http\Requests\UpdateRecordRequest;
use App\Services\RecordService;
use App\Traits\LogsMethodExecution;

class RecordController extends Controller
{
    use LogsMethodExecution;

    protected RecordService $recordService;

    public function __construct(RecordService $recordService)
    {
        $this->recordService = $recordService;
    }

    /**
     * 記録を登録
     */
    public function store(StoreRecordRequest $request): JsonResponse
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

            $record = $this->recordService->createRecord($authenticatedUserId, $request->validated());

            $result = response()->json([
                'success' => true,
                'record' => $record
            ], 201);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Record creation error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to create record'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * ユーザーの記録一覧を取得
     */
    public function index(Request $request): JsonResponse
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

            $records = $this->recordService->getRecords($authenticatedUserId);

            $result = response()->json([
                'success' => true,
                'records' => $records
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Record fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch records'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * レビューを検索
     */
    public function search(Request $request): JsonResponse
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

            // 検索条件を取得
            $filters = [
                'shop_type_ids' => $request->input('shop_type_ids', []),
                'overall_rating_min' => $request->input('overall_rating_min'),
                'overall_rating_max' => $request->input('overall_rating_max'),
                'visit_date_from' => $request->input('visit_date_from'),
                'visit_date_to' => $request->input('visit_date_to'),
            ];

            $records = $this->recordService->searchRecords($authenticatedUserId, $filters);

            $result = response()->json([
                'success' => true,
                'records' => $records
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Record search error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to search records',
                'message' => $e->getMessage()
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * グラフ表示用に過去10件の記録を取得
     */
    public function getRecentRecordsForChart(Request $request): JsonResponse
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

            $limit = (int) $request->query('limit', 10);
            $records = $this->recordService->getRecentRecordsForChart($authenticatedUserId, $limit);

            $result = response()->json([
                'success' => true,
                'records' => $records
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Recent records for chart fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch recent records for chart'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * お店のタイプごとの集計を取得（円グラフ用）
     */
    public function getShopTypeStatistics(Request $request): JsonResponse
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

            $statistics = $this->recordService->getShopTypeStatistics($authenticatedUserId);

            $result = response()->json([
                'success' => true,
                'statistics' => $statistics
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Shop type statistics fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch shop type statistics'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * 総合評価ごとの集計を取得（円グラフ用）
     */
    public function getOverallRatingStatistics(Request $request): JsonResponse
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

            $statistics = $this->recordService->getOverallRatingStatistics($authenticatedUserId);

            $result = response()->json([
                'success' => true,
                'statistics' => $statistics
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Overall rating statistics fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch overall rating statistics'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * お店の種類とユーザーIDに基づいて登録済みのお店名を取得
     */
    public function getShopNames(Request $request): JsonResponse
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
            
            if (!$shopType) {
                return response()->json([
                    'error' => 'shop_type is required'
                ], 400);
            }

            $shopNames = $this->recordService->getShopNames($authenticatedUserId, $shopType);

            $result = response()->json([
                'success' => true,
                'shop_names' => $shopNames
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Shop names fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch shop names'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * お店の種類、お店の名前、ユーザーIDに基づいて登録済みの女の子の名前を取得
     */
    public function getGirlNames(Request $request): JsonResponse
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

            $girlNames = $this->recordService->getGirlNames($authenticatedUserId, $shopType, $shopName);

            $result = response()->json([
                'success' => true,
                'girl_names' => $girlNames
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Girl names fetch error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $result = response()->json([
                'error' => 'Failed to fetch girl names'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * ユーザーが投稿したことがある全ヒメ（女の子）の一覧を取得
     */
    public function getAllGirlNames(Request $request): JsonResponse
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

            $girlNames = $this->recordService->getAllGirlNames($authenticatedUserId);

            $result = response()->json([
                'success' => true,
                'girl_names' => $girlNames
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('All girl names fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch girl names'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * ユーザーが登録した全お店の一覧を取得（shop_typeごとにグループ化）
     */
    public function getShops(Request $request): JsonResponse
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

            $shops = $this->recordService->getShops($authenticatedUserId);

            $result = response()->json([
                'success' => true,
                'shops' => $shops
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Shops fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch shops'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * 記録を更新
     */
    public function update(UpdateRecordRequest $request, string $id): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['request' => $request, 'id' => $id], __FILE__, __LINE__);
        try {
            // 認証されたユーザーIDを取得
            $authenticatedUserId = $request->input('authenticated_user_id');
            if (!$authenticatedUserId) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => '認証が必要です'
                ], 401);
            }

            $record = Record::where('user_id', $authenticatedUserId)
                ->find($id);
            
            if (!$record) {
                return response()->json([
                    'error' => 'Record not found'
                ], 404);
            }

            try {
                $record = $this->recordService->updateRecord($record, $authenticatedUserId, $request->validated());
            } catch (\Exception $e) {
                if ($e->getMessage() === 'この記録を更新する権限がありません') {
                    return response()->json([
                        'error' => 'Forbidden'
                    ], 403);
                }
                throw $e;
            }

            $result = response()->json([
                'success' => true,
                'record' => $record
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Record update error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to update record'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * 特定のお店の記録一覧を取得
     */
    public function getShopRecords(Request $request): JsonResponse
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

            $records = $this->recordService->getShopRecords($authenticatedUserId, $shopType, $shopName);

            $result = response()->json([
                'success' => true,
                'records' => $records
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Shop records fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch shop records'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * 特定のヒメ（女の子）の記録一覧を取得
     */
    public function getGirlRecords(Request $request): JsonResponse
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

            $girlName = $request->query('girl_name');
            
            if (!$girlName) {
                return response()->json([
                    'error' => 'girl_name is required'
                ], 400);
            }

            $records = $this->recordService->getGirlRecords($authenticatedUserId, $girlName);

            $result = response()->json([
                'success' => true,
                'records' => $records
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Girl records fetch error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to fetch girl records'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * 記録を削除
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['id' => $id], __FILE__, __LINE__);
        try {
            // 認証されたユーザーIDを取得
            $authenticatedUserId = $request->input('authenticated_user_id');
            if (!$authenticatedUserId) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => '認証が必要です'
                ], 401);
            }

            $record = Record::where('user_id', $authenticatedUserId)
                ->find($id);
            
            if (!$record) {
                return response()->json([
                    'error' => 'Record not found'
                ], 404);
            }

            try {
                $this->recordService->deleteRecord($record, $authenticatedUserId);
            } catch (\Exception $e) {
                if ($e->getMessage() === 'この記録を削除する権限がありません') {
                    return response()->json([
                        'error' => 'Forbidden'
                    ], 403);
                }
                throw $e;
            }

            $result = response()->json([
                'success' => true,
                'message' => 'Record deleted successfully'
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Record deletion error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to delete record'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * レビューを公開（HTML生成とS3アップロード）
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['id' => $id], __FILE__, __LINE__);
        try {
            // 認証されたユーザーIDを取得
            $authenticatedUserId = $request->input('authenticated_user_id');
            if (!$authenticatedUserId) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => '認証が必要です'
                ], 401);
            }

            $record = Record::where('user_id', $authenticatedUserId)
                ->find($id);
            
            if (!$record) {
                return response()->json([
                    'error' => 'Record not found'
                ], 404);
            }

            // 公開オプションを取得（デフォルトはtrue）
            $includeShopName = filter_var($request->input('include_shop_name', true), FILTER_VALIDATE_BOOLEAN);
            $includeGirlName = filter_var($request->input('include_girl_name', true), FILTER_VALIDATE_BOOLEAN);
            $publicReview = $request->input('public_review', '');

            try {
                $publicUrl = $this->recordService->publishRecord(
                    $record, 
                    $authenticatedUserId,
                    $includeShopName,
                    $includeGirlName,
                    $publicReview
                );
            } catch (\Exception $e) {
                if ($e->getMessage() === 'この記録を公開する権限がありません') {
                    return response()->json([
                        'error' => 'Forbidden'
                    ], 403);
                }
                throw $e;
            }

            $result = response()->json([
                'success' => true,
                'public_url' => $publicUrl,
                'public_token' => $record->public_token
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Record publish error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to publish record'
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * 公開URLを取得
     */
    public function getPublicUrl(Request $request, string $id): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['id' => $id], __FILE__, __LINE__);
        try {
            // 認証されたユーザーIDを取得
            $authenticatedUserId = $request->input('authenticated_user_id');
            if (!$authenticatedUserId) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => '認証が必要です'
                ], 401);
            }

            $record = Record::where('user_id', $authenticatedUserId)
                ->find($id);
            
            if (!$record) {
                return response()->json([
                    'error' => 'Record not found'
                ], 404);
            }

            // 公開されていない場合
            if (!$record->public_token) {
                return response()->json([
                    'success' => true,
                    'is_published' => false,
                    'public_url' => null
                ]);
            }

            // 公開URLを生成
            $s3Service = new \App\Services\S3Service();
            $filename = $record->public_token . '.html';
            $publicUrl = $s3Service->getPublicUrl($filename);

            $result = response()->json([
                'success' => true,
                'is_published' => true,
                'public_url' => $publicUrl,
                'public_token' => $record->public_token
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Get public URL error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to get public URL',
                'message' => $e->getMessage()
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }

    /**
     * 公開ページを削除
     */
    public function unpublish(Request $request, string $id): JsonResponse
    {
        $this->logMethodStart(__FUNCTION__, ['id' => $id], __FILE__, __LINE__);
        try {
            // 認証されたユーザーIDを取得
            $authenticatedUserId = $request->input('authenticated_user_id');
            if (!$authenticatedUserId) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => '認証が必要です'
                ], 401);
            }

            $record = Record::where('user_id', $authenticatedUserId)
                ->find($id);
            
            if (!$record) {
                return response()->json([
                    'error' => 'Record not found'
                ], 404);
            }

            try {
                $this->recordService->unpublishRecord($record, $authenticatedUserId);
            } catch (\Exception $e) {
                if ($e->getMessage() === 'この記録の公開を削除する権限がありません') {
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => $e->getMessage()
                    ], 403);
                }
                throw $e;
            }

            $result = response()->json([
                'success' => true,
                'message' => '公開ページを削除しました'
            ]);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        } catch (\Exception $e) {
            Log::error('Record unpublish error: ' . $e->getMessage());
            $result = response()->json([
                'error' => 'Failed to unpublish record',
                'message' => $e->getMessage()
            ], 500);
            $this->logMethodEnd(__FUNCTION__, $result, __FILE__, __LINE__);
            return $result;
        }
    }
}
