<x-layouts.user :title="__('Shipping Rate Calculator')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', ['tabs' => 'rates', 'heading' => __('Shipping Rate Calculator'), 'intro' => __('Work out billable weight for a set of cartons under each courier and agent rule, before a shipment exists.')])

        <form method="POST" action="{{ route('user.shipping.calculator.calculate') }}" class="section-card space-y-3">
            @csrf
            @include('shipping::user.management.partials.carton-rows', ['cartons' => $cartons])
            <x-forms.submit :label="__('Calculate billable weight')" />
        </form>

        @if($results)
            <section class="section-card">
                <h2 class="heading-5 text-title">{{ __('Billable weight by provider') }}</h2>
                <div class="table-responsive mt-3">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Provider') }}</th><th>{{ __('Divisor') }}</th><th>{{ __('Rounds up to') }}</th><th>{{ __('Packages') }}</th><th>{{ __('Actual') }}</th><th>{{ __('Dimensional') }}</th><th>{{ __('Billable') }}</th><th>{{ __('Flags') }}</th></tr></thead>
                        <tbody>
                            @foreach($results as $result)
                                <tr>
                                    <td class="font-semibold text-title">{{ $result['name'] }}</td>
                                    <td>{{ $result['divisor'] }}</td>
                                    <td>{{ $result['rounding_step'] }} kg</td>
                                    <td>{{ $result['packages'] }}</td>
                                    <td>{{ $result['actual_weight_kg'] }} kg</td>
                                    <td>{{ $result['dimensional_weight_kg'] }} kg</td>
                                    <td class="font-semibold text-title">{{ $result['billable_weight_kg'] }} kg</td>
                                    <td>@forelse($result['flags'] as $flag)<span class="badge badge-warning">{{ __($flagLabels[$flag] ?? $flag) }}</span> @empty — @endforelse</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="form-hint">{{ __('Handling limits are editable defaults. Confirm them against each provider\'s current service guide.') }}</p>
            </section>
        @endif
    </div>
</x-layouts.user>
