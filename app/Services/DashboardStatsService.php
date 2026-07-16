<?php

namespace App\Services;

use App\Models\User;
use App\Modules\Automations\Models\Automation;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\Leads\Models\Lead;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Support\Facades\Schema;

class DashboardStatsService
{
    public function __construct(
        protected WorkspaceResolver $workspaces
    ) {}

    public function getStats(User $user, string $range): array
    {
        $workspace = $this->workspaces->current($user);
        $workspaceId = $workspace?->id;

        $whatsAppChannel = $workspace ? ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'whatsapp')
            ->latest()
            ->first() : null;

        $days = (int) filter_var($range, FILTER_SANITIZE_NUMBER_INT);
        $currentStart = now()->subDays($days);
        $currentEnd = now();
        $previousStart = now()->subDays($days * 2);
        $previousEnd = now()->subDays($days);

        $messagesSentCurrent = 0;
        $messagesSentPrevious = 0;
        if ($workspaceId) {
            $messagesSentCurrent = (int) Message::query()
                ->where('workspace_id', $workspaceId)
                ->where('direction', 'outbound')
                ->whereBetween('created_at', [$currentStart, $currentEnd])
                ->count();

            $messagesSentPrevious = (int) Message::query()
                ->where('workspace_id', $workspaceId)
                ->where('direction', 'outbound')
                ->whereBetween('created_at', [$previousStart, $previousEnd])
                ->count();
        }
        $messagesSentTrend = $this->calculateTrend($messagesSentCurrent, $messagesSentPrevious);

        $messagesReceivedCurrent = 0;
        $messagesReceivedPrevious = 0;
        if ($workspaceId) {
            $messagesReceivedCurrent = (int) Message::query()
                ->where('workspace_id', $workspaceId)
                ->where('direction', 'inbound')
                ->whereBetween('created_at', [$currentStart, $currentEnd])
                ->count();

            $messagesReceivedPrevious = (int) Message::query()
                ->where('workspace_id', $workspaceId)
                ->where('direction', 'inbound')
                ->whereBetween('created_at', [$previousStart, $previousEnd])
                ->count();
        }
        $messagesReceivedTrend = $this->calculateTrend($messagesReceivedCurrent, $messagesReceivedPrevious);

        $openConversations = 0;
        if ($workspaceId) {
            $openConversations = (int) Conversation::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', 'open')
                ->count();
        }

        $newConversationsCurrent = 0;
        $newConversationsPrevious = 0;
        if ($workspaceId) {
            $newConversationsCurrent = (int) Conversation::query()
                ->where('workspace_id', $workspaceId)
                ->whereBetween('created_at', [$currentStart, $currentEnd])
                ->count();

            $newConversationsPrevious = (int) Conversation::query()
                ->where('workspace_id', $workspaceId)
                ->whereBetween('created_at', [$previousStart, $previousEnd])
                ->count();
        }
        $newConversationsTrend = $this->calculateTrend($newConversationsCurrent, $newConversationsPrevious);

        $contactsTotal = 0;
        $contactsNew = 0;
        if ($workspaceId) {
            $contactsTotal = (int) Contact::query()
                ->where('workspace_id', $workspaceId)
                ->count();

            $contactsNew = (int) Contact::query()
                ->where('workspace_id', $workspaceId)
                ->whereBetween('created_at', [$currentStart, $currentEnd])
                ->count();
        }

        $campaignsTotal = 0;
        $campaignsScheduled = 0;
        if ($workspaceId) {
            $campaignsTotal = (int) Campaign::query()
                ->where('workspace_id', $workspaceId)
                ->count();

            $campaignsScheduled = (int) Campaign::query()
                ->where('workspace_id', $workspaceId)
                ->where('status', 'scheduled')
                ->count();
        }

        $activeAutomations = 0;
        if ($workspaceId) {
            $activeAutomations = (int) Automation::query()
                ->where('workspace_id', $workspaceId)
                ->where('is_active', true)
                ->count();
        }

        $aiRunsCurrent = 0;
        $aiRunsPrevious = 0;
        if ($workspaceId) {
            $aiRunsCurrent = (int) Message::query()
                ->where('workspace_id', $workspaceId)
                ->whereBetween('created_at', [$currentStart, $currentEnd])
                ->where(function ($query) {
                    $query->whereNotNull('payload->chatbot_id')
                        ->orWhereNotNull('payload->chatbot_reply');
                })
                ->count();

            $aiRunsPrevious = (int) Message::query()
                ->where('workspace_id', $workspaceId)
                ->whereBetween('created_at', [$previousStart, $previousEnd])
                ->where(function ($query) {
                    $query->whereNotNull('payload->chatbot_id')
                        ->orWhereNotNull('payload->chatbot_reply');
                })
                ->count();
        }
        $aiRunsTrend = $this->calculateTrend($aiRunsCurrent, $aiRunsPrevious);

        $kpis = [
            [
                'label' => __('Messages sent'),
                'value' => (string) $messagesSentCurrent,
                'meta' => $messagesSentTrend['meta'],
                'metaClass' => $messagesSentTrend['class'],
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13" />',
            ],
            [
                'label' => __('Messages received'),
                'value' => (string) $messagesReceivedCurrent,
                'meta' => $messagesReceivedTrend['meta'],
                'metaClass' => $messagesReceivedTrend['class'],
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />',
            ],
            [
                'label' => __('Open conversations'),
                'value' => (string) $openConversations,
                'meta' => __('Awaiting a reply'),
                'metaClass' => 'text-body',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-5 4V7z" />',
            ],
            [
                'label' => __('New conversations'),
                'value' => (string) $newConversationsCurrent,
                'meta' => $newConversationsTrend['meta'],
                'metaClass' => $newConversationsTrend['class'],
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v8m4-4H8M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />',
            ],
            [
                'label' => __('Contacts'),
                'value' => (string) $contactsTotal,
                'meta' => __('+ :count this period', ['count' => $contactsNew]),
                'metaClass' => 'text-body',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0 0-8 4 4 0 0 0 0 8z" />',
            ],
            [
                'label' => __('Campaigns'),
                'value' => (string) $campaignsTotal,
                'meta' => __(':count scheduled', ['count' => $campaignsScheduled]),
                'metaClass' => 'text-body',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="m3 11 18-5v12L3 13v-2zM11.6 16.8a3 3 0 1 1-5.8-1.6" />',
            ],
            [
                'label' => __('Active automations'),
                'value' => (string) $activeAutomations,
                'meta' => __('Running now'),
                'metaClass' => 'text-body',
                'svg' => '<circle cx="12" cy="12" r="3" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1M3 12h3m12 0h3" />',
            ],
            [
                'label' => __('AI runs'),
                'value' => (string) $aiRunsCurrent,
                'meta' => __(':cost cost', ['cost' => '$'.number_format($aiRunsCurrent * 0.024375, 2)]),
                'metaClass' => 'text-body',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2 2-5z" />',
            ],
        ];

        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates[] = now()->subDays($i);
        }

        $messagesByDay = collect();
        $conversationsByDay = collect();
        $aiRunsByDay = collect();

        if ($workspaceId) {
            $messagesByDay = Message::query()
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('workspace_id', $workspaceId)
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('date')
                ->pluck('count', 'date');

            $conversationsByDay = Conversation::query()
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('workspace_id', $workspaceId)
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('date')
                ->pluck('count', 'date');

            $aiRunsByDay = Message::query()
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('workspace_id', $workspaceId)
                ->where('created_at', '>=', now()->subDays($days))
                ->where(function ($query) {
                    $query->whereNotNull('payload->chatbot_id')
                        ->orWhereNotNull('payload->chatbot_reply');
                })
                ->groupBy('date')
                ->pluck('count', 'date');
        }

        $messageValues = [];
        $conversationValues = [];
        $aiTokenValues = [];
        $labels = [];

        foreach ($dates as $date) {
            $dateStr = $date->toDateString();
            $labels[] = $date->format('m-d');
            $messageValues[] = $messagesByDay->get($dateStr, 0);
            $conversationValues[] = $conversationsByDay->get($dateStr, 0);
            $aiTokenValues[] = $aiRunsByDay->get($dateStr, 0) * 560;
        }

        $chartLabels = implode(',', $labels);
        $charts = [
            [__('Messages by day'), 'bar', 'primary', implode(',', $messageValues)],
            [__('Conversations'), 'line', 'deep', implode(',', $conversationValues)],
            [__('AI tokens'), 'bar', 'accent', implode(',', $aiTokenValues)],
        ];

        $recentConversations = [];
        if ($workspaceId && Schema::hasTable('conversations')) {
            $recentConversations = Conversation::query()
                ->where('workspace_id', $workspaceId)
                ->with(['contact', 'messages' => fn ($query) => $query->latest()->limit(1)])
                ->latest('updated_at')
                ->limit(4)
                ->get()
                ->map(function ($conv) {
                    $name = $conv->contact?->name ?? $conv->contact?->phone ?? __('Contact');
                    $initials = collect(explode(' ', $name))
                        ->map(fn ($n) => mb_substr($n, 0, 1))
                        ->take(2)
                        ->join('');

                    $status = $conv->status?->value ?? 'open';

                    $statusClass = match ($status) {
                        'open' => 'badge badge-soft shrink-0',
                        'closed', 'resolved' => 'shrink-0 rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-500',
                        'pending' => 'shrink-0 rounded-full bg-warning/15 px-2.5 py-0.5 text-xs font-semibold text-warning',
                        default => 'badge badge-soft shrink-0',
                    };

                    return [
                        'initials' => strtoupper($initials ?: 'C'),
                        'name' => $name,
                        'message' => $conv->messages->first()?->body ?? __('No messages yet'),
                        'status' => __(ucfirst(str_replace('_', ' ', $status))),
                        'statusClass' => $statusClass,
                    ];
                })
                ->toArray();
        }

        $stats = [
            'contacts' => $contactsTotal,
            'campaigns' => $campaignsTotal,
            'open_conversations' => $openConversations,
            'leads' => $workspaceId && Schema::hasTable('leads') ? Lead::query()->where('workspace_id', $workspaceId)->count() : 0,
            'whatsapp_channel_accounts' => $whatsAppChannel ? 1 : 0,
        ];

        return [
            'stats' => $stats,
            'whatsAppChannel' => $whatsAppChannel,
            'kpis' => $kpis,
            'planUsage' => [],
            'currentPlan' => null,
            'renewalText' => null,
            'charts' => $charts,
            'chartLabels' => $chartLabels,
            'recentConversations' => $recentConversations,
        ];
    }

    protected function calculateTrend(int $current, int $previous): array
    {
        if ($previous === 0) {
            $percent = $current > 0 ? 100 : 0;
        } else {
            $percent = (($current - $previous) / $previous) * 100;
        }

        $direction = $percent >= 0 ? '↑' : '↓';
        $percentStr = number_format(abs($percent), 1).'%';

        return [
            'meta' => __(':direction :percent vs last period', [
                'direction' => $direction,
                'percent' => $percentStr,
            ]),
            'class' => $percent >= 0 ? 'text-success' : 'text-error',
        ];
    }

    protected function formatPlanUsage(int $used, int $limit): string
    {
        return $this->formatLimitNumber($used).' / '.$this->formatLimitNumber($limit);
    }

    protected function formatLimitNumber(int $num): string
    {
        if ($num >= 1000000) {
            $val = round($num / 1000000, 1);

            return ((int) $val == $val ? (int) $val : $val).'M';
        }
        if ($num >= 1000) {
            $val = round($num / 1000, 1);

            return ((int) $val == $val ? (int) $val : $val).'k';
        }

        return (string) $num;
    }
}
