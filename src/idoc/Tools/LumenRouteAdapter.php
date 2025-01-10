<?php

namespace OVAC\IDoc\Tools;

use Illuminate\Routing\Route;

/**
 * Class LumenRouteAdapter.
 */
class LumenRouteAdapter extends Route
{
    /**
     * LumenRouteAdapter constructor.
     */
    public function __construct(array $lumenRoute)
    {
        parent::__construct($lumenRoute['method'], $lumenRoute['uri'], $lumenRoute['action']);
    }
}
