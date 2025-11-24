<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Shop extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'shop_type_id',
        'shop_name',
        'memo',
    ];

    protected $hidden = ['shopType'];

    protected $appends = ['shop_type'];

    /**
     * ShopTypeとのリレーション
     */
    public function shopType()
    {
        return $this->belongsTo(ShopType::class, 'shop_type_id');
    }

    /**
     * ShopUrlとのリレーション
     */
    public function shopUrls()
    {
        return $this->hasMany(ShopUrl::class, 'shop_id')->orderBy('display_order');
    }

    /**
     * shop_typeアクセサ
     */
    public function getShopTypeAttribute()
    {
        if ($this->relationLoaded('shopType')) {
            $shopType = $this->getRelationValue('shopType');
            return $shopType ? $shopType->name : null;
        }
        
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
}
