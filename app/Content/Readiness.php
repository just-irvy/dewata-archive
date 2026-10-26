<?php

namespace App\Content;

use Illuminate\Database\Eloquent\Model;

interface Readiness
{
    public function check(Model $record): ReadinessReport;
}
