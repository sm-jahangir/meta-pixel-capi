<?php

namespace Codersgift\FacebookPixelService;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPixelService
{
    protected string $accessToken;
    protected string $pixelId;

    public function __construct()
    {
        $this->accessToken = config('facebookpixel.access_token');
        $this->pixelId = config('facebookpixel.pixel_id');
    }

    public function sendPixelEvent(array $eventData)
    {
        try {
            $url = "https://graph.facebook.com/v17.0/{$this->pixelId}/events";

            $response = Http::post($url, [
                'access_token' => $this->accessToken,
                'data' => [
                    [
                        'event_name' => $eventData['event_name'],
                        'event_time' => $eventData['event_time'],
                        'event_id' => $eventData['event_id'],
                        'user_data' => [
                            // 'email' => hash('sha256', $eventData['email']),
                            'ph' => hash('sha256', $eventData['phone']),
                            'external_id' => hash('sha256', $eventData['userID']), // Hashed user ID (External ID)
                            'fbp' => $eventData['fbp'], // Browser ID
                        ],
                        'custom_data' => [
                            'event_name' => $eventData['event_name'],
                            'currency' => $eventData['currency'],
                            'value' => $eventData['value'],
                            'content_ids' => $eventData['content_ids'],
                            'content_type' => $eventData['content_type'],
                            'order_number' => $eventData['order_id'],
                            'event_time' => $eventData['event_time'],
                            'action_source' => 'website',
                            'event_id' => $eventData['event_id'],
                            'user_phone' => $eventData['phone'],
                        ],
                        'event_source_url' => url()->current(),
                        'action_source' => 'website',
                    ]
                ],
                // 'test_event_code' => env('TESTCPICODE'), // Replace with your test event code
                'test_event_code' => env('production') ? null : env('TESTCPICODE'),
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Facebook Pixel API Error: ' . $e->getMessage());
            return null; // Handle failure gracefully
        }
    }
}
