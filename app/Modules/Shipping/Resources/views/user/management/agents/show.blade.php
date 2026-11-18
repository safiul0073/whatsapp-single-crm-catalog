<x-layouts.user :title="$agent->name">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('user.shipping.agents.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-body hover:text-title"><i class="ph ph-arrow-left"></i>{{ __('Back to agents') }}</a>
                <h1 class="heading-4 text-title mt-1">{{ $agent->name }}</h1>
                <p class="mt-1 text-sm text-body">{{ collect([$agent->company_name, $agent->carrierLabel(), $agent->whatsapp ?: $agent->phone])->filter()->implode(' · ') }}</p>
            </div>
            @if($canManage)
                <div class="flex gap-2">
                    <x-ui.button :href="route('user.shipping.agents.edit', $agent)" variant="outline">{{ __('Edit') }}</x-ui.button>
                    <form method="POST" action="{{ route('user.shipping.agents.destroy', $agent) }}">@csrf @method('DELETE')<button class="btn btn-ghost" type="submit">{{ __('Delete') }}</button></form>
                </div>
            @endif
        </div>

        <dl class="section-card grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
            <div><dt class="text-body">{{ __('Currency') }}</dt><dd class="font-semibold text-title">{{ $agent->currency }} @if($agent->currency !== $storeCurrency)<span class="text-xs font-normal text-body">× {{ (float) $agent->exchange_rate }} = {{ $storeCurrency }}</span>@endif</dd></div>
            <div><dt class="text-body">{{ __('Fuel surcharge') }}</dt><dd class="font-semibold text-title">{{ (float) $agent->fuel_surcharge_percent }}%</dd></div>
            <div><dt class="text-body">{{ $agent->other_fee_label ?: __('Other fee') }}</dt><dd class="font-semibold text-title">{{ number_format((float) $agent->other_fee, 2) }}</dd></div>
            <div><dt class="text-body">{{ __('Weight rule') }}</dt><dd class="font-semibold text-title">÷{{ $agent->dim_divisor }}, {{ (float) $agent->weight_rounding_step }} kg</dd></div>
            <div><dt class="text-body">{{ __('Payment') }}</dt><dd>@include('shipping::user.management.partials.status-badge', ['status' => $agent->payment_status])</dd></div>
        </dl>

        @if($canManage)
            <section class="section-card">
                <h2 class="heading-5 text-title">{{ __('Add a rate chart') }}</h2>
                <p class="mt-1 text-sm text-body">{{ __('Excel or CSV columns: country (2-letter code), zone, min weight, max weight (or weight), price, price per kg, transit days. PDF charts are stored for reference and their rates are entered by hand. Every chart must be reviewed and activated before it is used.') }}</p>
                <form method="POST" action="{{ route('user.shipping.charts.store', $agent) }}" enctype="multipart/form-data" class="mt-4 grid gap-4 md:grid-cols-5">
                    @csrf
                    <div class="md:col-span-2"><label class="form-label" for="chart_name">{{ __('Chart name') }}</label><input id="chart_name" class="form-input" name="name" maxlength="120" required value="{{ old('name') }}" placeholder="{{ __('e.g. UPS Express — November') }}">@error('name')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                    <div><label class="form-label" for="chart_currency">{{ __('Currency') }}</label><input id="chart_currency" class="form-input" name="currency" maxlength="3" required value="{{ old('currency', $agent->currency) }}"></div>
                    <div><label class="form-label" for="effective_from">{{ __('Effective from') }}</label><input id="effective_from" class="form-input" type="date" name="effective_from" value="{{ old('effective_from') }}"></div>
                    <div><label class="form-label" for="expires_on">{{ __('Expires on') }}</label><input id="expires_on" class="form-input" type="date" name="expires_on" value="{{ old('expires_on') }}">@error('expires_on')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                    <div class="md:col-span-3"><label class="form-label" for="chart_file">{{ __('File (Excel, CSV or PDF — optional)') }}</label><input id="chart_file" class="form-input" type="file" name="file" accept=".xlsx,.xls,.csv,.pdf">@error('file')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                    <div class="flex items-end md:col-span-2"><x-forms.submit :label="__('Save rate chart')" /></div>
                </form>
            </section>
        @endif

        @error('status')<p class="form-hint text-error">{{ $message }}</p>@enderror

        @forelse($charts as $chart)
            <section class="section-card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="heading-5 text-title">{{ $chart->name }}</h2>
                        <p class="mt-1 text-sm text-body">
                            {{ $chart->currency }} · {{ __(':count rate rows', ['count' => $chart->rows_count]) }}
                            @if($chart->effective_from) · {{ __('from :date', ['date' => $chart->effective_from->format('d M Y')]) }}@endif
                            @if($chart->expires_on) · {{ __('until :date', ['date' => $chart->expires_on->format('d M Y')]) }}@endif
                            @if($chart->file_path) · <a class="text-primary" href="{{ route('user.shipping.charts.file', $chart) }}">{{ $chart->original_name }}</a>@endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @include('shipping::user.management.partials.status-badge', ['status' => $chart->status])
                        @if($chart->isExpired())<span class="badge badge-error">{{ __('Expired') }}</span>@endif
                        @if($canManage)
                            @if($chart->status !== 'active')
                                <form method="POST" action="{{ route('user.shipping.charts.status', $chart) }}">@csrf @method('PUT')<input type="hidden" name="status" value="active"><button class="btn btn-primary btn-sm" type="submit">{{ __('Reviewed — activate') }}</button></form>
                            @else
                                <form method="POST" action="{{ route('user.shipping.charts.status', $chart) }}">@csrf @method('PUT')<input type="hidden" name="status" value="archived"><button class="btn btn-outline btn-sm" type="submit">{{ __('Archive') }}</button></form>
                            @endif
                            <form method="POST" action="{{ route('user.shipping.charts.destroy', $chart) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit">{{ __('Delete') }}</button></form>
                        @endif
                    </div>
                </div>

                @if($chart->rows->isNotEmpty())
                    <div class="table-responsive mt-4">
                        <table class="table w-full text-sm whitespace-nowrap">
                            <thead><tr><th>{{ __('Country') }}</th><th>{{ __('Zone') }}</th><th>{{ __('From (kg)') }}</th><th>{{ __('To (kg)') }}</th><th class="text-right">{{ __('Price') }}</th><th class="text-right">{{ __('Per kg') }}</th><th>{{ __('Transit') }}</th><th></th></tr></thead>
                            <tbody>
                                @foreach($chart->rows as $row)
                                    <tr>
                                        <td>{{ $row->country_code ?: __('Any') }}</td>
                                        <td>{{ $row->zone ?: '—' }}</td>
                                        <td>{{ (float) $row->min_weight_kg }}</td>
                                        <td>{{ $row->max_weight_kg !== null ? (float) $row->max_weight_kg : __('No limit') }}</td>
                                        <td class="text-right">{{ number_format((float) $row->price, 2) }}</td>
                                        <td class="text-right">{{ $row->price_per_kg !== null ? number_format((float) $row->price_per_kg, 2) : '—' }}</td>
                                        <td>{{ $row->transit_days ?: '—' }}</td>
                                        <td class="text-right">@if($canManage)<form method="POST" action="{{ route('user.shipping.charts.rows.destroy', $row) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit" aria-label="{{ __('Remove rate row') }}"><i class="ph ph-trash"></i></button></form>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($chart->rows_count > $chart->rows->count())<p class="form-hint">{{ __('Showing the first :shown of :total rows.', ['shown' => $chart->rows->count(), 'total' => $chart->rows_count]) }}</p>@endif
                @endif

                @if($canManage)
                    <form method="POST" action="{{ route('user.shipping.charts.rows.store', $chart) }}" class="mt-4 grid gap-3 border-t border-border-soft pt-4 md:grid-cols-8">
                        @csrf
                        <div class="md:col-span-2">
                            <label class="form-label" for="row_country_{{ $chart->id }}">{{ __('Country') }}</label>
                            <select id="row_country_{{ $chart->id }}" class="form-input" name="country_code"><option value="">{{ __('Any country') }}</option>@foreach($countries as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select>
                        </div>
                        <div><label class="form-label" for="row_zone_{{ $chart->id }}">{{ __('Zone') }}</label><input id="row_zone_{{ $chart->id }}" class="form-input" name="zone" maxlength="40"></div>
                        <div><label class="form-label" for="row_min_{{ $chart->id }}">{{ __('From kg') }}</label><input id="row_min_{{ $chart->id }}" class="form-input" type="number" step="0.001" min="0" name="min_weight_kg" required value="0"></div>
                        <div><label class="form-label" for="row_max_{{ $chart->id }}">{{ __('To kg') }}</label><input id="row_max_{{ $chart->id }}" class="form-input" type="number" step="0.001" min="0" name="max_weight_kg"></div>
                        <div><label class="form-label" for="row_price_{{ $chart->id }}">{{ __('Price') }}</label><input id="row_price_{{ $chart->id }}" class="form-input" type="number" step="0.01" min="0" name="price" required value="0"></div>
                        <div><label class="form-label" for="row_perkg_{{ $chart->id }}">{{ __('Per kg') }}</label><input id="row_perkg_{{ $chart->id }}" class="form-input" type="number" step="0.01" min="0" name="price_per_kg"></div>
                        <div><label class="form-label" for="row_transit_{{ $chart->id }}">{{ __('Transit') }}</label><input id="row_transit_{{ $chart->id }}" class="form-input" name="transit_days" maxlength="40"></div>
                        <div class="md:col-span-8"><button class="btn btn-outline btn-sm" type="submit">{{ __('Add rate row') }}</button> <span class="form-hint">{{ __('Cost = price + (per kg × billable weight).') }}</span></div>
                    </form>
                @endif
            </section>
        @empty
            <p class="section-card text-center text-body">{{ __('No rate charts yet for this agent.') }}</p>
        @endforelse
    </div>
</x-layouts.user>
