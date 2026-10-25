<?php

namespace Mmoollllee\FilamentBuilderExtensions;

use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Component;
use LogicException;

/**
 * Adding to a Builder or Repeater from outside it — from a row of actions beside it
 * instead of its own button, which offers one kind of item alone: the row can offer
 * several (a free position or one from a catalogue, a discount) and serve several lists.
 * An item is added as the list's own button adds it, its fields at their defaults, with
 * a given state over them. And what a list takes, as its own buttons see it.
 */
final class Items
{
    /**
     * The Builder or Repeater `$name` beside `$component`, found by its state path as `$get()`
     * finds a field: `'lines'` in the same item or form, `'../lines'` one level up. Layout
     * components around the one or the other do not matter.
     */
    public static function beside(Component $component, string $name): Builder|Repeater
    {
        $list = $component->getRootContainer()->getComponentByStatePath(
            $component->resolveRelativeStatePath($name),
            withHidden: true,
            withAbsoluteStatePath: true,
        );

        if ($list instanceof Builder || $list instanceof Repeater) {
            return $list;
        }

        throw new LogicException("There is no builder or repeater [{$name}] beside [{$component->getKey()}].");
    }

    /** Whether the list holds as many items as its `maxItems()` allows. */
    public static function isFull(Builder|Repeater $list): bool
    {
        return filled($maxItems = $list->getMaxItems()) && ($list->getItemsCount() >= $maxItems);
    }

    /**
     * Whether the builder takes one more item of the block `$name`, as its block picker offers
     * blocks: one it has, and not as often yet as the block's own `maxItems()` allows.
     */
    public static function offers(Builder $builder, string $name): bool
    {
        foreach ($builder->getBlockPickerBlocks() as $block) {
            /** @var Block $block */
            if ($block->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds an item as the list's own button does — its fields at their defaults —, `$state`
     * over them: a Repeater row's state, a Builder block's data (the block named by `$block`).
     * A closure for `$state` gets the defaults (`$defaults`) and returns the state to put
     * over them. The item goes last, or right after the item `$after`.
     *
     * @param  array<string, mixed>|Closure(array<string, mixed>): array<string, mixed>  $state
     * @return array-key the new item's key
     */
    public static function add(Builder|Repeater $list, array|Closure $state = [], ?string $block = null, string|int|null $after = null): string|int
    {
        if (($list instanceof Builder) && (blank($block) || (! $list->hasBlock($block)))) {
            throw new LogicException("The builder [{$list->getName()}] has no block [{$block}].");
        }

        $items = $list->getRawState();
        $items = is_array($items) ? $items : [];
        $item = ($list instanceof Builder) ? ['type' => $block, 'data' => []] : [];
        $key = $list->generateUuid();

        if ($key === null) {
            $items[] = $item;
            $key = array_key_last($items);
        } else {
            $items[$key] = $item;
        }

        if (($after !== null) && ((string) $after !== (string) $key) && array_key_exists($after, $items)) {
            $items = self::placeAfter($items, $key, $after);
        }

        $list->rawState($items);
        $list->getChildSchema($key)->fill();

        $items = $list->getRawState();
        $defaults = ($list instanceof Builder) ? ($items[$key]['data'] ?? []) : $items[$key];
        $state = ($state instanceof Closure) ? $state($defaults) : $state;

        if ($state !== []) {
            // The state's own key order first: a pasted copy keeps its original's.
            if ($list instanceof Builder) {
                $items[$key]['data'] = $state + $defaults;
            } else {
                $items[$key] = $state + $defaults;
            }

            $list->rawState($items);
        }

        $list->collapsed(false, shouldMakeComponentCollapsible: false);
        $list->callAfterStateUpdated();

        return $key;
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return array<array-key, mixed>
     */
    private static function placeAfter(array $items, string|int $key, string|int $after): array
    {
        $item = $items[$key];
        unset($items[$key]);

        $placed = [];

        foreach ($items as $itemKey => $itemState) {
            $placed[$itemKey] = $itemState;

            if ((string) $itemKey === (string) $after) {
                $placed[$key] = $item;
            }
        }

        return $placed;
    }
}
