<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontPage;
use App\Modules\Commerce\Storefront\Models\StorefrontSection;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionStoreService
{
    public function provision(Workspace $workspace, ?string $host = null): StoreDomain
    {
        return DB::transaction(function () use ($workspace, $host): StoreDomain {
            Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $domain = StoreDomain::query()->where('workspace_id', $workspace->id)->where('type', 'system')->first();
            if ($domain && ! $host) {
                return $domain;
            }
            $handle = Str::lower(Str::random(10));
            $host = DomainResolver::normalize($host ?: $handle.'.'.config('storefront.root_domain'));
            abort_if(StoreDomain::query()->where('host', $host)->where('workspace_id', '!=', $workspace->id)->exists(), 422, 'Domain belongs to another store.');
            $domain = StoreDomain::query()->firstOrCreate(['host' => $host], ['workspace_id' => $workspace->id, 'handle' => $handle, 'type' => 'system', 'is_primary' => ! StoreDomain::query()->where('workspace_id', $workspace->id)->where('is_primary', true)->exists(), 'status' => 'active', 'verification_token' => Str::random(48), 'verified_at' => now()]);
            StorefrontSetting::query()->firstOrCreate(['workspace_id' => $workspace->id], ['config' => ['name' => $workspace->name, 'primary' => '#0866FF', 'hover' => '#0055D4', 'footer_color' => '#0A2540', 'password_login' => false]]);
            $page = StorefrontPage::query()->firstOrCreate(['workspace_id' => $workspace->id, 'slug' => 'home'], ['title' => 'Discover your next favorite', 'status' => 'published', 'published_at' => now()]);
            if (! StorefrontSection::query()->where('workspace_id', $workspace->id)->where('page_id', $page->id)->exists()) {
                $sections = [['type' => 'category_grid', 'config' => ['title' => 'Shop by category'], 'position' => 0], ['type' => 'product_carousel', 'config' => ['title' => 'New arrivals', 'source' => 'new-arrivals', 'limit' => 8], 'position' => 1], ['type' => 'newsletter', 'config' => ['title' => 'Stay in the know'], 'position' => 2]];
                foreach ($sections as $section) {
                    StorefrontSection::query()->create(['workspace_id' => $workspace->id, 'page_id' => $page->id, ...$section, 'active' => true]);
                }
                $page->update(['published_content' => ['title' => $page->title, 'seo' => [], 'sections' => StorefrontSection::query()->where('workspace_id', $workspace->id)->where('page_id', $page->id)->orderBy('position')->get()->toArray()]]);
            }

            return $domain;
        });
    }
}
