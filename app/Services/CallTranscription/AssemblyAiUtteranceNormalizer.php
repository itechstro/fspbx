<?php

namespace App\Services\CallTranscription;

class AssemblyAiUtteranceNormalizer
{
    private const WORD_GAP_MS = 1500;

    /**
     * @param  array<string, mixed>  $full
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $full): array
    {
        $utterances = (array) ($full['utterances'] ?? []);
        $words = (array) ($full['words'] ?? []);
        $channels = (int) ($full['audio_channels'] ?? 0);

        if ($channels < 2 && empty($full['multichannel'])) {
            return $utterances;
        }

        $split = $this->splitAndInterleaveUtterances($utterances);
        if (count($split) > count($utterances)) {
            return $split;
        }

        if ($words !== []) {
            $fromWords = $this->fromWords($words);
            if (count($fromWords) > count($utterances)) {
                return $fromWords;
            }
        }

        return $utterances;
    }

    /**
     * @param  array<int, array<string, mixed>>  $words
     * @return array<int, array<string, mixed>>
     */
    private function fromWords(array $words): array
    {
        usort($words, fn (array $a, array $b) => ((int) ($a['start'] ?? 0)) <=> ((int) ($b['start'] ?? 0)));

        $groups = [];
        $current = null;

        foreach ($words as $word) {
            $speaker = (string) ($word['speaker'] ?? $word['channel'] ?? '1');
            $start = (int) ($word['start'] ?? 0);
            $end = (int) ($word['end'] ?? $start);
            $text = trim((string) ($word['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            if (
                $current === null
                || $current['speaker'] !== $speaker
                || $start - $current['end'] > self::WORD_GAP_MS
            ) {
                if ($current !== null) {
                    $groups[] = $current;
                }

                $current = [
                    'speaker' => $speaker,
                    'channel' => $word['channel'] ?? null,
                    'start' => $start,
                    'end' => $end,
                    'text' => $text,
                ];

                continue;
            }

            $current['end'] = $end;
            $current['text'] = trim($current['text'] . ' ' . $text);
        }

        if ($current !== null) {
            $groups[] = $current;
        }

        return array_values($groups);
    }

    /**
     * @param  array<int, array<string, mixed>>  $utterances
     * @return array<int, array<string, mixed>>
     */
    private function splitAndInterleaveUtterances(array $utterances): array
    {
        $splitBySpeaker = [];

        foreach ($utterances as $utterance) {
            $speaker = (string) ($utterance['speaker'] ?? '1');
            $parts = $this->splitClauses(trim((string) ($utterance['text'] ?? '')));

            if ($parts === []) {
                continue;
            }

            $start = (int) ($utterance['start'] ?? 0);
            $end = (int) ($utterance['end'] ?? $start);
            $duration = max(1, $end - $start);
            $slice = (int) floor($duration / count($parts));

            foreach ($parts as $index => $part) {
                $splitBySpeaker[$speaker][] = [
                    'speaker' => $speaker,
                    'channel' => $utterance['channel'] ?? null,
                    'start' => $start + ($index * $slice),
                    'end' => min($end, $start + (($index + 1) * $slice)),
                    'text' => $part,
                ];
            }
        }

        if ($splitBySpeaker === []) {
            return $utterances;
        }

        $maxParts = max(array_map('count', $splitBySpeaker));
        if ($maxParts <= 1) {
            return $utterances;
        }

        $speakers = array_keys($splitBySpeaker);
        sort($speakers, SORT_NATURAL);

        $interleaved = [];
        for ($turn = 0; $turn < $maxParts; $turn++) {
            foreach ($speakers as $speaker) {
                if (! isset($splitBySpeaker[$speaker][$turn])) {
                    continue;
                }

                $interleaved[] = $splitBySpeaker[$speaker][$turn];
            }
        }

        return $interleaved !== [] ? $interleaved : $utterances;
    }

    /**
     * @return array<int, string>
     */
    private function splitClauses(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $parts = preg_split(
            '/(?<=[ครับค่ะ。！？.!?])(?=(?:ขอบคุณ|ขอโทษ|ต้องขอโทษ|ห้องน้ำ|ยินดี|ได้เลย|สวัสดี|谢谢|謝謝|非常感谢|非常感謝|非常抱歉|再次向您|浴室|浴室沒有|不客气|不客氣|好的|你好|The |Thank|I am so sorry|I apologize|You are|Hello|Certainly))/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (! is_array($parts)) {
            return [$text];
        }

        $parts = array_values(array_filter(array_map('trim', $parts)));

        return $parts !== [] ? $parts : [$text];
    }
}
