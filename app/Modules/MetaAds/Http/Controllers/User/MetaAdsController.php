<?php

namespace App\Modules\MetaAds\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MetaAds\Http\Requests\MetaAdsReportRequest;
use App\Modules\MetaAds\Services\MetaAdsReportService;
use Illuminate\View\View;

class MetaAdsController extends Controller
{
    public function __construct(protected MetaAdsReportService $reports) {}

    public function index(MetaAdsReportRequest $request): View
    {
        return view('meta-ads::user.index', [
            'report' => $this->reports->report(auth()->user(), $request->period()),
            'periods' => array_keys(MetaAdsReportService::PERIODS),
        ]);
    }
}
