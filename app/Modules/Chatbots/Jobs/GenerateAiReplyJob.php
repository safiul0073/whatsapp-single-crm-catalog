<?php

namespace App\Modules\Chatbots\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateAiReplyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $conversationId, public string $message) {}

    public function handle(): void {}
}
