<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class HealthResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => 'ok',
            'name' => config('app.name'),
        ];
    }
}
