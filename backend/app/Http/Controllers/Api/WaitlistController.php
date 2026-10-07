<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaitlistRequest;
use App\Services\AnalyticsRecorder;
use Illuminate\Http\Response;

/**
 * Public coming-soon waitlist capture. Always 204: a repeat signup, a brand-new
 * one and a honeypot hit all look identical to the caller.
 */
class WaitlistController extends Controller
{
    public function store(WaitlistRequest $request, AnalyticsRecorder $recorder): Response
    {
        if ($request->filled('website')) {
            return response()->noContent(); // honeypot tripped — drop silently
        }

        $recorder->recordWaitlist(
            $request->validated('email'),
            $request->validated('visitorId'),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->noContent();
    }
}
