<?php

if (!function_exists('durationToHuman')) {
    function durationToHuman($seconds): string
    {
        $seconds = max(0, (int) $seconds);
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        return "{$minutes}m {$remainingSeconds}s";
    }
}

if (!function_exists('agentDurationToHuman')) {
    function agentDurationToHuman($seconds): string
    {
        $seconds = max(0, (int) $seconds);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];

        if ($days > 0) {
            $parts[] = "{$days}d";
        }

        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }

        if ($minutes > 0 || $parts === []) {
            $parts[] = "{$minutes}m";
        }

        return implode(' ', $parts);
    }
}
