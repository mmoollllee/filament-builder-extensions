<?php

namespace Mmoollllee\FilamentBuilderExtensions\Clipboard;

use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
use LogicException;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\Concerns\HasKind;

/**
 * An item action (`->extraItemActions([fn () => CopyItemAction::make()])`) that copies the
 * item — its state as the form holds it now, unsaved edits included — as JSON to the
 * clipboard and, kept for a day where the browser refuses that, to localStorage. It runs in
 * the browser alone; a {@see PasteItemAction} of the same kind takes it back, in another
 * list, another record or another tab.
 */
class CopyItemAction extends Action
{
    use HasKind;

    public static function getDefaultName(): ?string
    {
        return 'copyItem';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-builder-extensions::builder-extensions.copy.label'));

        $this->icon(Heroicon::OutlinedClipboardDocument);

        $this->successNotificationTitle(__('filament-builder-extensions::builder-extensions.copy.copied'));

        $this->alpineClickHandler(fn (CopyItemAction $action, array $arguments, Builder|Repeater $component): string => 'window.FilamentBuilderExtensions?.copy($wire, '.Js::from([
            'path' => "{$component->getStatePath()}.{$arguments['item']}",
            ...$action->getClipboardConfig(),
        ])->toHtml().')');
    }

    /**
     * What the script needs to copy an item of the list, but its path.
     *
     * @return array{kind: string, builder: bool, notification: ?string}
     */
    public function getClipboardConfig(): array
    {
        return [
            'kind' => $this->getKind(),
            'builder' => $this->getSchemaComponent() instanceof Builder,
            'notification' => $this->getSuccessNotificationTitle(),
        ];
    }

    public function getKind(): string
    {
        $list = $this->getSchemaComponent();

        if (! ($list instanceof Builder || $list instanceof Repeater)) {
            throw new LogicException('A CopyItemAction belongs among the extraItemActions() of a Builder or Repeater.');
        }

        return $this->evaluate($this->kind) ?? $list->getName();
    }
}
