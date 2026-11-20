<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Receives unexpected JavaScript errors from the browser (PRD-07 §8.2), rate limited. */
class ClientErrorController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'stack' => ['nullable', 'string', 'max:4000'],
            'url' => ['nullable', 'string', 'max:300'],
            'component' => ['nullable', 'string', 'max:120'],
        ]);

        $ref = app('error.ref');
        Log::warning('Client error', $data + ['ref' => $ref, 'ua' => mb_substr((string) $request->userAgent(), 0, 200)]);

        return response()->json(['ref' => $ref]);
    }
}
