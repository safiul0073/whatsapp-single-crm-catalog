@php
    $variant = match ($status) {
        'delivered', 'closed', 'paid', 'verified', 'active', 'normal', 'clear', 'reconciled', 'matched' => 'success',
        'approved', 'booked', 'in_transit', 'out_for_delivery' => 'info',
        'cancelled', 'rejected', 'overdue', 'extended_remote', 'exception', 'unmatched' => 'error',
        'quoted', 'unverified', 'pending_review', 'remote', 'partial', 'due', 'unpaid', 'needs_review', 'duplicate', 'draft' => 'warning',
        default => 'neutral',
    };
@endphp
<span class="badge badge-{{ $variant }}">{{ $label ?? str($status)->replace('_', ' ')->title() }}</span>
