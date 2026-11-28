<?php

namespace App\Http\Controllers;

use App\Http\Resources\AppVersionResource;

class AppVersionController extends Controller
{
    /**
     * The minimum app version per platform, read by the app on launch and foreground. Public.
     */
    public function show(): AppVersionResource
    {
        $versions = config('elevenmed.app_versions');

        return new AppVersionResource($versions);
    }
}
