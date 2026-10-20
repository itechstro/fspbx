<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('mobile_app_users', 'provider')) {
            Schema::table('mobile_app_users', function (Blueprint $table) {
                $table->string('provider', 20)->nullable();
            });
        }

        if (! Schema::hasColumn('mobile_app_users', 'device_mac')) {
            Schema::table('mobile_app_users', function (Blueprint $table) {
                $table->string('device_mac', 12)->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('mobile_app_users', 'settings')) {
            Schema::table('mobile_app_users', function (Blueprint $table) {
                $table->json('settings')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('mobile_app_users', function (Blueprint $table) {
            if (Schema::hasColumn('mobile_app_users', 'device_mac')) {
                $table->dropUnique(['device_mac']);
                $table->dropColumn('device_mac');
            }
            if (Schema::hasColumn('mobile_app_users', 'settings')) {
                $table->dropColumn('settings');
            }
            if (Schema::hasColumn('mobile_app_users', 'provider')) {
                $table->dropColumn('provider');
            }
        });
    }
};
