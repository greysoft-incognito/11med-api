<?php

namespace App\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Partners\ReviewPartnerRequest;
use App\Http\Resources\Partners\PartnerCollection;
use App\Http\Resources\Partners\PartnerResource;
use App\Services\Partners\Organisations;
use Illuminate\Http\Request;

class AdminPartnerController extends Controller
{
    public function __construct(
        private readonly Organisations $organisations,
    ) {}

    public function index(Request $request): PartnerCollection
    {
        $page = $this->organisations->list($request->only(['kind', 'status', 'q']), ListQuery::from($request));

        return new PartnerCollection($page);
    }

    public function review(ReviewPartnerRequest $request, string $id): PartnerResource
    {
        $partner = $this->organisations->review($id, $request->validated('decision'), $request->validated('notes'));

        return new PartnerResource($partner);
    }
}
