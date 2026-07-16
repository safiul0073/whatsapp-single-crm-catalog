# Payment Gateways

A multi-gateway payment abstraction system. Developers charge payments with one line of code, admins configure credentials through the UI, and the system handles all gateway differences (redirect, embedded, client-side) automatically.

---

## Table of Contents

- [Overview](#overview)
- [How It Works](#how-it-works)
- [Quick Start: Charging a Payment](#quick-start-charging-a-payment)
- [Understanding Payment Flows](#understanding-payment-flows)
  - [Redirect Flow (PayPal, SSLCommerz, Paystack)](#redirect-flow)
  - [Client-Side Flow (Stripe)](#client-side-flow)
  - [Embedded Modal Flow (Razorpay, Flutterwave)](#embedded-modal-flow)
  - [Handling All Flows in One Controller](#handling-all-flows-in-one-controller)
- [Available Gateways](#available-gateways)
- [Gateway Setup](#gateway-setup)
  - [Stripe](#stripe)
  - [PayPal](#paypal)
  - [Razorpay](#razorpay)
  - [SSLCommerz](#sslcommerz)
  - [Paystack](#paystack)
  - [Flutterwave](#flutterwave)
  - [Log (Development)](#log-development)
- [Webhooks](#webhooks)
  - [Webhook URL](#webhook-url)
  - [Webhook Processing](#webhook-processing)
- [Events](#events)
- [PaymentResponse DTO](#paymentresponse-dto)
- [Adding a New Gateway](#adding-a-new-gateway)
  - [Using the Scaffold Command](#using-the-scaffold-command)
  - [Implementing the Interface](#implementing-the-interface)
  - [Removing a Gateway](#removing-a-gateway)
- [Admin Panel](#admin-panel)
- [Artisan Commands](#artisan-commands)
- [Architecture Overview](#architecture-overview)
- [Troubleshooting](#troubleshooting)

---

## Overview

The payment system uses the same **driver pattern** as the SMS notification channels:

1. **Interface** — `PaymentGatewayInterface` defines 6 methods every gateway implements
2. **Drivers** — each gateway (Stripe, PayPal, etc.) is a driver class
3. **Manager** — resolves the active driver based on `payment_gateway_setting('payment_gateway')`
4. **PaymentService** — wraps the manager with logging, events, and error handling
5. **Admin UI** — credentials configured in Settings → Payment Gateways (each gateway gets its own tab)

---

## How It Works

```
Developer calls: PaymentService::charge(29.99, 'USD')
        |
        v
PaymentService creates Payment record (status: pending)
        |
        v
PaymentGatewayManager resolves active driver
        |
        v
Driver::createPayment() calls gateway API
        |
        v
Returns PaymentResponse (polymorphic):
  ├── isRedirect()        → return redirect URL (PayPal, SSLCommerz, Paystack)
  ├── requiresClientAction() → return client data to JS (Stripe, Razorpay, Flutterwave)
  └── isComplete()        → done immediately (Log driver)
        |
        v
User completes payment → returns to your app
        |
        v
PaymentService::verify() confirms with gateway API
        |
        v
Webhook confirms asynchronously (source of truth)
```

---

## Quick Start: Charging a Payment

```php
use App\Modules\PaymentGateways\Services\PaymentService;

// One-liner charge
$result = app(PaymentService::class)->charge(29.99, 'USD', [
    'description' => 'Pro Plan Subscription',
    'return_url'  => route('payment.return'),
    'cancel_url'  => route('payment.cancel'),
    'metadata'    => ['plan_id' => 5],
]);

$payment  = $result['payment'];   // Payment model (saved to DB)
$response = $result['response'];  // PaymentResponse DTO

// Handle the gateway's flow type
if ($response->isRedirect()) {
    return redirect($response->redirectUrl);       // PayPal, SSLCommerz, Paystack
}

if ($response->requiresClientAction()) {
    return response()->json($response->clientData); // Stripe, Razorpay, Flutterwave
}

// Log driver: already complete
return redirect()->route('payment.success');
```

---

## Understanding Payment Flows

Gateways have 3 fundamentally different payment flows. The `PaymentResponse` DTO tells you which one to use.

### Redirect Flow

**Gateways:** PayPal, SSLCommerz, Paystack

User leaves your site, pays on the gateway's page, then returns.

```php
$result = app(PaymentService::class)->charge(29.99, 'USD', [
    'return_url' => route('payment.return'),
    'cancel_url' => route('payment.cancel'),
]);

if ($result['response']->isRedirect()) {
    return redirect($result['response']->redirectUrl);
}
```

**Return handler:**
```php
// routes/web.php or panel routes
Route::get('payment/return', function (Request $request) {
    $payment = app(PaymentService::class)->verify($request);

    if ($payment->status === 'completed') {
        return redirect()->route('dashboard')->with('success', 'Payment successful!');
    }

    return redirect()->route('dashboard')->with('error', 'Payment failed.');
});
```

### Client-Side Flow

**Gateways:** Stripe

Server creates a PaymentIntent, frontend confirms with Stripe.js.

```php
// Controller
$result = app(PaymentService::class)->charge(29.99, 'USD');
return response()->json($result['response']->clientData);
// Returns: { client_secret: "pi_..._secret_...", publishable_key: "pk_..." }
```

```javascript
// Frontend (Alpine.js or vanilla JS)
const stripe = Stripe(clientData.publishable_key);
const { error } = await stripe.confirmPayment({
    clientSecret: clientData.client_secret,
    confirmParams: { return_url: '/payment/return' }
});
```

### Embedded Modal Flow

**Gateways:** Razorpay, Flutterwave

A payment modal opens on your page.

```php
// Controller
$result = app(PaymentService::class)->charge(29.99, 'USD');
return response()->json($result['response']->clientData);
// Returns: { order_id: "order_...", key_id: "rzp_...", amount: 2999 }
```

```javascript
// Frontend (Razorpay example)
const rzp = new Razorpay({
    key: clientData.key_id,
    order_id: clientData.order_id,
    amount: clientData.amount,
    handler: function (response) {
        // POST response to your verify endpoint
        fetch('/payment/verify', {
            method: 'POST',
            body: JSON.stringify(response)
        });
    }
});
rzp.open();
```

### Handling All Flows in One Controller

```php
class CheckoutController extends Controller
{
    public function charge(Request $request, PaymentService $paymentService)
    {
        $result = $paymentService->charge(
            $request->amount,
            'USD',
            [
                'description' => $request->description,
                'return_url'  => route('payment.return'),
                'cancel_url'  => route('payment.cancel'),
            ]
        );

        $response = $result['response'];

        if ($response->isRedirect()) {
            return redirect($response->redirectUrl);
        }

        if ($response->requiresClientAction()) {
            return view('checkout.confirm', [
                'clientData' => $response->clientData,
                'gateway'    => payment_gateway()->name(),
                'paymentId'  => $result['payment']->id,
            ]);
        }

        return redirect()->route('payment.success');
    }

    public function verify(Request $request, PaymentService $paymentService)
    {
        $payment = $paymentService->verify($request);

        return $payment->status === 'completed'
            ? redirect()->route('dashboard')->with('success', 'Payment successful!')
            : redirect()->route('dashboard')->with('error', 'Payment failed.');
    }
}
```

---

## Available Gateways

| Gateway | Flow Type | SDK Package | Currencies |
|---------|-----------|-------------|------------|
| **Stripe** | Client-side | `stripe/stripe-php` | 135+ |
| **PayPal** | Redirect | `blendbyte/paypal` | 100+ |
| **Razorpay** | Embedded modal | `razorpay/razorpay` | 160+ (INR primary) |
| **SSLCommerz** | Redirect | HTTP (no SDK) | BDT, USD, EUR, GBP |
| **Paystack** | Redirect | HTTP (no SDK) | NGN, GHS, ZAR, KES, USD |
| **Flutterwave** | Redirect/Inline | HTTP (no SDK) | NGN, GHS, KES, USD, EUR, GBP |
| **Log** | Immediate | None | Any (dev only) |

---

## Gateway Setup

All gateway credentials are configured in the admin panel at **Settings → Payment Gateways**. Each gateway has its own tab.

### Stripe

1. Create a Stripe account at [stripe.com](https://stripe.com)
2. Get your API keys from Dashboard → Developers → API keys
3. In admin panel → Settings → Payment Gateways → Stripe tab:
   - **Publishable Key**: `pk_test_...` or `pk_live_...`
   - **Secret Key**: `STRIPE_SECRET_KEY_PLACEHOLDER` or `STRIPE_SECRET_KEY_PLACEHOLDER`
   - **Webhook Secret**: `whsec_...` (from Webhook settings)
4. Set **Active Payment Gateway** to "Stripe" in the General tab
5. Register webhook URL in Stripe Dashboard: `https://yoursite.com/webhooks/payments/stripe`
   - Events to subscribe: `payment_intent.succeeded`, `payment_intent.payment_failed`

### PayPal

1. Create a PayPal Developer account at [developer.paypal.com](https://developer.paypal.com)
2. Create an app in Dashboard → My Apps & Credentials
3. In admin panel → Settings → Payment Gateways → PayPal tab:
   - **Client ID**: from your PayPal app
   - **Client Secret**: from your PayPal app
   - **Sandbox Mode**: Enable for testing, disable for production
4. Set **Active Payment Gateway** to "PayPal" in the General tab
5. Register webhook URL: `https://yoursite.com/webhooks/payments/paypal`
   - Events: `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`

### Razorpay

1. Create a Razorpay account at [razorpay.com](https://razorpay.com)
2. Get keys from Dashboard → Settings → API Keys
3. In admin panel → Settings → Payment Gateways → Razorpay tab:
   - **Key ID**: `rzp_test_...` or `rzp_live_...`
   - **Key Secret**: your Razorpay key secret
4. Set **Active Payment Gateway** to "Razorpay" in the General tab
5. Register webhook URL: `https://yoursite.com/webhooks/payments/razorpay`
   - Events: `payment.authorized`, `payment.captured`

### SSLCommerz

1. Register at [sslcommerz.com](https://sslcommerz.com)
2. Get Store ID and Password from your merchant panel
3. In admin panel → Settings → Payment Gateways → SSLCommerz tab:
   - **Store ID**: your SSLCommerz store ID
   - **Store Password**: your store password
   - **Sandbox Mode**: Enable for testing (uses `sandbox.sslcommerz.com`)
4. Set **Active Payment Gateway** to "SSLCommerz" in the General tab
5. Configure IPN URL in SSLCommerz panel: `https://yoursite.com/webhooks/payments/sslcommerz`

### Paystack

1. Create a Paystack account at [paystack.com](https://paystack.com)
2. Get keys from Dashboard → Settings → API Keys & Webhooks
3. In admin panel → Settings → Payment Gateways → Paystack tab:
   - **Public Key**: `pk_test_...` or `pk_live_...`
   - **Secret Key**: `STRIPE_SECRET_KEY_PLACEHOLDER` or `STRIPE_SECRET_KEY_PLACEHOLDER`
4. Set **Active Payment Gateway** to "Paystack" in the General tab
5. Register webhook URL: `https://yoursite.com/webhooks/payments/paystack`
   - Paystack sends all events automatically when webhook URL is set

### Flutterwave

1. Create a Flutterwave account at [flutterwave.com](https://flutterwave.com)
2. Get keys from Dashboard → Settings → API Keys
3. In admin panel → Settings → Payment Gateways → Flutterwave tab:
   - **Public Key**: `FLWPUBK-...`
   - **Secret Key**: `FLWSECK-...`
   - **Encryption Key**: your encryption key
4. Set **Active Payment Gateway** to "Flutterwave" in the General tab
5. Register webhook URL: `https://yoursite.com/webhooks/payments/flutterwave`

### Log (Development)

The default gateway. Auto-completes all payments and logs them to `storage/logs/laravel.log`. No configuration needed.

---

## Webhooks

### Webhook URL

Every gateway shares the same URL pattern:

```
POST https://yoursite.com/webhooks/payments/{gateway}
```

| Gateway | Webhook URL |
|---------|-------------|
| Stripe | `https://yoursite.com/webhooks/payments/stripe` |
| PayPal | `https://yoursite.com/webhooks/payments/paypal` |
| Razorpay | `https://yoursite.com/webhooks/payments/razorpay` |
| SSLCommerz | `https://yoursite.com/webhooks/payments/sslcommerz` |
| Paystack | `https://yoursite.com/webhooks/payments/paystack` |
| Flutterwave | `https://yoursite.com/webhooks/payments/flutterwave` |

This route has no authentication and no CSRF protection (registered automatically by the ServiceProvider).

### Webhook Processing

1. Webhook arrives → `WebhookController` logs it to `webhook_logs` table
2. Idempotency check: if the event ID was already processed, returns 200 immediately
3. Signature verification: each driver verifies the webhook authenticity
4. Queued processing: `ProcessWebhook` job handles the payment status update asynchronously
5. Events dispatched: `PaymentSucceeded` or `PaymentFailed`

---

## Events

Listen to these events in your application:

```php
use App\Modules\PaymentGateways\Events\PaymentSucceeded;
use App\Modules\PaymentGateways\Events\PaymentFailed;
use App\Modules\PaymentGateways\Events\PaymentCreated;

// In a listener or EventServiceProvider
Event::listen(PaymentSucceeded::class, function (PaymentSucceeded $event) {
    $payment = $event->payment;
    // Send confirmation email, activate subscription, etc.
    $payment->user->notify(new PaymentConfirmationNotification($payment));
});

Event::listen(PaymentFailed::class, function (PaymentFailed $event) {
    // Notify user, log failure, etc.
});
```

---

## PaymentResponse DTO

The `createPayment()` method returns a `PaymentResponse` with these helpers:

```php
$response->isRedirect()           // true for PayPal, SSLCommerz, Paystack
$response->requiresClientAction() // true for Stripe, Razorpay, Flutterwave
$response->isComplete()           // true for Log driver (immediate)
$response->isFailed()             // true if gateway returned error

$response->redirectUrl            // URL to redirect user to (redirect flow)
$response->clientData             // Array of data for frontend JS (embedded flow)
$response->gatewayPaymentId       // Gateway's reference ID
$response->status                 // pending, processing, completed, failed
$response->message                // Error message (if failed)
$response->metadata               // Additional gateway-specific data
```

---

## Adding a New Gateway

### Using the Scaffold Command

```bash
php artisan make:payment-gateway Mollie
```

This automatically:
1. Creates `app/Modules/PaymentGateways/Drivers/MolliePaymentGateway.php`
2. Registers it in `PaymentGatewayManager`'s `match()` statement
3. Adds a **Mollie** settings tab to `config/payment-gateway-settings.php`
4. Adds "Mollie" to the gateway dropdown in the General tab
5. Clears config cache and seeds new settings into the database

### Implementing the Interface

Edit the generated driver file and implement the 6 methods:

```php
class MolliePaymentGateway implements PaymentGatewayInterface
{
    public function name(): string { return 'mollie'; }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $apiKey = payment_gateway_setting('mollie_api_key');
        // Call Mollie API...
        return PaymentResponse::redirect($id, $checkoutUrl);
    }

    public function verifyPayment(Request $request): PaymentResponse { /* ... */ }
    public function verifyWebhook(Request $request): bool { /* ... */ }
    public function handleWebhook(Request $request): WebhookResult { /* ... */ }
    public function getClientConfig(): array { return ['gateway' => 'mollie']; }
}
```

### Removing a Gateway

```bash
php artisan remove:payment-gateway Mollie
```

Removes: driver file, manager entry, settings tab, select option, and database settings.

---

## Admin Panel

### Settings → Payment Gateways

Located at **Settings → Payment Gateways** in the admin sidebar. Each gateway has its own tab with credential fields. The **General** tab has the active gateway selector and default currency.

### Payments

Located at **Payments → All Payments**. Shows all payment attempts with:
- UUID, amount, gateway, status, customer, date
- Click to view payment detail

### Payments → Webhook Logs

Shows all received webhooks with gateway, event type, processing status, and raw JSON payload viewer.

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `php artisan make:payment-gateway {name}` | Scaffold a new gateway driver with settings tab |
| `php artisan remove:payment-gateway {name}` | Remove a gateway completely |
| `php artisan payment:test {gateway?}` | Test gateway configuration |

---

## Architecture Overview

```
config/payment-gateway-settings.php        (Credentials per gateway — each is a tab)
        |
        v
PaymentGatewaySettings module              (Dedicated DB table, service, helper)
        |
        v
payment_gateway_setting('stripe_secret_key')  (Global helper — reads DB > config)
        |
        v
PaymentGatewayInterface                    (7 methods)
  ├── LogPaymentGateway                    (dev — auto-completes)
  ├── StripePaymentGateway                 (client_secret flow)
  ├── PayPalPaymentGateway                 (redirect flow)
  ├── RazorpayPaymentGateway               (embedded modal flow)
  ├── SslCommerzPaymentGateway             (redirect flow)
  ├── PaystackPaymentGateway               (redirect flow)
  └── FlutterwavePaymentGateway            (redirect/inline flow)
        |
        v
PaymentGatewayManager                      (resolves active driver via match())
        |
        v
PaymentService                             (charge, verify + logging + events)
        |
        v
Payment / WebhookLog models                (DB audit trail)
```

**Key files:**

| File | Purpose |
|------|---------|
| `app/Modules/PaymentGateways/Contracts/PaymentGatewayInterface.php` | The 6-method interface |
| `app/Modules/PaymentGateways/DataObjects/PaymentResponse.php` | Polymorphic response (redirect/client/complete) |
| `app/Modules/PaymentGateways/Services/PaymentGatewayManager.php` | Driver resolver (`match()`) |
| `app/Modules/PaymentGateways/Services/PaymentService.php` | Main API: `charge()`, `verify()` |
| `app/Modules/PaymentGateways/Drivers/` | All gateway driver implementations |
| `app/Modules/PaymentGateways/Helpers/PaymentGatewayHelper.php` | `payment_gateway()` global helper |
| `app/Modules/PaymentGatewaySettings/` | Dedicated settings module (DB table, service, helper) |
| `config/payment-gateway-settings.php` | Gateway credential definitions (each group = tab) |
| `app/Http/Controllers/WebhookController.php` | Public webhook endpoint |
| `stubs/payment-gateway/PaymentGateway.stub` | Scaffold template |

---

## Troubleshooting

### "Credentials not configured" error

Set credentials in admin panel → Settings → Payment Gateways → select the gateway tab and fill in the fields.

### Payment stuck on "pending"

The queue worker is not running. Start it:
```bash
php artisan queue:listen
```
Or use the dev command: `composer run dev`

### Webhook not received

1. Verify the webhook URL is registered in the gateway's dashboard
2. Check `webhook_logs` table for received webhooks
3. For local development, use ngrok or similar to expose your local server

### "Class not found" for Stripe/PayPal/Razorpay

Install the SDK:
```bash
composer require stripe/stripe-php        # Stripe
composer require blendbyte/paypal          # PayPal
composer require razorpay/razorpay         # Razorpay
```

SSLCommerz, Paystack, and Flutterwave use direct HTTP calls — no SDK needed.

### New gateway settings not saving

Run `php artisan config:clear` to clear cached config, then try again.

### Payment works in test but not in production

Check that sandbox/test mode is disabled in the gateway settings tab, and that you're using production API keys.
