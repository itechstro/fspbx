<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('v_extensions') && ! Schema::hasColumn('v_extensions', 'phonebook_contact_uuid')) {
            Schema::table('v_extensions', function (Blueprint $table) {
                $table->uuid('phonebook_contact_uuid')->nullable()->after('cloudplay_ed_id');
            });
        }

        if (Schema::hasTable('v_users') && ! Schema::hasColumn('v_users', 'phonebook_contact_uuid')) {
            Schema::table('v_users', function (Blueprint $table) {
                $table->uuid('phonebook_contact_uuid')->nullable()->after('extension_uuid');
            });
        }

        if (Schema::hasTable('v_contacts') && ! Schema::hasColumn('v_contacts', 'messages_crm_contact_uuid')) {
            Schema::table('v_contacts', function (Blueprint $table) {
                $table->uuid('messages_crm_contact_uuid')->nullable()->after('cloudplay_ed_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('v_extensions', 'phonebook_contact_uuid')) {
            Schema::table('v_extensions', function (Blueprint $table) {
                $table->dropColumn('phonebook_contact_uuid');
            });
        }

        if (Schema::hasColumn('v_users', 'phonebook_contact_uuid')) {
            Schema::table('v_users', function (Blueprint $table) {
                $table->dropColumn('phonebook_contact_uuid');
            });
        }

        if (Schema::hasColumn('v_contacts', 'messages_crm_contact_uuid')) {
            Schema::table('v_contacts', function (Blueprint $table) {
                $table->dropColumn('messages_crm_contact_uuid');
            });
        }
    }
};
