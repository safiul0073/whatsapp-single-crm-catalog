<x-layouts.user :title="__('Meta Business')">
    @php
        $firstVisit = $connection->selected === null;
        $isChecked = function (string $type, string $id) use ($connection, $activeIds, $firstVisit): bool {
            if (in_array($id, $activeIds[$type] ?? [], true)) {
                return true;
            }

            return $firstVisit ? true : in_array($id, $connection->selectedOfType($type), true);
        };
        $whatsappNumbers = $connection->assetsOfType('whatsapp');
        $selectedWhatsApp = collect($whatsappNumbers)->pluck('id')->first(fn (string $id): bool => in_array($id, $activeIds['whatsapp'] ?? [], true))
            ?? $connection->selectedOfType('whatsapp')[0] ?? null;
        $sections = [
            'messenger' => ['title' => __('Facebook Pages (Messenger)'), 'icon' => 'ph-messenger-logo', 'empty' => __('No Pages were shared.')],
            'instagram' => ['title' => __('Instagram accounts'), 'icon' => 'ph-instagram-logo', 'empty' => __('No Instagram Business accounts linked to the shared Pages.')],
            'ad_accounts' => ['title' => __('Ad accounts'), 'icon' => 'ph-megaphone', 'empty' => __('No ad accounts shared. Grant ads permissions to manage Meta Ads later.')],
            'catalogs' => ['title' => __('Product catalogs'), 'icon' => 'ph-storefront', 'empty' => __('No catalogs shared.')],
        ];
    @endphp

    <div class="space-y-4">
        <a href="{{ route('user.channels.index') }}" class="inline-flex items-center gap-1.5 rounded-md text-sm font-medium text-body transition hover:text-primary">
            <i class="ph ph-arrow-left text-base"></i>
            {{ __('All channels') }}
        </a>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="heading-2">{{ $connection->business_name ?: __('Meta Business') }}</h2>
                <p class="m-text mt-1">{{ __('Everything Meta shared with this workspace. Tick what to turn on; each item becomes its own channel.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('user.meta-social.business.refresh') }}">
                    @csrf
                    <button type="submit" class="btn-sm btn-outline">
                        <i class="ph ph-arrows-clockwise text-base"></i>
                        {{ __('Refresh from Meta') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('user.meta-social.business.disconnect') }}">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        data-confirm
                        data-confirm-title="{{ __('Remove Meta Business login?') }}"
                        data-confirm-message="{{ __('Channels you already connected keep working. You can connect the business again at any time.') }}"
                        data-confirm-button="{{ __('Remove') }}"
                        data-confirm-variant="danger"
                        data-confirm-icon="ph-plugs"
                        class="btn-sm btn-outline text-error hover:border-error hover:text-error"
                    >
                        <i class="ph ph-plugs text-base"></i>
                        {{ __('Remove login') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <x-marketing-channels::status-banner />

    <form method="POST" action="{{ route('user.meta-social.business.activate') }}" class="mt-6 space-y-6">
        @csrf

        <section class="section-card" data-meta-business-section="whatsapp">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-primary/10 text-primary"><i class="ph ph-whatsapp-logo text-xl"></i></span>
                <div>
                    <h3 class="heading-5">{{ __('WhatsApp number') }}</h3>
                    <p class="text-xs text-body">{{ __('A workspace uses one WhatsApp number. Choosing another replaces the current one.') }}</p>
                </div>
            </div>

            @if (! $canManageWhatsApp)
                <p class="mt-4 text-sm text-body">{{ __('Ask a workspace admin with channel access to connect WhatsApp.') }}</p>
            @elseif ($whatsappNumbers === [])
                <p class="mt-4 text-sm text-body">{{ __('No WhatsApp Business accounts were shared. To add a new number, use WhatsApp setup.') }}</p>
            @else
                <div class="mt-4 space-y-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 px-3 py-2 dark:border-gray-800">
                        <input type="radio" name="whatsapp" value="" class="form-radio" @checked($selectedWhatsApp === null)>
                        <span class="text-sm text-body">{{ __('Keep WhatsApp as it is') }}</span>
                    </label>
                    @foreach ($whatsappNumbers as $number)
                        <label class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 px-3 py-2 dark:border-gray-800">
                            <input type="radio" name="whatsapp" value="{{ $number['id'] }}" class="form-radio" @checked($selectedWhatsApp === $number['id'])>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-heading">{{ $number['verified_name'] ?: $number['display_phone_number'] }} · {{ $number['display_phone_number'] }}</span>
                                <span class="block truncate text-xs text-body">{{ __('WhatsApp account: :name', ['name' => $number['waba_name']]) }}</span>
                                @php($numberNote = match (true) {
                                    ($number['name_status'] ?? '') === 'PENDING_REVIEW' => __('Display name is waiting for Meta review. You can turn it on now; sending starts after approval.'),
                                    ($number['name_status'] ?? '') === 'DECLINED' => __('Meta declined the display name. Fix it in WhatsApp Manager before sending.'),
                                    filled($number['status'] ?? null) && ($number['status'] ?? '') !== 'CONNECTED' => __('Not registered with the Cloud API yet. Use "Add a WhatsApp number" on the Channels page to finish registration.'),
                                    default => null,
                                })
                                @if ($numberNote)
                                    <span class="mt-0.5 block text-xs font-medium text-warning" data-asset-note>{{ $numberNote }}</span>
                                @endif
                            </span>
                            @if (in_array($number['id'], $activeIds['whatsapp'] ?? [], true))
                                <span class="badge badge-soft ml-auto">{{ __('Connected') }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            @endif
        </section>

        @foreach ($sections as $type => $section)
            @php($assets = $connection->assetsOfType($type))
            <section class="section-card" data-meta-business-section="{{ $type }}">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-primary/10 text-primary"><i class="ph {{ $section['icon'] }} text-xl"></i></span>
                    <h3 class="heading-5">{{ $section['title'] }}</h3>
                </div>

                @if ($assets === [])
                    <p class="mt-4 text-sm text-body">{{ $section['empty'] }}</p>
                @else
                    <div class="mt-4 grid gap-2 md:grid-cols-2">
                        @foreach ($assets as $asset)
                            <label class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 px-3 py-2 dark:border-gray-800">
                                @php($cannotMessage = $type === 'messenger' && ($asset['can_message'] ?? '1') === '0')
                                <input type="checkbox" name="{{ $type }}[]" value="{{ $asset['id'] }}" class="form-checkbox" @checked($isChecked($type, $asset['id'])) @disabled($cannotMessage)>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-heading">{{ $type === 'instagram' && filled($asset['username'] ?? null) ? '@'.$asset['username'] : $asset['name'] }}</span>
                                    @if ($type === 'instagram')
                                        <span class="block truncate text-xs text-body">{{ __('Linked Page: :page', ['page' => $asset['page_name']]) }}</span>
                                    @elseif ($cannotMessage)
                                        <span class="block text-xs font-medium text-warning" data-asset-note>{{ __('You do not have Messages access on this Page. Ask a Page admin to give you full control or Messages access in Page settings, then press Refresh.') }}</span>
                                    @elseif ($type === 'ad_accounts' && filled($asset['currency'] ?? null))
                                        <span class="block truncate text-xs text-body">{{ $asset['id'] }} · {{ $asset['currency'] }}</span>
                                    @endif
                                </span>
                                @if (in_array($asset['id'], $activeIds[$type] ?? [], true))
                                    <span class="badge badge-soft ml-auto">{{ __('Connected') }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">
                <i class="ph ph-plugs-connected text-base"></i>
                {{ __('Turn on selected') }}
            </button>
        </div>
    </form>
</x-layouts.user>
