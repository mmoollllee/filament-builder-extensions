<?php

namespace Workbench\App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\CopyItemAction;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\PasteItemAction;
use UnitEnum;

/**
 * Repeater titles; and the same rows in an action's modal, where ⌘C / ⌘V work inside the
 * modal's focus trap.
 */
class RepeaterDemo extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static string|UnitEnum|null $navigationGroup = 'Titles';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Repeater titles';

    protected static ?string $slug = 'repeater-titles';

    protected string $view = 'filament-builder-demo';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'specs' => [
                ['beschriftung' => 'Working height', 'wert' => '8', 'einheit' => 'm'],
                ['beschriftung' => 'Weight', 'wert' => '1200', 'einheit' => 'kg'],
            ],
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Repeater::make('specs')
                    ->schema([
                        Hidden::make('beschriftung'),
                        TextInput::make('wert')->label('Value'),
                        TextInput::make('einheit')->label('Unit'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->title('beschriftung', placeholder: 'Row label...'),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editInModal')
                ->label('Edit in a modal')
                ->fillForm(fn (): array => ['specs' => $this->data['specs'] ?? []])
                ->schema([
                    Repeater::make('specs')
                        ->schema([
                            Hidden::make('beschriftung'),
                            TextInput::make('wert')->label('Value'),
                        ])
                        ->title('beschriftung', placeholder: 'Row label...')
                        ->extraItemActions([fn (): CopyItemAction => CopyItemAction::make('copySpec')->kind('workbench.spec')])
                        ->registerActions([fn (): PasteItemAction => PasteItemAction::make('pasteSpec')->kind('workbench.spec')])
                        ->clipboardShortcuts(),
                ])
                ->action(fn (array $data) => $this->form->fill(['specs' => $data['specs']])),
        ];
    }

    public function save(): void
    {
        $this->form->getState();
    }
}
