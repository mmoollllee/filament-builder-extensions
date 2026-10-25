<?php

namespace Mmoollllee\FilamentBuilderExtensions\Transfer;

use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Contracts\HasSchemas;
use Mmoollllee\FilamentBuilderExtensions\Items;

/**
 * An item dropped into this list from another of its transfer group. The script mounts it
 * on the target list, as Filament's own reorder is mounted, with
 *
 *  - `from`: the key of the list the item left,
 *  - `moved`: the item's key,
 *  - `fromItems` and `toItems`: both lists' keys in the order the browser shows them,
 *  - `toIndex`: where in `toItems` the item was dropped.
 *
 * None of it is taken on trust. The source has to be a field of the same form, of the same
 * kind and in the same group, both lists have to be reorderable and the source deletable —
 * an item that leaves it is deleted there, a relationship's record with it on save —, the
 * item has to be one of the source's, and a Builder has to offer the item's block. Then the
 * item leaves the one state for the other, its key kept — unless the target holds that key
 * already, as a copy or a clone of the item's list does: then it takes a key of its own
 * there —, and both lists take the order the browser shows; keys they leave out go last. A
 * drop the target cannot take (a block it does not offer, a list that is full) says so; the
 * item stays where it was.
 */
class TransferItemAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'transferItem';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->livewireClickHandlerEnabled(false);

        $this->visible(fn (Builder|Repeater $component): bool => $component->isReorderableWithDragAndDrop());

        $this->failureNotificationTitle(__('filament-builder-extensions::builder-extensions.transfer.refused'));

        $this->action(function (array $arguments, Builder|Repeater $component, TransferItemAction $action): void {
            $action->transfer($component, $arguments);
        });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function transfer(Builder|Repeater $target, array $arguments): void
    {
        $source = $this->resolveSource($target, $arguments['from'] ?? null);
        $moved = $arguments['moved'] ?? null;

        if ((! $source) || (! (is_string($moved) || is_int($moved)))) {
            return;
        }

        $sourceItems = $source->getRawState();

        if ((! is_array($sourceItems)) || (! array_key_exists($moved, $sourceItems)) || (! is_array($sourceItems[$moved]))) {
            return;
        }

        // A list inside the item itself: SortableJS never drops an item into itself.
        if (str_starts_with("{$target->getStatePath()}.", "{$source->getStatePath()}.{$moved}.")) {
            return;
        }

        $item = $sourceItems[$moved];

        if (
            (($target instanceof Builder) && (! Items::offers($target, (string) ($item['type'] ?? '')))) ||
            Items::isFull($target)
        ) {
            $this->failure();

            return;
        }

        $key = $moved;
        $toItems = is_array($arguments['toItems'] ?? null) ? array_values($arguments['toItems']) : [];
        $targetItems = $target->getRawState();

        // The target holds that key already — a copy's or a clone's, which keep the keys of the
        // items inside them: the item takes a key of its own there, where it was dropped.
        if (is_array($targetItems) && array_key_exists($moved, $targetItems)) {
            $key = $target->generateUuid();
            $index = $arguments['toIndex'] ?? null;

            if (blank($key) || (! is_int($index)) || (($toItems[$index] ?? null) !== $moved)) {
                $this->failure();

                return;
            }

            $toItems[$index] = $key;
        }

        unset($sourceItems[$moved]);
        $source->rawState(self::inOrder($sourceItems, $arguments['fromItems'] ?? []));

        // Read again: the target may lie inside one of the source's items, or the source inside the target's.
        $targetItems = $target->getRawState();
        $targetItems = is_array($targetItems) ? $targetItems : [];
        $targetItems[$key] = $item;
        $target->rawState(self::inOrder($targetItems, $toItems));

        $source->callAfterStateUpdated();
        $target->callAfterStateUpdated();
    }

    /** The list the item left: a field of this form, of the target's kind and group, other than the target, that may lose it. */
    protected function resolveSource(Builder|Repeater $target, mixed $key): Builder|Repeater|null
    {
        $livewire = $target->getLivewire();

        if ((! is_string($key)) || blank($key) || (! $livewire instanceof HasSchemas)) {
            return null;
        }

        $source = $livewire->getSchemaComponent($key);
        $group = TransferGroup::of($target);

        if (
            (! ($source instanceof Builder || $source instanceof Repeater)) ||
            (($source instanceof Builder) !== ($target instanceof Builder)) ||
            ($source->getStatePath() === $target->getStatePath()) ||
            ($group === null) ||
            (TransferGroup::of($source) !== $group) ||
            (! $source->isReorderableWithDragAndDrop()) ||
            (! $source->isDeletable())
        ) {
            return null;
        }

        return $source;
    }

    /**
     * A list's items in the order given, any it leaves out after them.
     *
     * @param  array<array-key, mixed>  $items
     * @param  array<mixed>  $order
     * @return array<array-key, mixed>
     */
    public static function inOrder(array $items, mixed $order): array
    {
        $ordered = [];

        foreach (is_array($order) ? $order : [] as $key) {
            if ((is_string($key) || is_int($key)) && array_key_exists($key, $items)) {
                $ordered[$key] = $items[$key];
                unset($items[$key]);
            }
        }

        return $ordered + $items;
    }
}
