<?php

namespace App\Modules\Commerce\Storefront\Console;

use App\Modules\Commerce\Storefront\Services\ProductRatingService;
use Illuminate\Console\Command;

class RefreshProductRatings extends Command
{
    protected $signature = 'commerce:refresh-ratings {--workspace=}';

    protected $description = 'Recalculate product rating and reviews_count from approved reviews';

    public function handle(ProductRatingService $ratings): int
    {
        $workspace = $this->option('workspace');
        $checked = $ratings->refreshAll($workspace !== null ? (int) $workspace : null);
        $this->info("Checked {$checked} products.");

        return self::SUCCESS;
    }
}
