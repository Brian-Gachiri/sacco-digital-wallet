<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send($recipient, $message)
    {
        $apiKey = config('services.expresssms.api_key');

        $message = urlencode($message);
        $url = "https://bulksms.ims.co.ke/api/sms/send";
        $query = [
            'apikey' => $apiKey,
            'message' => $message,
            'phone' => $recipient,
        ];

        try {
            $response = Http::get($url, $query);
            Log::info('IMS Bulk SMS sent', ['response' => $response]);

            if ($response->status() == 200 && str_contains($response->body(), '""')) {
                return [
                    'success' => true,
                    'response' => $response,
                ];
            } else {
                return [
                    'success' => false,
                    'response' => $response,
                ];
            }
        } catch (\Exception $e) {
            Log::error('IMS Bulk SMS failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

}