<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Audit\AuditSearchRequest;
use App\Http\Resources\Audit\AuditEventCollection;
use App\Services\Audit\AuditTrail;

class AuditController extends Controller
{
    public function __construct(
        private readonly AuditTrail $trail,
    ) {}

    public function index(AuditSearchRequest $request): AuditEventCollection
    {
        $page = $this->trail->list($request->validated(), ListQuery::from($request));

        return new AuditEventCollection($page);
    }
}
