<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Record;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RecordController extends Controller
{
    /**
     * 記録を登録
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|string',
                'shop_type' => 'required|string|max:255',
                'shop_name' => 'required|string|max:255',
                'girl_name' => 'nullable|string|max:255',
                'visit_date' => 'required|date|before_or_equal:today',
                'face_rating' => 'nullable|integer|min:1|max:10',
                'style_rating' => 'nullable|integer|min:1|max:10',
                'service_rating' => 'nullable|integer|min:1|max:10',
                'overall_rating' => 'nullable|integer|min:1|max:10',
                'review' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            $record = Record::create([
                'user_id' => $request->input('user_id'),
                'shop_type' => $request->input('shop_type'),
                'shop_name' => $request->input('shop_name'),
                'girl_name' => $request->input('girl_name'),
                'visit_date' => $request->input('visit_date'),
                'face_rating' => $request->input('face_rating'),
                'style_rating' => $request->input('style_rating'),
                'service_rating' => $request->input('service_rating'),
                'overall_rating' => $request->input('overall_rating'),
                'review' => $request->input('review'),
            ]);

            Log::info('Record created', ['record_id' => $record->id, 'user_id' => $record->user_id]);

            return response()->json([
                'success' => true,
                'record' => $record
            ], 201);
        } catch (\Exception $e) {
            Log::error('Record creation error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to create record',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ユーザーの記録一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->query('user_id');
            
            if (!$userId) {
                return response()->json([
                    'error' => 'user_id is required'
                ], 400);
            }

            $records = Record::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'records' => $records
            ]);
        } catch (\Exception $e) {
            Log::error('Record fetch error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch records',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * お店の種類とユーザーIDに基づいて登録済みのお店名を取得
     */
    public function getShopNames(Request $request): JsonResponse
    {
        try {
            $userId = $request->query('user_id');
            $shopType = $request->query('shop_type');
            
            if (!$userId) {
                return response()->json([
                    'error' => 'user_id is required'
                ], 400);
            }

            if (!$shopType) {
                return response()->json([
                    'error' => 'shop_type is required'
                ], 400);
            }

            $shopNames = Record::where('user_id', $userId)
                ->where('shop_type', $shopType)
                ->distinct()
                ->pluck('shop_name')
                ->sort()
                ->values();

            return response()->json([
                'success' => true,
                'shop_names' => $shopNames
            ]);
        } catch (\Exception $e) {
            Log::error('Shop names fetch error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch shop names',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * お店の種類、お店の名前、ユーザーIDに基づいて登録済みの女の子の名前を取得
     */
    public function getGirlNames(Request $request): JsonResponse
    {
        try {
            $userId = $request->query('user_id');
            $shopType = $request->query('shop_type');
            $shopName = $request->query('shop_name');
            
            if (!$userId) {
                return response()->json([
                    'error' => 'user_id is required'
                ], 400);
            }

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

            $girlNames = Record::where('user_id', $userId)
                ->where('shop_type', $shopType)
                ->where('shop_name', $shopName)
                ->distinct()
                ->pluck('girl_name')
                ->sort()
                ->values();

            return response()->json([
                'success' => true,
                'girl_names' => $girlNames
            ]);
        } catch (\Exception $e) {
            Log::error('Girl names fetch error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch girl names',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 記録を更新
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $record = Record::find($id);
            
            if (!$record) {
                return response()->json([
                    'error' => 'Record not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'shop_type' => 'required|string|max:255',
                'shop_name' => 'required|string|max:255',
                'girl_name' => 'nullable|string|max:255',
                'visit_date' => 'required|date|before_or_equal:today',
                'face_rating' => 'nullable|integer|min:1|max:10',
                'style_rating' => 'nullable|integer|min:1|max:10',
                'service_rating' => 'nullable|integer|min:1|max:10',
                'overall_rating' => 'nullable|integer|min:1|max:10',
                'review' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            $record->update([
                'shop_type' => $request->input('shop_type'),
                'shop_name' => $request->input('shop_name'),
                'girl_name' => $request->input('girl_name'),
                'visit_date' => $request->input('visit_date'),
                'face_rating' => $request->input('face_rating'),
                'style_rating' => $request->input('style_rating'),
                'service_rating' => $request->input('service_rating'),
                'overall_rating' => $request->input('overall_rating'),
                'review' => $request->input('review'),
            ]);

            Log::info('Record updated', ['record_id' => $record->id, 'user_id' => $record->user_id]);

            return response()->json([
                'success' => true,
                'record' => $record
            ]);
        } catch (\Exception $e) {
            Log::error('Record update error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to update record',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 記録を削除
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $record = Record::find($id);
            
            if (!$record) {
                return response()->json([
                    'error' => 'Record not found'
                ], 404);
            }

            $record->delete();

            Log::info('Record deleted', ['record_id' => $id, 'user_id' => $record->user_id]);

            return response()->json([
                'success' => true,
                'message' => 'Record deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Record deletion error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete record',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
