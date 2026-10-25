<?php

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Livewire\Livewire;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\CopyItemAction;
use Workbench\App\Livewire\DocumentForm;
use Workbench\App\Livewire\NestedBuilderForm;
use Workbench\App\Livewire\PageBuilderForm;
use Workbench\App\Livewire\SchemaForm;
use Workbench\App\Schemas\DocumentSchema;
use Workbench\App\Schemas\PageBuilderSchema;

/**
 * `->clipboardShortcuts()`: the list's wrapper tells the script (builder-extensions.js)
 * how its own copy and paste actions copy an item and paste after one, for ⌘C / ⌘V on a
 * selected item.
 */

/**
 * The shortcuts of every list on the page, by state path, as the browser reads them.
 *
 * @return array<string, array<string, mixed>>
 */
function shortcutsIn(string $html): array
{
    $document = new DOMDocument;

    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
    libxml_clear_errors();

    $shortcuts = [];

    foreach ((new DOMXPath($document))->query('//*[@data-clipboard-shortcuts]') as $wrapper) {
        $shortcuts[$wrapper->getAttribute('data-state-path')] = json_decode($wrapper->getAttribute('data-clipboard-shortcuts'), true);
    }

    return $shortcuts;
}

it('tells the script how a list that asks for them copies and pastes its items', function () {
    $form = Livewire::test(PageBuilderForm::class);
    $intro = keyOf($form->get('data.content'), 'Intro');
    $blocks = array_keys($form->get("data.content.{$intro}.data.blocks"));
    $shortcuts = shortcutsIn($form->html());

    // A section's blocks: the item actions "Copy block" and "Paste after this block", each item showing them.
    expect($shortcuts["data.content.{$intro}.data.blocks"])
        ->copy->toBe(['kind' => PageBuilderSchema::BLOCK, 'builder' => true, 'notification' => 'Copied', 'items' => $blocks])
        ->paste->toMatchArray(['name' => 'pasteBlockAfter', 'arguments' => [], 'kind' => PageBuilderSchema::BLOCK, 'builder' => true, 'items' => $blocks])
        // The page's sections: "Paste section" below the builder, for any item.
        ->and($shortcuts['data.content'])
        ->copy->kind->toBe(PageBuilderSchema::SECTION)
        ->paste->name->toBe('pasteSection')
        ->paste->items->toBeNull()
        ->and(Livewire::test(DocumentForm::class)->html())->toContain('data-clipboard-shortcuts=')
        ->and(Livewire::test(NestedBuilderForm::class)->html())->not->toContain('data-clipboard-shortcuts=');
});

it('pastes after the item the keyboard names', function () {
    $form = Livewire::test(DocumentForm::class);
    $options = keyOf($form->get('data.sections'), 'Options');
    $sim = keyOf($form->get("data.sections.{$options}.positions"), 'SIM card');
    $paste = shortcutsIn($form->html())["data.sections.{$options}.positions"]['paste'];

    // A position registered on the list for the keys: its own sanitizer applies.
    expect($paste['name'])->toBe('pastePositionAfter')
        ->and($paste['kind'])->toBe(DocumentSchema::POSITION);

    $form->call('mountAction', $paste['name'], ['item' => $sim, 'pasted' => ['title' => 'Copied position', 'quantity' => 'two']], $paste['context']);

    $positions = $form->get("data.sections.{$options}.positions");

    expect(titlesOf($positions))->toBe(['SIM card', 'Copied position'])
        ->and(end($positions)['quantity'])->toEqual(1);

    Notification::assertNotified('Pasted');
});

it('pastes nothing into a full list, and says so', function () {
    $form = Livewire::test(DocumentForm::class);
    $hardware = keyOf($form->get('data.sections'), 'Hardware');
    $paste = shortcutsIn($form->html())["data.sections.{$hardware}.positions"]['paste'];

    // Three positions a section at most: Hardware's two and a third.
    $form->set("data.sections.{$hardware}.positions.extra", ['title' => 'Extra', 'quantity' => 1, 'price' => 1]);
    $form->call('mountAction', $paste['name'], ['pasted' => ['title' => 'One too many']], $paste['context']);

    expect(titlesOf($form->get("data.sections.{$hardware}.positions")))->toBe(['Camera rental', 'Mounting', 'Extra']);

    // That alone: nothing was pasted.
    Notification::assertNotified('This list is full');
    Notification::assertNotNotified('Pasted');
});

it('copies with the keys only items whose own copy button shows', function () {
    SchemaForm::$state = ['rows' => [['title' => 'Open'], ['title' => 'Secret']]];
    SchemaForm::$components = fn (): array => [
        Repeater::make('rows')
            ->schema([TextInput::make('title')])
            ->extraItemActions([
                fn (): CopyItemAction => CopyItemAction::make()
                    ->visible(fn (array $arguments, Repeater $component): bool => ($component->getRawState()[$arguments['item']]['title'] ?? null) !== 'Secret'),
            ])
            ->clipboardShortcuts(),
    ];

    $form = Livewire::test(SchemaForm::class);

    expect(shortcutsIn($form->html())['data.rows']['copy']['items'])->toBe([keyOf($form->get('data.rows'), 'Open')]);
});
