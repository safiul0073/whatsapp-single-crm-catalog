<?php

use App\Modules\Commerce\Storefront\Services\UserAgentParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_storefront_visitors', function (Blueprint $table) {
            $table->string('region', 120)->nullable()->after('city');
            $table->string('os', 20)->nullable()->after('device');
            $table->string('browser', 20)->nullable()->after('os');
        });

        Schema::table('commerce_storefront_page_views', function (Blueprint $table) {
            $table->string('city', 120)->nullable()->after('country_code');
            $table->string('region', 120)->nullable()->after('city');
            $table->string('device', 20)->nullable()->after('region');
            $table->string('os', 20)->nullable()->after('device');
            $table->string('browser', 20)->nullable()->after('os');
            $table->index(['workspace_id', 'viewed_at', 'city'], 'commerce_sf_page_views_city_index');
        });

        $agents = new UserAgentParser;
        DB::table('commerce_storefront_visitors')->orderBy('id')->chunkById(500, function ($visitors) use ($agents): void {
            foreach ($visitors as $visitor) {
                $agent = (string) $visitor->user_agent;
                $profile = ['device' => $agents->device($agent), 'os' => $agents->os($agent), 'browser' => $agents->browser($agent)];
                DB::table('commerce_storefront_visitors')->where('id', $visitor->id)->update(['os' => $profile['os'], 'browser' => $profile['browser']]);
                DB::table('commerce_storefront_page_views')->where('visitor_id', $visitor->id)->update([
                    ...$profile,
                    'device' => $visitor->device ?? $profile['device'],
                    'city' => $visitor->city,
                    'region' => $visitor->region ?? null,
                ]);
                if ($visitor->country_code) {
                    DB::table('commerce_storefront_page_views')->where('visitor_id', $visitor->id)->whereNull('country_code')->update(['country_code' => $visitor->country_code]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('commerce_storefront_page_views', function (Blueprint $table) {
            $table->dropIndex('commerce_sf_page_views_city_index');
            $table->dropColumn(['city', 'region', 'device', 'os', 'browser']);
        });

        Schema::table('commerce_storefront_visitors', function (Blueprint $table) {
            $table->dropColumn(['region', 'os', 'browser']);
        });
    }
};
