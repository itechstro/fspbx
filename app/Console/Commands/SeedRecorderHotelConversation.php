<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\OpenAIService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class SeedRecorderHotelConversation extends Command
{
    protected $signature = 'recorder:seed-hotel-conversation
        {--domain=devel.talk.cloudplay.cloud : Domain name or UUID}
        {--room=512 : Guest room number shown in the conversation}
        {--caller= : Recorder caller extension (defaults to room number)}
        {--destination=1008 : Dialed extension}
        {--language=en : Conversation language (en, th, zh, or zh-tw)}
        {--scenario=towels : Conversation scenario (towels, dirty-room, good-service, or bad-room-service)}
        {--transcribe : Queue transcription after seeding}
        {--dry-run : Generate audio only, do not write recorder rows}';

    protected $description = 'Generate a synthetic hotel guest/front desk conversation and insert it into Recorder';

    public function handle(OpenAIService $openAIService, SeedRecorderTestCalls $seeder): int
    {
        if (! $openAIService->isConfigured()) {
            $this->error('OpenAI is not configured. TTS is required to generate the conversation audio.');

            return self::FAILURE;
        }

        if (! $this->binaryExists('ffmpeg') || ! $this->binaryExists('ffprobe')) {
            $this->error('ffmpeg and ffprobe are required.');

            return self::FAILURE;
        }

        $domain = $this->resolveDomain((string) $this->option('domain'));
        if (! $domain) {
            $this->error('Domain not found.');

            return self::FAILURE;
        }

        $room = trim((string) $this->option('room'));
        $caller = trim((string) ($this->option('caller') ?: $room));
        $destination = (string) $this->option('destination');
        $dryRun = (bool) $this->option('dry-run');

        $language = $this->normalizeLanguage((string) $this->option('language'));
        $scenario = $this->normalizeScenario((string) $this->option('scenario'));

        $lines = $this->conversationLines($room, $language, $scenario);
        $this->info(sprintf(
            'Generating hotel conversation (%s, %s) for %s (%d lines)',
            strtoupper($language),
            $scenario,
            $domain->domain_description ?: $domain->domain_name,
            count($lines)
        ));

        $segmentFiles = [];
        $tempDir = sys_get_temp_dir() . '/recorder-hotel-' . Str::uuid();

        try {
            if (! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
                throw new \RuntimeException("Unable to create temp directory: {$tempDir}");
            }

            foreach ($lines as $index => $line) {
                $monoWavPath = $tempDir . '/segment-' . $index . '-mono.wav';
                $stereoWavPath = $tempDir . '/segment-' . $index . '.wav';
                $this->line(sprintf('[%s] %s', $line['speaker'], $line['text']));

                $audio = $openAIService->textToSpeech(
                    'gpt-4o-mini-tts-2025-12-15',
                    $line['text'],
                    $line['voice'],
                    'wav',
                    '1.0'
                );

                file_put_contents($monoWavPath, $audio);

                if (! $this->panMonoToStereoChannel($monoWavPath, $stereoWavPath, $line['channel'])) {
                    return self::FAILURE;
                }

                $segmentFiles[] = $stereoWavPath;
                $segmentFiles[] = $this->createSilenceWav($tempDir, $this->segmentSilenceMs($scenario));
            }

            array_pop($segmentFiles);

            $combinedWav = $tempDir . '/conversation.wav';
            $outputMp3 = $tempDir . '/conversation.mp3';

            if (! $this->concatenateWavFiles($segmentFiles, $combinedWav)) {
                return self::FAILURE;
            }

            if (! $this->convertWavToMp3($combinedWav, $outputMp3)) {
                return self::FAILURE;
            }

            if ($dryRun) {
                $this->info("DRY RUN: generated {$outputMp3}");

                return self::SUCCESS;
            }

            $result = $seeder->seedFromMp3(
                $domain,
                $outputMp3,
                $caller,
                $destination,
                Carbon::now(get_local_time_zone($domain->domain_uuid)),
                (bool) $this->option('transcribe'),
                $this->transcriptionOverrides(),
            );

            $this->table(
                ['Field', 'Value'],
                [
                    ['UUID', $result['xml_cdr_uuid']],
                    ['Caller', $caller],
                    ['Destination', $destination],
                    ['Start', $result['start_local']],
                    ['Duration', $result['duration'] . 's'],
                    ['Recording', $result['record_file']],
                ]
            );

            $this->info('Done. Open /recorder to review the seeded hotel conversation.');

            return self::SUCCESS;
        } finally {
            if (is_dir($tempDir)) {
                foreach (glob($tempDir . '/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($tempDir);
            }
        }
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function conversationLines(string $room, string $language = 'en', string $scenario = 'towels'): array
    {
        return match ($scenario) {
            'dirty-room' => match ($language) {
                'th' => $this->dirtyRoomComplaintLinesThai($room),
                'zh-tw' => $this->dirtyRoomComplaintLinesChineseTraditional($room),
                'zh' => $this->dirtyRoomComplaintLinesChinese($room),
                default => $this->dirtyRoomComplaintLinesEnglish($room),
            },
            'good-service' => match ($language) {
                'th' => $this->goodServiceComplimentLinesThai($room),
                'zh-tw' => $this->goodServiceComplimentLinesChineseTraditional($room),
                'zh' => $this->goodServiceComplimentLinesChinese($room),
                default => $this->goodServiceComplimentLinesEnglish($room),
            },
            'bad-room-service' => match ($language) {
                'th' => $this->badRoomServiceComplaintLinesThai($room),
                'zh-tw' => $this->badRoomServiceComplaintLinesChineseTraditional($room),
                'zh' => $this->badRoomServiceComplaintLinesChinese($room),
                default => $this->badRoomServiceComplaintLinesEnglish($room),
            },
            default => match ($language) {
                'th' => $this->towelRequestLinesThai($room),
                'zh-tw' => $this->towelRequestLinesChineseTraditional($room),
                'zh' => $this->towelRequestLinesChinese($room),
                default => $this->towelRequestLinesEnglish($room),
            },
        };
    }

    protected function segmentSilenceMs(string $scenario): int
    {
        return in_array($scenario, ['good-service', 'bad-room-service'], true) ? 2200 : 1800;
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function towelRequestLinesThai(string $room): array
    {
        return [
                [
                    'speaker' => 'Guest',
                    'voice' => 'shimmer',
                    'channel' => 'left',
                    'text' => "สวัสดีครับ ห้อง {$room} ครับ ขอผ้าเช็ดตัวเพิ่มได้ไหมครับ",
                ],
                [
                    'speaker' => 'Front Desk',
                    'voice' => 'echo',
                    'channel' => 'right',
                    'text' => 'ได้เลยค่ะ เราจะส่งผ้าเช็ดตัวเพิ่มให้ในอีกสักครู่ค่ะ',
                ],
                [
                    'speaker' => 'Guest',
                    'voice' => 'shimmer',
                    'channel' => 'left',
                    'text' => 'ขอบคุณมากครับ',
                ],
                [
                    'speaker' => 'Front Desk',
                    'voice' => 'echo',
                    'channel' => 'right',
                    'text' => 'ยินดีค่ะ ขอให้พักผ่อนสบายนะคะ',
                ],
            ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function towelRequestLinesChinese(string $room): array
    {
        return [
                [
                    'speaker' => 'Guest',
                    'voice' => 'shimmer',
                    'channel' => 'left',
                    'text' => "你好，我是{$room}号房，可以多送几条毛巾吗？",
                ],
                [
                    'speaker' => 'Front Desk',
                    'voice' => 'echo',
                    'channel' => 'right',
                    'text' => '好的，我们马上给您送毛巾上去。',
                ],
                [
                    'speaker' => 'Guest',
                    'voice' => 'shimmer',
                    'channel' => 'left',
                    'text' => '非常感谢。',
                ],
                [
                    'speaker' => 'Front Desk',
                    'voice' => 'echo',
                    'channel' => 'right',
                    'text' => '不客气，祝您住得愉快。',
                ],
            ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function towelRequestLinesChineseTraditional(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "你好，我是{$room}號房，可以多送幾條毛巾嗎？",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '好的，我們馬上給您送毛巾上去。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '非常感謝。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '不客氣，祝您住得愉快。',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function towelRequestLinesEnglish(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "Hello, this is room {$room}. Could I please have some extra towels sent up?",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'Certainly. We will send extra towels to your room in just a moment.',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'Thank you very much.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'You are welcome. Enjoy your stay.',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function dirtyRoomComplaintLinesEnglish(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "Hello, this is room {$room}. I just checked in and my room is very dirty.",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'I am so sorry to hear that. Can you tell me what you found?',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'The bathroom was not cleaned, and there are stains on the carpet.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'I apologize. We will send housekeeping to reclean your room right away.',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'Thank you. I appreciate the quick response.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'You are welcome. Again, we are sorry for the inconvenience.',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function dirtyRoomComplaintLinesThai(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "สวัสดีครับ ห้อง {$room} ครับ เพิ่งเช็คอินมา ห้องสกปรกมากครับ",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ขอโทษด้วยนะคะ ช่วยบอกได้ไหมคะว่าเจออะไรบ้าง',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'ห้องน้ำยังไม่ได้ทำความสะอาด และพรมมีรอยเปื้อนครับ',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ต้องขอโทษอีกครั้งค่ะ เราจะส่งแม่บ้านเข้าไปทำความสะอาดให้ทันทีค่ะ',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'ขอบคุณครับ ขอบคุณที่ช่วยเหลืออย่างรวดเร็วครับ',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ยินดีค่ะ ขอโทษอีกครั้งสำหรับความไม่สะดวกนะคะ',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function dirtyRoomComplaintLinesChinese(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "你好，我是{$room}号房，我刚入住，房间很脏。",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '非常抱歉，请问您具体看到了什么问题？',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '浴室没有打扫，而且地毯上有污渍。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '再次向您道歉，我们会马上派客房服务去重新打扫。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '谢谢，感谢你们的快速处理。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '不客气，再次为给您带来的不便表示歉意。',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function dirtyRoomComplaintLinesChineseTraditional(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "你好，我是{$room}號房，我剛入住，房間很髒。",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '非常抱歉，請問您具體看到了什麼問題？',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '浴室沒有打掃，而且地毯上有污漬。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '再次向您道歉，我們會馬上派客房服務去重新打掃。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '謝謝，感謝你們的快速處理。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '不客氣，再次為給您帶來的不便表示歉意。',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function goodServiceComplimentLinesEnglish(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "Hello, this is room {$room}. I wanted to call and thank you for the excellent service during our stay.",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'Thank you so much for calling. We are delighted to hear that. What stood out most for you?',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'From check-in, everyone was warm and professional. The concierge helped us book a wonderful dinner nearby.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'That is wonderful to hear. I will pass your compliments to the concierge team right away.',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'Housekeeping has also been fantastic. The room is always tidy when we return in the evening.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'Thank you. Our housekeeping supervisor will be very pleased to hear that feedback.',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'Earlier today I asked for extra pillows, and they arrived in less than five minutes.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'We are glad we could respond quickly. Fast service is something we work hard on every day.',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'It really makes the stay comfortable. We are enjoying the hotel and may book again soon.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'We would love to welcome you back. Thank you again for your kind words, and have a wonderful evening.',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function goodServiceComplimentLinesThai(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "สวัสดีครับ ห้อง {$room} ครับ โทรมาขอบคุณสำหรับบริการที่ดีมากตลอดการเข้าพักครับ",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ขอบคุณมากค่ะที่โทรมาบอก ดีใจมากเลยค่ะ อะไรที่ประทับใจที่สุดคะ',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'ตั้งแต่เช็คอิน ทุกคนอบอุ่นและเป็นมืออาชีพมาก คอนเซียร์จช่วยจองร้านอาหารดีๆ ให้ด้วยครับ',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ดีใจมากค่ะ เดี๋ยวจะส่งคำชมไปให้ทีมคอนเซียร์จทันทีค่ะ',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'แม่บ้านก็ยอดเยี่ยมมากครับ กลับห้องตอนเย็นทุกครั้งห้องสะอาดเรียบร้อย',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ขอบคุณค่ะ หัวหน้าแม่บ้านจะดีใจมากที่ได้ฟังแบบนี้ค่ะ',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'เมื่อกี้ขอหมอนเพิ่ม ส่งมาให้ในไม่ถึงห้านาทีครับ',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ดีใจที่ช่วยได้รวดเร็วค่ะ การบริการที่รวดเร็วเป็นสิ่งที่เราให้ความสำคัญทุกวันค่ะ',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'ทำให้พักสบายมากครับ ชอบโรงแรมนี้มาก อาจกลับมาพักอีกเร็วๆ นี้',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ยินดีต้อนรับกลับมาเสมอค่ะ ขอบคุณสำหรับคำชมอีกครั้ง ขอให้ค่ำคืนนี้พักผ่อนสบายนะคะ',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function goodServiceComplimentLinesChinese(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "你好，我是{$room}号房。我想打电话感谢你们在入住期间提供的优质服务。",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '非常感谢您的来电。我们很高兴听到这些反馈。请问哪方面让您最满意？',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '从入住开始，每位员工都很热情、很专业。礼宾部还帮我们预订了一家很不错的餐厅。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '太好了。我会马上把您的表扬转达给礼宾团队。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '客房服务也很棒。我们晚上回到房间时，房间总是整洁干净。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '谢谢您。客房主管听到这样的评价一定会非常高兴。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '今天下午我要求加送枕头，不到五分钟就送到了。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '很高兴我们反应够快。快速服务是我们每天都非常重视的一项工作。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '这让我们的住宿非常舒适。我们很喜欢这家酒店，可能很快会再次预订。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '我们非常期待您再次光临。再次感谢您的肯定，祝您今晚愉快。',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function goodServiceComplimentLinesChineseTraditional(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "你好，我是{$room}號房。我想打電話感謝你們在入住期間提供的優質服務。",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '非常感謝您的來電。我們很高興聽到這些回饋。請問哪方面讓您最滿意？',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '從入住開始，每位員工都很熱情、很專業。禮賓部還幫我們預訂了一家很不錯的餐廳。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '太好了。我會馬上把您的表揚轉達給禮賓團隊。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '客房服務也很棒。我們晚上回到房間時，房間總是整潔乾淨。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '謝謝您。客房主管聽到這樣的評價一定會非常高興。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '今天下午我要求加送枕頭，不到五分鐘就送到了。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '很高興我們反應夠快。快速服務是我們每天都非常重視的一項工作。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '這讓我們的住宿非常舒適。我們很喜歡這家酒店，可能很快就會再次預訂。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '我們非常期待您再次光臨。再次感謝您的肯定，祝您今晚愉快。',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function badRoomServiceComplaintLinesEnglish(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "Hello, this is room {$room}. I ordered room service about twenty minutes ago and I need to complain.",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'I am sorry to hear that. Please tell me what happened and I will help right away.',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'The pasta tastes really bad. It has a strange sour flavor and does not seem fresh at all.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'I sincerely apologize. May I confirm which dish you ordered from the menu?',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'The seafood pasta from the evening room service menu.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'Thank you. I will alert the kitchen manager immediately and review this order.',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'I only took a few bites. Honestly, I do not feel comfortable eating the rest.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'I completely understand. We will remove the charge from your room bill right away.',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'Thank you. Could you send a replacement? Maybe a simple soup or sandwich, prepared fresh.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'Of course. We will prepare a fresh alternative immediately and a manager will follow up to apologize again.',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function badRoomServiceComplaintLinesThai(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "สวัสดีครับ ห้อง {$room} ครับ สั่งรูมเซอร์วิสมาประมาณยี่สิบนาทีแล้ว อยากร้องเรียนครับ",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ขอโทษด้วยค่ะ ช่วยเล่าให้ฟังหน่อยได้ไหมคะ เดี๋ยวช่วยดูให้ทันทีค่ะ',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'พาสต้ารสชาติแย่มากครับ มีรสเปรี้ยวแปลกๆ และไม่สดเลย',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ต้องขอโทษจริงๆ ค่ะ ขอยืนยันเมนูที่สั่งได้ไหมคะ',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'พาสต้าซีฟู้ดจากเมนูรูมเซอร์วิสช่วงเย็นครับ',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ขอบคุณค่ะ จะแจ้งหัวหน้าครัวทันทีและตรวจสอบออเดอร์นี้ค่ะ',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'ผมทานไปแค่ไม่กี่คำครับ รู้สึกไม่สบายใจที่จะทานต่อ',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'เข้าใจค่ะ เราจะยกเลิกค่าใช้จ่ายรายการนี้จากบิลห้องให้ทันทีค่ะ',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'ขอบคุณครับ ช่วยส่งอาหารมาใหม่ได้ไหม อาจเป็นซุปหรือแซนด์วิชที่ทำสดๆ ก็ได้ครับ',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ได้เลยค่ะ จะจัดเตรียมอาหารใหม่ให้ทันที และผู้จัดการจะติดต่อขอโทษอีกครั้งค่ะ',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function badRoomServiceComplaintLinesChinese(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "你好，我是{$room}号房。大约二十分钟前点了客房送餐，我想投诉一下。",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '非常抱歉。请您说一下具体情况，我会马上帮您处理。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '这份意面味道很差，有奇怪的酸味，而且看起来也不新鲜。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '真诚向您道歉。请问您点的是哪一道菜？',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '晚间客房送餐菜单里的海鲜意面。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '谢谢您。我会立刻通知厨房主管并核查这份订单。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '我只尝了几口，老实说，我不太敢继续吃。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '我完全理解。我们会马上把这笔费用从您的房费账单中取消。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '谢谢。可以换一份别的吗？比如简单一点的汤或三明治，请现做。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '当然可以。我们会立刻准备一份新鲜餐点，并由值班经理再次向您致歉。',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function badRoomServiceComplaintLinesChineseTraditional(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "你好，我是{$room}號房。大約二十分鐘前點了客房送餐，我想投訴一下。",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '非常抱歉。請您說一下具體情況，我會馬上幫您處理。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '這份義大利麵味道很差，有奇怪的酸味，而且看起來也不新鮮。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '真誠向您道歉。請問您點的是哪一道菜？',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '晚間客房送餐菜單裡的海鮮義大利麵。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '謝謝您。我會立刻通知廚房主廚並核查這份訂單。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '我只嚐了幾口，老實說，我不太敢繼續吃。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '我完全理解。我們會馬上把這筆費用從您的房費帳單中取消。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '謝謝。可以換一份別的嗎？比如簡單一點的湯或三明治，請現做。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '當然可以。我們會立刻準備一份新鮮餐點，並由值班經理再次向您致歉。',
            ],
        ];
    }

    protected function normalizeScenario(string $scenario): string
    {
        $scenario = strtolower(trim(str_replace('_', '-', $scenario)));

        $aliases = [
            'dirtyroom' => 'dirty-room',
            'goodservice' => 'good-service',
            'service-compliment' => 'good-service',
            'badroomservice' => 'bad-room-service',
            'room-service-complaint' => 'bad-room-service',
            'room-service-taste' => 'bad-room-service',
        ];

        $scenario = $aliases[$scenario] ?? $scenario;

        return in_array($scenario, ['towels', 'dirty-room', 'good-service', 'bad-room-service'], true)
            ? $scenario
            : 'towels';
    }

    /**
     * @return array<string, mixed>
     */
    protected function transcriptionOverrides(): array
    {
        $language = $this->normalizeLanguage((string) $this->option('language'));

        $overrides = [
            // Non-English hotel seeds use stereo multichannel for two-speaker output.
            'multichannel' => true,
            'speaker_labels' => false,
            'speakers_expected' => null,
            'speaker_options' => null,
        ];

        if ($language === 'th') {
            $overrides = array_merge($overrides, $this->explicitLanguageOverrides('th'));
        }

        if (in_array($language, ['zh', 'zh-tw'], true)) {
            // AssemblyAI only accepts zh; Traditional script comes from TTS dialogue.
            $overrides = array_merge($overrides, $this->explicitLanguageOverrides('zh'));
        }

        return $overrides;
    }

    /**
     * @return array<string, mixed>
     */
    protected function explicitLanguageOverrides(string $languageCode): array
    {
        return [
            'language_code' => $languageCode,
            'language_detection' => false,
            'language_detection_options' => null,
            'auto_chapters' => false,
            'auto_highlights' => false,
            'sentiment_analysis' => false,
            'entity_detection' => false,
            'iab_categories' => false,
        ];
    }

    protected function normalizeLanguage(string $language): string
    {
        $language = strtolower(trim($language));

        if (in_array($language, ['zh-tw', 'zh-hant', 'traditional'], true)) {
            return 'zh-tw';
        }

        if (in_array($language, ['zh', 'zh-cn', 'mandarin', 'cmn'], true)) {
            return 'zh';
        }

        return in_array($language, ['en', 'th'], true) ? $language : 'en';
    }

    protected function createSilenceWav(string $tempDir, int $milliseconds): string
    {
        $path = $tempDir . '/silence-' . $milliseconds . '-' . Str::uuid() . '.wav';
        $seconds = max(0.1, $milliseconds / 1000);

        $process = new Process([
            'ffmpeg',
            '-y',
            '-f',
            'lavfi',
            '-i',
            'anullsrc=r=16000:cl=stereo',
            '-t',
            (string) $seconds,
            $path,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($path)) {
            throw new \RuntimeException('Failed to generate silence segment: ' . trim($process->getErrorOutput()));
        }

        return $path;
    }

    protected function concatenateWavFiles(array $inputFiles, string $outputFile): bool
    {
        $listFile = tempnam(sys_get_temp_dir(), 'recorder-hotel-concat-');
        $lines = array_map(
            fn (string $path) => "file '" . str_replace("'", "'\\''", $path) . "'",
            $inputFiles
        );
        file_put_contents($listFile, implode(PHP_EOL, $lines) . PHP_EOL);

        $process = new Process([
            'ffmpeg',
            '-y',
            '-f',
            'concat',
            '-safe',
            '0',
            '-i',
            $listFile,
            '-c:a',
            'pcm_s16le',
            '-ar',
            '16000',
            '-ac',
            '2',
            $outputFile,
        ]);
        $process->setTimeout(300);
        $process->run();
        @unlink($listFile);

        if (! $process->isSuccessful() || ! is_file($outputFile)) {
            $this->error('ffmpeg wav concat failed: ' . trim($process->getErrorOutput()));

            return false;
        }

        return true;
    }

    protected function panMonoToStereoChannel(string $inputPath, string $outputPath, string $channel): bool
    {
        $inputs = $channel === 'right'
            ? ['-f', 'lavfi', '-i', 'anullsrc=r=16000:cl=mono', '-i', $inputPath]
            : ['-i', $inputPath, '-f', 'lavfi', '-i', 'anullsrc=r=16000:cl=mono'];

        $filter = $channel === 'right'
            ? '[1:a]aresample=16000,aformat=channel_layouts=mono[speech];[0:a][speech]amerge=inputs=2'
            : '[0:a]aresample=16000,aformat=channel_layouts=mono[speech];[speech][1:a]amerge=inputs=2';

        $process = new Process(array_merge(
            ['ffmpeg', '-y'],
            $inputs,
            ['-filter_complex', $filter, $outputPath]
        ));
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($outputPath)) {
            $this->error('ffmpeg channel pan failed: ' . trim($process->getErrorOutput()));

            return false;
        }

        return true;
    }

    protected function convertWavToMp3(string $sourcePath, string $targetPath): bool
    {
        $process = new Process([
            'ffmpeg',
            '-y',
            '-i',
            $sourcePath,
            '-ac',
            '2',
            '-ar',
            '16000',
            '-codec:a',
            'libmp3lame',
            '-qscale:a',
            '4',
            $targetPath,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($targetPath)) {
            $this->error('ffmpeg mp3 conversion failed: ' . trim($process->getErrorOutput()));

            return false;
        }

        return true;
    }

    protected function resolveDomain(string $value): ?Domain
    {
        if (Str::isUuid($value)) {
            return Domain::query()->where('domain_uuid', $value)->first();
        }

        return Domain::query()
            ->where('domain_name', $value)
            ->orWhere('domain_description', $value)
            ->first();
    }

    protected function binaryExists(string $binary): bool
    {
        $process = new Process(['which', $binary]);
        $process->run();

        return $process->isSuccessful();
    }
}
