<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Girl extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'internal_user_id',
        'shop_id',
        'girl_name',
        'memo',
    ];

    /**
     * Shopとのリレーション
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * GirlUrlとのリレーション
     */
    public function girlUrls()
    {
        return $this->hasMany(GirlUrl::class, 'girl_id')->orderBy('display_order');
    }

    /**
     * GirlImageUrlとのリレーション
     */
    public function girlImageUrls()
    {
        return $this->hasMany(GirlImageUrl::class, 'girl_id')->orderBy('display_order');
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


