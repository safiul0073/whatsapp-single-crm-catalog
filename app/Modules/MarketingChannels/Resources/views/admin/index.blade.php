<x-layouts.admin :title="__('Connected Channels')">
    <div class="space-y-6">
        <div class="flex flex-col gap-1">
            <h1 class="heading-4 text-neutral-950">{{ __('Connected Channels') }}</h1>
            <p class="text-sm text-neutral-500">{{ __('Monitor WhatsApp now, with room for Messenger, Instagram, and Telegram later.') }}</p>
        </div>

        <div class="section-card">
            <x-tables.datatable :url="route('admin.marketing-channels.index')" :searchable="false" :perPageOptions="[]">
                <x-tables.table>
                    <thead>
                        <tr>
                            <th>{{ __('Workspace') }}</th>
                            <th>{{ __('Provider') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Last Sync') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($channels as $channel)
                            <tr>
                                <td data-th="{{ __('Workspace') }}">
                                    <p class="text-sm font-medium text-neutral-900">{{ $channel->workspace?->name ?? __('Unknown') }}</p>
                                </td>
                                <td data-th="{{ __('Provider') }}">
                                    <span class="text-sm font-medium text-neutral-700">{{ ucfirst($channel->provider) }}</span>
                                </td>
                                <td data-th="{{ __('Name') }}">
                                    <span class="text-sm text-neutral-700">{{ $channel->name }}</span>
                                </td>
                                <td data-th="{{ __('Status') }}">
                                    <div class="flex justify-end lg:justify-start rtl:justify-start">
                                        <x-ui.badge variant="info">{{ ucfirst($channel->status->value) }}</x-ui.badge>
                                    </div>
                                </td>
                                <td data-th="{{ __('Last Sync') }}" class="text-sm text-neutral-500">
                                    {{ $channel->last_synced_at?->diffForHumans() ?? __('Never') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="table-empty-state">{{ __('No connected channels yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-tables.table>

                <x-slot:pagination>
                    <x-tables.pagination :paginator="$channels" />
                </x-slot:pagination>
            </x-tables.datatable>
        </div>
    </div>
</x-layouts.admin>
