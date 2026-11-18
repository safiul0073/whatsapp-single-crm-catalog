<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Contacts\Services\ContactService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class ShippingBaseController extends Controller
{
    protected function workspaceId(Request $request): int
    {
        return app(WorkspaceResolver::class)->current($request->user())->id;
    }

    /**
     * Records from another workspace behave as if they do not exist.
     */
    protected function assertWorkspace(Request $request, Model $record): void
    {
        abort_unless((int) $record->getAttribute('workspace_id') === $this->workspaceId($request), 404);
    }

    protected function storeCurrency(Request $request): string
    {
        return StoreOrderSetting::forWorkspace($this->workspaceId($request))->currency ?: 'USD';
    }

    /**
     * @return array<string, string>
     */
    protected function countries(): array
    {
        return array_map(fn (array $option): string => $option['name'], app(ContactService::class)->countryOptions());
    }
}
