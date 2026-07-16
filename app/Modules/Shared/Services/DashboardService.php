<?php

namespace App\Modules\Shared\Services;

use App\Models\Admin;
use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Automations\Models\Automation;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Chatbots\Models\ChatbotWidgetSession;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\LoginActivity\Models\LoginActivity;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\Shared\Contracts\DashboardWidget;
use App\Modules\Workspaces\Models\Workspace;
use App\Services\WidgetRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getAdminStats(): array
    {
        return $this->getAdminOverviewStats();
    }

    public function getAdminOverviewStats(): array
    {
        return [
            'main_users' => User::whereIn('id', Workspace::query()->select('owner_id')->whereNotNull('owner_id'))->count(),
            'active_main_users' => User::where('is_active', true)
                ->whereIn('id', Workspace::query()->select('owner_id')->whereNotNull('owner_id'))
                ->count(),
            'total_users' => User::count(),
            'messages_last_30_days' => Message::where('created_at', '>=', now()->subDays(30))->count(),
            'active_automations' => Automation::where('is_active', true)->count(),
            'connected_channels' => ChannelAccount::where('status', ChannelAccountStatus::Connected->value)->count(),
            'total_widgets' => ChatbotWidget::count(),
            'total_templates' => MessageTemplate::count(),
            'total_contacts' => Contact::count(),
            'total_campaigns' => Campaign::count(),
        ];
    }

    public function getPlatformActivityChartData(int $days = 14): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $categories = [];
        $users = [];
        $messages = [];
        $conversations = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $startDate->copy()->addDays($day);

            $categories[] = $date->format('M j');
            $users[] = User::whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])->count();
            $messages[] = Message::whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])->count();
            $conversations[] = Conversation::whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])->count();
        }

        return [
            'series' => [
                ['name' => __('Users'), 'data' => $users],
                ['name' => __('Messages'), 'data' => $messages],
                ['name' => __('Conversations'), 'data' => $conversations],
            ],
            'categories' => $categories,
        ];
    }

    public function getRevenueChartData(int $days = 14): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $categories = [];
        $revenue = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $startDate->copy()->addDays($day);

            $categories[] = $date->format('M j');
            $revenue[] = (float) Payment::completed()
                ->whereBetween('paid_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                ->sum('amount');
        }

        return [
            'series' => [
                ['name' => __('Revenue'), 'data' => $revenue],
            ],
            'categories' => $categories,
        ];
    }

    public function getDailyMessagesByChannelChartData(int $days = 14): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $channels = $this->messageChannels($startDate);
        $categories = [];
        $series = $channels
            ->mapWithKeys(fn (ChannelAccount $channel): array => [
                $channel->id => ['name' => $channel->name, 'data' => []],
            ])
            ->all();

        for ($day = 0; $day < $days; $day++) {
            $date = $startDate->copy()->addDays($day);
            $categories[] = $date->format('M j');

            foreach ($channels as $channel) {
                $series[$channel->id]['data'][] = Message::where('channel_account_id', $channel->id)
                    ->where('provider', '!=', 'website_widget')
                    ->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                    ->count();
            }
        }

        return [
            'series' => array_values($series),
            'categories' => $categories,
        ];
    }

    public function getMonthlyMessagesByChannelChartData(int $months = 12): array
    {
        $startDate = now()->subMonths($months - 1)->startOfMonth();
        $channels = $this->messageChannels($startDate);
        $categories = [];
        $series = $channels
            ->mapWithKeys(fn (ChannelAccount $channel): array => [
                $channel->id => ['name' => $channel->name, 'data' => []],
            ])
            ->all();

        for ($month = 0; $month < $months; $month++) {
            $date = $startDate->copy()->addMonths($month);
            $categories[] = $date->format('M Y');

            foreach ($channels as $channel) {
                $series[$channel->id]['data'][] = Message::where('channel_account_id', $channel->id)
                    ->where('provider', '!=', 'website_widget')
                    ->whereBetween('created_at', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])
                    ->count();
            }
        }

        return [
            'series' => array_values($series),
            'categories' => $categories,
        ];
    }

    public function getDailyWidgetMessagesChartData(int $days = 14): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $categories = [];
        $messages = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $startDate->copy()->addDays($day);

            $categories[] = $date->format('M j');
            $messages[] = $this->widgetMessageQuery()
                ->whereBetween('messages.created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                ->count();
        }

        return [
            'series' => [
                ['name' => __('Messages'), 'data' => $messages],
            ],
            'categories' => $categories,
        ];
    }

    public function getMonthlyWidgetMessagesChartData(int $months = 12): array
    {
        $startDate = now()->subMonths($months - 1)->startOfMonth();
        $categories = [];
        $messages = [];

        for ($month = 0; $month < $months; $month++) {
            $date = $startDate->copy()->addMonths($month);

            $categories[] = $date->format('M Y');
            $messages[] = $this->widgetMessageQuery()
                ->whereBetween('messages.created_at', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])
                ->count();
        }

        return [
            'series' => [
                ['name' => __('Messages'), 'data' => $messages],
            ],
            'categories' => $categories,
        ];
    }

    public function getPlanSubscriptionChartData(): array
    {
        $subscriptions = Plan::query()
            ->select('plans.name')
            ->selectRaw('count(subscriptions.id) as subscription_count')
            ->leftJoin('subscriptions', function ($join): void {
                $join->on('plans.id', '=', 'subscriptions.plan_id')
                    ->whereIn('subscriptions.status', [
                        SubscriptionStatus::Active->value,
                        SubscriptionStatus::Trialing->value,
                    ]);
            })
            ->groupBy('plans.id', 'plans.name')
            ->orderBy('plans.sort_order')
            ->pluck('subscription_count', 'plans.name')
            ->toArray();

        return [
            'series' => array_values($subscriptions),
            'labels' => array_keys($subscriptions),
        ];
    }

    public function getChannelUsageChartData(): array
    {
        $usage = Message::query()
            ->select('channel_account_id')
            ->selectRaw('count(*) as message_count')
            ->whereNotNull('channel_account_id')
            ->where('provider', '!=', 'website_widget')
            ->groupBy('channel_account_id')
            ->orderByDesc('message_count')
            ->pluck('message_count', 'channel_account_id');

        $channels = ChannelAccount::query()
            ->whereIn('id', $usage->keys())
            ->get()
            ->sortBy(fn (ChannelAccount $channel): int => -1 * (int) $usage->get($channel->id))
            ->values();

        return [
            'series' => $channels
                ->map(fn (ChannelAccount $channel): int => (int) $usage->get($channel->id))
                ->all(),
            'labels' => $channels
                ->map(fn (ChannelAccount $channel): string => $channel->name)
                ->all(),
        ];
    }

    public function getUserLoginActivityChartData(int $days = 14): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $categories = [];
        $events = [
            'login' => __('Logins'),
            'failed' => __('Failed'),
            'lockout' => __('Lockouts'),
        ];
        $series = collect($events)
            ->map(fn (string $label, string $event): array => ['name' => $label, 'data' => []])
            ->all();

        for ($day = 0; $day < $days; $day++) {
            $date = $startDate->copy()->addDays($day);
            $categories[] = $date->format('M j');

            foreach (array_keys($events) as $event) {
                $series[$event]['data'][] = LoginActivity::query()
                    ->where('user_type', User::class)
                    ->where('event', $event)
                    ->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                    ->count();
            }
        }

        return [
            'series' => array_values($series),
            'categories' => $categories,
        ];
    }

    public function getRecentPayments(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getRecentChannels(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return ChannelAccount::with('workspace')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getUserRoleDistribution(): array
    {
        return Admin::select('roles.name', DB::raw('count(*) as count'))
            ->join('model_has_roles', function ($join) {
                $join->on('admins.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', Admin::class);
            })
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->groupBy('roles.name')
            ->pluck('count', 'name')
            ->toArray();
    }

    public function getRecentActivity(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getUnifiedRecentActivity(int $limit = 10): Collection
    {
        $auditActivity = AuditLog::with('user')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $activity): array => [
                'type' => 'audit',
                'label' => $this->formatAuditAction($activity->action),
                'description' => $activity->user?->name ?? class_basename($activity->auditable_type),
                'icon' => $this->auditActivityIcon($activity->action),
                'color' => $this->auditActivityColor($activity->action),
                'created_at' => $activity->created_at,
            ]);

        $loginActivity = LoginActivity::with('user')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LoginActivity $activity): array => [
                'type' => 'login',
                'label' => $this->formatLoginEvent($activity->event),
                'description' => $activity->user?->name
                    ?? $activity->user?->email
                    ?? ($activity->metadata['email'] ?? $activity->ip_address ?? __('Unknown actor')),
                'icon' => $this->loginActivityIcon($activity->event),
                'color' => $this->loginActivityColor($activity->event),
                'created_at' => $activity->created_at,
            ]);

        return $auditActivity
            ->concat($loginActivity)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();
    }

    public function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'database' => config('database.default'),
        ];
    }

    public function getRecentUsers(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return Admin::with('roles')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get rendered widgets for a specific panel.
     *
     * @return Collection<int, DashboardWidget>
     */
    public function getWidgetsForPanel(string $panel, mixed $user = null): Collection
    {
        return app(WidgetRegistry::class)->getForPanel($panel, $user);
    }

    protected function formatAuditAction(string $action): string
    {
        return __(str($action)->replace(['_', '-'], ' ')->headline()->toString());
    }

    protected function formatLoginEvent(string $event): string
    {
        return __(str($event)->replace(['_', '-'], ' ')->headline()->toString());
    }

    protected function auditActivityIcon(string $action): string
    {
        return match ($action) {
            'created' => 'ph-plus-circle',
            'updated' => 'ph-pencil-simple',
            'deleted' => 'ph-trash',
            default => 'ph-clock-counter-clockwise',
        };
    }

    protected function auditActivityColor(string $action): string
    {
        return match ($action) {
            'created' => 'bg-success/10 text-success',
            'updated' => 'bg-info/10 text-info',
            'deleted' => 'bg-error/10 text-error',
            default => 'bg-primary/10 text-primary',
        };
    }

    protected function loginActivityIcon(string $event): string
    {
        return match ($event) {
            'login' => 'ph-sign-in',
            'logout' => 'ph-sign-out',
            'failed' => 'ph-x-circle',
            'lockout' => 'ph-lock',
            'impersonate_start' => 'ph-user-switch',
            'impersonate_stop' => 'ph-user-circle-minus',
            default => 'ph-activity',
        };
    }

    protected function loginActivityColor(string $event): string
    {
        return match ($event) {
            'login' => 'bg-success/10 text-success',
            'logout' => 'bg-info/10 text-info',
            'failed' => 'bg-error/10 text-error',
            'lockout', 'impersonate_start' => 'bg-warning/10 text-warning',
            'impersonate_stop' => 'bg-info/10 text-info',
            default => 'bg-neutral-100 text-neutral-500',
        };
    }

    protected function messageChannels(Carbon $startDate): Collection
    {
        $channelIds = Message::query()
            ->whereNotNull('channel_account_id')
            ->where('provider', '!=', 'website_widget')
            ->where('created_at', '>=', $startDate)
            ->distinct()
            ->pluck('channel_account_id');

        return ChannelAccount::query()
            ->whereIn('id', $channelIds)
            ->orderBy('name')
            ->get();
    }

    protected function widgetMessageQuery(): Builder
    {
        $conversationIds = ChatbotWidgetSession::query()
            ->whereNotNull('conversation_id')
            ->select('conversation_id');

        return Message::query()
            ->where('provider', 'website_widget')
            ->whereIn('conversation_id', $conversationIds);
    }
}
