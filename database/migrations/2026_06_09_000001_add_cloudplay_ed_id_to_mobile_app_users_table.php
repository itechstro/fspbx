<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('mobile_app_users', 'cloudplay_ed_id')) {
            Schema::table('mobile_app_users', function (Blueprint $table) {
                $table->unsignedBigInteger('cloudplay_ed_id')->nullable()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mobile_app_users', 'cloudplay_ed_id')) {
            Schema::table('mobile_app_users', function (Blueprint $table) {
                $table->dropColumn('cloudplay_ed_id');
            });
        }
    }
};
