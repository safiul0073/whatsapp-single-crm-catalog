<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your Order Has Shipped</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Great news! Your order is on the way.</h2>
    
    <p>Hi {{ $order->contact->first_name ?? 'Customer' }},</p>
    
    <p>Your order <strong>#{{ $order->number }}</strong> has been shipped and is currently in transit.</p>
    
    @if($order->tracking_number)
    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
        <p style="margin: 0 0 10px 0;"><strong>Tracking Number:</strong> {{ $order->tracking_number }}</p>
        
        @if($order->tracking_url)
            <a href="{{ $order->tracking_url }}" style="display: inline-block; padding: 10px 20px; background-color: #0f172a; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Track Your Package
            </a>
        @endif
    </div>
    @endif
    
    <h3>Order Summary</h3>
    <ul style="list-style-type: none; padding-left: 0;">
        @foreach($order->items as $item)
            <li style="border-bottom: 1px solid #eee; padding: 10px 0;">
                {{ $item->product_name }} x {{ $item->quantity }}
            </li>
        @endforeach
    </ul>
    
    <p>If you have any questions, please reply to this email or contact our support team.</p>
    
    <p>Thank you for shopping with us!</p>
</body>
</html>
