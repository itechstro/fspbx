<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('call_transcriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('call_transcriptions', 'transcription_audio_duration_seconds')) {
                $table->unsignedInteger('transcription_audio_duration_seconds')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('call_transcriptions', 'transcription_speech_model')) {
                $table->string('transcription_speech_model', 64)->nullable()->after('transcription_audio_duration_seconds');
            }
            if (! Schema::hasColumn('call_transcriptions', 'transcription_cost_usd')) {
                $table->decimal('transcription_cost_usd', 12, 6)->nullable()->after('transcription_speech_model');
            }

            if (! Schema::hasColumn('call_transcriptions', 'summary_provider')) {
                $table->string('summary_provider', 32)->nullable()->after('summary_completed_at');
            }
            if (! Schema::hasColumn('call_transcriptions', 'summary_model')) {
                $table->string('summary_model', 64)->nullable()->after('summary_provider');
            }
            if (! Schema::hasColumn('call_transcriptions', 'summary_input_tokens')) {
                $table->unsignedInteger('summary_input_tokens')->nullable()->after('summary_model');
            }
            if (! Schema::hasColumn('call_transcriptions', 'summary_output_tokens')) {
                $table->unsignedInteger('summary_output_tokens')->nullable()->after('summary_input_tokens');
            }
            if (! Schema::hasColumn('call_transcriptions', 'summary_total_tokens')) {
                $table->unsignedInteger('summary_total_tokens')->nullable()->after('summary_output_tokens');
            }
            if (! Schema::hasColumn('call_transcriptions', 'summary_cost_usd')) {
                $table->decimal('summary_cost_usd', 12, 6)->nullable()->after('summary_total_tokens');
            }

            if (! Schema::hasColumn('call_transcriptions', 'translation_model')) {
                $table->string('translation_model', 64)->nullable()->after('translation_completed_at');
            }
            if (! Schema::hasColumn('call_transcriptions', 'translation_input_tokens')) {
                $table->unsignedInteger('translation_input_tokens')->nullable()->after('translation_model');
            }
            if (! Schema::hasColumn('call_transcriptions', 'translation_output_tokens')) {
                $table->unsignedInteger('translation_output_tokens')->nullable()->after('translation_input_tokens');
            }
            if (! Schema::hasColumn('call_transcriptions', 'translation_total_tokens')) {
                $table->unsignedInteger('translation_total_tokens')->nullable()->after('translation_output_tokens');
            }
            if (! Schema::hasColumn('call_transcriptions', 'translation_cost_usd')) {
                $table->decimal('translation_cost_usd', 12, 6)->nullable()->after('translation_total_tokens');
            }

            if (! Schema::hasColumn('call_transcriptions', 'total_ai_cost_usd')) {
                $table->decimal('total_ai_cost_usd', 12, 6)->nullable()->after('translation_cost_usd');
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_transcriptions', function (Blueprint $table) {
            $columns = [
                'transcription_audio_duration_seconds',
                'transcription_speech_model',
                'transcription_cost_usd',
                'summary_provider',
                'summary_model',
                'summary_input_tokens',
                'summary_output_tokens',
                'summary_total_tokens',
                'summary_cost_usd',
                'translation_model',
                'translation_input_tokens',
                'translation_output_tokens',
                'translation_total_tokens',
                'translation_cost_usd',
                'total_ai_cost_usd',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('call_transcriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
