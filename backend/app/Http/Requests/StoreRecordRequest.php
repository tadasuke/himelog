<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecordRequest extends FormRequest
{
    /**
     * リクエストの認証を許可するかどうかを決定
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルールを取得
     */
    public function rules(): array
    {
        return [
            'shop_type' => 'required_without:shop_type_id|string|max:255',
            'shop_type_id' => 'required_without:shop_type|integer|exists:shop_types,id',
            'shop_name' => 'required|string|max:255',
            'girl_name' => 'nullable|string|max:255',
            'visit_date' => 'required|date|before_or_equal:today',
            'face_rating' => 'nullable|integer|min:1|max:10',
            'style_rating' => 'nullable|integer|min:1|max:10',
            'service_rating' => 'nullable|integer|min:1|max:10',
            'overall_rating' => 'nullable|integer|min:1|max:10',
            'review' => 'nullable|string',
        ];
    }

    /**
     * バリデーションエラーメッセージをカスタマイズ
     */
    public function messages(): array
    {
        return [
            'shop_type.required_without' => 'お店の種類は必須です',
            'shop_type_id.required_without' => 'お店の種類は必須です',
            'shop_type_id.integer' => 'お店の種類IDは整数で入力してください',
            'shop_type_id.exists' => '指定されたお店の種類が見つかりません',
            'shop_name.required' => 'お店の名前は必須です',
            'visit_date.required' => '来店日は必須です',
            'visit_date.date' => '来店日は有効な日付形式で入力してください',
            'visit_date.before_or_equal' => '来店日は今日以前の日付を入力してください',
            'face_rating.integer' => '顔の評価は整数で入力してください',
            'face_rating.min' => '顔の評価は1以上で入力してください',
            'face_rating.max' => '顔の評価は10以下で入力してください',
            'style_rating.integer' => 'スタイルの評価は整数で入力してください',
            'style_rating.min' => 'スタイルの評価は1以上で入力してください',
            'style_rating.max' => 'スタイルの評価は10以下で入力してください',
            'service_rating.integer' => '接客の評価は整数で入力してください',
            'service_rating.min' => '接客の評価は1以上で入力してください',
            'service_rating.max' => '接客の評価は10以下で入力してください',
            'overall_rating.integer' => '総合の評価は整数で入力してください',
            'overall_rating.min' => '総合の評価は1以上で入力してください',
            'overall_rating.max' => '総合の評価は10以下で入力してください',
        ];
    }
}

