# Facebook Conversions API Service for Laravel

A simple Laravel package to send server-side events to the Facebook Conversions API (CAPI). This helps in tracking user actions more reliably, especially when browser-based tracking (like the Pixel) is blocked or limited.

## Features

-   Easy-to-use Facade for sending events.
-   Handles hashing of user data (`phone`, `userID`).
-   Configurable via `.env` file.
-   Supports test events for debugging.

## 1. Installation

Since this is a local package, it's already integrated into your project. If it were a standard Composer package, you would run:

```bash
composer require codersgift/facebook-pixel-service
```

## 2. Configuration

### a. Register Service Provider and Facade

The package's Service Provider and Facade must be registered in your `config/app.php` file. Your project already has this configured.

**`config/app.php`**
```php
'providers' => [
    // ...
    Codersgift\FacebookPixelService\Providers\FacebookPixelServiceProvider::class,
],

'aliases' => Facade::defaultAliases()->merge([
    // ...
    'FacebookPixel' => Codersgift\FacebookPixelService\Facades\FacebookPixel::class,
])->toArray(),
```

### b. Publish Configuration File

To customize the configuration, publish the config file using the following Artisan command:

```bash
php artisan vendor:publish --provider="Codersgift\FacebookPixelService\Providers\FacebookPixelServiceProvider" --tag="config"
```

This command will create a `config/facebookpixel.php` file in your project, allowing you to manage settings centrally.

### c. Set Environment Variables

Add the following keys to your `.env` file with your Facebook App credentials.

```env
FACEBOOK_PIXEL_ID=your_pixel_id
FACEBOOK_ACCESS_TOKEN=your_long_lived_access_token
FACEBOOK_TEST_EVENT_CODE=your_test_event_code_for_debugging
```

-   `FACEBOOK_PIXEL_ID`: Your Facebook Pixel ID.
-   `FACEBOOK_ACCESS_TOKEN`: A server-side access token generated from your Business Manager.
-   `FACEBOOK_TEST_EVENT_CODE`: (Optional) Use this to test events in the Events Manager without affecting your production data. The package automatically uses this when `APP_ENV` is not `production`.

## 3. Usage

The primary way to use the service is through the `FacebookPixel` facade.

### a. Capturing the `_fbp` Cookie (Frontend)

For accurate event matching and deduplication, it's crucial to send the `_fbp` (Facebook browser ID) cookie value with your server-side events. This value is created by the Facebook Pixel script on the user's browser.

You can capture it by adding a hidden input field to your forms and using a small JavaScript snippet to populate it.

**1. Add a hidden input to your form:**

```html
<form action="/your-order-route" method="POST">
    @csrf
    <!-- other form fields -->
    <input type="hidden" id="fbp" name="fbp" value="">
</form>
```

**2. Add the following JavaScript to your page:**

This script will find the `_fbp` cookie and set its value to the hidden input field.

```javascript
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var fbpValue = null;
        if (document.cookie) {
            var cookies = document.cookie.split('; ');
            cookies.forEach(function(cookie) {
                if (cookie.startsWith('_fbp=')) {
                    fbpValue = cookie.split('=')[1];
                }
            });
        }
        if (fbpValue) {
            document.getElementById('fbp').value = fbpValue;
        }
    });
</script>
```

Now, when the form is submitted, the `fbp` value will be available in your controller via `$request->fbp`.

### b. Sending an Event (Backend)

To send an event, call the `sendEvent` method with an array of event data.

```php
use Codersgift\FacebookPixelService\Facades\FacebookPixel;

// ... inside a controller method

$eventData = [
    'event_name' => 'Purchase',
    'event_time' => time(),
    'event_id' => 'your_unique_event_id', // Crucial for deduplication
    'userID' => (string) $user->id,
    'phone' => $order->mobile,
    'email' => $order->email,
    'order_id' => (string) $order->id,
    'value' => $order->total,
    'currency' => 'BDT',
    'fbp' => $request->fbp, // _fbp cookie value
    'client_ip_address' => $request->ip(),
    'client_user_agent' => $request->userAgent(),
    'content_ids' => ['product_id_1', 'product_id_2'],
    'content_type' => 'product',
];

$response = FacebookPixel::sendEvent($eventData);
```

### Event Data Parameters

The `$eventData` array should contain the following keys:

| Key                 | Required | Description                                                                                             |
| ------------------- | -------- | ------------------------------------------------------------------------------------------------------- |
| `event_name`        | Yes      | The type of event (e.g., `Purchase`, `AddToCart`, `ViewContent`).                                       |
| `event_time`        | Yes      | Unix timestamp of when the event occurred.                                                              |
| `event_id`          | Yes      | A unique ID for this specific event. **Required for deduplication.**                                    |
| `userID`            | Yes      | The unique ID of the logged-in user in your system. Will be hashed.                                     |
| `phone`             | Yes      | The user's phone number. Will be hashed.                                                                |
| `fbp`               | Yes      | The `_fbp` cookie from the user's browser. Helps with matching.                                         |
| `client_ip_address` | Yes      | The user's IP address.                                                                                  |
| `client_user_agent` | Yes      | The user's browser user agent string.                                                                   |
| `value`             | Yes      | The monetary value of the event (e.g., total order price).                                              |
| `currency`          | Yes      | The currency code (e.g., `BDT`, `USD`).                                                                 |
| `content_ids`       | Yes      | An array of product IDs associated with the event.                                                      |
| `content_type`      | Yes      | The type of content (usually `product` or `product_group`).                                             |
| `order_id`          | Yes      | The unique ID for the order.                                                                            |
| `email`             | No       | The user's email address. The service will hash it if provided.                                         |

## 4. Example: Tracking a Purchase Event

This example shows how to track a purchase event from a controller and ensure it's deduplicated with the browser-side Pixel event.

### Controller (`OrderController.php`)

This is a simplified version of your `placeOrder` method, highlighting the CAPI integration steps.

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Codersgift\FacebookPixelService\Facades\FacebookPixel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
// ... other necessary models and facades

class OrderController extends Controller
{
    public function placeOrder(Request $request)
    {
        // ... (validation and other logic) ...

        try {
            DB::beginTransaction();
            // ... (Create user, order, order items, transaction) ...
            $new_user = // ...
            $order = // ...
            DB::commit();

            // --- Facebook CAPI Integration ---
            $eventId = uniqid('server_', true);
            $eventTime = time();

            $eventData = [ /* ... build the $eventData array as shown above ... */ ];

            FacebookPixel::sendEvent($eventData);

            $productIds = json_encode($order->orderItems->pluck('product_id')->toArray());
            return view('frontend.landingpage.thank-you', compact('order', 'productIds', 'eventId', 'eventTime'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order placement failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Order placement failed.');
        }
    }
}
```

### View (`thank-you.blade.php`)

To prevent duplicate event counting, you must send the **same `event_id`** from both the server (CAPI) and the browser (Pixel).

```blade
{{-- ... your thank you page content ... --}}

@push('scripts')
<script>
    // Ensure fbq is initialized before using it
    if (typeof fbq === 'function') {
        fbq('track', 'Purchase', {
            value: {{ $order->total }},
            currency: 'BDT',
            content_ids: {!! $productIds !!},
            content_type: 'product',
            order_id: '{{ $order->id }}'
        },
        // This object is for event deduplication
        {
            eventID: '{{ $eventId }}' // Pass the same event_id from the controller
        });
    }
</script>
@endpush
```

This setup ensures that Facebook receives both events but understands they represent the same purchase, correctly deduplicating them in your Events Manager.
