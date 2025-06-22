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

    public function sendEvent(array $eventData): ?array
    {
        $url = "https://graph.facebook.com/v17.0/{$this->pixelId}/events";

        try {
            $response = Http::post($url, [
                'access_token' => $this->accessToken,
                'data' => [[
                    'event_name' => $eventData['event_name'],
                    'event_time' => $eventData['event_time'],
                    'event_id' => $eventData['event_id'],
                    'user_data' => [
                        'ph' => hash('sha256', $eventData['phone']),
                        'external_id' => hash('sha256', $eventData['userID']),
                        'fbp' => $eventData['fbp'],
                        'client_ip_address' => $eventData['client_ip_address'],
                        'client_user_agent' => $eventData['client_user_agent'],
                    ],
                    'custom_data' => [
                        'currency' => $eventData['currency'] ?? 'BDT',
                        'value' => $eventData['value'],
                        'content_ids' => $eventData['content_ids'],
                        'content_type' => $eventData['content_type'],
                        'order_number' => $eventData['order_id'],
                    ],
                    'event_source_url' => url()->current(),
                    'action_source' => 'website',
                ]],
                'test_event_code' => app()->isProduction() ? null : config('facebookpixel.test_event_code'),
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Facebook Pixel API Error: ' . $e->getMessage());
            return null;
        }
    }
}
