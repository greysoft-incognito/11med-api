<?php

namespace App\Http\Controllers;

use App\Http\Resources\HealthResource;

class HealthController extends Controller
{
    /**
     * Liveness probe for the deploy workflow. Public.
     */
    public function show(): HealthResource
    {
        return new HealthResource(null);
    }
}
