<?php

namespace App\Modules\PlansSubscriptions\Console\Commands;

use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Services\SubscriptionManager;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Console\Command;

class BackfillFreeSubscriptions extends Command
{
    protected $signature = 'plans:backfill-free {--plan=free : Slug of the free plan to assign} {--dry-run : List the workspaces without changing anything}';

    protected $description = 'Give every workspace without a subscription the free plan, so enabling enforcement does not lock anyone out';

    public function handle(SubscriptionManager $subscriptions): int
    {
        $plan = Plan::query()->where('slug', $this->option('plan'))->first();

        if (! $plan || ! $plan->isFree()) {
            $this->error(__('Free plan [:slug] not found. Seed plans first (PlanSeeder).', ['slug' => $this->option('plan')]));

            return self::FAILURE;
        }

        $count = 0;

        Workspace::query()
            ->whereNotIn('id', Subscription::query()->select('workspace_id'))
            ->chunkById(100, function ($workspaces) use ($subscriptions, $plan, &$count): void {
                foreach ($workspaces as $workspace) {
                    $count++;

                    if ($this->option('dry-run')) {
                        $this->line("#{$workspace->id} {$workspace->name}");

                        continue;
                    }

                    $subscriptions->activate($workspace, $plan, null, null, SubscriptionStatus::Active);
                }
            });

        $this->info($this->option('dry-run')
            ? __(':count workspaces would get the :plan plan.', ['count' => $count, 'plan' => $plan->name])
            : __(':count workspaces now have the :plan plan.', ['count' => $count, 'plan' => $plan->name]));

        return self::SUCCESS;
    }
}
