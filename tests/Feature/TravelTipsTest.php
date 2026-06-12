<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TravelTipsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Valid itinerary data user acreoss multiple tests.
     */
    private function validItinerary(): array
    {
        return [
            'destination' => 'Tokyo',
            'start_date'  => '2026-07-15',
            'end_date'    => '2026-07-20',
            'activities'  => ['visit temples', 'try shushi', 'explore Shibuya'],
        ];
    }

    /**
     * Simulates a successful Claude API response.
     */
    private function fakeClaudeResponse(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            ['title' => 'Visit Senso-ji Early', 'description' => 'Arrive before 7am to avoid crowds.'],
                            ['title' => 'Try Tsukiji Market', 'description' => 'Best sushi early morning.'],
                            ['title' => 'Shibuya at Night', 'description' => 'The crossing is best after 8pm.'],
                        ]),
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_rejects_request_without_destination(): void
    {
        $data = $this->validItinerary();
        unset($data['destination']);

        $response = $this->postJson('/api/travel-tips', $data);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.destination.0', 'The destination field is required.');
    }

    public function test_rejects_request_without_activities(): void
    {
        $data = $this->validItinerary();
        unset($data['activities']);

        $response = $this->postJson('/api/travel-tips', $data);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_rejects_request_with_empty_activities(): void
    {
        $data = $this->validItinerary();
        $data['activities'] = [];

        $response = $this->postJson('/api/travel-tips', $data);

        $response->assertStatus(422);
    }

    public function test_rejects_request_with_invalid_date_format(): void
    {
        $data = $this->validItinerary();
        $data['start_date'] = '15-07-2026';

        $response = $this->postJson('/api/travel-tips', $data);

        $response->assertStatus(422);
    }

    public function test_rejects_request_when_end_date_is_before_start_date(): void
    {
        $data = $this->validItinerary();
        $data['start_date'] = '2026-07-20';
        $data['end_date'] = '2026-07-15';

        $response = $this->postJson('/api/travel-tips', $data);

        $response->assertStatus(422);
    }

       public function test_returns_three_tips_for_valid_itinerary(): void
    {
        $this->fakeClaudeResponse();

        $response = $this->postJson('/api/travel-tips', $this->validItinerary());

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonCount(3, 'data')
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         ['title', 'description'],
                         ['title', 'description'],
                         ['title', 'description'],
                     ],
                     'cached',
                 ]);
    }

    public function test_each_tip_has_title_and_description(): void
    {
        $this->fakeClaudeResponse();

        $response = $this->postJson('/api/travel-tips', $this->validItinerary());

        $data = $response->json('data');

        foreach ($data as $tip) {
            $this->assertArrayHasKey('title', $tip);
            $this->assertArrayHasKey('description', $tip);
            $this->assertNotEmpty($tip['title']);
            $this->assertNotEmpty($tip['description']);
        }
    }

    public function test_second_request_returns_cached_response(): void
    {
        $this->fakeClaudeResponse();

        // First request — should call Claude
        $first = $this->postJson('/api/travel-tips', $this->validItinerary());
        $first->assertJsonPath('cached', false);

        // Second request — same itinerary, should come from cache
        $second = $this->postJson('/api/travel-tips', $this->validItinerary());
        $second->assertJsonPath('cached', true);
    }

    public function test_returns_500_when_claude_api_fails(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'server error'], 500),
        ]);

        $response = $this->postJson('/api/travel-tips', $this->validItinerary());

        $response->assertStatus(500)
                 ->assertJsonPath('success', false)
                 ->assertJsonPath('message', 'Failed to generate travel tips.');
    }

    public function test_returns_500_when_claude_returns_invalid_json(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'This is not valid JSON at all'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/travel-tips', $this->validItinerary());

        $response->assertStatus(500)
                 ->assertJsonPath('success', false);
    }

    public function test_returns_json_for_completely_empty_request(): void
    {
        $response = $this->postJson('/api/travel-tips', []);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false);
    }
}

