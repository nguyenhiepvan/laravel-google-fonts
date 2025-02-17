<?php

namespace Spatie\GoogleFonts;

use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Spatie\GoogleFonts\Commands\FetchGoogleFontsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GoogleFontsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('google-fonts')
            ->hasConfigFile()
            ->hasCommand(FetchGoogleFontsCommand::class);
    }

    public function packageRegistered()
    {
        $this->app->singleton(GoogleFonts::class, function (Application $app) {
            return new GoogleFonts(
                $app->make(FilesystemManager::class)->disk(config('google-fonts.disk')),
                config('google-fonts.path'),
                config('google-fonts.inline'),
                config('google-fonts.fallback'),
                config('google-fonts.user_agent'),
                config('google-fonts.fonts')
            );
        });
    }

    public function packageBooted()
    {
        Blade::directive('googlefonts', function ($expression) {
            return "<?php echo app(Spatie\GoogleFonts\GoogleFonts::class)->load($expression)->toHtml(); ?>";
        });
    }
}
