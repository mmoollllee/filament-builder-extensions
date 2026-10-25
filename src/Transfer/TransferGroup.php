<?php

namespace Mmoollllee\FilamentBuilderExtensions\Transfer;

use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Mmoollllee\FilamentBuilderExtensions\FilamentBuilderExtensionsServiceProvider;

/**
 * Dragging items between lists: `->transferGroup('name')` on a Builder or Repeater lets
 * its items be dragged into every other list of that group on the page, and theirs into
 * it — the positions of one section into another, the blocks of one section into the next.
 *
 * No view is overridden. The list itself takes no attributes, so the field's wrapper
 * carries the group, the field's key and its state path; the script (builder-extensions.js)
 * joins the group's lists into one SortableJS group when a handle is pressed, and a drop
 * into another list mounts the target's `transferItem` action ({@see TransferItemAction}),
 * which checks both lists against the form and moves the item in its state.
 */
final class TransferGroup
{
    public const ATTRIBUTE = 'data-transfer-group';

    public static function registerMacros(): void
    {
        foreach ([Builder::class, Repeater::class] as $list) {
            $list::macro('transferGroup', function (string|Closure|null $group) {
                /** @var Builder|Repeater $this */
                // Kept apart from the attributes, so the server asks the list itself, not its markup.
                $this->meta(TransferGroup::class, $group);

                $this->extraAttributes(
                    fn (Builder|Repeater $component): array => TransferGroup::attributes($component),
                    merge: true,
                );

                $this->registerActions([
                    fn (): TransferItemAction => TransferItemAction::make(),
                ]);

                return $this;
            });
        }
    }

    /**
     * What the field's wrapper carries for the script: the group, the field's key (which
     * resolves it on the server, as Filament's own reorder does) and whether items may leave
     * it — and its state path, which names the list on the page, for scripts of your own as
     * for the shortcuts. All escaped: Filament merges extra attributes unescaped.
     *
     * @return array<string, string>
     */
    public static function attributes(Builder|Repeater $list): array
    {
        if (($group = self::of($list)) === null) {
            return [];
        }

        return [
            self::ATTRIBUTE => e($group),
            'data-transfer-key' => e($list->getKey()),
            // An item dragged out is one deleted here: not from a list that may not lose any.
            ...($list->isDeletable() ? [] : ['data-transfer-pull' => 'false']),
            'data-state-path' => e($list->getStatePath()),
            'data-transfer-drop-label' => e(__('filament-builder-extensions::builder-extensions.transfer.drop_here')),
            'x-load-js' => FilamentBuilderExtensionsServiceProvider::loadScriptAttribute(),
        ];
    }

    /** The group of a list, null for none. */
    public static function of(Builder|Repeater $list): ?string
    {
        $group = $list->evaluate($list->getMeta(self::class));

        return filled($group) ? (string) $group : null;
    }
}
