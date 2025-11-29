<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class User extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'public_uuid',
        'provider',
        'provider_user_id',
        'name',
        'email',
        'username',
        'avatar',
        'last_verified_at',
        'registered_at',
        'last_login_at',
        'status',
    ];

    protected $casts = [
        'last_verified_at' => 'datetime',
        'registered_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }

            if (empty($model->public_uuid)) {
                $model->public_uuid = (string) Str::uuid();
            }

            if (empty($model->registered_at)) {
                $model->registered_at = now();
            }
        });
    }

    /**
     * 認証サービスで使用する形式の配列に変換
     *
     * @return array
     */
    public function toAuthArray(): array
    {
        return [
            'user_id' => $this->provider_user_id,
            'email' => $this->email,
            'name' => $this->name,
            'username' => $this->username,
            'avatar' => $this->avatar,
        ];
    }
}


