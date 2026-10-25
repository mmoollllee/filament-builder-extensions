<?php

namespace Mmoollllee\FilamentBuilderExtensions\Clipboard;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
use LogicException;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\Concerns\HasKind;
use Mmoollllee\FilamentBuilderExtensions\Items;

/**
 * Pastes an item copied with a {@see CopyItemAction} of the same kind. A click reads the
 * clipboard in the browser — the last copy of the past day in localStorage where the
 * clipboard holds none; an older one goes —, then mounts this action itself with what it
 * found (`pasted`), so no view has to offer it.
 *
 * Where it goes:
 *  - among a list's `extraItemActions()`: right after that item;
 *  - in a list's own `belowContent()` / `aboveContent()`: last;
 *  - anywhere else, say in a row of actions beside the list: last in the list `into()` names.
 *
 * What was pasted comes from the browser and is taken on no trust: a Builder item has to be
 * of a block the builder offers, and only what fits the item's fields is kept — or, with
 * `sanitizeUsing()`, only what the closure returns. Either way the item is added as the
 * list's own button adds it, its other fields at their defaults ({@see Items::add()}), and
 * the lists inside it are made a copy's ({@see settle()}).
 */
class PasteItemAction extends Action
{
    use HasKind;

    protected string|Closure|null $into = null;

    protected ?Closure $sanitizeUsing = null;

    public static function getDefaultName(): ?string
    {
        return 'pasteItem';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-builder-extensions::builder-extensions.paste.label'));

        $this->icon(Heroicon::OutlinedClipboardDocumentCheck);

        $this->failureNotificationTitle(__('filament-builder-extensions::builder-extensions.paste.nothing'));

        $this->alpineClickHandler(fn (PasteItemAction $action): string => 'window.FilamentBuilderExtensions?.paste($wire, '.Js::from($action->getClipboardConfig())->toHtml().')');

        // The new item's key is the answer: after ⌘V the script selects that item.
        $this->action(function (array $arguments, PasteItemAction $action): string|int|null {
            $list = $action->getList();

            // Stopped without a word, nor a success notification.
            if ($list->isDisabled()) {
                $action->cancel();
            }

            if (Items::isFull($list)) {
                Notification::make()
                    ->warning()
                    ->title(__('filament-builder-extensions::builder-extensions.paste.full'))
                    ->send();

                $action->cancel();
            }

            $item = $action->sanitize($arguments['pasted'] ?? null, $list);

            if ($item === null) {
                $action->failure();

                return null;
            }

            $state = ($list instanceof Builder) ? $item['data'] : $item;

            if (! $action->sanitizeUsing) {
                $state = fn (array $defaults): array => array_intersect_key($state, $defaults);
            }

            $after = ($action->getSchemaComponent() === $list) ? ($arguments['item'] ?? null) : null;

            $key = Items::add(
                $list,
                $state,
                block: ($list instanceof Builder) ? $item['type'] : null,
                after: (is_string($after) || is_int($after)) ? $after : null,
            );

            self::settle($list->getChildSchema($key));

            $action->success();

            return $key;
        });
    }

    /**
     * The list to paste into, when the action is not one of its own: the name of a list
     * beside the action ({@see Items::beside()}), or a closure returning the list.
     */
    public function into(string|Closure|null $list): static
    {
        $this->into = $list;

        return $this;
    }

    /**
     * Takes from a pasted item what may be added: the closure gets it as it was copied
     * (`$item`: a Repeater row's state, a Builder's `['type' => …, 'data' => […]]`) and the
     * target (`$list`), and returns the state to add in the same shape — put over the
     * fields' defaults as it is — or null to refuse it.
     */
    public function sanitizeUsing(?Closure $callback): static
    {
        $this->sanitizeUsing = $callback;

        return $this;
    }

    public function getList(): Builder|Repeater
    {
        $into = $this->evaluate($this->into);

        if ($into instanceof Builder || $into instanceof Repeater) {
            return $into;
        }

        $component = $this->getSchemaComponent();

        if (filled($into)) {
            return Items::beside($component, $into);
        }

        if ($component instanceof Builder || $component instanceof Repeater) {
            return $component;
        }

        throw new LogicException('A PasteItemAction outside a Builder or Repeater needs into() to name the list it pastes into.');
    }

    public function getKind(): string
    {
        return $this->evaluate($this->kind) ?? $this->getList()->getName();
    }

    /**
     * What the script needs to mount the action with a pasted item: invoked with
     * `['item' => $key]`, it pastes after that item. Only the arguments it was invoked with,
     * as in Filament's own click handlers: a mount leaves its own on the action.
     *
     * @return array{name: string, arguments: object, context: object, kind: string, builder: bool, notification: ?string}
     */
    public function getClipboardConfig(): array
    {
        return [
            'name' => $this->getName(),
            'arguments' => (object) ($this->getInvokedArguments() ?? []),
            'context' => (object) $this->getContext(),
            'kind' => $this->getKind(),
            'builder' => $this->getList() instanceof Builder,
            'notification' => $this->getFailureNotificationTitle(),
        ];
    }

    /**
     * A pasted item in the shape the list holds — through the closure of sanitizeUsing() when
     * there is one —, null for nothing to add. Without a closure, the action keeps no more of
     * it than the keys the item's fields have.
     *
     * @return ?array<string, mixed>
     */
    public function sanitize(mixed $pasted, Builder|Repeater $list): ?array
    {
        if (! is_array($pasted)) {
            return null;
        }

        $item = $this->sanitizeUsing
            ? $this->evaluate($this->sanitizeUsing, ['item' => $pasted, 'list' => $list])
            : $pasted;

        if (! is_array($item)) {
            return null;
        }

        if (! $list instanceof Builder) {
            return $item;
        }

        $type = $item['type'] ?? null;
        $data = $item['data'] ?? [];

        if ((! is_string($type)) || (! Items::offers($list, $type)) || (! is_array($data))) {
            return null;
        }

        return ['type' => $type, 'data' => $data];
    }

    /**
     * The lists inside a pasted item, made a copy's: every item under a key of its own — the
     * copy shares none with its original, so items can be dragged between the two —, and in
     * a Builder only items of a block it has, as the item itself had to be. Anything else
     * would stay in the state unseen: Filament renders no item of a block it does not know.
     */
    protected static function settle(?Schema $schema): void
    {
        foreach ($schema?->getComponents(withActions: false, withHidden: true) ?? [] as $component) {
            if (! ($component instanceof Builder || $component instanceof Repeater)) {
                foreach ($component->getChildSchemas(withHidden: true) as $childSchema) {
                    self::settle($childSchema);
                }

                continue;
            }

            $items = [];

            foreach (is_array($state = $component->getRawState()) ? $state : [] as $item) {
                if ((! is_array($item)) || (($component instanceof Builder) && (! (is_string($item['type'] ?? null) && $component->hasBlock($item['type']))))) {
                    continue;
                }

                if (filled($key = $component->generateUuid())) {
                    $items[$key] = $item;
                } else {
                    $items[] = $item;
                }
            }

            $component->rawState($items);

            foreach (array_keys($items) as $key) {
                self::settle($component->getChildSchema($key));
            }
        }
    }
}
