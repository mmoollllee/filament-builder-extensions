<?php

namespace Workbench\App\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\CopyItemAction;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\PasteItemAction;

/**
 * Builders inside a builder, as a page's sections hold its blocks: a block is dragged from
 * one section into another — an empty one included —, copied, and pasted after another
 * block or below a section's blocks, by button or by ⌘C / ⌘V on a selected block or
 * section. The "Quote" block only exists among the blocks of the second builder kind
 * ("aside"), so a drop of one into a section is refused, and a section holds one "Image" at
 * most.
 */
final class PageBuilderSchema
{
    public const BLOCK = 'workbench.block';

    public const SECTION = 'workbench.section';

    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            Builder::make('content')
                ->hiddenLabel()
                ->blockNumbers(false)
                ->collapsible()
                ->addActionLabel('Add section')
                ->clipboardShortcuts()
                ->extraItemActions([
                    fn (): CopyItemAction => CopyItemAction::make('copySection')
                        ->kind(self::SECTION)
                        ->label('Copy section'),
                ])
                ->belowContent([
                    PasteItemAction::make('pasteSection')
                        ->kind(self::SECTION)
                        ->label('Paste section')
                        ->link(),
                ])
                ->blocks([
                    Block::make('section')
                        ->label('Section')
                        ->title('title', placeholder: 'Section title…', suffix: 'Section')
                        ->schema([
                            Hidden::make('title'),
                            self::blocks(withQuote: false),
                        ]),
                    Block::make('aside')
                        ->label('Aside')
                        ->title('title', placeholder: 'Aside title…', suffix: 'Aside')
                        ->schema([
                            Hidden::make('title'),
                            self::blocks(withQuote: true),
                        ]),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sampleState(): array
    {
        return [
            'content' => [
                'intro' => [
                    'type' => 'section',
                    'data' => [
                        'title' => 'Intro',
                        'blocks' => [
                            'welcome' => ['type' => 'text', 'data' => ['title' => 'Welcome', 'body' => 'Hello there.']],
                            'hero' => ['type' => 'image', 'data' => ['title' => 'Hero image', 'url' => 'https://example.test/hero.jpg', 'alt' => 'A hero']],
                        ],
                    ],
                ],
                'services' => [
                    'type' => 'section',
                    'data' => [
                        'title' => 'Services',
                        'blocks' => [
                            'list' => ['type' => 'text', 'data' => ['title' => 'What we do', 'body' => 'Plenty.']],
                        ],
                    ],
                ],
                'outro' => [
                    'type' => 'section',
                    'data' => [
                        'title' => 'Empty section',
                        'blocks' => [],
                    ],
                ],
                'sidebar' => [
                    'type' => 'aside',
                    'data' => [
                        'title' => 'Sidebar',
                        'blocks' => [
                            'praise' => ['type' => 'quote', 'data' => ['title' => 'Praise', 'quote' => 'Splendid!']],
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function blocks(bool $withQuote): Builder
    {
        return Builder::make('blocks')
            ->hiddenLabel()
            ->blockNumbers(false)
            ->collapsible()
            ->addActionLabel('Add block')
            ->transferGroup('blocks')
            ->clipboardShortcuts()
            // As closures: every section's blocks are a clone of this builder, and its actions
            // must be their own (see the README).
            ->extraItemActions([
                fn (): CopyItemAction => CopyItemAction::make('copyBlock')
                    ->kind(self::BLOCK)
                    ->label('Copy block'),
                fn (): PasteItemAction => PasteItemAction::make('pasteBlockAfter')
                    ->kind(self::BLOCK)
                    ->label('Paste after this block'),
            ])
            ->belowContent([
                PasteItemAction::make('pasteBlock')
                    ->kind(self::BLOCK)
                    ->label('Paste block')
                    ->link(),
            ])
            ->blocks([
                Block::make('text')
                    ->label('Text')
                    ->title('title', placeholder: 'Text title…', suffix: 'Text')
                    ->schema([
                        Hidden::make('title'),
                        Textarea::make('body'),
                    ]),
                Block::make('image')
                    ->label('Image')
                    ->maxItems(1)
                    ->title('title', placeholder: 'Image title…', suffix: 'Image')
                    ->schema([
                        Hidden::make('title'),
                        TextInput::make('url')
                            ->url(),
                        TextInput::make('alt')
                            ->default('No description'),
                    ]),
                ...($withQuote ? [
                    Block::make('quote')
                        ->label('Quote')
                        ->title('title', placeholder: 'Quote title…', suffix: 'Quote')
                        ->schema([
                            Hidden::make('title'),
                            Textarea::make('quote'),
                        ]),
                ] : []),
            ]);
    }
}
