<?php

namespace App\Exceptions;

use App\Content\ReadinessReport;
use RuntimeException;

class NotReadyToPublish extends RuntimeException
{
    public function __construct(public readonly ReadinessReport $report)
    {
        parent::__construct('Record belum memenuhi syarat terbit.');
    }
}
