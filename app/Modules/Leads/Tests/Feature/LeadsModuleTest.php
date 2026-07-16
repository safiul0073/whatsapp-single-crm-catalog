<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Models\User;
use App\Modules\AiSettings\Services\AiUsageLogger;
use App\Modules\Chatbots\Models\ChatbotAiProvider;
use App\Modules\Chatbots\Services\ChatbotAiProviderService;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Enums\ContactSource;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use App\Modules\Inbox\Models\Message;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Services\GooglePlacesLeadSourceService;
use App\Modules\Leads\Services\LeadGenerationService;
use App\Modules\Leads\Services\LeadService;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlaceApiSettings\Models\PlaceApiSetting;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(EnsureOnboardingComplete::class);
    config([
        'ai.default' => 'openai',
        'ai.providers.openai.key' => null,
    ]);
});

function leadsUserContext(array $permissions = ['leads.view', 'leads.manage', 'contacts.view', 'contacts.manage']): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return [$user, app(WorkspaceResolver::class)->current($user)];
}

function subscribeLeadWorkspace(int $workspaceId, array $limits): Subscription
{
    $plan = Plan::query()->create([
        'name' => 'Lead Plan',
        'slug' => 'lead-plan-'.uniqid(),
        'price' => 0,
        'interval' => 'month',
        'limits' => $limits,
        'features' => [],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    return Subscription::query()->create([
        'workspace_id' => $workspaceId,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
        'usage' => [],
    ]);
}

function configureGooglePlacesLeadSource(bool $enabled = true, ?string $apiKey = 'google-secret'): void
{
    PlaceApiSetting::query()->updateOrCreate(['key' => 'google_places_enabled'], ['value' => $enabled ? '1' : '0']);

    if ($apiKey !== null) {
        PlaceApiSetting::query()->updateOrCreate(['key' => 'google_places_api_key'], ['value' => Crypt::encryptString($apiKey)]);
    }

    PlaceApiSetting::query()->updateOrCreate(['key' => 'google_places_language'], ['value' => 'en']);
    PlaceApiSetting::query()->updateOrCreate(['key' => 'google_places_result_limit'], ['value' => '25']);
}

function fakeGooglePlacesResponse(array $places = []): void
{
    $places = $places ?: [
        [
            'id' => 'places/one',
            'displayName' => ['text' => 'Austin Cafe One'],
            'formattedAddress' => '100 Congress Ave, Austin, TX, USA',
            'internationalPhoneNumber' => '+1 415-555-2601',
            'websiteUri' => 'https://austin-cafe-one.example',
            'googleMapsUri' => 'https://maps.google.com/?cid=one',
            'rating' => 4.6,
            'userRatingCount' => 128,
            'businessStatus' => 'OPERATIONAL',
            'types' => ['restaurant'],
            'addressComponents' => [
                ['longText' => 'Austin', 'shortText' => 'Austin', 'types' => ['locality']],
                ['longText' => 'United States', 'shortText' => 'US', 'types' => ['country']],
            ],
        ],
        [
            'id' => 'places/two',
            'displayName' => ['text' => 'Austin Cafe Two'],
            'formattedAddress' => '200 Congress Ave, Austin, TX, USA',
            'websiteUri' => 'https://austin-cafe-two.example',
            'googleMapsUri' => 'https://maps.google.com/?cid=two',
            'rating' => 4.2,
            'userRatingCount' => 64,
            'businessStatus' => 'OPERATIONAL',
            'types' => ['restaurant'],
            'addressComponents' => [
                ['longText' => 'Austin', 'shortText' => 'Austin', 'types' => ['locality']],
                ['longText' => 'United States', 'shortText' => 'US', 'types' => ['country']],
            ],
        ],
        [
            'id' => 'places/three',
            'displayName' => ['text' => 'Austin Cafe Three'],
            'formattedAddress' => '300 Congress Ave, Austin, TX, USA',
            'internationalPhoneNumber' => '+1 415-555-2603',
            'googleMapsUri' => 'https://maps.google.com/?cid=three',
            'rating' => 3.9,
            'userRatingCount' => 20,
            'businessStatus' => 'OPERATIONAL',
            'types' => ['restaurant'],
            'addressComponents' => [
                ['longText' => 'Austin', 'shortText' => 'Austin', 'types' => ['locality']],
                ['longText' => 'United States', 'shortText' => 'US', 'types' => ['country']],
            ],
        ],
    ];

    Http::fake([
        'https://places.googleapis.com/v1/places:searchText' => Http::response(['places' => $places], 200),
    ]);
}

it('renders workspace scoped leads only', function (): void {
    [$user, $workspace] = leadsUserContext();
    [, $otherWorkspace] = leadsUserContext();

    $workspaceLead = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'North Cafe',
        'company' => 'North Cafe LLC',
        'email' => 'north@example.com',
        'country' => 'US',
        'category' => 'Restaurants',
        'source' => 'manual',
    ]);
    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'email',
        'name' => 'Email',
        'status' => ChannelAccountStatus::Connected,
        'provider_display_id' => 'sales@example.com',
        'credentials' => ['mail_mailer' => 'log'],
        'connected_at' => now(),
    ]);

    Lead::query()->create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Hidden Studio',
        'company' => 'Hidden Studio LLC',
        'country' => 'US',
        'category' => 'Fitness',
        'source' => 'manual',
    ]);

    $convertedLead = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Converted Studio',
        'phone' => '+14155559999',
        'source' => 'ai',
        'stage' => 'converted',
        'contact_status' => 'converted',
        'verification_status' => 'verified',
    ]);
    $uncontactableLead = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Missing Contact Route',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);

    $response = $this->actingAs($user)
        ->get(route('user.leads.index'))
        ->assertOk()
        ->assertSee('North Cafe')
        ->assertSee('Find leads')
        ->assertSee('Advanced')
        ->assertSee('leadAdvancedFilters', false)
        ->assertSee('name="stage[]"', false)
        ->assertSee('name="source[]"', false)
        ->assertSee('name="verification_status[]"', false)
        ->assertSee('data-placeholder="Stage"', false)
        ->assertSee('data-placeholder="Source"', false)
        ->assertSee('data-placeholder="Verification status"', false)
        ->assertSee('data-select-all="leads"', false)
        ->assertSee('data-bulk-bar="leads"', false)
        ->assertSee('data-selected-count="leads"', false)
        ->assertSee('Phone')
        ->assertSee('Email')
        ->assertSee('north@example.com')
        ->assertSee('data-modal-open="sendLead'.$workspaceLead->id.'"', false)
        ->assertSee('editLead'.$workspaceLead->id.'Stage', false)
        ->assertSee('editLead'.$workspaceLead->id.'Source', false)
        ->assertSee('form-select ts-basic', false)
        ->assertSee('id="bulkConvertGroups"', false)
        ->assertSee('id="bulkConvertTags"', false)
        ->assertSee(route('user.leads.bulk-delete'), false)
        ->assertSee('Convert selected leads into contacts, or remove them from the lead list.')
        ->assertSee('Delete')
        ->assertSee('bulkDeleteLeadsConfirm', false)
        ->assertSee('data-modal-open="bulkDeleteLeadsConfirm"', false)
        ->assertSee('deleteLeadConfirm'.$workspaceLead->id, false)
        ->assertSee('data-modal-open="deleteLeadConfirm'.$workspaceLead->id.'"', false)
        ->assertSee('form-select ts-multi', false)
        ->assertSee('Admin has not configured Google Places API settings.')
        ->assertDontSee('Hidden Studio');

    $content = $response->getContent();

    expect($content)->toContain('data-select-item="leads"')
        ->and($content)->toContain('value="'.$workspaceLead->id.'"')
        ->and($content)->not->toContain('value="'.$convertedLead->id.'" data-select-item="leads"')
        ->and($content)->not->toContain('value="'.$uncontactableLead->id.'" data-select-item="leads"');
});

it('filters leads by multiple selected stages and sources', function (): void {
    [$user, $workspace] = leadsUserContext();

    Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'New AI Lead',
        'phone' => '+14155552671',
        'stage' => 'new',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);

    Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Qualified Manual Lead',
        'email' => 'qualified@example.com',
        'stage' => 'qualified',
        'source' => 'manual',
        'verification_status' => 'manual',
    ]);

    Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Lost Website Lead',
        'phone' => '+14155552672',
        'stage' => 'lost',
        'source' => 'website',
        'verification_status' => 'verified',
    ]);

    $this->actingAs($user)
        ->get(route('user.leads.index', [
            'stage' => ['new', 'qualified'],
            'source' => ['ai', 'manual'],
            'verification_status' => ['unverified', 'manual'],
        ]))
        ->assertOk()
        ->assertSee('New AI Lead')
        ->assertSee('Qualified Manual Lead')
        ->assertDontSee('Lost Website Lead');
});

it('generates unverified google places leads without converting contacts', function (): void {
    [$user, $workspace] = leadsUserContext();
    configureGooglePlacesLeadSource();
    fakeGooglePlacesResponse();
    config(['ai.providers.openai.key' => 'sk-platform']);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.generate'), [
            'country' => 'US',
            'place' => 'Austin',
            'category' => 'restaurants',
            'audience' => 'Owners preparing seasonal campaigns',
            'channel' => 'whatsapp',
            'count' => 3,
            'notes' => 'Prioritize local businesses.',
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHas('status', '3 Google Places leads generated.');

    expect(Lead::query()->where('workspace_id', $workspace->id)->count())->toBe(3)
        ->and(Contact::query()->where('workspace_id', $workspace->id)->count())->toBe(0);

    $lead = Lead::query()->where('workspace_id', $workspace->id)->firstOrFail();

    expect($lead->source)->toBe('google_places')
        ->and($lead->verification_status)->toBe('unverified')
        ->and($lead->contact_id)->toBeNull()
        ->and(filled($lead->phone) || filled($lead->email))->toBeTrue()
        ->and($lead->criteria['country'])->toBe('US')
        ->and($lead->external_source)->toBe('google_places')
        ->and($lead->external_id)->not->toBeEmpty()
        ->and($lead->metadata['generated_by'])->toBe('platform_ai')
        ->and($lead->metadata['lead_source'])->toBe('google_places')
        ->and($lead->metadata['platform_ai_used'])->toBeFalse();
});

it('rejects lead generation when google places is not configured', function (): void {
    [$user] = leadsUserContext();

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.generate'), [
            'country' => 'US',
            'category' => 'restaurants',
            'count' => 2,
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHasErrors('place_api');
});

it('skips google places results without phone email or website contact route', function (): void {
    [$user, $workspace] = leadsUserContext();
    configureGooglePlacesLeadSource();
    fakeGooglePlacesResponse([
        [
            'id' => 'places/contactable',
            'displayName' => ['text' => 'Contactable Cafe'],
            'formattedAddress' => '100 Main St, Austin, TX, USA',
            'internationalPhoneNumber' => '+1 415-555-2601',
            'addressComponents' => [
                ['longText' => 'Austin', 'shortText' => 'Austin', 'types' => ['locality']],
                ['longText' => 'United States', 'shortText' => 'US', 'types' => ['country']],
            ],
        ],
        [
            'id' => 'places/uncontactable',
            'displayName' => ['text' => 'No Route Cafe'],
            'formattedAddress' => '200 Main St, Austin, TX, USA',
            'addressComponents' => [
                ['longText' => 'Austin', 'shortText' => 'Austin', 'types' => ['locality']],
                ['longText' => 'United States', 'shortText' => 'US', 'types' => ['country']],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.generate'), [
            'country' => 'US',
            'place' => 'Austin',
            'category' => 'restaurants',
            'count' => 2,
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHas('status', '1 Google Places lead generated. 2 drafts skipped because no phone or email was available.');

    expect(Lead::query()->where('workspace_id', $workspace->id)->pluck('name')->all())->toBe(['Contactable Cafe']);
});

it('skips duplicate google places leads and existing contact matches', function (): void {
    [$user, $workspace] = leadsUserContext();
    configureGooglePlacesLeadSource();

    Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Existing Place',
        'phone' => '+1 415-555-2601',
        'source' => 'google_places',
        'external_source' => 'google_places',
        'external_id' => 'places/existing',
    ]);
    Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Existing Contact',
        'phone' => '+1 415-555-2602',
        'opt_in_status' => ContactOptInStatus::Unknown,
        'source' => ContactSource::Manual,
    ]);

    fakeGooglePlacesResponse([
        [
            'id' => 'places/existing',
            'displayName' => ['text' => 'Existing Place'],
            'internationalPhoneNumber' => '+1 415-555-2601',
            'formattedAddress' => '100 Main St, Austin, TX, USA',
        ],
        [
            'id' => 'places/contact-match',
            'displayName' => ['text' => 'Contact Match'],
            'internationalPhoneNumber' => '+1 415-555-2602',
            'formattedAddress' => '200 Main St, Austin, TX, USA',
        ],
        [
            'id' => 'places/new',
            'displayName' => ['text' => 'New Place'],
            'internationalPhoneNumber' => '+1 415-555-2603',
            'formattedAddress' => '300 Main St, Austin, TX, USA',
        ],
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.generate'), [
            'country' => 'US',
            'place' => 'Austin',
            'category' => 'restaurants',
            'count' => 3,
        ])
        ->assertRedirect(route('user.leads.index'));

    expect(Lead::query()->where('workspace_id', $workspace->id)->where('name', 'New Place')->exists())->toBeTrue()
        ->and(Lead::query()->where('workspace_id', $workspace->id)->count())->toBe(2);
});

it('tops up google places shortfalls with ai fallback leads', function (): void {
    [$user, $workspace] = leadsUserContext();
    configureGooglePlacesLeadSource();
    fakeGooglePlacesResponse([
        [
            'id' => 'places/contactable',
            'displayName' => ['text' => 'Contactable Cafe'],
            'formattedAddress' => '100 Main St, Austin, TX, USA',
            'internationalPhoneNumber' => '+1 415-555-2601',
            'addressComponents' => [
                ['longText' => 'Austin', 'shortText' => 'Austin', 'types' => ['locality']],
                ['longText' => 'United States', 'shortText' => 'US', 'types' => ['country']],
            ],
        ],
    ]);

    $generator = Mockery::mock(LeadGenerationService::class, [
        app(ChatbotAiProviderService::class),
        app(GooglePlacesLeadSourceService::class),
        app(AiUsageLogger::class),
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $generator->shouldReceive('fromPlatformProvider')
        ->once()
        ->with(Mockery::on(fn (array $criteria): bool => $criteria['count'] === 2), Mockery::any(), Mockery::any())
        ->andReturn([
            [
                'name' => 'AI Cafe Two',
                'company' => 'AI Cafe Two',
                'phone' => '+14155552602',
                'email' => null,
                'country' => 'US',
                'city' => 'Austin',
                'place' => 'Austin',
                'category' => 'restaurants',
                'score' => 80,
                'notes' => 'AI fallback lead.',
            ],
            [
                'name' => 'AI Cafe Three',
                'company' => 'AI Cafe Three',
                'phone' => null,
                'email' => 'ai-three@example.com',
                'country' => 'US',
                'city' => 'Austin',
                'place' => 'Austin',
                'category' => 'restaurants',
                'score' => 79,
                'notes' => 'AI fallback lead.',
            ],
        ]);
    app()->instance(LeadGenerationService::class, $generator);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.generate'), [
            'country' => 'US',
            'place' => 'Austin',
            'category' => 'restaurants',
            'count' => 3,
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHas('status', '3 Google Places leads generated.');

    $leads = Lead::query()->where('workspace_id', $workspace->id)->orderBy('id')->get();

    expect($leads)->toHaveCount(3)
        ->and($leads->pluck('source')->all())->toBe(['google_places', 'ai', 'ai'])
        ->and($leads->pluck('metadata.lead_source')->all())->toBe(['google_places', 'ai_fallback', 'ai_fallback'])
        ->and($leads->last()->metadata['google_places']['lead_source'])->toBe('mixed');
});

it('uses ai fallback for the full count when google places returns no usable leads', function (): void {
    [$user, $workspace] = leadsUserContext();
    configureGooglePlacesLeadSource();
    Http::fake([
        'https://places.googleapis.com/v1/places:searchText' => Http::response(['places' => []], 200),
    ]);

    $generator = Mockery::mock(LeadGenerationService::class, [
        app(ChatbotAiProviderService::class),
        app(GooglePlacesLeadSourceService::class),
        app(AiUsageLogger::class),
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $generator->shouldReceive('fromPlatformProvider')
        ->once()
        ->with(Mockery::on(fn (array $criteria): bool => $criteria['count'] === 2), Mockery::any(), Mockery::any())
        ->andReturn([
            [
                'name' => 'AI Only One',
                'company' => 'AI Only One',
                'phone' => '+14155552611',
                'email' => null,
                'country' => 'US',
                'city' => 'Austin',
                'place' => 'Austin',
                'category' => 'restaurants',
                'score' => 82,
                'notes' => 'AI fallback lead.',
            ],
            [
                'name' => 'AI Only Two',
                'company' => 'AI Only Two',
                'phone' => null,
                'email' => 'ai-only-two@example.com',
                'country' => 'US',
                'city' => 'Austin',
                'place' => 'Austin',
                'category' => 'restaurants',
                'score' => 81,
                'notes' => 'AI fallback lead.',
            ],
        ]);
    app()->instance(LeadGenerationService::class, $generator);

    app(LeadService::class)->generateForUser($user, [
        'country' => 'US',
        'place' => 'Austin',
        'category' => 'restaurants',
        'count' => 2,
    ]);

    $leads = Lead::query()->where('workspace_id', $workspace->id)->get();

    expect($leads)->toHaveCount(2)
        ->and($leads->pluck('source')->unique()->values()->all())->toBe(['ai'])
        ->and($leads->first()->metadata['google_places']['lead_source'])->toBe('ai_fallback');
});

it('skips duplicate and uncontactable ai fallback drafts when places cannot fill the target', function (): void {
    [$user, $workspace] = leadsUserContext();
    configureGooglePlacesLeadSource();
    fakeGooglePlacesResponse([
        [
            'id' => 'places/contactable',
            'displayName' => ['text' => 'Contactable Cafe'],
            'formattedAddress' => '100 Main St, Austin, TX, USA',
            'internationalPhoneNumber' => '+1 415-555-2601',
            'addressComponents' => [
                ['longText' => 'Austin', 'shortText' => 'Austin', 'types' => ['locality']],
                ['longText' => 'United States', 'shortText' => 'US', 'types' => ['country']],
            ],
        ],
    ]);

    $generator = Mockery::mock(LeadGenerationService::class, [
        app(ChatbotAiProviderService::class),
        app(GooglePlacesLeadSourceService::class),
        app(AiUsageLogger::class),
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $generator->shouldReceive('fromPlatformProvider')
        ->once()
        ->andReturn([
            [
                'name' => 'Contactable Cafe',
                'company' => 'Contactable Cafe',
                'phone' => '+1 415-555-2601',
                'email' => null,
                'country' => 'US',
                'city' => 'Austin',
                'place' => '100 Main St, Austin, TX, USA',
                'category' => 'restaurants',
                'score' => 80,
                'notes' => 'Duplicate fallback lead.',
            ],
            [
                'name' => 'No Route AI',
                'company' => 'No Route AI',
                'phone' => null,
                'email' => null,
                'country' => 'US',
                'city' => 'Austin',
                'place' => 'Austin',
                'category' => 'restaurants',
                'score' => 70,
                'notes' => 'Uncontactable fallback lead.',
            ],
            [
                'name' => 'Unique AI Cafe',
                'company' => 'Unique AI Cafe',
                'phone' => '+14155552612',
                'email' => null,
                'country' => 'US',
                'city' => 'Austin',
                'place' => 'Austin',
                'category' => 'restaurants',
                'score' => 78,
                'notes' => 'Unique fallback lead.',
            ],
        ]);
    app()->instance(LeadGenerationService::class, $generator);

    $result = app(LeadService::class)->generateForUser($user, [
        'country' => 'US',
        'place' => 'Austin',
        'category' => 'restaurants',
        'count' => 4,
    ]);

    expect($result['leads'])->toHaveCount(2)
        ->and($result['skipped'])->toBe(2)
        ->and(Lead::query()->where('workspace_id', $workspace->id)->pluck('name')->sort()->values()->all())->toBe(['Contactable Cafe', 'Unique AI Cafe']);
});

it('rejects lead updates without phone or email', function (): void {
    [$user, $workspace] = leadsUserContext();
    $lead = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Contactable Lead',
        'email' => 'contactable@example.com',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->put(route('user.leads.update', $lead), [
            'name' => 'No Route',
            'phone' => '',
            'email' => '',
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHasErrors(['phone', 'email']);
});

it('prefers active workspace ai providers without consuming platform ai credits', function (): void {
    [$user, $workspace] = leadsUserContext();
    configureGooglePlacesLeadSource();
    fakeGooglePlacesResponse();
    config(['ai.providers.openai.key' => 'sk-platform']);
    $subscription = subscribeLeadWorkspace($workspace->id, [
        'max_lead_generations_per_month' => 10,
        'max_ai_lead_results_per_month' => 100,
        'max_ai_credits' => 100,
    ]);

    ChatbotAiProvider::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'openai',
        'name' => 'Workspace OpenAI',
        'credentials' => ['api_key' => 'sk-workspace'],
        'settings' => ['default_model' => 'gpt-workspace'],
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.generate'), [
            'country' => 'US',
            'place' => 'Austin',
            'category' => 'restaurants',
            'channel' => 'whatsapp',
            'count' => 2,
        ])
        ->assertRedirect(route('user.leads.index'));

    $lead = Lead::query()->where('workspace_id', $workspace->id)->firstOrFail();
    $usage = $subscription->fresh()->usage;

    expect($lead->metadata['generated_by'])->toBe('workspace_ai')
        ->and($lead->metadata['provider'])->toBe('openai')
        ->and($lead->metadata['model'])->toBe('gpt-workspace')
        ->and($lead->metadata['platform_ai_used'])->toBeFalse()
        ->and((int) data_get($usage, 'max_lead_generations_per_month'))->toBe(1)
        ->and((int) data_get($usage, 'max_ai_lead_results_per_month'))->toBe(2)
        ->and((int) data_get($usage, 'max_ai_credits'))->toBe(0);
});

it('uses platform ai metadata without consuming platform credits when google places runs without ai query help', function (): void {
    [$user, $workspace] = leadsUserContext();
    configureGooglePlacesLeadSource();
    fakeGooglePlacesResponse();
    config(['ai.providers.openai.key' => 'sk-platform']);
    $subscription = subscribeLeadWorkspace($workspace->id, [
        'max_lead_generations_per_month' => 10,
        'max_ai_lead_results_per_month' => 100,
        'max_ai_credits' => 100,
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.generate'), [
            'country' => 'US',
            'place' => 'Austin',
            'category' => 'restaurants',
            'channel' => 'whatsapp',
            'count' => 2,
        ])
        ->assertRedirect(route('user.leads.index'));

    $lead = Lead::query()->where('workspace_id', $workspace->id)->firstOrFail();
    $usage = $subscription->fresh()->usage;

    expect($lead->metadata['generated_by'])->toBe('platform_ai')
        ->and($lead->metadata['provider'])->toBe('openai')
        ->and($lead->metadata['platform_ai_used'])->toBeFalse()
        ->and((int) data_get($usage, 'max_lead_generations_per_month'))->toBe(1)
        ->and((int) data_get($usage, 'max_ai_lead_results_per_month'))->toBe(2)
        ->and((int) data_get($usage, 'max_ai_credits'))->toBe(0);
});

it('blocks lead generation when request or result quotas are exhausted', function (): void {
    [$user, $workspace] = leadsUserContext();
    subscribeLeadWorkspace($workspace->id, [
        'max_lead_generations_per_month' => 0,
        'max_ai_lead_results_per_month' => 100,
        'max_ai_credits' => 100,
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.generate'), [
            'country' => 'US',
            'category' => 'restaurants',
            'count' => 2,
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHasErrors('plan');

    expect(Lead::query()->where('workspace_id', $workspace->id)->count())->toBe(0);

    Subscription::query()->where('workspace_id', $workspace->id)->delete();
    subscribeLeadWorkspace($workspace->id, [
        'max_lead_generations_per_month' => 10,
        'max_ai_lead_results_per_month' => 1,
        'max_ai_credits' => 100,
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.generate'), [
            'country' => 'US',
            'category' => 'restaurants',
            'count' => 2,
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHasErrors('count');

    expect(Lead::query()->where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('does not block google places generation when platform ai credits are exhausted but ai is not used', function (): void {
    [$user, $workspace] = leadsUserContext();
    configureGooglePlacesLeadSource();
    fakeGooglePlacesResponse();
    config(['ai.providers.openai.key' => 'sk-platform']);
    subscribeLeadWorkspace($workspace->id, [
        'max_lead_generations_per_month' => 10,
        'max_ai_lead_results_per_month' => 100,
        'max_ai_credits' => 1,
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.generate'), [
            'country' => 'US',
            'category' => 'restaurants',
            'count' => 2,
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHas('status', '2 Google Places leads generated.');

    expect(Lead::query()->where('workspace_id', $workspace->id)->count())->toBe(2);
});

it('converts a reviewed lead into an unknown opt-in ai generated contact', function (): void {
    [$user, $workspace] = leadsUserContext();

    $lead = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Ada Lovelace',
        'company' => 'Analytical Engines',
        'phone' => '+14155552671',
        'email' => 'ada@example.com',
        'country' => 'US',
        'city' => 'San Francisco',
        'category' => 'Software',
        'stage' => 'qualified',
        'source' => 'ai',
        'score' => 88,
        'verification_status' => 'unverified',
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.convert', $lead))
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHas('status', 'Lead converted to contact.');

    $contact = Contact::query()->where('workspace_id', $workspace->id)->firstOrFail();

    expect($lead->fresh()->contact_id)->toBe($contact->id)
        ->and($lead->fresh()->stage)->toBe('converted')
        ->and($contact->name)->toBe('Ada Lovelace')
        ->and($contact->source)->toBe(ContactSource::AiGenerated)
        ->and($contact->opt_in_status)->toBe(ContactOptInStatus::Unknown)
        ->and($contact->custom_fields['generated_from_lead'])->toBeTrue();
});

it('converts an email only lead into a contact and attaches it to groups', function (): void {
    [$user, $workspace] = leadsUserContext();
    $group = ContactGroup::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Email Leads',
        'slug' => 'email-leads',
        'type' => 'static',
    ]);
    $lead = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Email Only',
        'email' => 'email-only@example.com',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.convert', $lead), [
            'group_ids' => [$group->id],
        ])
        ->assertRedirect(route('user.leads.index'));

    $contact = Contact::query()->where('workspace_id', $workspace->id)->where('email', 'email-only@example.com')->firstOrFail();

    expect($lead->fresh()->contact_id)->toBe($contact->id)
        ->and($contact->phone)->toBeNull()
        ->and($contact->opt_in_status)->toBe(ContactOptInStatus::Unknown)
        ->and($group->contacts()->whereKey($contact->id)->exists())->toBeTrue();
});

it('bulk converts selected leads and attaches contacts to a static group', function (): void {
    [$user, $workspace] = leadsUserContext();
    $group = ContactGroup::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'AI Prospects',
        'slug' => 'ai-prospects',
        'type' => 'static',
    ]);

    $first = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'First Lead',
        'phone' => '+14155552671',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);
    $second = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Second Lead',
        'email' => 'second@example.com',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.bulk-convert'), [
            'lead_ids' => [$first->id, $second->id],
            'group_ids' => [$group->id],
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHas('status', '2 leads converted to contacts.');

    expect(Contact::query()->where('workspace_id', $workspace->id)->count())->toBe(2)
        ->and($group->contacts()->count())->toBe(2)
        ->and($first->fresh()->contact_id)->not->toBeNull()
        ->and($second->fresh()->contact_id)->not->toBeNull();
});

it('bulk convert skips uncontactable and already converted selected leads', function (): void {
    [$user, $workspace] = leadsUserContext();

    $valid = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Valid Lead',
        'phone' => '+14155552671',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);
    $missingRoute = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Missing Contact Route',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);
    $converted = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Converted Lead',
        'phone' => '+14155552672',
        'contact_status' => 'converted',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.bulk-convert'), [
            'lead_ids' => [$valid->id, $missingRoute->id, $converted->id],
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHas('status', '1 lead converted to contacts.');

    expect($valid->fresh()->contact_id)->not->toBeNull()
        ->and($missingRoute->fresh()->contact_id)->toBeNull()
        ->and($converted->fresh()->contact_id)->toBeNull();
});

it('bulk deletes selected workspace leads only', function (): void {
    [$user, $workspace] = leadsUserContext();
    [, $otherWorkspace] = leadsUserContext();

    $first = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Delete Me',
        'phone' => '+14155552671',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);
    $second = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Keep Me',
        'phone' => '+14155552672',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);
    $other = Lead::query()->create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other Workspace',
        'phone' => '+14155552673',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.bulk-delete'), [
            'lead_ids' => [$first->id, $other->id],
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHas('status', '1 lead deleted.');

    expect(Lead::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and(Lead::query()->whereKey($second->id)->exists())->toBeTrue()
        ->and(Lead::query()->whereKey($other->id)->exists())->toBeTrue();
});

it('sends a direct email message from a lead after converting it to a contact', function (): void {
    [$user, $workspace] = leadsUserContext();
    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'email',
        'name' => 'Email',
        'status' => ChannelAccountStatus::Connected,
        'provider_display_id' => 'sales@example.com',
        'credentials' => ['mail_mailer' => 'log'],
        'connected_at' => now(),
    ]);
    $lead = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Email Prospect',
        'email' => 'prospect@example.com',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.send-message', $lead), [
            'channel' => 'email',
            'subject' => 'Hello',
            'body' => 'Thanks for connecting.',
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHas('status', 'Email message sent.');

    expect($lead->fresh()->contact_id)->not->toBeNull()
        ->and(Message::query()->where('workspace_id', $workspace->id)->where('provider', 'email')->where('body', 'Thanks for connecting.')->exists())->toBeTrue();
});

it('sends a direct sms message from a phone lead after converting it to a contact', function (): void {
    [$user, $workspace] = leadsUserContext();
    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'sms',
        'name' => 'SMS',
        'status' => ChannelAccountStatus::Connected,
        'provider_display_id' => '+14155550000',
        'credentials' => ['sms_provider' => 'log'],
        'connected_at' => now(),
    ]);
    $lead = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Phone Prospect',
        'phone' => '+14155552671',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.send-message', $lead), [
            'channel' => 'sms',
            'body' => 'Thanks for connecting.',
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHas('status', 'Sms message sent.');

    expect($lead->fresh()->contact_id)->not->toBeNull()
        ->and(Message::query()->where('workspace_id', $workspace->id)->where('provider', 'sms')->where('body', 'Thanks for connecting.')->exists())->toBeTrue();
});

it('blocks users without lead permissions', function (): void {
    [, $workspace] = leadsUserContext();
    $user = User::factory()->create(['email_verified_at' => now()]);
    Role::findOrCreate('workspace-staff', 'web');

    $workspace->members()->attach($user->id, [
        'role' => WorkspaceMemberRole::Staff->value,
        'status' => WorkspaceMemberStatus::Active->value,
    ]);

    $this->actingAs($user)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->get(route('user.leads.index'))
        ->assertForbidden();
});

it('gracefully converts leads in bulk with conflicting duplicate emails without constraint violations', function (): void {
    [$user, $workspace] = leadsUserContext();

    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'email' => 'info@facebook.com',
        'name' => 'Existing Email Contact',
    ]);

    $lead = Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Conflicting Lead',
        'phone' => '+14155552671',
        'email' => 'info@facebook.com',
        'source' => 'ai',
        'verification_status' => 'unverified',
    ]);

    $this->actingAs($user)
        ->from(route('user.leads.index'))
        ->post(route('user.leads.bulk-convert'), [
            'lead_ids' => [$lead->id],
        ])
        ->assertRedirect(route('user.leads.index'))
        ->assertSessionHas('status', '1 lead converted to contacts.');

    expect(Contact::query()->where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and($lead->fresh()->contact_id)->toBe($contact->id)
        ->and($contact->fresh()->phone)->toBe('+14155552671');
});

it('overrides count to 10 when matching leads already exist in the same workspace', function (): void {
    [$user, $workspace] = leadsUserContext();

    subscribeLeadWorkspace($workspace->id, [
        'max_lead_generations_per_month' => 10,
        'max_ai_lead_results_per_month' => 100,
        'max_ai_credits' => 1000,
    ]);

    $criteria = [
        'country' => 'US',
        'place' => 'Austin',
        'category' => 'restaurant',
        'audience' => 'any',
        'channel' => 'any',
        'count' => 5,
    ];

    Lead::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Existing Lead',
        'phone' => '+14155552671',
        'criteria' => $criteria,
        'source' => 'google_places',
        'verification_status' => 'unverified',
    ]);

    $mockGenerator = $this->mock(LeadGenerationService::class);
    $mockGenerator->shouldReceive('providerMetadataFor')->andReturn([]);
    $mockGenerator->shouldReceive('generate')
        ->once()
        ->with($user, Mockery::on(function ($arg) {
            return $arg['count'] === 10;
        }), $workspace->id)
        ->andReturn([
            'leads' => [
                [
                    'name' => 'New Lead',
                    'phone' => '+14155552672',
                    'email' => 'new@example.com',
                    'metadata' => [
                        'google_place_id' => 'place2',
                    ],
                ],
            ],
            'skipped_uncontactable' => 0,
            'skipped_duplicates' => 0,
            'metadata' => [],
        ]);

    app(LeadService::class)->generateForUser($user, $criteria);
});

it('copies leads from a different workspace when criteria matches and bypasses Place API/AI', function (): void {
    [$user, $workspace] = leadsUserContext();
    [$otherUser, $otherWorkspace] = leadsUserContext();

    $criteria = [
        'country' => 'US',
        'place' => 'Austin',
        'category' => 'restaurant',
        'audience' => 'any',
        'channel' => 'any',
        'count' => 5,
    ];

    $otherLead = Lead::query()->create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other Workspace Lead',
        'company' => 'Other Workspace Co',
        'phone' => '+14155552671',
        'email' => 'other@example.com',
        'country' => 'US',
        'city' => 'Austin',
        'place' => 'Austin Address',
        'category' => 'restaurant',
        'score' => 85,
        'criteria' => $criteria,
        'source' => 'google_places',
        'external_source' => 'google_places',
        'external_id' => 'place-other',
        'verification_status' => 'unverified',
        'notes' => 'Some notes',
        'metadata' => ['key' => 'value'],
    ]);

    $mockGenerator = $this->mock(LeadGenerationService::class);
    $mockGenerator->shouldNotReceive('generate');

    $result = app(LeadService::class)->generateForUser($user, $criteria);

    expect($result['leads'])->toHaveCount(1)
        ->and(Lead::query()->where('workspace_id', $workspace->id)->count())->toBe(1);

    $copiedLead = Lead::query()->where('workspace_id', $workspace->id)->first();
    expect($copiedLead->name)->toBe('Other Workspace Lead')
        ->and($copiedLead->company)->toBe('Other Workspace Co')
        ->and($copiedLead->phone)->toBe('+14155552671')
        ->and($copiedLead->email)->toBe('other@example.com')
        ->and($copiedLead->criteria)->toMatchArray($criteria)
        ->and($copiedLead->metadata)->toMatchArray(['key' => 'value']);
});
