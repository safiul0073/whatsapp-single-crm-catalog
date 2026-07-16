<?php

namespace App\Modules\Campaigns\Providers;

use App\Modules\Campaigns\Services\AudienceResolver;
use App\Modules\Campaigns\Services\CampaignDoctorService;
use App\Modules\Campaigns\Services\CampaignRecipientService;
use App\Modules\Campaigns\Services\CampaignReportService;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\Campaigns\Services\SegmentQueryService;
use App\Modules\Campaigns\Services\TemplateVariableMapper;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class CampaignsServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(TemplateVariableMapper::class);
        $this->app->singleton(CampaignRecipientService::class);
        $this->app->singleton(CampaignReportService::class);
        $this->app->singleton(CampaignDoctorService::class);
        $this->app->singleton(SegmentQueryService::class);
        $this->app->singleton(AudienceResolver::class);
        $this->app->singleton(CampaignService::class);
    }
}
