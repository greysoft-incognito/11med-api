<?php

namespace App\Services\Payments\Gateway;

use RuntimeException;

/**
 * The gateway could not be reached or refused the call.
 */
class GatewayUnavailable extends RuntimeException {}
