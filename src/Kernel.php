<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{

    use MicroKernelTrait;

    public function boot(): void
    {
        static $started = false;
        if ($started) {
            assert(false);
        }
        parent::boot();
    }


}
