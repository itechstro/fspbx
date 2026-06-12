<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_sync_connections')) {
            return;
        }

        Schema::create('contact_sync_connections', function (Blueprint $table) {
            $table->uuid('contact_sync_connection_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('provider', 32);
            $table->string('account_email')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('access_token')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->text('scopes')->nullable();
            $table->string('sync_enabled', 8)->default('true');
            $table->uuid('connected_by_user_uuid')->nullable();
            $table->timestampTz('last_sync_at')->nullable();
            $table->string('last_sync_status', 32)->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestampTz('insert_date')->nullable();
            $table->uuid('insert_user')->nullable();
            $table->timestampTz('update_date')->nullable();
            $table->uuid('update_user')->nullable();

            $table->unique(['domain_uuid', 'provider']);
            $table->index('domain_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_sync_connections');
    }
};
