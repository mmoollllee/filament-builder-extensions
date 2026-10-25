<?php

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Livewire\Livewire;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\CopyItemAction;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\PasteItemAction;
use Workbench\App\Livewire\DocumentForm;
use Workbench\App\Livewire\PageBuilderForm;
use Workbench\App\Livewire\SchemaForm;
use Workbench\App\Schemas\DocumentSchema;
use Workbench\App\Schemas\PageBuilderSchema;

/**
 * `CopyItemAction` and `PasteItemAction`: a copy is the browser's alone (builder-extensions.js),
 * a paste mounts its action with what the browser found — here as the script mounts it,
 * from the name, arguments and context the action's click handler hands it.
 */

/** The paste call a click on `$name` makes in the list at `$statePath`, after `$item` for an item action. */
function pasteCall(string $html, string $name, string $statePath, ?string $item = null): array
{
    foreach (clipboardCalls($html, 'paste') as $call) {
        if (($call['name'] === $name) && (($call['arguments']['item'] ?? null) === $item) && str_ends_with($call['context']['schemaComponent'], $statePath)) {
            return $call;
        }
    }

    throw new RuntimeException("No paste action [{$name}] at [{$statePath}].");
}

it('copies an item as the form holds it, with its kind', function () {
    $form = Livewire::test(DocumentForm::class);
    $sections = $form->get('data.sections');
    $hardware = keyOf($sections, 'Hardware');
    $camera = keyOf($sections[$hardware]['positions'], 'Camera rental');

    expect(clipboardCalls($form->html(), 'copy'))->toContain([
        'path' => "data.sections.{$hardware}.positions.{$camera}",
        'kind' => DocumentSchema::POSITION,
        'builder' => false,
        'notification' => 'Copied',
    ]);
});

it('pastes a position last into the list beside the action, as its closure lets it', function () {
    $form = Livewire::test(DocumentForm::class);
    $options = keyOf($form->get('data.sections'), 'Options');
    // The row of actions sits in the section beside its positions: its context names the section.
    $call = pasteCall($form->html(), 'pastePosition', $options);

    expect($call['kind'])->toBe(DocumentSchema::POSITION);

    $form->call('mountAction', $call['name'], [...$call['arguments'], 'pasted' => [
        'title' => 'Camera rental',
        'quantity' => 'two',
        'price' => '49.5',
        'notes' => 'not a field',
    ]], $call['context']);

    $positions = $form->get("data.sections.{$options}.positions");

    expect(titlesOf($positions))->toBe(['SIM card', 'Camera rental'])
        ->and(end($positions))->toEqual(['title' => 'Camera rental', 'quantity' => 1, 'price' => 49.5]);
});

it('pastes a block right after the item it was asked from, keeping what fits its fields', function () {
    $form = Livewire::test(PageBuilderForm::class);
    $intro = keyOf($form->get('data.content'), 'Intro');
    $blocks = $form->get("data.content.{$intro}.data.blocks");
    $call = pasteCall($form->html(), 'pasteBlockAfter', "{$intro}.data.blocks", keyOf($blocks, 'Welcome'));

    expect($call['kind'])->toBe(PageBuilderSchema::BLOCK);

    $form->call('mountAction', $call['name'], [...$call['arguments'], 'pasted' => [
        'type' => 'text',
        'data' => ['title' => 'Copied text', 'body' => 'Again.', 'script' => '<script>'],
    ]], $call['context'])
        // The action answers with the new item's key, for the script to select it.
        ->assertReturned(fn (string $key): bool => keyOf($form->get("data.content.{$intro}.data.blocks"), 'Copied text') === $key);

    $blocks = $form->get("data.content.{$intro}.data.blocks");

    expect(titlesOf($blocks))->toBe(['Welcome', 'Copied text', 'Hero image'])
        // The key no field has dropped.
        ->and($blocks[keyOf($blocks, 'Copied text')]['data'])->toEqual(['title' => 'Copied text', 'body' => 'Again.']);
});

it('gives a pasted block what it lacks at its fields\' defaults, in the copy\'s order', function () {
    $form = Livewire::test(PageBuilderForm::class);
    $services = keyOf($form->get('data.content'), 'Services');
    $call = pasteCall($form->html(), 'pasteBlock', "{$services}.data.blocks");

    $form->call('mountAction', $call['name'], [...$call['arguments'], 'pasted' => [
        'type' => 'image',
        'data' => ['title' => 'Copied image', 'url' => 'https://example.test/copy.jpg'],
    ]], $call['context']);

    $blocks = $form->get("data.content.{$services}.data.blocks");
    $pasted = $blocks[keyOf($blocks, 'Copied image')];

    expect($pasted['data'])->toEqual(['title' => 'Copied image', 'url' => 'https://example.test/copy.jpg', 'alt' => 'No description'])
        ->and(array_keys($pasted['data']))->toBe(['title', 'url', 'alt']);
});

it('pastes a block last from below the builder', function () {
    $form = Livewire::test(PageBuilderForm::class);
    $services = keyOf($form->get('data.content'), 'Services');
    $call = pasteCall($form->html(), 'pasteBlock', "{$services}.data.blocks");

    $form->call('mountAction', $call['name'], [...$call['arguments'], 'pasted' => [
        'type' => 'text',
        'data' => ['title' => 'Copied text', 'body' => 'Again.'],
    ]], $call['context']);

    expect(titlesOf($form->get("data.content.{$services}.data.blocks")))->toBe(['What we do', 'Copied text']);
});

it('refuses what the list cannot hold, and says so', function (mixed $pasted) {
    $form = Livewire::test(PageBuilderForm::class);
    $services = keyOf($form->get('data.content'), 'Services');
    $call = pasteCall($form->html(), 'pasteBlock', "{$services}.data.blocks");

    $form->call('mountAction', $call['name'], [...$call['arguments'], 'pasted' => $pasted], $call['context']);

    expect(titlesOf($form->get("data.content.{$services}.data.blocks")))->toBe(['What we do']);

    Notification::assertNotified('Nothing to paste here');
})->with([
    'a block the builder does not offer' => [['type' => 'quote', 'data' => ['title' => 'Praise', 'quote' => 'Splendid!']]],
    'a block without a type' => [['data' => ['title' => 'Untyped']]],
    'no item at all' => ['{"type":"text"}'],
]);

it('loads the script from its button, whatever extra attributes the app gives it', function () {
    foreach ([CopyItemAction::make(), PasteItemAction::make()->into('lines')] as $action) {
        expect($action->extraAttributes(['data-app' => 'own'])->getExtraAttributes())
            ->toHaveKey('data-app', 'own')
            ->toHaveKey('x-load-js');
    }
});

it('refuses a block the builder holds as often as the block allows', function () {
    $form = Livewire::test(PageBuilderForm::class);
    $intro = keyOf($form->get('data.content'), 'Intro');
    $call = pasteCall($form->html(), 'pasteBlock', "{$intro}.data.blocks");

    // A section holds one image at most, and the intro has its hero.
    $form->call('mountAction', $call['name'], [...$call['arguments'], 'pasted' => ['type' => 'image', 'data' => ['title' => 'Second image']]], $call['context']);

    expect(titlesOf($form->get("data.content.{$intro}.data.blocks")))->toBe(['Welcome', 'Hero image']);

    Notification::assertNotified('Nothing to paste here');
});

it('pastes the lists inside an item as a copy\'s: own keys, and blocks the builder has', function () {
    $form = Livewire::test(PageBuilderForm::class);
    $content = $form->get('data.content');
    $intro = $content[keyOf($content, 'Intro')];
    $call = pasteCall($form->html(), 'pasteSection', '');

    // The intro as copied, its blocks under their keys, and a quote no section has.
    $form->call('mountAction', $call['name'], [...$call['arguments'], 'pasted' => ['type' => 'section', 'data' => [
        'title' => 'Copied intro',
        'blocks' => [...$intro['data']['blocks'], 'quote' => ['type' => 'quote', 'data' => ['title' => 'Praise', 'quote' => 'Splendid!']]],
    ]]], $call['context']);

    $content = $form->get('data.content');
    $blocks = $content[keyOf($content, 'Copied intro')]['data']['blocks'];

    expect(titlesOf($blocks))->toBe(['Welcome', 'Hero image'])
        ->and(array_intersect_key($blocks, $intro['data']['blocks']))->toBe([]);
});

it('keeps a nested list on actions of its own after a paste it refused', function () {
    $form = Livewire::test(PageBuilderForm::class);
    $services = keyOf($form->get('data.content'), 'Services');
    $after = keyOf($form->get("data.content.{$services}.data.blocks"), 'What we do');
    $call = pasteCall($form->html(), 'pasteBlockAfter', "{$services}.data.blocks", $after);

    $form->call('mountAction', $call['name'], [...$call['arguments'], 'pasted' => ['type' => 'quote', 'data' => ['title' => 'Praise']]], $call['context']);

    // The services' buttons still paste into the services' blocks, not those rendered before them.
    expect(pasteCall($form->html(), 'pasteBlockAfter', "{$services}.data.blocks", $after)['context'])->toBe($call['context']);
});

it('pastes last from the button below the builder after ⌘V pasted after an item', function () {
    $form = Livewire::test(PageBuilderForm::class);
    $intro = keyOf($form->get('data.content'), 'Intro');
    $call = pasteCall($form->html(), 'pasteSection', '');

    // As ⌘V on the selected intro mounts the action below the builder.
    $form->call('mountAction', $call['name'], [...$call['arguments'], 'item' => $intro, 'pasted' => ['type' => 'section', 'data' => ['title' => 'After the intro']]], $call['context']);

    $again = pasteCall($form->html(), 'pasteSection', '');

    expect($again['arguments'])->toBe([]);

    $form->call('mountAction', $again['name'], [...$again['arguments'], 'pasted' => ['type' => 'section', 'data' => ['title' => 'Last']]], $again['context']);

    expect(titlesOf($form->get('data.content')))->toBe(['Intro', 'After the intro', 'Services', 'Empty section', 'Sidebar', 'Last']);
});

it('pastes a repeater row that has a field named type', function () {
    SchemaForm::$state = ['rows' => []];
    SchemaForm::$components = fn (): array => [
        Repeater::make('rows')
            ->schema([
                Select::make('type')->multiple()->options(['a' => 'A', 'b' => 'B']),
                TextInput::make('name'),
            ])
            ->belowContent([PasteItemAction::make('pasteRow')->link()]),
    ];

    $form = Livewire::test(SchemaForm::class);
    $call = pasteCall($form->html(), 'pasteRow', 'rows');

    $form->call('mountAction', $call['name'], [...$call['arguments'], 'pasted' => ['type' => ['a', 'b'], 'name' => 'Copy']], $call['context']);

    expect(array_values($form->get('data.rows')))->toEqual([['type' => ['a', 'b'], 'name' => 'Copy']]);
});
