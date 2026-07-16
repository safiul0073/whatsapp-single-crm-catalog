<?php

use App\Models\User;
use App\Modules\Campaigns\Services\AudienceResolver;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Jobs\ProcessContactImportJob;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use App\Modules\Contacts\Models\ContactImport;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\Contacts\Services\ContactGroupService;
use App\Modules\Contacts\Services\ContactService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function contactsWorkspaceFor(User $user)
{
    return app(WorkspaceResolver::class)->current($user);
}

it('shows the csv upload drop zone in the import modal', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.contacts.index'))
        ->assertOk()
        ->assertSee('Maintain clean customer records so conversations, campaigns, CRM leads, and automation use the right people.')
        ->assertSee('Contacts guide')
        ->assertSee('Drop your file here or click to browse')
        ->assertSee(route('user.contacts.export'), false)
        ->assertSee('Custom fields')
        ->assertSee('custom_fields[website]', false)
        ->assertSee('Phone country code')
        ->assertSee('sm:grid-cols-[10rem_minmax(0,1fr)]', false)
        ->assertSee(':class="{ \'is-active\': step === 1 }"', false);
});

it('wires contact and group edit actions and keeps leads navigation permission aware', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = contactsWorkspaceFor($user);

    app(ContactService::class)->upsert($workspace->id, [
        'phone' => '+14155552671',
        'name' => 'Ada Lovelace',
    ]);

    ContactGroup::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'VIP Customers',
        'slug' => 'vip-customers',
        'type' => 'static',
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.contacts.index'))
        ->assertOk()
        ->assertSee('data-contact=', false)
        ->assertSee("openInlineModal('editContact')", false)
        ->assertSee(route('user.leads.index'), false);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.groups.index'))
        ->assertOk()
        ->assertSee('data-group=', false)
        ->assertSee("openInlineModal('editGroup')", false)
        ->assertSee(route('user.leads.index'), false);
});

it('exports workspace contacts as filtered csv', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $workspace = contactsWorkspaceFor($user);
    $otherWorkspace = contactsWorkspaceFor($other);
    $service = app(ContactService::class);
    $tag = ContactTag::query()->create(['workspace_id' => $workspace->id, 'name' => 'VIP', 'slug' => 'vip']);
    $group = ContactGroup::query()->create(['workspace_id' => $workspace->id, 'name' => 'Buyers', 'slug' => 'buyers']);

    $ada = $service->upsert($workspace->id, [
        'name' => 'Ada Export',
        'phone' => '+14155552671',
        'email' => 'ada-export@example.com',
        'city' => 'Dhaka',
        'country' => 'BD',
        'custom_fields' => ['plan' => 'Premium'],
        'tag_ids' => [$tag->id],
        'group_ids' => [$group->id],
        'opt_in_status' => ContactOptInStatus::Subscribed->value,
    ]);
    $service->upsert($workspace->id, [
        'name' => 'Bob Export',
        'phone' => '+442071838750',
        'opt_in_status' => ContactOptInStatus::Unknown->value,
    ]);
    $service->upsert($otherWorkspace->id, [
        'name' => 'Other Ada Export',
        'phone' => '+33142278186',
        'email' => 'other-ada@example.com',
        'tags' => ['VIP'],
    ]);

    $response = $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.contacts.export', [
            'q' => 'Ada',
            'tag' => 'VIP',
            'optin' => 'opted-in',
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Name,Phone,Email,Country,City,Tags,Groups')
        ->toContain('Ada Export')
        ->toContain('ada-export@example.com')
        ->toContain('VIP')
        ->toContain('Buyers')
        ->toContain('Premium')
        ->not->toContain('Bob Export')
        ->not->toContain('Other Ada Export');

    expect($ada->fresh()->workspace_id)->toBe($workspace->id);
});

it('normalizes valid e164 phones and rejects local numbers', function (): void {
    $user = User::factory()->create();
    $workspace = contactsWorkspaceFor($user);
    $service = app(ContactService::class);

    $contact = $service->upsert($workspace->id, [
        'phone' => '+1 (415) 555-2671',
        'name' => 'Ada',
    ]);

    expect($contact->phone)->toBe('+14155552671');

    $service->upsert($workspace->id, [
        'phone' => '01712345678',
        'name' => 'Local',
    ]);
})->throws(ValidationException::class);

it('stores cleaned custom fields for template shortcodes', function (): void {
    $user = User::factory()->create();
    $workspace = contactsWorkspaceFor($user);
    $service = app(ContactService::class);

    $contact = $service->upsert($workspace->id, [
        'phone' => '+14155552671',
        'name' => 'Ada',
        'custom_fields' => [
            'website' => ' ada.dev ',
            'Order ID' => ' A-100 ',
            '' => 'ignored',
            'blank' => '',
        ],
    ]);

    expect($contact->custom_fields)->toBe([
        'website' => 'ada.dev',
        'order_id' => 'A-100',
    ]);

    $updated = $service->updateForUser($user, (string) $contact->id, [
        'custom_fields' => [
            'website' => 'lovelace.example',
            'Plan Name' => 'Pro',
        ],
    ]);

    expect($updated->custom_fields)->toBe([
        'website' => 'lovelace.example',
        'plan_name' => 'Pro',
    ]);
});

it('updates duplicate phones inside a workspace and allows same phone across workspaces', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $firstWorkspace = contactsWorkspaceFor($first);
    $secondWorkspace = contactsWorkspaceFor($second);
    $service = app(ContactService::class);

    $service->upsert($firstWorkspace->id, ['phone' => '+14155552671', 'name' => 'Original']);
    $updated = $service->upsert($firstWorkspace->id, ['phone' => '+1 415 555 2671', 'name' => 'Updated']);
    $service->upsert($secondWorkspace->id, ['phone' => '+14155552671', 'name' => 'Other Workspace']);

    expect($updated->name)->toBe('Updated')
        ->and(Contact::query()->where('workspace_id', $firstWorkspace->id)->count())->toBe(1)
        ->and(Contact::query()->where('workspace_id', $secondWorkspace->id)->count())->toBe(1);
});

it('keeps tag and group attachments workspace scoped', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workspace = contactsWorkspaceFor($user);
    $otherWorkspace = contactsWorkspaceFor($other);
    $tag = ContactTag::query()->create(['workspace_id' => $workspace->id, 'name' => 'Promo', 'slug' => 'promo']);
    $otherTag = ContactTag::query()->create(['workspace_id' => $otherWorkspace->id, 'name' => 'Other', 'slug' => 'other']);
    $group = ContactGroup::query()->create(['workspace_id' => $workspace->id, 'name' => 'VIP', 'slug' => 'vip']);
    $otherGroup = ContactGroup::query()->create(['workspace_id' => $otherWorkspace->id, 'name' => 'Other', 'slug' => 'other']);

    $contact = app(ContactService::class)->upsert($workspace->id, [
        'phone' => '+14155552671',
        'tag_ids' => [$tag->id, $otherTag->id],
        'group_ids' => [$group->id, $otherGroup->id],
    ]);

    expect($contact->tags->pluck('id')->all())->toBe([$tag->id])
        ->and($contact->groups->pluck('id')->all())->toBe([$group->id]);
});

it('imports csv rows, updates duplicates, and creates groups and tags', function (): void {
    $user = User::factory()->create();
    $workspace = contactsWorkspaceFor($user);
    app(ContactService::class)->upsert($workspace->id, ['phone' => '+14155552671', 'name' => 'Before']);

    $path = 'imports/'.$workspace->id.'/contacts.csv';
    Storage::disk('local')->put($path, implode("\n", [
        'name,phone,email,tags,groups',
        'After,+14155552671,after@example.com,"Promo,VIP","Eid Customers"',
        'New,+442071838750,new@example.com,Newsletter,Leads',
    ]));

    $import = ContactImport::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'file_name' => 'contacts.csv',
        'file_path' => $path,
        'total_rows' => 2,
        'column_mapping' => [
            'name' => 'name',
            'phone' => 'phone',
            'email' => 'email',
            'tags' => 'tags',
            'groups' => 'groups',
        ],
        'options' => ['mark_optin' => true],
    ]);

    (new ProcessContactImportJob($import->id))->handle(app(ContactService::class));
    $import->refresh();

    expect($import->created_rows)->toBe(1)
        ->and($import->updated_rows)->toBe(1)
        ->and($import->failed_rows)->toBe(0)
        ->and(Contact::query()->where('workspace_id', $workspace->id)->count())->toBe(2)
        ->and(ContactTag::query()->where('workspace_id', $workspace->id)->where('slug', Str::slug('Promo'))->exists())->toBeTrue()
        ->and(ContactGroup::query()->where('workspace_id', $workspace->id)->where('slug', Str::slug('Eid Customers'))->exists())->toBeTrue();
});

it('uploads csv imports with inferred columns and processes the final mapping', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent('contacts.csv', implode("\n", [
        'Full Name,WhatsApp Number,Email,Tags',
        'Ada Lovelace,+14155552671,ada@example.com,"vip,lead"',
    ]));

    $upload = $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.imports.upload'), [
            'file' => $file,
            'update_existing' => true,
            'mark_optin' => true,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('columns.0.name', 'Full Name')
        ->assertJsonPath('columns.0.map', 'name')
        ->assertJsonPath('columns.1.name', 'WhatsApp Number')
        ->assertJsonPath('columns.1.map', 'phone');

    $importId = $upload->json('import.id');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.imports.process', $importId), [
            'column_mapping' => [
                'Full Name' => 'name',
                'WhatsApp Number' => 'phone',
                'Email' => 'email',
                'Tags' => 'tags',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'processing');

    expect(ContactImport::query()->findOrFail($importId)->column_mapping)->toMatchArray([
        'Full Name' => 'name',
        'WhatsApp Number' => 'phone',
        'Email' => 'email',
        'Tags' => 'tags',
    ]);

    Queue::assertPushed(ProcessContactImportJob::class, fn (ProcessContactImportJob $job): bool => $job->importId === $importId);
});

it('resolves static and dynamic groups with multiple operators', function (): void {
    $user = User::factory()->create();
    $workspace = contactsWorkspaceFor($user);
    $service = app(ContactService::class);
    $latest = $service->upsert($workspace->id, [
        'phone' => '+14155552671',
        'name' => 'Latest VIP',
        'city' => 'Dhaka',
        'opt_in_status' => ContactOptInStatus::Subscribed->value,
    ]);
    $old = $service->upsert($workspace->id, [
        'phone' => '+442071838750',
        'name' => 'Old Lead',
        'city' => 'London',
        'opt_in_status' => ContactOptInStatus::Unknown->value,
    ]);
    $old->update(['created_at' => now()->subDays(20)]);
    $groupService = app(ContactGroupService::class);

    $dynamic = ContactGroup::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Latest 10 days contact',
        'slug' => 'latest-10-days-contact',
        'type' => 'dynamic',
        'rules' => [
            ['field' => 'created_at', 'operator' => 'within_days', 'value' => 10, 'boolean' => 'and'],
            ['field' => 'name', 'operator' => 'contains', 'value' => 'VIP', 'boolean' => 'and'],
        ],
    ]);
    $orGroup = ContactGroup::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Dhaka or London',
        'slug' => 'dhaka-or-london',
        'type' => 'dynamic',
        'rules' => [
            ['field' => 'city', 'operator' => '=', 'value' => 'Dhaka', 'boolean' => 'and'],
            ['field' => 'city', 'operator' => '=', 'value' => 'London', 'boolean' => 'or'],
        ],
    ]);
    $static = ContactGroup::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'VIP',
        'slug' => 'vip',
        'type' => 'static',
    ]);
    $static->contacts()->sync([$latest->id]);

    expect($groupService->count($dynamic))->toBe(1)
        ->and($groupService->count($orGroup))->toBe(2)
        ->and($groupService->count($static))->toBe(1);
});

it('resolves campaign audiences and sendability reasons', function (): void {
    $user = User::factory()->create();
    $workspace = contactsWorkspaceFor($user);
    $service = app(ContactService::class);
    $tag = ContactTag::query()->create(['workspace_id' => $workspace->id, 'name' => 'Promo', 'slug' => 'promo']);
    $group = ContactGroup::query()->create(['workspace_id' => $workspace->id, 'name' => 'Promo Group', 'slug' => 'promo-group', 'type' => 'static']);
    $sendable = $service->upsert($workspace->id, [
        'phone' => '+14155552671',
        'opt_in_status' => ContactOptInStatus::Subscribed->value,
        'tag_ids' => [$tag->id],
        'group_ids' => [$group->id],
    ]);
    $blocked = $service->upsert($workspace->id, [
        'phone' => '+442071838750',
        'opt_in_status' => ContactOptInStatus::Subscribed->value,
        'tag_ids' => [$tag->id],
        'group_ids' => [$group->id],
    ]);
    $blocked->update(['blocked_at' => now()]);

    $resolver = app(AudienceResolver::class);
    $contacts = $resolver->contacts($workspace->id, [
        'audience_type' => 'groups',
        'audience_ids' => [$group->id],
    ]);

    expect($contacts->pluck('id')->sort()->values()->all())->toBe([$sendable->id, $blocked->id])
        ->and($resolver->sendability($sendable, 'whatsapp'))->toBeNull()
        ->and($resolver->sendability($blocked, 'whatsapp'))->toBe('blocked');
});

it('gracefully merges duplicate contacts on duplicate phone or email during upsert', function (): void {
    $user = User::factory()->create();
    $workspace = contactsWorkspaceFor($user);
    $service = app(ContactService::class);

    $contact1 = $service->upsert($workspace->id, [
        'phone' => '+14155552671',
        'name' => 'Phone Contact',
    ]);

    $contact2 = $service->upsert($workspace->id, [
        'email' => 'info@facebook.com',
        'name' => 'Email Contact',
    ]);

    $tag = ContactTag::query()->create(['workspace_id' => $workspace->id, 'name' => 'VIP', 'slug' => 'vip']);
    $contact2->tags()->sync([$tag->id]);

    $upserted = $service->upsert($workspace->id, [
        'phone' => '+14155552671',
        'email' => 'info@facebook.com',
        'name' => 'Merged Contact',
    ]);

    expect(Contact::query()->where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and($upserted->id)->toBe($contact1->id)
        ->and($upserted->email)->toBe('info@facebook.com')
        ->and($upserted->tags->pluck('id')->all())->toContain($tag->id);
});
