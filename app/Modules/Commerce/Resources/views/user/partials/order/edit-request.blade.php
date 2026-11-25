@if($editRequest && in_array($editRequest->status, ['pending', 'approved', 'used'], true))
    @php
        $editDifference = $editRequest->difference !== null ? (float) $editRequest->difference : 0.0;
    @endphp
    <section id="edit-request" class="space-y-3 rounded-md border border-primary/30 bg-primary/10 p-4 text-sm text-title" aria-label="{{ __('Customer edit request') }}">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 font-semibold"><i class="ph ph-pencil-simple-line"></i> {{ __('Customer edit request') }} · {{ $editRequest->reference }}</h2>
            <span class="text-xs text-body">{{ $editRequest->created_at?->format('M j, Y · g:i A') }}</span>
        </div>

        @if($editRequest->customer_note)
            <p class="text-body"><span class="font-medium text-title">{{ __('Customer note') }}:</span> {{ $editRequest->customer_note }}</p>
        @endif

        @if($editRequest->status === 'pending')
            <p class="text-body">{{ __('The customer wants to change this order. If you approve, they can edit the products once. Any change in total is collected or refunded by you outside the app.') }}</p>
            @if($canUpdate)
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <form method="POST" action="{{ route('user.commerce.orders.edit-requests.decide', [$order, $editRequest]) }}" class="flex-1 space-y-2">
                        @csrf @method('PUT')
                        <label for="editRequestNote" class="form-label">{{ __('Note to customer (optional)') }}</label>
                        <input id="editRequestNote" type="text" name="owner_note" maxlength="500" class="form-input w-full text-sm">
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="decision" value="approve" class="btn btn-primary btn-sm" data-confirm data-confirm-title="{{ __('Approve this edit?') }}" data-confirm-message="{{ __('The customer can change the products of this order one time.') }}" data-confirm-button="{{ __('Approve') }}" data-confirm-variant="primary" data-confirm-icon="ph-check-circle"><i class="ph ph-check-circle"></i> {{ __('Approve edit') }}</button>
                            <button type="submit" name="decision" value="reject" class="btn btn-outline btn-sm" data-confirm data-confirm-title="{{ __('Decline this edit?') }}" data-confirm-message="{{ __('The order stays as it is.') }}" data-confirm-button="{{ __('Decline') }}" data-confirm-variant="danger"><i class="ph ph-x-circle"></i> {{ __('Decline') }}</button>
                        </div>
                    </form>
                </div>
            @endif
        @elseif($editRequest->status === 'approved')
            <p class="text-body">{{ __('Approved. Waiting for the customer to edit the order (one time).') }}</p>
            @if($canUpdate)
                <form method="POST" action="{{ route('user.commerce.orders.edit-requests.decide', [$order, $editRequest]) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="decision" value="revoke">
                    <button type="submit" class="btn btn-outline btn-sm" data-confirm data-confirm-title="{{ __('Withdraw edit permission?') }}" data-confirm-message="{{ __('The customer will no longer be able to edit this order.') }}" data-confirm-button="{{ __('Withdraw') }}" data-confirm-variant="danger"><i class="ph ph-prohibit"></i> {{ __('Withdraw permission') }}</button>
                </form>
            @endif
        @else
            <dl class="grid gap-2 sm:grid-cols-3">
                <div><dt class="text-body">{{ __('Total before') }}</dt><dd class="font-semibold tabular-nums">{{ $money($editRequest->previous_total) }}</dd></div>
                <div><dt class="text-body">{{ __('Total after') }}</dt><dd class="font-semibold tabular-nums">{{ $money($editRequest->new_total) }}</dd></div>
                <div>
                    <dt class="text-body">{{ $editDifference > 0 ? __('Collect from customer') : ($editDifference < 0 ? __('Refund to customer') : __('Difference')) }}</dt>
                    <dd class="font-semibold tabular-nums {{ $editDifference > 0 ? 'text-warning' : ($editDifference < 0 ? 'text-error' : '') }}">{{ $money(abs($editDifference)) }}</dd>
                </div>
            </dl>
            <p class="text-xs text-body">{{ __('Edited by the customer on :time.', ['time' => $editRequest->used_at?->format('M j, Y · g:i A')]) }}</p>
        @endif
    </section>
@endif
