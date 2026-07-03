<?php

namespace App\Jobs;

use App\Mail\DomainAiUsageLimitAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDomainAiUsageLimitAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $recipients,
        public array $data,
    ) {
    }

    public function handle(): void
    {
        foreach ($this->recipients as $email) {
            Mail::to($email)->send(new DomainAiUsageLimitAlert($this->data));
        }
    }
}
