<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XUser extends Model
{
    protected $primaryKey = 'x_user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'x_user_id',
        'name',
        'username',
        'avatar',
        'last_verified_at',
    ];

    protected $casts = [
        'last_verified_at' => 'datetime',
    ];

    /**
     * XAuthServiceで使用する形式の配列に変換
     *
     * @return array
     */
    public function toAuthArray(): array
    {
        return [
            'user_id' => $this->x_user_id,
            'email' => null, // X APIではemailは取得できない
            'name' => $this->name,
            'username' => $this->username,
            'avatar' => $this->avatar,
        ];
    }
}
