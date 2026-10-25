<?php

namespace Mmoollllee\FilamentBuilderExtensions\Title;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * An editable title in a Builder block's or a Repeater row's header: `->title('field')`
 * renders an input bound to one of the item's fields in place of the read-only label.
 * Carried over from mmoollllee/filament-builder-title, which this package replaces.
 */
final class ItemTitle
{
    public static function registerMacros(): void
    {
        self::registerBlockMacro();
        self::registerRepeaterMacro();
    }

    /**
     * Render the inline title <input> that stands in for a Builder block / Repeater row
     * header label, two-way bound to the field at `wireModel` via Alpine `$entangle` — at once
     * with `live`, else with the next request. `value`, the title as it is, is text for those
     * who read the label as text. Without a `wireModel` the input is bound to nothing.
     *
     * @param  array{wireModel?: ?string, live?: bool, value?: mixed, placeholder?: ?string, suffix?: ?string, error?: ?string}  $data
     */
    public static function input(array $data): HtmlString
    {
        return new HtmlString(
            view('filament-builder-extensions::title-input', $data)->render()
        );
    }

    /**
     * Look up the first validation error for a wire path off the given component's error bag,
     * swallowing any error-resolution failure. Shared by both title macros.
     */
    public static function firstError(object $component, string $wireModel): ?string
    {
        try {
            return $component->getLivewire()->getErrorBag()->first($wireModel) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** A block's or field's name as Filament makes a label of it: `hero_section` → "Hero section". */
    public static function humanize(string $name): string
    {
        return (string) str($name)
            ->afterLast('.')
            ->kebab()
            ->replace(['-', '_'], ' ')
            ->ucfirst();
    }

    private static function registerBlockMacro(): void
    {
        Block::macro('title', function (string $field, ?string $placeholder = null, ?string $suffix = null) {
            /** @var Block $this */

            // The label the block was given before — text, HTML or a closure —, kept for the
            // block picker and as the placeholder: the macro's closure takes its place.
            $configuredLabel = $this->label;

            $this->label(function (Block $component, ?array $state, ?string $key) use ($field, $placeholder, $suffix, $configuredLabel): string|Htmlable {
                $label = $component->evaluate($configuredLabel, ['key' => $key, 'state' => $state]) ?? ItemTitle::humanize($component->getName());

                // Block picker context: no per-item state/key available → plain label.
                if ($state === null || $key === null) {
                    return $label;
                }

                // The block Filament renders the item with — in a nested builder a clone of the
                // one the macro was called on — knows its builder, whose state path leads to the
                // field. Guard isset(): a block outside a builder has no container, and the typed
                // property throws on access. Its input is bound to nothing.
                $builder = isset($component->container) ? $component->getContainer()?->getParentComponent() : null;
                $wireModel = ($builder instanceof Builder) ? "{$builder->getStatePath()}.{$key}.data.{$field}" : null;

                return ItemTitle::input([
                    'wireModel' => $wireModel,
                    // A preview renders on the server: it shows a new title at once.
                    'live' => ($builder instanceof Builder) && $builder->hasBlockPreviews() && $component->hasPreview(),
                    'value' => $state[$field] ?? null,
                    'placeholder' => $placeholder ?? (($label instanceof Htmlable) ? html_entity_decode(strip_tags($label->toHtml()), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $label),
                    'suffix' => $suffix,
                    'error' => ($wireModel === null) ? null : ItemTitle::firstError($component, $wireModel),
                ]);
            });

            return $this;
        });
    }

    /**
     * Repeater equivalent of the Block `title()` macro: renders an inline, editable input
     * for one of the row's fields directly in the row header (in place of the read-only
     * `itemLabel`). Bind the field with `Hidden::make($field)` in the schema so it is edited
     * only in the header. Repeater rows are flat, so the wire path is `{row}.{field}` (no
     * `.data.` wrapper the Builder needs).
     */
    private static function registerRepeaterMacro(): void
    {
        Repeater::macro('title', function (string $field, ?string $placeholder = null, ?string $suffix = null) {
            /** @var Repeater $this */
            $this->itemLabel(function ($container) use ($field, $placeholder, $suffix): string|Htmlable|null {
                // $container is the row's child schema; its state path is
                // "{repeaterStatePath}.{uuid}", so the field lives one level below.
                $rowStatePath = $container?->getStatePath();

                if (blank($rowStatePath)) {
                    return null;
                }

                $wireModel = "{$rowStatePath}.{$field}";

                return ItemTitle::input([
                    'wireModel' => $wireModel,
                    'value' => $container->getRawState()[$field] ?? null,
                    'placeholder' => $placeholder ?? ItemTitle::humanize($field),
                    'suffix' => $suffix,
                    'error' => ItemTitle::firstError($container, $wireModel),
                ]);
            });

            return $this;
        });
    }
}
