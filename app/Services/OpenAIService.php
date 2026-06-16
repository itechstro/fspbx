<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAIService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
    }

    public function textToSpeech($model = 'gpt-4o-mini-tts-2025-12-15', $input, $voice = 'alloy', $response_format = 'wav', $speed = '1.0')
    {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key is not configured. Please set the API key in your environment file.');
        }

        $url = 'https://api.openai.com/v1/audio/speech';
        $timeout = (int) config('services.openai.speech_timeout', 60);
        $maxAttempts = max(1, (int) config('services.openai.speech_max_attempts', 2));

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout($timeout)
            ->retry(
                $maxAttempts,
                500,
                fn($exception) => $exception instanceof ConnectionException,
                throw: true
            )
            ->post($url, [
                'model' => $model,
                'input' => $input,
                'voice' => $voice,
                'response_format' => $response_format,
                'speed' => (float) $speed,
            ]);

        return $this->handleResponse($response);
    }

    private function handleResponse($response)
    {
        if ($response->successful()) {
            return $response->body();
        }

        if ($response->clientError()) {
            // Log client errors
            logger('OpenAI API Client Error: ' . $response->body());
            throw new \Exception('There was an error with your request: ' . $response->json('error.message'));
        }

        if ($response->serverError()) {
            // Log server errors
            logger('OpenAI API Server Error: ' . $response->body());
            throw new \Exception('The OpenAI API is currently unavailable. Please try again later.');
        }

        // Handle unexpected errors
        throw new \Exception('An unexpected error occurred. Please try again.');
    }

    public function getVoices()
    {
        return [
            ['value' => 'alloy', 'label' => 'Alloy'],
            ['value' => 'ash', 'label' => 'Ash'],
            ['value' => 'ballad', 'label' => 'Ballad'],
            ['value' => 'coral', 'label' => 'Coral'],
            ['value' => 'echo', 'label' => 'Echo'],
            ['value' => 'fable', 'label' => 'Fable'],
            ['value' => 'onyx', 'label' => 'Onyx'],
            ['value' => 'nova', 'label' => 'Nova'],
            ['value' => 'sage', 'label' => 'Sage'],
            ['value' => 'shimmer', 'label' => 'Shimmer'],
            ['value' => 'verse', 'label' => 'Verse'],
            ['value' => 'marin', 'label' => 'Marin'],
            ['value' => 'cedar', 'label' => 'Cedar'],
        ];
    }

    public function getDefaultVoice()
    {
        return get_domain_setting('openai_default_voice');
    }

    public function getSpeeds()
    {
        $openAiSpeeds = [];

        for ($i = 0.85; $i <= 1.3; $i += 0.05) {
            // Format all with two decimals, or stick with your logic if needed
            $formattedValue = number_format($i, 2, '.', '');
            $openAiSpeeds[] = [
                'value' => $formattedValue,
                'label' => $formattedValue
            ];
        }

        return $openAiSpeeds;
    }

    public function transcribeAudio($filePath, $model = 'whisper-1', $language = null)
    {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key is not configured. Please set the API key in your environment file.');
        }

        $url = 'https://api.openai.com/v1/audio/transcriptions';

        $params = [
            'model' => $model,
        ];
        if ($language) {
            $params['language'] = $language;
        }

        $response = Http::withToken($this->apiKey)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post($url, $params);

        if ($response->successful()) {
            return [
                'message' => $response->json('text')
            ];
        } else {
            logger()->error('OpenAI transcription failed: ' . $response->body());
            return null;
        }
    }

    /**
     * Kick off a background Responses task with your exact system/user prompt and utterances.
     * Returns ["id" => "resp_...","status" => "queued|in_progress|..."].
     */
    public function createBackgroundSummary(array $utteranceLines, string $model = 'gpt-4.1-mini'): array
    {
        $url = 'https://api.openai.com/v1/responses';

        $systemText = implode("\n", [
            'You are a precise call-summary assistant for a VoIP platform.',
            'Transform call transcripts into a concise summary and structured insights.',
            'Rules:',
            '- Use only information present; do not guess or invent.',
            '- Attribute statements correctly when relevant.',
            '- Prefer plain, clear business language.',
            '- If a field is unknown, use null.',
            '- If you can guess the participants name, use that name in your responses.',
            '- If the name is unknown, use the guessed role instead (e.g., "Agent", "Customer").',
            '- Return ONLY valid JSON matching the schema below—no prose.',
            '',
            'Output JSON schema:',
            '{',
            '  "summary": "string (2-4 sentences)",',
            '  "participants": [',
            '    {"label": "A|B|C...", "role_guess": "agent|customer|other|null", "name_guess": "string|null"}',
            '  ],',
            '  "key_points": ["string"],',
            '  "decisions_made": ["string"],',
            '  "action_items": [',
            '    {"owner": "name_guess|role_guess|name|null", "description": "string", "due": "ISO-8601 date or null"}',
            '  ],',
            '  "follow_up_risks": ["string"],',
            '  "sentiment_overall": "positive|neutral|negative|null",',
            '  "compliance_flags": ["string"],',
            '  "next_best_step": "string",',
            '  "confidence": 0.0',
            '}',
        ]);

        $userText = implode("\n", array_merge(
            [
                'Using the utterances below (speaker-labeled, no timestamps), produce the Output JSON.',
                'Return ONLY the JSON object (no markdown, no commentary).',
                '',
                'Utterances:',
            ],
            $utteranceLines
        ));

        $payload = [
            'model'        => $model,
            'background'   => true,
            'instructions' => $systemText,
            'input'        => $userText,
            'store' => false,
        ];

        $resp = Http::withToken($this->apiKey)
            ->acceptJson()
            ->post($url, $payload)
            ->throw()
            ->json();

        return [
            'id'     => data_get($resp, 'id'),
            'status' => data_get($resp, 'status'),
        ];
    }


    /**
     * Retrieve a background response by id.
     * Returns the raw JSON and a convenient tuple of [status, outputText].
     */
    public function retrieveResponseById(string $responseId): array
    {
        $url = 'https://api.openai.com/v1/responses';

        $resp = Http::withToken($this->apiKey)
            ->acceptJson()
            ->get($url . '/' . $responseId)
            ->throw()
            ->json();

        // Prefer the top-level output_text when present
        $text = (string) data_get($resp, 'output_text', '');

        if ($text === '') {
            // Fallback 1: first assistant message text
            $text = (string) data_get($resp, 'output.0.content.0.text', '');
            if ($text === '') {
                // Fallback 2: scan for any message item with output_text
                foreach ((array) data_get($resp, 'output', []) as $item) {
                    if (($item['type'] ?? null) === 'message') {
                        $candidate = (string) data_get($item, 'content.0.text', '');
                        if ($candidate !== '') {
                            $text = $candidate;
                            break;
                        }
                    }
                }
            }
        }

        return [
            'raw'    => $resp,
            'status' => data_get($resp, 'status'),
            'text'   => $text, // may be '', caller should handle
            'model'  => data_get($resp, 'model'),
            'usage'  => $this->extractUsage($resp),
        ];
    }

    /**
     * Kick off a background translation task for speaker-labeled utterances (preferred) or flat transcript text.
     */
    public function createBackgroundTranslation(
        array $utterances,
        ?string $summaryText,
        string $targetLanguage,
        ?string $fallbackTranscriptText = null,
        string $model = 'gpt-4.1-mini'
    ): array
    {
        $url = 'https://api.openai.com/v1/responses';
        $summaryInput = trim((string) $summaryText) !== '' ? (string) $summaryText : '[none]';
        $hasUtterances = count($utterances) > 0;

        if ($hasUtterances) {
            $instructions = implode("\n", [
                'You are a precise translation assistant for call transcriptions.',
                'Translate each utterance and the optional summary to the requested target language.',
                'Rules:',
                '- Translate only the "text" field of each utterance.',
                '- Keep speaker labels and numeric start/end values exactly as provided.',
                '- Return one utterance per input utterance, in the same order.',
                '- Preserve meaning, speaker intent, and tone.',
                '- Return ONLY valid JSON, no markdown and no commentary.',
                '- If summary is missing, return summary_text as null.',
                '',
                'Output JSON schema:',
                '{',
                '  "utterances": [',
                '    {"speaker": "A|B|C...", "start": 0, "end": 0, "text": "translated line"}',
                '  ],',
                '  "summary_text": "string|null"',
                '}',
            ]);

            $input = implode("\n\n", [
                "Target language: {$targetLanguage}",
                'Utterances JSON:',
                json_encode($utterances, JSON_UNESCAPED_UNICODE),
                'Summary:',
                $summaryInput,
            ]);
        } else {
            $transcriptText = trim((string) $fallbackTranscriptText);
            $instructions = implode("\n", [
                'You are a precise translation assistant for call transcriptions.',
                'Translate the provided transcript and summary to the requested target language.',
                'Rules:',
                '- Preserve meaning, speaker intent, and tone.',
                '- Keep line breaks when they exist.',
                '- Return ONLY valid JSON, no markdown and no commentary.',
                '- If summary is missing, return summary_text as null.',
                '',
                'Output JSON schema:',
                '{',
                '  "transcript_text": "string",',
                '  "summary_text": "string|null"',
                '}',
            ]);

            $input = implode("\n\n", [
                "Target language: {$targetLanguage}",
                'Transcript:',
                $transcriptText,
                'Summary:',
                $summaryInput,
            ]);
        }

        $payload = [
            'model'        => $model,
            'background'   => true,
            'instructions' => $instructions,
            'input'        => $input,
            'store'        => false,
        ];

        $resp = Http::withToken($this->apiKey)
            ->acceptJson()
            ->post($url, $payload)
            ->throw()
            ->json();

        return [
            'id'     => data_get($resp, 'id'),
            'status' => data_get($resp, 'status'),
        ];
    }

    /**
     * Parse a completed translation response into text, summary, and optional speaker utterances.
     */
    public function parseTranslationResponse(string $outputText, array $originalUtterances = []): array
    {
        $outputText = trim($outputText);
        if ($outputText === '') {
            return ['text' => '', 'summary_text' => null, 'utterances' => []];
        }

        $decoded = json_decode($outputText, true);
        if (!is_array($decoded)) {
            return ['text' => $outputText, 'summary_text' => null, 'utterances' => []];
        }

        $summaryText = data_get($decoded, 'summary_text');
        $summaryText = $summaryText !== null && trim((string) $summaryText) !== ''
            ? trim((string) $summaryText)
            : null;

        $rawUtterances = data_get($decoded, 'utterances');
        if (is_array($rawUtterances) && count($rawUtterances) > 0) {
            $utterances = $this->normalizeTranslatedUtterances($originalUtterances, $rawUtterances);
            $text = $this->utterancesToTranscriptText($utterances);
            if ($text === '') {
                $text = trim((string) data_get($decoded, 'transcript_text', ''));
            }

            return [
                'text' => $text,
                'summary_text' => $summaryText,
                'utterances' => $utterances,
            ];
        }

        $text = trim((string) data_get($decoded, 'transcript_text', ''));
        if ($text === '') {
            $text = $outputText;
        }

        return [
            'text' => $text,
            'summary_text' => $summaryText,
            'utterances' => [],
        ];
    }

    /**
     * Build a translation payload row for persistence.
     */
    public function buildTranslationPayload(array $parsed, ?string $targetLanguage): array
    {
        return [
            'text' => $parsed['text'] ?? '',
            'summary_text' => $parsed['summary_text'] ?? null,
            'utterances' => $parsed['utterances'] ?? [],
            'target_language' => $targetLanguage,
        ];
    }

    private function normalizeTranslatedUtterances(array $original, array $translated): array
    {
        $out = [];

        foreach ($translated as $i => $item) {
            if (!is_array($item)) {
                continue;
            }

            $text = trim((string) data_get($item, 'text', ''));
            if ($text === '') {
                continue;
            }

            $orig = is_array($original[$i] ?? null) ? $original[$i] : null;

            $out[] = [
                'speaker' => data_get($item, 'speaker') ?? data_get($orig, 'speaker'),
                'start'   => data_get($item, 'start', data_get($orig, 'start')),
                'end'     => data_get($item, 'end', data_get($orig, 'end')),
                'text'    => $text,
            ];
        }

        return $out;
    }

    private function utterancesToTranscriptText(array $utterances): string
    {
        $lines = [];

        foreach ($utterances as $u) {
            if (!is_array($u)) {
                continue;
            }

            $text = trim((string) data_get($u, 'text', ''));
            if ($text === '') {
                continue;
            }

            $speaker = data_get($u, 'speaker');
            $lines[] = $speaker ? "{$speaker}: {$text}" : $text;
        }

        return implode("\n", $lines);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Generate a synchronous executive summary from structured analytics context.
     *
     * @return array{overview?: string, highlights?: array, concerns?: array, recommendations?: array, model?: string, usage?: array}
     */
    public function createExecutiveSummary(array $context, string $model = 'gpt-4.1-mini'): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $url = 'https://api.openai.com/v1/responses';
        $timeout = (int) config('services.openai.executive_summary_timeout', 120);

        $instructions = implode("\n", [
            'You are an executive reporting assistant for a business phone recorder analytics dashboard.',
            'Synthesize the provided period stats, trends, topics, and per-call summaries into a concise leadership brief.',
            'Rules:',
            '- Use only information present in the input; do not invent calls, metrics, or outcomes.',
            '- Write in plain, practical business language.',
            '- Keep overview to 2-4 short paragraphs.',
            '- Highlights should capture positive trends, wins, or useful patterns.',
            '- Concerns should capture risks, negative sentiment patterns, or operational issues supported by the data.',
            '- Recommendations should be actionable next steps for managers.',
            '- If a section has nothing meaningful to say, return an empty array for that field.',
            '- Return ONLY valid JSON matching the schema below.',
            '',
            'Output JSON schema:',
            '{',
            '  "overview": "string",',
            '  "highlights": ["string"],',
            '  "concerns": ["string"],',
            '  "recommendations": ["string"]',
            '}',
        ]);

        $input = implode("\n\n", [
            'Using the recorder analytics context below, produce the Output JSON.',
            'Return ONLY the JSON object (no markdown, no commentary).',
            'Analytics context JSON:',
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        $resp = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($timeout)
            ->post($url, [
                'model' => $model,
                'background' => false,
                'instructions' => $instructions,
                'input' => $input,
                'store' => false,
            ])
            ->throw()
            ->json();

        $text = (string) data_get($resp, 'output_text', '');
        if ($text === '') {
            $text = (string) data_get($resp, 'output.0.content.0.text', '');
        }

        $decoded = $this->parseJsonResponseText($text);
        $decoded['model'] = data_get($resp, 'model', $model);
        $decoded['usage'] = $this->extractUsage($resp);

        return $decoded;
    }

    public function extractUsage(array $response): array
    {
        $usage = (array) data_get($response, 'usage', []);

        return [
            'input_tokens' => (int) data_get($usage, 'input_tokens', 0),
            'output_tokens' => (int) data_get($usage, 'output_tokens', 0),
            'total_tokens' => (int) data_get($usage, 'total_tokens', 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function parseJsonResponseText(string $outputText): array
    {
        $json = trim($outputText);
        if ($json === '') {
            throw new \RuntimeException('OpenAI returned an empty response.');
        }

        if (str_starts_with($json, '```')) {
            $json = trim(preg_replace('/^```(?:json)?|```$/m', '', $json));
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON from OpenAI: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
