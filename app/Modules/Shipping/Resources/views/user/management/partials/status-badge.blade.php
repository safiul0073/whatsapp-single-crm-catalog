@php
    $variant = match ($status) {
        'delivered', 'closed', 'paid', 'verified', 'active', 'normal', 'clear' => 'success',
        'approved', 'booked', 'in_transit' => 'info',
        'cancelled', 'rejected', 'overdue', 'extended_remote' => 'error',
        'quoted', 'unverified', 'pending_review', 'remote', 'partial', 'due', 'unpaid' => 'warning',
        default => 'neutral',
    };
@endphp
<span class="badge badge-{{ $variant }}">{{ $label ?? str($status)->replace('_', ' ')->title() }}</span>
