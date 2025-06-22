<?php

namespace Codersgift\FacebookPixelService\Facades;

use Illuminate\Support\Facades\Facade;

class FacebookPixel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'facebookpixel';
    }
}
