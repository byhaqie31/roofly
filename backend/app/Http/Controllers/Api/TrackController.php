<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackRequest;
use App\Services\AnalyticsRecorder;
use Illuminate\Http\Response;

/** Public analytics beacon (spec § 3). Always 204 on success; clients ignore the body. */
class TrackController extends Controller
{
    public function store(TrackRequest $request, AnalyticsRecorder $recorder): Response
    {
        $recorder->record($request->validated(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
