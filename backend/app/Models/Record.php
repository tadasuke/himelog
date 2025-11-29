<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Record extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'public_token',
        'user_id',
        'internal_user_id',
        'shop_type_id',
        'shop_name',
        'girl_name',
        'visit_date',
        'face_rating',
        'style_rating',
        'service_rating',
        'overall_rating',
        'review',
        'price',
        'course',
    ];

    protected $casts = [
        'visit_date' => 'date',
    ];

    protected $appends = ['shop_type'];
    
    protected $hidden = ['shopType'];

    /**
     * モデルを配列に変換
     */
    public function toArray()
    {
        $array = parent::toArray();
        
        // shopTypeリレーションを確実に除外
        unset($array['shopType']);
        
        // shop_typeがオブジェクトや配列の場合は文字列に変換
        if (isset($array['shop_type'])) {
            if (is_array($array['shop_type'])) {
                $array['shop_type'] = $array['shop_type']['name'] ?? null;
            } elseif (is_object($array['shop_type'])) {
                $array['shop_type'] = $array['shop_type']->name ?? null;
            }
        }
        
        return $array;
    }

    /**
     * ShopTypeとのリレーション
     */
    public function shopType()
    {
        return $this->belongsTo(ShopType::class, 'shop_type_id');
    }

    /**
     * shop_typeアクセサ（後方互換性のため）
     */
    public function getShopTypeAttribute()
    {
        // リレーションが既にロードされている場合
        if ($this->relationLoaded('shopType')) {
            $shopType = $this->getRelationValue('shopType');
            return $shopType ? $shopType->name : null;
        }
        
        // リレーションがロードされていない場合は、shop_type_idから取得
        if ($this->shop_type_id) {
            $shopType = ShopType::find($this->shop_type_id);
            return $shopType ? $shopType->name : null;
        }
        
        return null;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * 公開用トークンを生成（4文字の数字+小文字）
     */
    public function generatePublicToken(): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz'; // 36文字（数字+小文字）
        do {
            $token = '';
            for ($i = 0; $i < 4; $i++) {
                $token .= $chars[random_int(0, 35)];
            }
        } while (self::where('public_token', $token)->exists());

        $this->public_token = $token;
        $this->save();

        return $token;
    }

    /**
     * 公開用トークンでレコードを取得
     */
    public static function findByPublicToken(string $token): ?self
    {
        return self::where('public_token', $token)->first();
    }
}
