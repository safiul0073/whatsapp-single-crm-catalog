<?php

namespace App\Console\Commands;

use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\WhatsAppCloud\Services\InboundMessageEventApplier;
use Illuminate\Console\Command;

class ApplyWhatsAppEventMessagesCommand extends Command
{
    protected $signature = 'inbox:apply-whatsapp-events';

    protected $description = 'Apply stored WhatsApp edit, deletion and reaction events to their original messages and remove the event rows';

    public function handle(InboundMessageEventApplier $applier): int
    {
        $applied = 0;
        $kept = 0;

        Message::query()
            ->where('provider', 'whatsapp')
            ->where('direction', 'inbound')
            ->whereIn('type', InboundMessageEventApplier::EVENT_TYPES)
            ->orderBy('id')
            ->each(function (Message $event) use ($applier, &$applied, &$kept): void {
                $account = ChannelAccount::query()->find($event->channel_account_id);

                if ($account && $applier->apply($account, (array) $event->payload)) {
                    $event->delete();
                    $applied++;

                    return;
                }

                $kept++;
            });

        $this->info("Applied and removed {$applied} event rows; {$kept} had no original message and were left alone.");

        return self::SUCCESS;
    }
}
