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
        // 既に他のマイグレーションで追加されている可能性もあるため、存在チェックを行う
        if (!Schema::hasColumn('records', 'internal_user_id')) {
            Schema::table('records', function (Blueprint $table) {
                $table->uuid('internal_user_id')
                    ->nullable()
                    ->after('user_id')
                    ->comment('Internal user UUID (users.id)');

                $table->index('internal_user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('records', 'internal_user_id')) {
            Schema::table('records', function (Blueprint $table) {
                $table->dropIndex(['internal_user_id']);
                $table->dropColumn('internal_user_id');
            });
        }
    }
};


