<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\KnowledgeBases\Jobs\IndexKnowledgeBaseSourceJob;
use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Builds the "Store Help" knowledge base, the "Store Assistant" chatbot and its
 * website widget for a workspace. Safe to run repeatedly: every record is found
 * by a stable key and sources are only re-indexed when their text changed.
 */
class DefaultKnowledgeBaseService
{
    public const MANAGED = 'store_defaults';

    public const KB_NAME = 'Store Help';

    public const BOT_NAME = 'Store Assistant';

    public function provision(Workspace $workspace): KnowledgeBase
    {
        $knowledgeBase = $this->knowledgeBase($workspace);
        $this->syncSources($workspace, $knowledgeBase);
        $this->chatbot($workspace, $knowledgeBase);

        return $knowledgeBase->fresh();
    }

    /** Re-reads store data into the managed sources (the "Refresh" button). */
    public function refresh(Workspace $workspace): KnowledgeBase
    {
        $knowledgeBase = $this->knowledgeBase($workspace);
        $this->syncSources($workspace, $knowledgeBase);

        return $knowledgeBase->fresh();
    }

    public function find(int $workspaceId): ?KnowledgeBase
    {
        return KnowledgeBase::query()
            ->where('workspace_id', $workspaceId)
            ->where('settings->managed', self::MANAGED)
            ->first();
    }

    protected function knowledgeBase(Workspace $workspace): KnowledgeBase
    {
        return $this->find($workspace->id) ?? KnowledgeBase::query()->create([
            'workspace_id' => $workspace->id,
            'name' => self::KB_NAME,
            'description' => 'Answers about your store, shipping, payment and common questions. Kept up to date automatically.',
            'status' => 'ready',
            'visibility' => 'workspace',
            'settings' => ['managed' => self::MANAGED],
        ]);
    }

    protected function syncSources(Workspace $workspace, KnowledgeBase $knowledgeBase): void
    {
        $sections = [
            'store_info' => ['Store information', $this->storeInfo($workspace)],
            'shipping' => ['Shipping and delivery', $this->shipping($workspace)],
            'payment' => ['Payment methods', $this->payment($workspace)],
            'faqs' => ['Frequently asked questions', $this->faqs($workspace)],
        ];

        foreach ($sections as $key => [$title, $content]) {
            $source = $knowledgeBase->sources()->where('metadata->managed_key', $key)->first();

            if ($source && $source->content === $content) {
                continue;
            }

            if ($source) {
                $source->update(['title' => $title, 'content' => $content, 'status' => 'pending']);
            } else {
                $source = $knowledgeBase->sources()->create([
                    'type' => 'text',
                    'title' => $title,
                    'content' => $content,
                    'status' => 'pending',
                    'metadata' => ['managed' => true, 'managed_key' => $key],
                ]);
            }

            IndexKnowledgeBaseSourceJob::dispatch($source->id)->afterCommit();
        }

        app(KnowledgeBaseService::class)->refreshStats($knowledgeBase);
    }

    protected function chatbot(Workspace $workspace, KnowledgeBase $knowledgeBase): void
    {
        $chatbot = Chatbot::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => self::BOT_NAME],
            [
                'persona' => $this->persona($workspace),
                'greeting' => "Hi! Welcome to {$workspace->name}. How can I help you today?",
                'temperature' => 0.4,
                'max_tokens' => 512,
                'fallback_only_knowledge_base' => true,
                'confidence_threshold' => 0.7,
                'handoff_rules' => [
                    'message' => 'I am not sure about that. A team member will get back to you soon.',
                ],
                'is_active' => true,
            ],
        );

        $chatbot->knowledgeBases()->syncWithoutDetaching([$knowledgeBase->id]);

        ChatbotWidget::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'chatbot_id' => $chatbot->id],
            [
                'name' => 'Website chat',
                'public_token' => Str::random(48),
                'is_active' => true,
                'allowed_domains' => $this->domains($workspace),
                'lead_fields' => [],
                'greeting' => $chatbot->greeting,
                'settings' => [
                    'primary_color' => '#16a34a',
                    'position' => 'right',
                    'launcher_label' => 'Chat',
                    'automated_reply_enabled' => true,
                ],
            ],
        );
    }

    protected function persona(Workspace $workspace): string
    {
        return "You are the friendly shop assistant for {$workspace->name}. Help customers with questions about products, shipping, delivery, payment, orders and returns. "
            .'Answer only from the store knowledge provided. Keep replies short, clear and polite. Use the product search tool when a customer asks about products. '
            .'If you do not know the answer, say so and offer to connect them with the team. Never invent prices, policies or delivery times.';
    }

    /** @return list<string> */
    protected function domains(Workspace $workspace): array
    {
        if (! Schema::hasTable('commerce_store_domains')) {
            return [];
        }

        return StoreDomain::query()->where('workspace_id', $workspace->id)->pluck('host')->all();
    }

    protected function storeInfo(Workspace $workspace): string
    {
        $config = Schema::hasTable('commerce_storefront_settings')
            ? (StorefrontSetting::query()->where('workspace_id', $workspace->id)->value('config') ?? [])
            : [];
        $config = is_string($config) ? (json_decode($config, true) ?: []) : (array) $config;

        $name = $config['name'] ?? $workspace->name;
        $lines = ["The store is called {$name}."];

        if ($host = $this->domains($workspace)[0] ?? null) {
            $lines[] = "The online store is at https://{$host}. Customers can track an order at https://{$host}/track-order.";
        }
        if (filled($config['contact_email'] ?? null)) {
            $lines[] = "Contact email: {$config['contact_email']}.";
        }
        if (filled($config['contact_phone'] ?? null)) {
            $lines[] = "Contact phone: {$config['contact_phone']}.";
        }
        if (filled(data_get($config, 'chat_channels.whatsapp.phone'))) {
            $lines[] = 'WhatsApp: '.data_get($config, 'chat_channels.whatsapp.phone').'.';
        }

        if (Schema::hasTable('commerce_store_order_settings')) {
            $settings = StoreOrderSetting::forWorkspace($workspace->id);
            $lines[] = 'Order numbers look like '.$settings->formatOrderNumber(1).'. Customers can find theirs in the order confirmation.';
            $lines[] = "Prices are shown in {$settings->currency}.";
        }

        return implode("\n\n", $lines);
    }

    protected function shipping(Workspace $workspace): string
    {
        if (! Schema::hasTable('shipping_methods')) {
            return $this->fallbackShipping();
        }

        $methods = ShippingMethod::query()->where('workspace_id', $workspace->id)->where('is_active', true)->orderBy('name')->get();
        $zones = ShippingZone::query()->where('workspace_id', $workspace->id)->where('is_active', true)->with('countries')->orderBy('name')->get();

        if ($methods->isEmpty() && $zones->isEmpty()) {
            return $this->fallbackShipping();
        }

        $lines = [];
        foreach ($methods as $method) {
            $days = $method->estimated_delivery_min_days || $method->estimated_delivery_max_days
                ? ' Estimated delivery: '.collect([$method->estimated_delivery_min_days, $method->estimated_delivery_max_days])->filter()->unique()->implode(' to ').' days.'
                : '';
            $carrier = filled($method->carrier) ? " via {$method->carrier}" : '';
            $lines[] = "Shipping method {$method->name}{$carrier}.{$days} ".trim((string) $method->description);
        }
        foreach ($zones as $zone) {
            $countries = $zone->countries->map(fn ($country) => $country->country_code ?? $country->code ?? null)->filter()->implode(', ');
            $lines[] = "We ship to the {$zone->name} zone".($countries !== '' ? " ({$countries})" : '').'.';
        }
        $lines[] = 'Shipping cost depends on the weight of the order and the destination. The exact cost is shown at checkout.';

        return implode("\n\n", array_map('trim', $lines));
    }

    protected function fallbackShipping(): string
    {
        return "Shipping costs and delivery times depend on your country and the weight of your order.\n\nThe exact shipping cost is shown at checkout before you pay.";
    }

    protected function payment(Workspace $workspace): string
    {
        if (! Schema::hasTable('commerce_store_order_settings')) {
            return $this->fallbackPayment();
        }

        $lines = [];
        // Only the name and how-to-pay steps: recipient_details holds bank/wallet numbers.
        foreach (StoreOrderSetting::paymentMethods($workspace->id, true) as $method) {
            $steps = trim((string) ($method['instructions'] ?? ''));
            $lines[] = "You can pay with {$method['name']}.".($steps !== '' ? " How to pay:\n{$steps}" : '');
        }

        if ($lines === []) {
            return $this->fallbackPayment();
        }

        $settings = StoreOrderSetting::forWorkspace($workspace->id);
        if (filled($settings->payment_instructions)) {
            $lines[] = trim((string) $settings->payment_instructions);
        }
        if ($settings->reservation_hours) {
            $lines[] = "Your order is reserved for {$settings->reservation_hours} hours while we wait for payment.";
        }
        $lines[] = 'The payment details are shown at checkout after you place your order.';

        return implode("\n\n", $lines);
    }

    protected function fallbackPayment(): string
    {
        return 'Payment options are shown at checkout. After placing your order you will see how to pay and where to upload your payment proof.';
    }

    protected function faqs(Workspace $workspace): string
    {
        $pairs = [];

        if (Schema::hasTable('commerce_storefront_contents')) {
            $pairs = StorefrontContent::query()
                ->where('workspace_id', $workspace->id)
                ->where('type', 'faq')
                ->whereNotNull('published_at')
                ->get()
                ->map(fn (StorefrontContent $faq): array => [
                    trim(strip_tags((string) data_get($faq->data, 'question'))),
                    trim(html_entity_decode(strip_tags((string) data_get($faq->data, 'answer')))),
                ])
                ->filter(fn (array $pair): bool => $pair[0] !== '' && $pair[1] !== '')
                ->all();
        }

        if ($pairs === []) {
            $pairs = [
                ['How do I track my order?', 'Open the Track Order page and enter your order number and phone or email.'],
                ['How long does delivery take?', 'Delivery time depends on your country and the shipping method you choose at checkout.'],
                ['What is your return policy?', 'If something is wrong with your order, contact us as soon as you receive it and we will help.'],
                ['Which payment methods do you accept?', 'The available payment methods are shown at checkout.'],
                ['Do you offer wholesale or bulk pricing?', 'Yes. Wholesale prices and packs are shown on product pages when quantity discounts apply.'],
            ];
        }

        return implode("\n\n", array_map(fn (array $pair): string => "Q: {$pair[0]}\nA: {$pair[1]}", $pairs));
    }
}
