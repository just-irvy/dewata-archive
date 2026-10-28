<?php

namespace App\Services\Maps;

interface RouteProvider
{
    /**
     * Kode kegagalan: not_configured, too_many_stops, quota_reached, invalid_request,
     * unavailable, no_route.
     */
    public function compute(RouteQuery $query): RouteResult;
}
