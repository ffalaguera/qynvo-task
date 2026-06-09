<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItineraryRequest;
use App\Services\ClaudeService;
use Exception;

class TravelTipsController extends Controller
{
    /**
     * Generate personalised travel tips for a given itinerary.
     */
    public function generate(ItineraryRequest $request)
    {
        try {
            $claudeService = new ClaudeService();

            $result = $claudeService->generateTips($request->validated());

            return response()->json([
                'success' => true,
                'data'    => $result['tips'],
                'cached'  => $result['cached'],
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate travel tips.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
