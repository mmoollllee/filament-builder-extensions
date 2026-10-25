<?php

namespace Mmoollllee\FilamentBuilderExtensions;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\ClipboardShortcuts;
use Mmoollllee\FilamentBuilderExtensions\Title\ItemTitle;
use Mmoollllee\FilamentBuilderExtensions\Transfer\TransferGroup;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Every tool of the package is opt-in: a macro does nothing until a field calls it, an
 * action nothing until a field lists it. Nothing overrides a Filament view or changes a
 * field by itself. The stylesheet only styles the package's own classes; the script loads
 * where a field asks for it (`x-load-js`), never on every page.
 */
class FilamentBuilderExtensionsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-builder-extensions';

    public static string $assetPackage = 'mmoollllee/filament-builder-extensions';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasViews(static::$name)
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make(static::$name, __DIR__.'/../resources/css/builder-extensions.css'),
            Js::make(static::$name, __DIR__.'/../resources/js/builder-extensions.js')->loadedOnRequest(),
        ], package: static::$assetPackage);

        ItemTitle::registerMacros();
        TransferGroup::registerMacros();
        ClipboardShortcuts::registerMacros();
    }

    /** The URL of the package's script, for an `x-load-js` of your own: `x-load-js="[url]"`. */
    public static function scriptSrc(): string
    {
        return FilamentAsset::getScriptSrc(static::$name, static::$assetPackage);
    }

    /**
     * The value of an `x-load-js` attribute that loads the package's script — for the
     * transfer and clipboard tools, on the element that needs it. Escaped: Filament merges
     * extra attributes unescaped.
     */
    public static function loadScriptAttribute(): string
    {
        return e(json_encode([static::scriptSrc()], JSON_UNESCAPED_SLASHES));
    }
}
