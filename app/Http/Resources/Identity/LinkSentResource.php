<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * `zForgotPasswordResponse`. The link goes by email only, so `dev_link` is always null here.
 */
class LinkSentResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        return [
            'sent' => true,
            'dev_link' => null,
        ];
    }
}
