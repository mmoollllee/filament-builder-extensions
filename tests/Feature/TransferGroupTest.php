<?php

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\App\Livewire\DocumentForm;
use Workbench\App\Livewire\PageBuilderForm;
use Workbench\App\Livewire\SchemaForm;

/**
 * `->transferGroup()`: an item dropped into another list of its group mounts the target's
 * `transferItem` action, as the script does after a drag (builder-extensions.js), with the
 * keys both lists show. The server checks both lists against the form before it moves.
 */
function documentSections(Testable $form): array
{
    return $form->get('data.sections');
}

it('marks the wrappers of the lists in a group, and of no other list', function () {
    $html = Livewire::test(DocumentForm::class)->html();

    expect(substr_count($html, 'data-transfer-group="positions"'))->toBe(3)
        ->and(substr_count($html, 'data-transfer-group='))->toBe(3)
        ->and($html)->toContain('data-transfer-drop-label="Drop here"')
        ->toMatch('#x-load-js="\[&quot;[^&]*/js/mmoollllee/filament-builder-extensions/filament-builder-extensions\.js#');
});

it('moves a position into another section, both lists in the order the browser shows', function () {
    $form = Livewire::test(DocumentForm::class);
    $html = $form->html();
    $sections = documentSections($form);
    [$hardware, $options] = [keyOf($sections, 'Hardware'), keyOf($sections, 'Options')];
    $camera = keyOf($sections[$hardware]['positions'], 'Camera rental');
    $mount = keyOf($sections[$hardware]['positions'], 'Mounting');
    $sim = keyOf($sections[$options]['positions'], 'SIM card');

    $form->call('mountAction', 'transferItem', [
        'from' => transferKeyOf($html, "data.sections.{$hardware}.positions"),
        'moved' => $camera,
        'fromItems' => [$mount],
        'toItems' => [$sim, $camera],
    ], ['schemaComponent' => transferKeyOf($html, "data.sections.{$options}.positions")]);

    $sections = documentSections($form);

    expect(titlesOf($sections[$hardware]['positions']))->toBe(['Mounting'])
        ->and(titlesOf($sections[$options]['positions']))->toBe(['SIM card', 'Camera rental'])
        ->and($sections[$options]['positions'][$camera])->toEqual(['title' => 'Camera rental', 'quantity' => 2, 'price' => 49]);
});

it('moves a position into an empty section', function () {
    $form = Livewire::test(DocumentForm::class);
    $html = $form->html();
    $sections = documentSections($form);
    [$options, $empty] = [keyOf($sections, 'Options'), keyOf($sections, 'Empty section')];
    $sim = keyOf($sections[$options]['positions'], 'SIM card');

    $form->call('mountAction', 'transferItem', [
        'from' => transferKeyOf($html, "data.sections.{$options}.positions"),
        'moved' => $sim,
        'fromItems' => [],
        'toItems' => [$sim],
    ], ['schemaComponent' => transferKeyOf($html, "data.sections.{$empty}.positions")]);

    $sections = documentSections($form);

    expect(titlesOf($sections[$options]['positions']))->toBe([])
        ->and(titlesOf($sections[$empty]['positions']))->toBe(['SIM card']);
});

it('moves a block into a nested builder of another section', function () {
    $form = Livewire::test(PageBuilderForm::class);
    $html = $form->html();
    $content = $form->get('data.content');
    [$intro, $services] = [keyOf($content, 'Intro'), keyOf($content, 'Services')];
    $welcome = keyOf($content[$intro]['data']['blocks'], 'Welcome');
    $hero = keyOf($content[$intro]['data']['blocks'], 'Hero image');
    $list = keyOf($content[$services]['data']['blocks'], 'What we do');

    $form->call('mountAction', 'transferItem', [
        'from' => transferKeyOf($html, "data.content.{$intro}.data.blocks"),
        'moved' => $welcome,
        'fromItems' => [$hero],
        'toItems' => [$welcome, $list],
    ], ['schemaComponent' => transferKeyOf($html, "data.content.{$services}.data.blocks")]);

    $content = $form->get('data.content');

    expect(titlesOf($content[$intro]['data']['blocks']))->toBe(['Hero image'])
        ->and(titlesOf($content[$services]['data']['blocks']))->toBe(['Welcome', 'What we do'])
        ->and($content[$services]['data']['blocks'][$welcome])->toEqual(['type' => 'text', 'data' => ['title' => 'Welcome', 'body' => 'Hello there.']]);
});

it('refuses a block the target builder does not offer, and says so', function () {
    $form = Livewire::test(PageBuilderForm::class);
    $html = $form->html();
    $content = $form->get('data.content');
    [$intro, $sidebar] = [keyOf($content, 'Intro'), keyOf($content, 'Sidebar')];
    $praise = keyOf($content[$sidebar]['data']['blocks'], 'Praise');

    $form->call('mountAction', 'transferItem', [
        'from' => transferKeyOf($html, "data.content.{$sidebar}.data.blocks"),
        'moved' => $praise,
        'fromItems' => [],
        'toItems' => [$praise, ...array_keys($content[$intro]['data']['blocks'])],
    ], ['schemaComponent' => transferKeyOf($html, "data.content.{$intro}.data.blocks")]);

    expect(outline($form->get('data.content')))->toBe(outline($content));

    Notification::assertNotified('This list cannot take the item');
});

it('refuses a full list, and says so', function () {
    $form = Livewire::test(DocumentForm::class);
    $html = $form->html();
    $sections = documentSections($form);
    [$hardware, $options] = [keyOf($sections, 'Hardware'), keyOf($sections, 'Options')];
    $sim = keyOf($sections[$options]['positions'], 'SIM card');

    // Three positions a section at most: Hardware's two and a third.
    $form->set("data.sections.{$hardware}.positions.extra", ['title' => 'Extra', 'quantity' => 1, 'price' => 1]);

    $form->call('mountAction', 'transferItem', [
        'from' => transferKeyOf($html, "data.sections.{$options}.positions"),
        'moved' => $sim,
        'fromItems' => [],
        'toItems' => [$sim, ...array_keys($sections[$hardware]['positions']), 'extra'],
    ], ['schemaComponent' => transferKeyOf($html, "data.sections.{$hardware}.positions")]);

    expect(titlesOf(documentSections($form)[$options]['positions']))->toBe(['SIM card']);

    Notification::assertNotified('This list cannot take the item');
});

it('refuses a block the target builder holds as often as the block allows', function () {
    $form = Livewire::test(PageBuilderForm::class);
    $html = $form->html();
    $content = $form->get('data.content');
    [$intro, $services] = [keyOf($content, 'Intro'), keyOf($content, 'Services')];
    $hero = keyOf($content[$intro]['data']['blocks'], 'Hero image');

    // A section holds one image at most: the services have one now.
    $form->set("data.content.{$services}.data.blocks.photo", ['type' => 'image', 'data' => ['title' => 'Photo', 'url' => null, 'alt' => 'A photo']]);

    $form->call('mountAction', 'transferItem', [
        'from' => transferKeyOf($html, "data.content.{$intro}.data.blocks"),
        'moved' => $hero,
        'fromItems' => [keyOf($content[$intro]['data']['blocks'], 'Welcome')],
        'toItems' => [...array_keys($content[$services]['data']['blocks']), 'photo', $hero],
    ], ['schemaComponent' => transferKeyOf($html, "data.content.{$services}.data.blocks")]);

    expect(titlesOf($form->get("data.content.{$intro}.data.blocks")))->toBe(['Welcome', 'Hero image']);

    Notification::assertNotified('This list cannot take the item');
});

it('gives an item a key of its own where the target holds its key already, as a copy of its list does', function () {
    $form = Livewire::test(DocumentForm::class);
    $html = $form->html();
    $sections = documentSections($form);
    [$hardware, $options] = [keyOf($sections, 'Hardware'), keyOf($sections, 'Options')];
    $camera = keyOf($sections[$hardware]['positions'], 'Camera rental');
    $sim = keyOf($sections[$options]['positions'], 'SIM card');

    // A copy of the camera's position, under its key.
    $form->set("data.sections.{$options}.positions.{$camera}", ['title' => 'Camera copy', 'quantity' => 1, 'price' => 1]);

    $form->call('mountAction', 'transferItem', [
        'from' => transferKeyOf($html, "data.sections.{$hardware}.positions"),
        'moved' => $camera,
        'fromItems' => [keyOf($sections[$hardware]['positions'], 'Mounting')],
        'toItems' => [$sim, $camera, $camera],
        'toIndex' => 2,
    ], ['schemaComponent' => transferKeyOf($html, "data.sections.{$options}.positions")]);

    $positions = documentSections($form)[$options]['positions'];

    expect(titlesOf($positions))->toBe(['SIM card', 'Camera copy', 'Camera rental'])
        ->and(array_key_last($positions))->not->toBe($camera)
        ->and(titlesOf(documentSections($form)[$hardware]['positions']))->toBe(['Mounting']);

    // Without the place it was dropped at, the item stays, and the user is told.
    $form->set("data.sections.{$hardware}.positions.{$sim}", ['title' => 'SIM copy', 'quantity' => 1, 'price' => 1]);

    $form->call('mountAction', 'transferItem', [
        'from' => transferKeyOf($html, "data.sections.{$options}.positions"),
        'moved' => $sim,
        'fromItems' => [],
        'toItems' => [$sim, $sim],
    ], ['schemaComponent' => transferKeyOf($html, "data.sections.{$hardware}.positions")]);

    expect(titlesOf(documentSections($form)[$options]['positions']))->toBe(['SIM card', 'Camera copy', 'Camera rental']);

    Notification::assertNotified('This list cannot take the item');
});

it('takes nothing from a list that may not lose items', function () {
    SchemaForm::$state = ['kept' => [['title' => 'Fixed']], 'open' => []];
    SchemaForm::$components = fn (): array => [
        Repeater::make('kept')->schema([TextInput::make('title')])->deletable(false)->transferGroup('rows'),
        Repeater::make('open')->schema([TextInput::make('title')])->transferGroup('rows'),
    ];

    $form = Livewire::test(SchemaForm::class);
    $html = $form->html();
    $fixed = keyOf($form->get('data.kept'), 'Fixed');

    // Its items are not dragged out in the first place…
    expect(wrapperAttribute($html, 'data.kept', 'data-transfer-pull'))->toBe('false')
        ->and(wrapperAttribute($html, 'data.open', 'data-transfer-pull'))->toBeNull();

    // …and a drop of one is not carried out: it would delete it there.
    $form->call('mountAction', 'transferItem', [
        'from' => transferKeyOf($html, 'data.kept'),
        'moved' => $fixed,
        'fromItems' => [],
        'toItems' => [$fixed],
    ], ['schemaComponent' => transferKeyOf($html, 'data.open')]);

    expect(titlesOf($form->get('data.kept')))->toBe(['Fixed'])
        ->and($form->get('data.open'))->toBe([]);
});

it('moves nothing for a list outside the group, the same list, or an item that is not there', function (Closure $arguments) {
    $form = Livewire::test(DocumentForm::class);
    $html = $form->html();
    $sections = documentSections($form);
    [$hardware, $options] = [keyOf($sections, 'Hardware'), keyOf($sections, 'Options')];

    $form->call('mountAction', 'transferItem', $arguments($html, $hardware, $options, $sections), [
        'schemaComponent' => transferKeyOf($html, "data.sections.{$options}.positions"),
    ]);

    expect(outline(documentSections($form)))->toBe(outline($sections));

    Notification::assertNotNotified();
})->with([
    'the sections, which are in no group' => [fn (string $html, string $hardware, string $options, array $sections): array => [
        'from' => 'form.sections',
        'moved' => $hardware,
        'toItems' => [$hardware, ...array_keys($sections[$options]['positions'])],
    ]],
    'the target itself' => [fn (string $html, string $hardware, string $options, array $sections): array => [
        'from' => transferKeyOf($html, "data.sections.{$options}.positions"),
        'moved' => keyOf($sections[$options]['positions'], 'SIM card'),
        'toItems' => array_keys($sections[$options]['positions']),
    ]],
    'an item of another list' => [fn (string $html, string $hardware, string $options, array $sections): array => [
        'from' => transferKeyOf($html, "data.sections.{$hardware}.positions"),
        'moved' => keyOf($sections[$options]['positions'], 'SIM card'),
        'toItems' => array_keys($sections[$options]['positions']),
    ]],
    'no item' => [fn (string $html, string $hardware, string $options, array $sections): array => [
        'from' => transferKeyOf($html, "data.sections.{$hardware}.positions"),
        'moved' => ['camera'],
        'toItems' => [],
    ]],
]);
