<?php

namespace Mmoollllee\FilamentBuilderExtensions\Clipboard\Concerns;

use Closure;
use Mmoollllee\FilamentBuilderExtensions\FilamentBuilderExtensionsServiceProvider;

/**
 * What a copy is of: a paste only takes a copy of its own kind, so a block copied in one
 * builder never lands among the positions of an invoice. The kind travels with the copy
 * (`{kind, item}` as JSON); the list's name is the default.
 *
 * Both clipboard actions run in the browser, so their button loads the package's script
 * — whatever extra attributes the app gives it.
 */
trait HasKind
{
    protected string|Closure|null $kind = null;

    public function kind(string|Closure|null $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    abstract public function getKind(): string;

    /**
     * @return array<mixed>
     */
    public function getExtraAttributes(): array
    {
        return [
            ...parent::getExtraAttributes(),
            'x-load-js' => FilamentBuilderExtensionsServiceProvider::loadScriptAttribute(),
        ];
    }
}
