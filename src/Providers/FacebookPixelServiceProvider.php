<?php
namespace Codersgift\FacebookPixelService\Providers;

use Illuminate\Support\ServiceProvider;
use Codersgift\FacebookPixelService\FacebookPixelService;

class FacebookPixelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/facebookpixel.php', 'facebookpixel');

        $this->app->singleton('facebookpixel', function () {
            return new FacebookPixelService();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/facebookpixel.php' => config_path('facebookpixel.php'),
        ], 'facebookpixel-config');
    }
}
