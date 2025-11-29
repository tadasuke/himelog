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
        if (Schema::hasColumn('records', 'user_id')) {
            Schema::table('records', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('records', 'user_id')) {
            Schema::table('records', function (Blueprint $table) {
                $table->string('user_id')->nullable()->after('id')->comment('Deprecated user identifier');
            });
        }
    }
};


