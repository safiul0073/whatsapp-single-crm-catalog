<?php

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\Leads\Models\Lead;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('creates temporary dummy crm data idempotently for a selected workspace', function (): void {
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);

    $_SERVER['DUMMY_CRM_WORKSPACE_ID'] = (string) $workspace->id;
    $_SERVER['DUMMY_CRM_LEADS_COUNT'] = '50';

    ob_start();
    require base_path('database/temp_dummy_crm_data.php');
    $firstOutput = ob_get_clean();

    ob_start();
    require base_path('database/temp_dummy_crm_data.php');
    $secondOutput = ob_get_clean();

    unset($_SERVER['DUMMY_CRM_WORKSPACE_ID']);
    unset($_SERVER['DUMMY_CRM_LEADS_COUNT']);

    expect($firstOutput)->toContain('Dummy CRM data ready for workspace #'.$workspace->id)
        ->and($secondOutput)->toContain('Dummy CRM data ready for workspace #'.$workspace->id)
        ->and(ContactTag::query()->where('workspace_id', $workspace->id)->count())->toBe(5)
        ->and(ContactGroup::query()->where('workspace_id', $workspace->id)->count())->toBe(4)
        ->and(Contact::query()->where('workspace_id', $workspace->id)->count())->toBe(10)
        ->and(Lead::query()->where('workspace_id', $workspace->id)->count())->toBe(50)
        ->and(DB::table('contact_group_contact')->count())->toBe(10)
        ->and(DB::table('contact_tag_contact')->count())->toBe(16);

    $lead = Lead::query()
        ->where('workspace_id', $workspace->id)
        ->where('external_source', 'local_dummy_crm')
        ->firstOrFail();

    expect($lead->metadata['dummy_seed'])->toBeTrue()
        ->and($lead->criteria['dummy_seed'])->toBeTrue();

    expect(Lead::query()
        ->where('workspace_id', $workspace->id)
        ->where(function ($query): void {
            $query->where('name', 'like', 'Dummy Prospect%')
                ->orWhere('email', 'like', 'dummy.lead.%');
        })
        ->exists())->toBeFalse();
});
