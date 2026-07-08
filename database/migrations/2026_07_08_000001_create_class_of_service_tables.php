<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('v_class_of_service')) {
            Schema::create('v_class_of_service', function (Blueprint $table) {
                $table->uuid('class_of_service_uuid')->primary();
                $table->uuid('domain_uuid')->index();
                $table->string('cos_name');
                $table->text('cos_description')->nullable();
                $table->text('toll_allow')->nullable();
                $table->string('default_action', 16)->default('allow');
                $table->string('enabled', 8)->default('true');
                $table->timestamp('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestamp('update_date')->nullable();
                $table->uuid('update_user')->nullable();
            });
        }

        if (! Schema::hasTable('v_class_of_service_destinations')) {
            Schema::create('v_class_of_service_destinations', function (Blueprint $table) {
                $table->uuid('class_of_service_destination_uuid')->primary();
                $table->uuid('class_of_service_uuid')->index();
                $table->string('destination_prefix');
                $table->string('destination_action', 16);
                $table->integer('destination_order')->default(100);
                $table->string('enabled', 8)->default('true');
                $table->text('destination_description')->nullable();
                $table->timestamp('insert_date')->nullable();
                $table->uuid('insert_user')->nullable();
                $table->timestamp('update_date')->nullable();
                $table->uuid('update_user')->nullable();
            });
        }

        if (! Schema::hasColumn('v_extensions', 'class_of_service_uuid')) {
            Schema::table('v_extensions', function (Blueprint $table) {
                $table->uuid('class_of_service_uuid')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('v_extensions', 'class_of_service_uuid')) {
            Schema::table('v_extensions', function (Blueprint $table) {
                $table->dropColumn('class_of_service_uuid');
            });
        }

        Schema::dropIfExists('v_class_of_service_destinations');
        Schema::dropIfExists('v_class_of_service');
    }
};
