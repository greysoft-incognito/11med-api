<?php

namespace App\Console\Commands;

use App\Services\Contract\RouteInventory;
use Illuminate\Console\Command;

class ContractRoutes extends Command
{
    protected $signature = 'contract:routes';

    protected $description = 'Refresh tests/Contract/routes.json from the client checkouts\' mock routes';

    public function handle(RouteInventory $inventory): int
    {
        $routes = $inventory->build();

        $inventory->write($routes);

        $this->info("{$routes->count()} routes written to " . RouteInventory::PATH);

        return self::SUCCESS;
    }
}
