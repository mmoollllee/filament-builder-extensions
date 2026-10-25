<?php

namespace Mmoollllee\FilamentBuilderExtensions\Clipboard;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Mmoollllee\FilamentBuilderExtensions\FilamentBuilderExtensionsServiceProvider;

/**
 * Copying and pasting whole items from the keyboard: with `->clipboardShortcuts()` on a
 * Builder or Repeater, a click on an item — outside its fields — selects it; ⌘C copies it
 * and ⌘V pastes after it (Ctrl+C / Ctrl+V on other systems). In a field, or with text
 * selected, the keys copy and paste text as ever.
 *
 * The keys use the list's own actions: its {@see CopyItemAction} (among its item actions)
 * and its {@see PasteItemAction} (among its item actions, registered on it, or in its
 * `aboveContent()` / `belowContent()`) — with their kind, their checks and their messages.
 */
final class ClipboardShortcuts
{
    public const ATTRIBUTE = 'data-clipboard-shortcuts';

    public static function registerMacros(): void
    {
        foreach ([Builder::class, Repeater::class] as $list) {
            $list::macro('clipboardShortcuts', function (bool|Closure $condition = true) {
                /** @var Builder|Repeater $this */
                $this->extraAttributes(
                    fn (Builder|Repeater $component): array => $component->evaluate($condition) ? ClipboardShortcuts::attributes($component) : [],
                    merge: true,
                );

                return $this;
            });
        }
    }

    /**
     * What the field's wrapper carries for the script: what the list's copy and paste actions
     * need in the browser, and its state path, by which the script finds the selected item
     * again whatever a render did to its element. Escaped: Filament merges extra attributes
     * unescaped.
     *
     * @return array<string, string>
     */
    public static function attributes(Builder|Repeater $list): array
    {
        $copy = self::findAction($list, CopyItemAction::class);
        $paste = $list->isDisabled() ? null : self::findAction($list, PasteItemAction::class);

        if (! ($copy || $paste)) {
            return [];
        }

        return [
            self::ATTRIBUTE => e(json_encode([
                'copy' => $copy ? [...$copy->getClipboardConfig(), 'items' => self::itemsOf($list, $copy)] : null,
                'paste' => $paste ? [...$paste->getClipboardConfig(), 'items' => self::itemsOf($list, $paste)] : null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'data-state-path' => e($list->getStatePath()),
            'x-load-js' => FilamentBuilderExtensionsServiceProvider::loadScriptAttribute(),
        ];
    }

    /**
     * The list's first visible action of a class: one of its own, else one of its content
     * slots. An item action counts as visible here: it is visible or not item by item
     * ({@see itemsOf()}), as Filament asks it.
     *
     * @template TAction of Action
     *
     * @param  class-string<TAction>  $class
     * @return ?TAction
     */
    private static function findAction(Builder|Repeater $list, string $class): ?Action
    {
        $actions = [...$list->getActions()];

        foreach ([Field::ABOVE_CONTENT_SCHEMA_KEY, Field::BELOW_CONTENT_SCHEMA_KEY] as $slot) {
            foreach ($list->getChildSchema($slot)?->getFlatComponents(withActions: true) ?? [] as $component) {
                if ($component instanceof Action) {
                    $actions[] = $component;
                }
            }
        }

        foreach ($actions as $action) {
            if (($action instanceof $class) && (self::isItemAction($list, $action) || $action->isVisible())) {
                return $action;
            }
        }

        return null;
    }

    /**
     * The keys of the items an item action is visible for, as Filament renders it for each
     * item; null for an action of the whole list, which acts on any item.
     *
     * @return ?list<array-key>
     */
    private static function itemsOf(Builder|Repeater $list, Action $action): ?array
    {
        if (! self::isItemAction($list, $action)) {
            return null;
        }

        $items = $list->getRawState();

        return array_values(array_filter(
            array_keys(is_array($items) ? $items : []),
            fn (string|int $key): bool => $action(['item' => $key])->isVisible(),
        ));
    }

    private static function isItemAction(Builder|Repeater $list, Action $action): bool
    {
        return ($list->getExtraItemActions()[$action->getName()] ?? null) === $action;
    }
}
