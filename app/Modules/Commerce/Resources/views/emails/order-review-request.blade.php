<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('How was your order?') }}</title>
</head>
<body>
    <h2>{{ __('How was your order?') }}</h2>

    <p>{{ __('Hi :name,', ['name' => $order->customer_snapshot['name'] ?? $order->contact?->name ?? __('there')]) }}</p>

    <p>{{ __('Your order #:number has been delivered. We would love to hear what you think of:', ['number' => $order->number]) }}</p>

    <ul>
        @foreach($productNames as $productName)
            <li>{{ $productName }}</li>
        @endforeach
    </ul>

    <p><a href="{{ $reviewUrl }}">{{ __('Rate your products') }}</a></p>

    <p>{{ __('Thank you for shopping with us!') }}</p>
</body>
</html>
