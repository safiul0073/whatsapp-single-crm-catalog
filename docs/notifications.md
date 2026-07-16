# Notification System

A multi-channel notification system with admin-editable templates. Developers define notification types in code, admins customize content through the UI, and dispatching works across 5 channels automatically.

---

## Table of Contents

- [Overview](#overview)
- [How It Works](#how-it-works)
- [Quick Start: Creating a Notification](#quick-start-creating-a-notification)
  - [Step 1: Define the Template in Config](#step-1-define-the-template-in-config)
  - [Step 2: Create the Notification Class](#step-2-create-the-notification-class)
  - [Step 3: Sync to Database](#step-3-sync-to-database)
  - [Step 4: Dispatch the Notification](#step-4-dispatch-the-notification)
- [Using the Artisan Scaffold Command](#using-the-artisan-scaffold-command)
- [Template Variables](#template-variables)
  - [Custom Variables](#custom-variables)
  - [Automatic Variables](#automatic-variables)
  - [Global Variables](#global-variables)
- [Channels](#channels)
  - [Email](#email)
  - [In-App (Bell Icon)](#in-app-bell-icon)
  - [SMS](#sms)
  - [Web Push (VAPID)](#web-push-vapid)
  - [Mobile Push (Firebase)](#mobile-push-firebase)
- [Channel Configuration](#channel-configuration)
  - [Enable/Disable Channels](#enabledisable-channels)
  - [SMS Provider Setup](#sms-provider-setup)
  - [Web Push (VAPID) Setup](#web-push-vapid-setup)
  - [Firebase (FCM) Setup](#firebase-fcm-setup)
- [Config Reference](#config-reference)
  - [Template Config Structure](#template-config-structure)
  - [Available Default Keys](#available-default-keys)
- [Notification Class Reference](#notification-class-reference)
  - [Required Methods](#required-methods)
  - [Optional Overrides](#optional-overrides)
  - [Channel Support Rules](#channel-support-rules)
- [Admin UI](#admin-ui)
  - [Template Editor](#template-editor)
  - [Notification Logs](#notification-logs)
- [Artisan Commands](#artisan-commands)
- [Full Example: Order Shipped Notification](#full-example-order-shipped-notification)
- [Custom SMS Driver](#custom-sms-driver)
- [Architecture Overview](#architecture-overview)
- [Troubleshooting](#troubleshooting)

---

## Overview

The notification system uses a **config-to-DB pattern** (same as the Settings module):

1. **Developers** define notification templates in `config/notification-templates.php`
2. **Seeder/sync command** populates the database with default content
3. **Admins** edit the content (subject, body, etc.) via the admin panel UI
4. **Developers** dispatch notifications using simple Laravel syntax

**5 supported channels:** Email, In-App (bell icon), SMS, Web Push, Mobile Push (Firebase)

---

## How It Works

```
Developer defines template in config
        |
        v
php artisan notification:sync  (creates DB record with defaults)
        |
        v
Admin edits content in UI  (optional — defaults work out of the box)
        |
        v
$user->notify(new OrderShippedNotification($order))
        |
        v
BaseTemplateNotification loads template from DB
        |
        v
For each enabled channel:
  1. Check global setting toggle (Settings → Notifications)
  2. Check per-template channel toggle
  3. Check if recipient supports the channel (has email? has phone?)
  4. Render template with variables
  5. Dispatch & log
```

---

## Quick Start: Creating a Notification

### Step 1: Define the Template in Config

Open `config/notification-templates.php` and add your template:

```php
'order-shipped' => [
    'name' => 'Order Shipped',
    'description' => 'Sent when an order is shipped to the customer',
    'channels' => ['email', 'in_app', 'sms'],
    'variables' => [
        'order_id'       => 'The order number',
        'tracking_number' => 'Shipping tracking number',
        'tracking_url'   => 'URL to track the shipment',
    ],
    'defaults' => [
        'email_subject' => 'Your order #{{order_id}} has been shipped!',
        'email_body'    => '<p>Hi {{user_name}},</p><p>Great news! Your order <strong>#{{order_id}}</strong> has been shipped.</p><p>Tracking number: <strong>{{tracking_number}}</strong></p>',
        'sms_body'      => 'Your order #{{order_id}} has shipped! Track it: {{tracking_url}}',
        'in_app_title'  => 'Order #{{order_id}} Shipped',
        'in_app_body'   => 'Your order has been shipped. Tracking: {{tracking_number}}',
    ],
],
```

### Step 2: Create the Notification Class

Create `app/Modules/NotificationTemplates/Notifications/OrderShippedNotification.php`:

```php
<?php

namespace App\Modules\NotificationTemplates\Notifications;

use App\Modules\Orders\Models\Order;

class OrderShippedNotification extends BaseTemplateNotification
{
    public function __construct(
        protected Order $order
    ) {}

    protected function templateSlug(): string
    {
        return 'order-shipped';
    }

    protected function templateVariables(): array
    {
        return [
            'order_id'        => $this->order->id,
            'tracking_number' => $this->order->tracking_number,
            'tracking_url'    => $this->order->tracking_url,
        ];
    }

    protected function actionUrl(): ?string
    {
        return $this->order->tracking_url;
    }

    protected function actionText(): ?string
    {
        return 'Track Your Order';
    }

    protected function inAppIcon(): string
    {
        return 'ph-truck';
    }

    protected function inAppType(): string
    {
        return 'success';
    }
}
```

### Step 3: Sync to Database

```bash
php artisan notification:sync
```

Output:
```
Syncing notification templates...
  Created: 1
  Skipped (already exist): 2
Done!
```

The template now appears in the admin panel under **Notifications → Templates**, where admins can customize the content.

### Step 4: Dispatch the Notification

```php
// Send to a single user
$user->notify(new OrderShippedNotification($order));

// Send to multiple users
Notification::send($users, new OrderShippedNotification($order));
```

That's it. The system automatically:
- Loads the template from the database
- Checks which channels are enabled (globally + per-template)
- Checks if the recipient supports each channel
- Renders the template with your variables
- Dispatches to all applicable channels
- Logs every dispatch attempt

---

## Using the Artisan Scaffold Command

Instead of creating files manually, use the scaffold command:

```bash
php artisan make:notification-template OrderShipped
```

This creates two things:
1. **Notification class** at `app/Modules/NotificationTemplates/Notifications/OrderShippedNotification.php`
2. **Config entry** appended to `config/notification-templates.php`

Then edit both files to add your data, and run:

```bash
php artisan notification:sync
```

---

## Template Variables

### Custom Variables

Define in your config's `variables` array. These appear in the admin UI as clickable chips:

```php
'variables' => [
    'order_id'   => 'The order number',        // Shown as tooltip in admin UI
    'item_count' => 'Number of items ordered',
],
```

Use in templates with double curly braces: `{{order_id}}`, `{{item_count}}`

Provide values in your notification class:

```php
protected function templateVariables(): array
{
    return [
        'order_id'   => $this->order->id,
        'item_count' => $this->order->items->count(),
    ];
}
```

### Automatic Variables

These are injected automatically from the recipient (`$notifiable`) — you don't need to define or return them:

| Variable | Source | Description |
|----------|--------|-------------|
| `{{user_name}}` | `$notifiable->name` | Recipient's name |
| `{{user_email}}` | `$notifiable->email` | Recipient's email |

You can override these by returning them in `templateVariables()`.

### Global Variables

Available in every template without defining them:

| Variable | Source | Example Output |
|----------|--------|----------------|
| `{{site_name}}` | `setting('site_name')` | `Admin Panel` |
| `{{site_url}}` | `config('app.url')` | `https://myapp.com` |
| `{{current_year}}` | `date('Y')` | `2026` |

---

## Channels

### Email

Uses the existing mail setup. Template body is rendered as HTML via a Quill.js editor in the admin UI.

**Email includes:**
- Subject line (with variable substitution)
- HTML body (rich text from Quill editor)
- Optional action button (CTA) if `actionUrl()` and `actionText()` are set
- Styled using `resources/views/emails/template-notification.blade.php`
- Extends the shared email layout with logo, brand colors, and footer

**Recipient requirement:** `$notifiable->email` must exist.

### In-App (Bell Icon)

Creates a record in the `system_notifications` table via the existing `SystemNotificationService`. Appears in the bell icon dropdown in the topbar — no changes needed to the existing bell icon system.

**In-App includes:**
- Title and body text
- Icon (Phosphor icon class, e.g., `ph-truck`)
- Type for color-coding (`info`, `success`, `warning`, `danger`)
- Optional URL (clicking the notification navigates to this URL)

**Recipient requirement:** Always supported for any notifiable.

### SMS

Sends via a configurable provider: Log (development), Vonage, or Twilio.

**SMS includes:**
- Plain text message (max 1600 characters)
- Character counter shown in admin UI editor

**Recipient requirement:** `$notifiable->phone` must exist.

**Admin configuration:** Settings → Notifications → SMS Provider

### Web Push (VAPID)

Browser push notifications using the VAPID protocol. Requires a service worker on the frontend (not included — implement based on your frontend framework).

**Push includes:**
- Title and body text
- Optional data payload (action URL)

**Recipient requirement:** User must have push subscriptions (via `HasPushSubscriptions` trait).

### Mobile Push (Firebase)

Push notifications to iOS/Android apps via Firebase Cloud Messaging (FCM).

**Push includes:**
- Title and body text
- Optional data payload (action URL)

**Recipient requirement:** User must have device tokens (via `HasDeviceTokens` trait).

**Note:** Push title and body are shared between Web Push and Mobile Push channels. Both use the same `push_title` and `push_body` fields in the template.

---

## Channel Configuration

### Enable/Disable Channels

Channels are toggled globally in the admin panel at **Settings → Notifications**:

| Setting | Default | Description |
|---------|---------|-------------|
| Email Notifications | Enabled | Send via email |
| SMS Notifications | Disabled | Send via SMS |
| Web Push Notifications | Disabled | Send via browser push |
| Mobile Push Notifications | Disabled | Send via Firebase |

In-App notifications are always enabled (they're just database records for the bell icon).

Individual templates can also toggle channels on their **Settings** tab in the template editor.

**Both must be enabled** for a channel to work: the global toggle AND the per-template toggle.

### SMS Provider Setup

**1. Choose a provider** in Settings → Notifications → SMS Provider:
- **Log (Development)** — Messages are logged to `storage/logs/laravel.log`. Default for development.
- **Vonage (Nexmo)** — Production SMS via Vonage.
- **Twilio** — Production SMS via Twilio.

**2. Enter credentials** in Settings → Notifications:

For **Vonage**:
- Vonage API Key
- Vonage API Secret
- SMS From Number (e.g., `+1234567890`)

For **Twilio**:
- Twilio Account SID
- Twilio Auth Token
- SMS From Number (e.g., `+1234567890`)

### Web Push (VAPID) Setup

**1. Generate VAPID keys:**

```bash
php artisan webpush:vapid
```

This writes `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY` to your `.env` file.

> **XAMPP on Windows:** If you get "Unable to create the key", run this first:
> ```bash
> set OPENSSL_CONF=D:\xampp\apache\conf\openssl.cnf
> php artisan webpush:vapid
> ```

**2. Set the subject** in your `.env`:

```env
VAPID_SUBJECT=mailto:admin@yoursite.com
```

**3. Enable the channel** in Settings → Notifications → Web Push Notifications.

**4. Implement the frontend service worker** in your app to subscribe users to push notifications. The `push_subscriptions` table (created by migration) stores the subscription data.

**Environment variables:**

```env
VAPID_SUBJECT=mailto:admin@example.com
VAPID_PUBLIC_KEY=    # Generated by webpush:vapid
VAPID_PRIVATE_KEY=   # Generated by webpush:vapid
```

### Firebase (FCM) Setup

**1. Create a Firebase project** at [Firebase Console](https://console.firebase.google.com).

**2. Download the service account JSON** file:
- Go to Project Settings → Service Accounts
- Click "Generate New Private Key"
- Save the JSON file to your project (e.g., `storage/firebase-credentials.json`)

**3. Set the path** in your `.env`:

```env
FIREBASE_CREDENTIALS=storage/firebase-credentials.json
```

**4. Enable the channel** in Settings → Notifications → Mobile Push Notifications.

**5. Register device tokens** from your mobile app. When a user logs in on their mobile device, send the FCM token to your API:

```php
// In your API controller
$user->addDeviceToken($request->fcm_token, $request->platform); // 'ios', 'android', or 'web'
```

The `device_tokens` table stores these tokens. The `HasDeviceTokens` trait on User/Admin models provides helper methods:

```php
$user->addDeviceToken($token, 'android');  // Register a token
$user->removeDeviceToken($token);          // Remove a token
$user->deviceTokens;                       // All tokens
```

---

## Config Reference

### Template Config Structure

File: `config/notification-templates.php`

```php
return [
    'template-slug' => [
        'name'        => 'Template Name',               // Required — shown in admin UI
        'description' => 'When this notification fires', // Optional — shown in admin UI
        'channels'    => ['email', 'in_app'],            // Required — default enabled channels
        'variables'   => [                                // Optional — variable descriptions
            'var_name' => 'Displayed in admin sidebar',
        ],
        'defaults'    => [                                // Optional — initial content
            'email_subject' => 'Subject with {{var_name}}',
            'email_body'    => '<p>HTML body</p>',
            'sms_body'      => 'Plain text message',
            'in_app_title'  => 'Title text',
            'in_app_body'   => 'Body text',
            'push_title'    => 'Push title',
            'push_body'     => 'Push body',
        ],
    ],
];
```

### Available Default Keys

| Key | Used By | Max Length | Format |
|-----|---------|-----------|--------|
| `email_subject` | Email channel | 255 chars | Plain text with `{{variables}}` |
| `email_body` | Email channel | Unlimited | HTML with `{{variables}}` |
| `sms_body` | SMS channel | 1600 chars | Plain text with `{{variables}}` |
| `in_app_title` | In-App channel | 255 chars | Plain text with `{{variables}}` |
| `in_app_body` | In-App channel | 1000 chars | Plain text with `{{variables}}` |
| `push_title` | Web Push + Mobile Push | 255 chars | Plain text with `{{variables}}` |
| `push_body` | Web Push + Mobile Push | 1000 chars | Plain text with `{{variables}}` |

### Available Channels

| Channel Key | Package | Description |
|-------------|---------|-------------|
| `email` | Built-in Laravel Mail | HTML email with template |
| `in_app` | Built-in SystemNotifications | Bell icon notification |
| `sms` | `vonage/client-core` or `twilio/sdk` | SMS text message |
| `web_push` | `laravel-notification-channels/webpush` | Browser push (VAPID) |
| `mobile_push` | `laravel-notification-channels/fcm` | Firebase mobile push |

---

## Notification Class Reference

### Required Methods

Every notification class must extend `BaseTemplateNotification` and implement two methods:

```php
use App\Modules\NotificationTemplates\Notifications\BaseTemplateNotification;

class MyNotification extends BaseTemplateNotification
{
    /**
     * The template slug matching a key in config/notification-templates.php
     */
    protected function templateSlug(): string
    {
        return 'my-template-slug';
    }

    /**
     * Variables to substitute into the template.
     * Keys must match the {{variable}} placeholders.
     */
    protected function templateVariables(): array
    {
        return [
            'order_id' => $this->order->id,
        ];
    }
}
```

### Optional Overrides

| Method | Return Type | Default | Purpose |
|--------|-------------|---------|---------|
| `actionUrl()` | `?string` | `null` | URL for the email CTA button and in-app notification link |
| `actionText()` | `?string` | `null` | Label for the email CTA button (e.g., "View Order") |
| `inAppIcon()` | `string` | `'ph-bell'` | Phosphor icon class for the in-app notification |
| `inAppType()` | `string` | `'info'` | Visual style: `info`, `success`, `warning`, `danger` |

**Example with all overrides:**

```php
class InvoicePaidNotification extends BaseTemplateNotification
{
    public function __construct(
        protected Invoice $invoice
    ) {}

    protected function templateSlug(): string
    {
        return 'invoice-paid';
    }

    protected function templateVariables(): array
    {
        return [
            'invoice_number' => $this->invoice->number,
            'amount'         => '$' . number_format($this->invoice->amount, 2),
        ];
    }

    protected function actionUrl(): ?string
    {
        return route('invoices.show', $this->invoice);
    }

    protected function actionText(): ?string
    {
        return 'View Invoice';
    }

    protected function inAppIcon(): string
    {
        return 'ph-receipt';
    }

    protected function inAppType(): string
    {
        return 'success';
    }
}
```

### Channel Support Rules

The system checks these conditions before sending to each channel:

| Channel | Condition |
|---------|-----------|
| Email | `$notifiable->email` is not empty |
| SMS | `$notifiable->phone` is not empty |
| In-App | Always supported |
| Web Push | `$notifiable` has `pushSubscriptions()` method (via `HasPushSubscriptions` trait) |
| Mobile Push | `$notifiable` has `deviceTokens()` with at least one token (via `HasDeviceTokens` trait) |

If a condition is not met, that channel is silently skipped for that recipient.

---

## Admin UI

### Template Editor

Located at **Notifications → Templates** in the admin sidebar.

The editor has 5 tabs:

| Tab | Fields | Description |
|-----|--------|-------------|
| **Email** | Subject, Body (Quill rich text editor) | HTML email content |
| **SMS** | Message textarea with character counter | Plain text, max 1600 chars |
| **In-App** | Title, Body | Bell icon notification content |
| **Push** | Title, Body | Shared for Web Push and Mobile Push |
| **Settings** | Channel toggles, Active/Inactive | Per-template configuration |

**Variable sidebar:** On the right side of the editor, clickable chips show all available variables. Click a chip to copy `{{variable_name}}` to your clipboard, then paste it into any field.

### Notification Logs

Located at **Notifications → Logs** in the admin sidebar.

Shows every notification dispatch attempt with:
- Template name
- Channel used
- Recipient
- Status (Sent / Failed / Queued)
- Timestamp
- Metadata (error details for failed notifications)

Stats cards at the top show total, sent, failed, and queued counts.

---

## Artisan Commands

### `make:notification-template`

Scaffold a new notification class and config entry:

```bash
php artisan make:notification-template OrderCancelled
```

Creates:
- `app/Modules/NotificationTemplates/Notifications/OrderCancelledNotification.php`
- Appends entry to `config/notification-templates.php`

### `notification:sync`

Sync templates from config to database. Creates new templates, preserves admin-edited content:

```bash
php artisan notification:sync
```

Run this after adding new templates to the config file or during deployment.

### `notification:test`

Send a test notification using a template:

```bash
# Test email channel (sends to first admin user)
php artisan notification:test welcome --channel=email

# Test with a specific recipient
php artisan notification:test welcome --channel=email --to=john@example.com

# Preview SMS content
php artisan notification:test order-shipped --channel=sms
```

### `webpush:vapid`

Generate VAPID keys for Web Push (provided by the webpush package):

```bash
php artisan webpush:vapid
```

### `remove:notification-template`

Completely remove a notification template — class file, config entry, and database record:

```bash
# Interactive (shows summary and asks for confirmation)
php artisan remove:notification-template OrderShipped

# Works with slug format too
php artisan remove:notification-template order-shipped

# Skip confirmation
php artisan remove:notification-template OrderShipped --force

# Keep the database record (only remove class and config)
php artisan remove:notification-template OrderShipped --keep-db
```

**What it removes:**
- Notification class file from `app/Modules/NotificationTemplates/Notifications/`
- Config entry from `config/notification-templates.php`
- Database record from `notification_templates` table
- Associated entries from `notification_logs` table

**What it warns about:**
- Any files that reference the notification class or slug (you fix these manually)

---

## Full Example: Order Shipped Notification

Here's a complete walkthrough from start to finish.

### 1. Add to config

In `config/notification-templates.php`:

```php
'order-shipped' => [
    'name' => 'Order Shipped',
    'description' => 'Sent when a customer order is shipped',
    'channels' => ['email', 'in_app', 'sms'],
    'variables' => [
        'order_id'        => 'The order number',
        'tracking_number' => 'Shipping tracking number',
        'tracking_url'    => 'URL to track the shipment',
        'estimated_date'  => 'Estimated delivery date',
    ],
    'defaults' => [
        'email_subject' => 'Your order #{{order_id}} has been shipped!',
        'email_body'    => '<p>Hi {{user_name}},</p><p>Your order <strong>#{{order_id}}</strong> is on its way!</p><p>Tracking number: {{tracking_number}}<br>Estimated delivery: {{estimated_date}}</p>',
        'sms_body'      => 'Hi {{user_name}}, your order #{{order_id}} has shipped! Track: {{tracking_url}}',
        'in_app_title'  => 'Order Shipped',
        'in_app_body'   => 'Your order #{{order_id}} has been shipped. Estimated delivery: {{estimated_date}}',
    ],
],
```

### 2. Create the class

```php
<?php

namespace App\Modules\NotificationTemplates\Notifications;

use App\Modules\Orders\Models\Order;

class OrderShippedNotification extends BaseTemplateNotification
{
    public function __construct(
        protected Order $order
    ) {}

    protected function templateSlug(): string
    {
        return 'order-shipped';
    }

    protected function templateVariables(): array
    {
        return [
            'order_id'        => $this->order->id,
            'tracking_number' => $this->order->tracking_number,
            'tracking_url'    => $this->order->tracking_url,
            'estimated_date'  => $this->order->estimated_delivery->format('M d, Y'),
        ];
    }

    protected function actionUrl(): ?string
    {
        return $this->order->tracking_url;
    }

    protected function actionText(): ?string
    {
        return 'Track Your Order';
    }

    protected function inAppIcon(): string
    {
        return 'ph-truck';
    }

    protected function inAppType(): string
    {
        return 'success';
    }
}
```

### 3. Sync and dispatch

```bash
php artisan notification:sync
```

```php
// In your controller or service when the order ships:
use App\Modules\NotificationTemplates\Notifications\OrderShippedNotification;

$order->user->notify(new OrderShippedNotification($order));
```

### What happens when dispatched:

1. `BaseTemplateNotification` loads the `order-shipped` template from the database
2. Checks the template is active
3. For each channel (`email`, `in_app`, `sms`):
   - Is the channel enabled globally in Settings? (email=yes, sms=depends on setting)
   - Is the channel enabled on this template? (all three enabled in config)
   - Does the user support it? (has email? has phone?)
4. Renders each template with variables: `{{order_id}}` → `1234`, `{{user_name}}` → `John Doe`, etc.
5. Dispatches to each channel via the queue
6. Creates a log entry for each dispatch

---

## Custom SMS Driver

If you need a provider other than Vonage or Twilio, implement the `SmsDriverInterface`:

```php
<?php

namespace App\Modules\NotificationTemplates\Channels\Drivers;

class MyCustomSmsDriver implements SmsDriverInterface
{
    public function send(string $to, string $message): void
    {
        // Your SMS sending logic here
        // $to = phone number (e.g., "+1234567890")
        // $message = rendered SMS text
    }
}
```

Then register it in the `SmsChannel` class by adding your driver to the `resolveDriver()` method's match expression, and add a corresponding option to the SMS Provider select in `config/settings.php`.

---

## Architecture Overview

```
config/notification-templates.php          (Developer defines templates)
        |
        v
notification_templates table               (Stores editable content)
        |
        v
BaseTemplateNotification                   (Abstract base class)
        |
        +-- templateSlug()                 (Maps to DB record)
        +-- templateVariables()            (Data for placeholders)
        +-- via()                          (Resolves channels)
        |
        +-- toMail()      → MailMessage    (Uses emails/template-notification.blade.php)
        +-- toInApp()      → array         (Creates system_notifications record)
        +-- toSms()        → string        (Sent via SmsChannel → Driver)
        +-- toWebPush()    → WebPushMessage (Via VAPID / push_subscriptions)
        +-- toFcm()        → FcmMessage    (Via Firebase / device_tokens)
        |
        v
notification_logs table                    (Audit trail: status, metadata)
```

**Key files:**

| File | Purpose |
|------|---------|
| `config/notification-templates.php` | Template definitions (developer-facing) |
| `app/Modules/NotificationTemplates/Notifications/BaseTemplateNotification.php` | Core abstract class |
| `app/Modules/NotificationTemplates/Services/TemplateRenderer.php` | `{{variable}}` substitution engine |
| `app/Modules/NotificationTemplates/Channels/SmsChannel.php` | SMS channel with driver abstraction |
| `app/Modules/NotificationTemplates/Channels/InAppChannel.php` | Bridge to SystemNotificationService |
| `app/Modules/NotificationTemplates/Channels/Drivers/` | SMS drivers (Log, Vonage, Twilio) |
| `app/Modules/NotificationTemplates/Traits/HasDeviceTokens.php` | FCM token management for models |
| `config/settings.php` (notifications group) | Channel toggles and credentials |
| `config/webpush.php` | VAPID configuration |
| `config/firebase.php` | Firebase SDK configuration |
| `stubs/notification/TemplateNotification.stub` | Scaffold template for new notifications |

---

## Troubleshooting

### Notification not sending

1. **Is the template active?** Check Notifications → Templates → edit the template → Settings tab → Active toggle.
2. **Is the channel enabled globally?** Check Settings → Notifications → the channel's feature toggle.
3. **Is the channel enabled on the template?** Check the template's Settings tab → channel checkboxes.
4. **Does the recipient support the channel?** Email requires `email` field, SMS requires `phone` field.
5. **Is the queue running?** Notifications are queued by default. Run `php artisan queue:listen`.

### Template changes not reflected

Admin edits are saved to the database, not the config file. The config file only provides initial defaults. If you need to reset a template to config defaults, delete it from the database and run `php artisan notification:sync`.

### VAPID key generation fails on Windows/XAMPP

Set the OpenSSL config path first:

```bash
set OPENSSL_CONF=D:\xampp\apache\conf\openssl.cnf
php artisan webpush:vapid
```

### SMS not sending in development

The default SMS provider is `log`. Messages are written to `storage/logs/laravel.log`. Switch to Vonage or Twilio in Settings → Notifications for production.

### Notification logs show "queued" but never change to "sent"

The queue worker is not running. Start it with:

```bash
php artisan queue:listen
```

Or use the dev command which starts the queue automatically:

```bash
composer run dev
```
