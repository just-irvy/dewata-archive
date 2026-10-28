<?php

namespace App\Services\Maps;

/** Dipakai saat server key belum diisi. Aplikasi tetap berjalan tanpa jarak rute. */
final class NullRouteProvider implements RouteProvider
{
    public function compute(RouteQuery $query): RouteResult
    {
        return RouteResult::failed('not_configured');
    }
}
