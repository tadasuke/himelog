<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('girls', function (Blueprint $table) {
            if (Schema::hasColumn('girls', 'image_url')) {
                $table->dropColumn('image_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('girls', function (Blueprint $table) {
            $table->text('image_url')->nullable()->after('memo')->comment('ヒメの画像URL');
        });
    }
};
