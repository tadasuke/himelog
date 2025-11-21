<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_order',
    ];

    public $timestamps = true;
}
