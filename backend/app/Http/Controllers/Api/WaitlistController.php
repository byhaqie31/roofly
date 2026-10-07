<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaitlistRequest;
use App\Notifications\WaitlistWelcome;
use App\Services\AnalyticsRecorder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Public coming-soon waitlist capture. Always 204: a repeat signup, a brand-new
 * one and a honeypot hit all look identical to the caller. Only a brand-new
 * lead gets the WaitlistWelcome email.
 */
class WaitlistController extends Controller
{
    public function store(WaitlistRequest $request, AnalyticsRecorder $recorder): Response
    {
        if ($request->filled('website')) {
            return response()->noContent(); // honeypot tripped — drop silently
        }

        $email = Str::lower(trim($request->validated('email')));

        $isNew = $recorder->recordWaitlist(
            $email,
            $request->validated('visitorId'),
            $request->ip(),
            $request->userAgent(),
        );

        // First signup only — a repeat must not let anyone re-mail someone else's address.
        if ($isNew) {
            try {
                Notification::route('mail', $email)->notify(new WaitlistWelcome);
            } catch (Throwable $e) {
                report($e); // the lead is saved; a queue outage shouldn't fail the form
            }
        }

        return response()->noContent();
    }
}
