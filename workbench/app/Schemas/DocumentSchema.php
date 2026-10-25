<?php

namespace Workbench\App\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\CopyItemAction;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\PasteItemAction;
use Mmoollllee\FilamentBuilderExtensions\Items;
use Workbench\App\Models\Document;

/**
 * Repeaters inside a repeater, as an invoice's sections hold its positions: a position is
 * dragged from one section into another (`->transferGroup()`), copied and pasted into
 * another section (`CopyItemAction`, `PasteItemAction`, or ⌘C / ⌘V on a selected position:
 * `->clipboardShortcuts()`), and added from a row of actions beside the positions instead
 * of the repeater's own button (`Items::add()`).
 */
final class DocumentSchema
{
    public const POSITION = 'workbench.position';

    /**
     * With `$relationships`, the sections and positions are a {@see Document}'s rows, saved
     * through relationship repeaters: a position dragged into another section is saved there
     * as the same row.
     *
     * @return array<Component>
     */
    public static function components(bool $relationships = false): array
    {
        return [
            Repeater::make('sections')
                ->when($relationships, fn (Repeater $repeater): Repeater => $repeater->relationship()->orderColumn('sort'))
                ->hiddenLabel()
                ->collapsible()
                ->addable(false)
                ->title('title', placeholder: 'Untitled section')
                ->schema([
                    Hidden::make('title'),
                    self::positions($relationships),
                    Actions::make([
                        self::addAction('addPosition', 'Add position')
                            ->action(fn (Actions $component) => Items::add(Items::beside($component, 'positions'))),
                        self::pastePositionAction('pastePosition')
                            ->into('positions')
                            ->button()
                            ->size(Size::Small)
                            ->color('gray'),
                    ]),
                ]),
            Actions::make([
                self::addAction('addSection', 'Add section')
                    ->action(fn (Actions $component) => Items::add(Items::beside($component, 'sections'), ['title' => 'New section'])),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sampleState(): array
    {
        return [
            'sections' => [
                'hardware' => [
                    'title' => 'Hardware',
                    'positions' => [
                        'camera' => ['title' => 'Camera rental', 'quantity' => 2, 'price' => 49],
                        'mount' => ['title' => 'Mounting', 'quantity' => 1, 'price' => 120],
                    ],
                ],
                'options' => [
                    'title' => 'Options',
                    'positions' => [
                        'sim' => ['title' => 'SIM card', 'quantity' => 1, 'price' => 10],
                    ],
                ],
                'empty' => [
                    'title' => 'Empty section',
                    'positions' => [],
                ],
            ],
        ];
    }

    private static function positions(bool $relationships): Repeater
    {
        return Repeater::make('positions')
            ->when($relationships, fn (Repeater $repeater): Repeater => $repeater->relationship()->orderColumn('sort'))
            ->hiddenLabel()
            ->collapsible()
            ->addable(false)
            ->maxItems(3)
            ->transferGroup('positions')
            // As closures: every section's positions are a clone of this repeater, and its
            // actions must be their own (see the README).
            ->extraItemActions([
                fn (): CopyItemAction => CopyItemAction::make('copyPosition')
                    ->kind(self::POSITION)
                    ->label('Copy position'),
            ])
            // ⌘V pastes after the selected position: with an action of the list's own.
            ->registerActions([
                fn (): PasteItemAction => self::pastePositionAction('pastePositionAfter'),
            ])
            ->clipboardShortcuts()
            ->title('title', placeholder: 'Untitled position')
            ->columns(2)
            ->schema([
                Hidden::make('title'),
                TextInput::make('quantity')
                    ->numeric()
                    ->default(1),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('€'),
            ]);
    }

    /** A copied position; it may come from another record or another tab: text as text, numbers as numbers. */
    private static function pastePositionAction(string $name): PasteItemAction
    {
        return PasteItemAction::make($name)
            ->kind(self::POSITION)
            ->successNotificationTitle('Pasted')
            ->sanitizeUsing(fn (array $item): array => [
                'title' => is_string($item['title'] ?? null) ? $item['title'] : null,
                'quantity' => is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1,
                'price' => is_numeric($item['price'] ?? null) ? (float) $item['price'] : null,
            ]);
    }

    private static function addAction(string $name, string $label): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::Plus)
            ->button()
            ->size(Size::Small)
            ->color('gray');
    }
}
