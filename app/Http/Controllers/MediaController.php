<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Settings\SchoolSettingsController;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Serves logos from private storage (they are shown on the PIN screen, so no PIN is required). */
class MediaController extends Controller
{
    public function logo(string $slot, Settings $settings): Response
    {
        $key = SchoolSettingsController::LOGO_SLOTS[$slot] ?? abort(404);
        $path = $settings->get($key);

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
