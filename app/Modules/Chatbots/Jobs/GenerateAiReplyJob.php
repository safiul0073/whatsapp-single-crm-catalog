<?php

namespace App\Modules\Chatbots\Jobs;

use App\Modules\Chatbots\Services\WhatsAppAiReplyService;
use App\Modules\Inbox\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateAiReplyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $messageId) {}

    public function handle(WhatsAppAiReplyService $service): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message) {
            $service->reply($message);
        }
    }
}
