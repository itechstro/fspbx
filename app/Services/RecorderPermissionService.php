<?php

namespace App\Services;

use App\Models\CDR;
use App\Models\CallTranscription;

class RecorderPermissionService
{
    public static function isRecorderDirection(?string $direction): bool
    {
        return $direction === 'recorder';
    }

    public static function canViewRecorder(): bool
    {
        return userCheckPermission('recorder_view');
    }

    public static function canViewGlobal(): bool
    {
        return userCheckPermission('recorder_view_global');
    }

    public static function canViewDetails(): bool
    {
        return userCheckPermission('recorder_view_details');
    }

    public static function canPlayRecording(): bool
    {
        return userCheckPermission('recorder_recording_play');
    }

    public static function canDownloadRecording(): bool
    {
        return userCheckPermission('recorder_recording_download');
    }

    public static function canDownloadCdrRecording(CDR $cdr): bool
    {
        if (self::isRecorderDirection($cdr->direction)) {
            return self::canDownloadRecording();
        }

        return userCheckPermission('call_recording_download');
    }

    public static function assertCanViewItemDetails(CDR $cdr): void
    {
        if (! self::isRecorderDirection($cdr->direction)) {
            return;
        }

        if (! self::canViewRecorder() || ! self::canViewDetails()) {
            abort(403);
        }
    }

    public static function assertCanOpenRecording(CDR $cdr): void
    {
        if (! self::isRecorderDirection($cdr->direction)) {
            return;
        }

        if (! self::canViewRecorder() || ! self::canPlayRecording()) {
            abort(403);
        }
    }

    public static function modalPermissionsForCdr(CDR $cdr): array
    {
        if (! self::isRecorderDirection($cdr->direction)) {
            return [
                'transcription_view' => userCheckPermission('transcription_view'),
                'transcription_read' => userCheckPermission('transcription_read'),
                'transcription_create' => userCheckPermission('transcription_create'),
                'transcription_summary' => userCheckPermission('transcription_summary'),
                'xml_cdr_search_sentiment' => userCheckPermission('xml_cdr_search_sentiment'),
            ];
        }

        return [
            'transcription_view' => userCheckPermission('recorder_transcription_view'),
            'transcription_read' => userCheckPermission('recorder_transcription_read'),
            'transcription_create' => userCheckPermission('recorder_transcription_create'),
            'transcription_summary' => userCheckPermission('recorder_transcription_summary'),
            'xml_cdr_search_sentiment' => userCheckPermission('recorder_search_sentiment'),
        ];
    }

    public static function assertCanTranscribe(CDR $cdr): void
    {
        if (self::isRecorderDirection($cdr->direction)) {
            if (! self::canViewRecorder() || ! userCheckPermission('recorder_transcription_create')) {
                abort(403);
            }

            return;
        }

        if (! userCheckPermission('transcription_create')) {
            abort(403);
        }
    }

    public static function assertCanSummarize(CDR $cdr): void
    {
        if (self::isRecorderDirection($cdr->direction)) {
            if (! self::canViewRecorder() || ! userCheckPermission('recorder_transcription_summary')) {
                abort(403);
            }

            return;
        }

        if (! userCheckPermission('transcription_summary')) {
            abort(403);
        }
    }

    public static function assertCanTranslate(CDR $cdr): void
    {
        self::assertCanSummarize($cdr);
    }

    public static function cdrForTranscriptionUuid(string $transcriptionUuid): ?CDR
    {
        $transcription = CallTranscription::query()
            ->where('uuid', $transcriptionUuid)
            ->select('xml_cdr_uuid')
            ->first();

        if (! $transcription) {
            return null;
        }

        return CDR::query()
            ->where('xml_cdr_uuid', $transcription->xml_cdr_uuid)
            ->select('xml_cdr_uuid', 'direction', 'domain_uuid')
            ->first();
    }

    public static function pagePermissions(): array
    {
        return [
            'view_global' => self::canViewGlobal(),
            'view_details' => self::canViewDetails(),
            'analytics_view' => userCheckPermission('recorder_analytics_view'),
            'recording_play' => self::canPlayRecording(),
            'recording_download' => self::canDownloadRecording(),
            'search_sentiment' => self::canSearchSentiment(),
        ];
    }

    public static function canSearchSentiment(): bool
    {
        if (! userCheckPermission('recorder_search_sentiment')) {
            return false;
        }

        $transcriptionService = app(\App\Services\CallTranscription\CallTranscriptionService::class);
        $config = $transcriptionService->getCachedConfig(session('domain_uuid') ?? null);

        return (bool) ($config['enabled'] ?? false);
    }
}
