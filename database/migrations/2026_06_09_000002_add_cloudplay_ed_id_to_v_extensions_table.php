<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('v_extensions', 'cloudplay_ed_id')) {
            Schema::table('v_extensions', function (Blueprint $table) {
                $table->unsignedBigInteger('cloudplay_ed_id')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('v_extensions', 'cloudplay_ed_id')) {
            Schema::table('v_extensions', function (Blueprint $table) {
                $table->dropColumn('cloudplay_ed_id');
            });
        }
    }
};
