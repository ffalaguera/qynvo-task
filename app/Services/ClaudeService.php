<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class ClaudeService
{
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    /**
     * Generate personalised travel tips for an itinerary.
     */
    public function generateTips(array $itinerary): array
    {
        // Bonus: check cache first
        $cacheKey = $this->buildCacheKey($itinerary);

        if (Cache::has($cacheKey)) {
            return [
                'tips'   => Cache::get($cacheKey),
                'cached' => true,
            ];
        }

        // Build the prompt and call Claude
        $prompt = $this->buildPrompt($itinerary);
        $tips   = $this->callClaude($prompt);

        // Bonus: store in cache for 24 hours
        Cache::put($cacheKey, $tips, now()->addHours(24));

        return [
            'tips'   => $tips,
            'cached' => false,
        ];
    }

    /**
     * Build a descriptive prompt based on the itinerary.
     */
    private function buildPrompt(array $itinerary): string
    {
        $destination = $itinerary['destination'];
        $startDate   = $itinerary['start_date'];
        $endDate     = $itinerary['end_date'];
        $activities  = implode(', ', $itinerary['activities']);

        return "You are a travel expert assistant. A traveller is planning a trip with the following details:

- Destination: {$destination}
- Dates: {$startDate} to {$endDate}
- Planned activities: {$activities}

Generate exactly 3 personalised travel tips for this trip. Each tip should be specific to the destination and activities, not generic advice.

Respond ONLY with a valid JSON array of 3 objects, each with 'title' and 'description' keys. No additional text, no markdown, no code blocks. Example format:
[{\"title\": \"Tip title\", \"description\": \"Tip description\"}]";
    }

    /**
     * Call the Anthropic Claude API and return parsed tips.
     */
    private function callClaude(string $prompt): array
    {
        $apiKey = config('services.anthropic.api_key');
        $model  = config('services.anthropic.model');

        if (empty($apiKey)) {
            throw new Exception('Anthropic API key is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)->post($this->apiUrl, [
                'model'      => $model,
                'max_tokens' => 1024,
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

            if ($response->failed()) {
                throw new Exception('Claude API returned status: ' . $response->status());
            }

            $body = $response->json();
            $text = $body['content'][0]['text'] ?? null;

            if (empty($text)) {
                throw new Exception('Claude API returned an empty response.');
            }

            return $this->parseTips($text);

        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Claude API')) {
                throw $e;
            }
            throw new Exception('Failed to connect to Claude API: ' . $e->getMessage());
        }
    }

    /**
     * Parse Claude's text response into a structured array of tips.
     */
    private function parseTips(string $text): array
    {
        $cleaned = trim($text);
        $cleaned = preg_replace('/^```json\s*/', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        $tips = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($tips)) {
            throw new Exception('Failed to parse Claude response as JSON.');
        }

        foreach ($tips as $tip) {
            if (!isset($tip['title']) || !isset($tip['description'])) {
                throw new Exception('Claude response has invalid tip structure.');
            }
        }

        return $tips;
    }

    /**
     * Build a unique cache key from the itinerary data.
     */
    private function buildCacheKey(array $itinerary): string
    {
        return 'travel_tips_' . md5(json_encode($itinerary));
    }
}