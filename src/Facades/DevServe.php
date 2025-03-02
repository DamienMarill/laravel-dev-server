<?php

namespace Marill\DevServe\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Marill\DevServe\DevServe
 */
class DevServe extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Marill\DevServe\DevServe::class;
    }
}
