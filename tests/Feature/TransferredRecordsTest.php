<?php

/**
 * `->transferGroup()` on relationship repeaters: a position dragged into another section is
 * saved there as the same row — its id, and the columns its item has no field for, kept —,
 * whichever of the two lists saves first. Keys no list left stay new rows.
 */

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\App\Livewire\DocumentRecordForm;
use Workbench\App\Models\Document;
use Workbench\App\Models\Note;
use Workbench\App\Models\Position;

uses(RefreshDatabase::class);

afterEach(function (): void {
    DocumentRecordForm::$components = null;
});

/**
 * The keys of the document's sections in the form, by title.
 *
 * @return array<string, string>
 */
function sectionKeys(Document $document): array
{
    return $document->sections()->pluck('id', 'title')->map(fn (int $id): string => "record-{$id}")->all();
}

function sectionId(Document $document, string $title): int
{
    return $document->sections()->where('title', $title)->value('id');
}

function positionOf(Document $document, string $title): Position
{
    return Position::query()->whereIn('section_id', $document->sections()->select('id'))->where('title', $title)->sole();
}

/** Drags `$position` from the positions of the section `$from` to the end of those of `$to`, as the script does. */
function dragPosition(Testable $form, Position $position, string $from, string $to): void
{
    $html = $form->html();
    $moved = "record-{$position->getKey()}";
    $keys = fn (string $section): array => array_keys($form->get("data.sections.{$section}.positions") ?? []);

    $form->call('mountAction', 'transferItem', [
        'from' => transferKeyOf($html, "data.sections.{$from}.positions"),
        'moved' => $moved,
        'fromItems' => array_values(array_diff($keys($from), [$moved])),
        'toItems' => [...$keys($to), $moved],
    ], ['schemaComponent' => transferKeyOf($html, "data.sections.{$to}.positions")]);
}

it('saves a position dragged into another section as the same row there, whichever saves first', function (string $title, string $from, string $to, int $sort) {
    $document = Document::sample();
    $sections = sectionKeys($document);
    $position = positionOf($document, $title);
    $position->forceFill(['origin' => 'contract 7'])->save();

    $form = Livewire::test(DocumentRecordForm::class, ['record' => $document]);

    dragPosition($form, $position, $sections[$from], $sections[$to]);

    // Edited in its new place before saving.
    $form->set("data.sections.{$sections[$to]}.positions.record-{$position->getKey()}.quantity", 5)
        ->call('save')
        ->assertHasNoErrors();

    $saved = Position::query()->find($position->getKey());

    expect($saved?->section_id)->toBe(sectionId($document, $to))
        ->and($saved->origin)->toBe('contract 7')
        ->and($saved->quantity)->toEqual(5)
        ->and($saved->sort)->toBe($sort)
        ->and(Position::query()->count())->toBe(3)
        // Loaded anew, the form shows it there.
        ->and(titlesOf($form->get("data.sections.{$sections[$from]}.positions")))->not->toContain($title)
        ->and(titlesOf($form->get("data.sections.{$sections[$to]}.positions")))->toContain($title);
})->with([
    'the list it left saves first' => ['Camera rental', 'Hardware', 'Options', 2],
    'the list it joined saves first' => ['SIM card', 'Options', 'Hardware', 3],
]);

it('saves a position dragged into a section added before saving as the same row there', function () {
    $document = Document::sample();
    $camera = positionOf($document, 'Camera rental');

    $form = Livewire::test(DocumentRecordForm::class, ['record' => $document])
        ->set('data.sections.added', ['title' => 'Added', 'positions' => []]);

    dragPosition($form, $camera, sectionKeys($document)['Hardware'], 'added');

    $form->call('save')->assertHasNoErrors();

    expect($camera->fresh()->section_id)->toBe(sectionId($document, 'Added'))
        ->and(Position::query()->count())->toBe(3);
});

it('saves what a dragged position holds of its own with its row', function () {
    DocumentRecordForm::$components = fn (): array => [
        Repeater::make('sections')
            ->relationship()
            ->orderColumn('sort')
            ->schema([
                Hidden::make('title'),
                Repeater::make('positions')
                    ->relationship()
                    ->orderColumn('sort')
                    ->transferGroup('positions')
                    ->schema([
                        Hidden::make('title'),
                        Repeater::make('notes')
                            ->relationship()
                            ->simple(TextInput::make('body')),
                    ]),
            ]),
    ];

    $document = Document::sample();
    $sections = sectionKeys($document);
    $camera = positionOf($document, 'Camera rental');
    $note = $camera->notes()->create(['body' => 'Check the battery']);

    $form = Livewire::test(DocumentRecordForm::class, ['record' => $document]);

    dragPosition($form, $camera, $sections['Hardware'], $sections['Options']);

    $form->set("data.sections.{$sections['Options']}.positions.record-{$camera->getKey()}.notes.record-{$note->getKey()}.body", 'Battery checked')
        ->call('save')
        ->assertHasNoErrors();

    expect($camera->fresh()->section_id)->toBe(sectionId($document, 'Options'))
        ->and(Note::query()->get(['id', 'position_id', 'body'])->toArray())->toBe([
            ['id' => $note->getKey(), 'position_id' => $camera->getKey(), 'body' => 'Battery checked'],
        ]);
});

it('saves the positions of a cloned section as new rows, the originals where they were', function () {
    $document = Document::sample();
    $sections = sectionKeys($document);
    $originals = Position::query()->get(['id', 'section_id', 'title'])->toArray();

    // A clone keeps the keys of the items inside it, as Filament's own clone action does.
    $form = Livewire::test(DocumentRecordForm::class, ['record' => $document]);

    $form->set('data.sections.clone', [...$form->get("data.sections.{$sections['Hardware']}"), 'title' => 'Clone'])
        ->call('save')
        ->assertHasNoErrors();

    expect(Position::query()->whereKey(array_column($originals, 'id'))->get(['id', 'section_id', 'title'])->toArray())->toBe($originals)
        ->and(Position::query()->where('section_id', sectionId($document, 'Clone'))->orderBy('sort')->pluck('title')->all())->toBe(['Camera rental', 'Mounting']);
});

it('gives a position the first section to save that holds its key, a clone of its own section a new row', function () {
    $document = Document::sample();
    $sections = sectionKeys($document);
    $camera = positionOf($document, 'Camera rental');

    $form = Livewire::test(DocumentRecordForm::class, ['record' => $document]);
    $form->set('data.sections.clone', [...$form->get("data.sections.{$sections['Hardware']}"), 'title' => 'Clone']);

    // The original out of Hardware into Options; the clone of Hardware holds its key too.
    dragPosition($form, $camera, $sections['Hardware'], $sections['Options']);

    $form->call('save')->assertHasNoErrors();

    expect($camera->fresh()->section_id)->toBe(sectionId($document, 'Options'))
        ->and(Position::query()->where('section_id', sectionId($document, 'Clone'))->orderBy('sort')->pluck('title')->all())->toBe(['Camera rental', 'Mounting'])
        ->and(Position::query()->count())->toBe(5);
});

it('takes over no record from outside the form', function () {
    $foreign = positionOf(Document::sample(), 'Camera rental');
    $document = Document::sample();

    Livewire::test(DocumentRecordForm::class, ['record' => $document])
        ->set('data.sections.'.sectionKeys($document)['Options'].".positions.record-{$foreign->getKey()}", ['title' => 'Forged', 'quantity' => 1, 'price' => 1])
        ->call('save')
        ->assertHasNoErrors();

    expect($foreign->fresh()->only(['section_id', 'title']))->toBe($foreign->only(['section_id', 'title']))
        ->and(positionOf($document, 'Forged')->getKey())->not->toBe($foreign->getKey());
});
