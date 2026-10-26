<?php

namespace Tests\Support;

/** Tes yang merender halaman tidak membutuhkan hasil `npm run build`. */
trait WithoutViteAssets
{
    protected function setUpWithoutViteAssets(): void
    {
        $this->withoutVite();
    }
}
