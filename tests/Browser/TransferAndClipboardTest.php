<?php

/**
 * The tools in Chromium, where SortableJS, Filament's reordering and the clipboard live:
 * items dragged into another list of their group — an empty one included, a relationship's
 * record saved there as the same row —, a drop the target refuses, and an item copied in
 * one list and pasted into another.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Pest\Browser\Playwright\Playwright;
use Workbench\App\Models\Document;
use Workbench\App\Models\Position;
use Workbench\Database\Factories\UserFactory;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Playwright::setTimeout(20000);

    // The script and stylesheet as they are now, where the testbench app serves them.
    Artisan::call('filament:assets');

    $this->actingAs(UserFactory::new()->create());
});

it('moves a position into another section by dragging it there', function (): void {
    $page = visit('/document', ['viewport' => ['width' => 1400, 'height' => 1400]])
        ->assertSee('Add position');

    [$hardware, $options] = listsOf($page, 'positions');

    dragInSteps($page, "[data-state-path=\"{$hardware}\"] > ul > li [x-sortable-handle]", "[data-state-path=\"{$options}\"] > ul > li .fi-fo-repeater-item-header");

    // Dropped onto the SIM card: before it, where the browser showed it.
    expect(waitForTitles($page, $options, ['Camera rental', 'SIM card']))->toBe(['Camera rental', 'SIM card'])
        ->and(titlesShown($page, $hardware))->toBe(['Mounting']);

    $page->assertNoJavaScriptErrors();
});

it('moves a position into an empty section, where a zone stands in for its list', function (): void {
    $page = visit('/document', ['viewport' => ['width' => 1400, 'height' => 1400]])
        ->assertSee('Add position');

    [$hardware, , $empty] = listsOf($page, 'positions');

    dragInSteps($page, "[data-state-path=\"{$hardware}\"] > ul > li [x-sortable-handle]", "[data-state-path=\"{$empty}\"] > .fi-be-drop-zone");

    expect(waitForTitles($page, $empty, ['Camera rental']))->toBe(['Camera rental'])
        ->and(titlesShown($page, $hardware))->toBe(['Mounting'])
        ->and($page->script("document.querySelectorAll('.fi-be-drop-zone').length"))->toBe(0);

    $page->assertNoJavaScriptErrors();
});

it('saves a position dragged into another section as the same row there', function (): void {
    $document = Document::sample();
    $camera = Position::query()->where('title', 'Camera rental')->sole();

    $page = visit('/document-records', ['viewport' => ['width' => 1400, 'height' => 1400]])
        ->assertSee('Add position');

    [$hardware, $options] = listsOf($page, 'positions');

    dragInSteps($page, "[data-state-path=\"{$hardware}\"] > ul > li [x-sortable-handle]", "[data-state-path=\"{$options}\"] > ul > li .fi-fo-repeater-item-header");

    expect(waitForTitles($page, $options, ['Camera rental', 'SIM card']))->toBe(['Camera rental', 'SIM card']);

    $page->click('Save')
        ->assertSee('Saved');

    expect($camera->fresh()->only(['section_id', 'sort']))->toBe(['section_id' => $document->sections()->where('title', 'Options')->value('id'), 'sort' => 1])
        ->and(Position::query()->count())->toBe(3);

    $page->assertNoJavaScriptErrors();
});

it('moves a block into a nested builder of another section, its list free to reorder after', function (): void {
    $page = visit('/page-builder', ['viewport' => ['width' => 1400, 'height' => 1400]])
        ->assertSee('Add block');

    [$intro, $services] = listsOf($page, 'blocks');

    dragInSteps($page, "[data-state-path=\"{$intro}\"] > ul > li [x-sortable-handle]", "[data-state-path=\"{$services}\"] > ul > li .fi-fo-builder-item-header");

    expect(waitForTitles($page, $services, ['Welcome', 'What we do']))->toBe(['Welcome', 'What we do'])
        ->and(titlesShown($page, $intro))->toBe(['Hero image'])
        // Filament 5.10+ tracks a drag on the list it began in; it ended with the transfer.
        ->and($page->script("Alpine.\$data(document.querySelector('[data-state-path=\"{$intro}\"]')).pending ?? null"))->toBeNull();

    $page->assertNoJavaScriptErrors();
});

it('puts a block back that the target does not offer, and says so', function (): void {
    $page = visit('/page-builder', ['viewport' => ['width' => 1400, 'height' => 1400]])
        ->assertSee('Add block');

    [$intro, , , $sidebar] = listsOf($page, 'blocks');

    dragInSteps($page, "[data-state-path=\"{$sidebar}\"] > ul > li [x-sortable-handle]", "[data-state-path=\"{$intro}\"] > ul > li .fi-fo-builder-item-header");

    $page->assertSee('This list cannot take the item');

    expect(waitForTitles($page, $sidebar, ['Praise']))->toBe(['Praise'])
        ->and(titlesShown($page, $intro))->toBe(['Welcome', 'Hero image']);

    $page->assertNoJavaScriptErrors();
});

it('copies a Builder item as Filament holds it and pastes one copied without a kind', function (): void {
    $page = visit('/page-builder', ['viewport' => ['width' => 1400, 'height' => 1400]])
        ->assertSee('Add block');

    [$intro, $services] = listsOf($page, 'blocks');

    // `{kind, type, data}`: tools that read bare Builder items read it too.
    $page->click("[data-state-path=\"{$intro}\"] > ul > li:first-child [aria-label=\"Copy block\"]")
        ->assertSee('Copied');

    $copied = keptCopy($page, 'workbench.block');

    expect($copied)->toMatchArray(['kind' => 'workbench.block', 'type' => 'text'])
        ->and($copied['data']['title'])->toBe('Welcome');

    // A bare `{type, data}`, as a copy made without the package holds it.
    keepCopy($page, 'workbench.block', ['type' => 'text', 'data' => ['title' => 'Bare copy', 'body' => 'From elsewhere']]);
    $page->script("[...document.querySelector('[data-state-path=\"{$services}\"]').parentElement.querySelectorAll('button')].find(button => button.textContent.trim() === 'Paste block').click()");

    expect(waitForTitles($page, $services, ['What we do', 'Bare copy']))->toBe(['What we do', 'Bare copy']);

    $page->assertNoJavaScriptErrors();
});

it('lets a copy older than a day go instead of pasting it', function (): void {
    $page = visit('/page-builder', ['viewport' => ['width' => 1400, 'height' => 1400]])
        ->assertSee('Add block');

    [, $services] = listsOf($page, 'blocks');

    // Headless Chromium grants no clipboard: localStorage alone could hold a copy.
    keepCopy($page, 'workbench.block', ['kind' => 'workbench.block', 'type' => 'text', 'data' => ['title' => 'Yesterday\'s copy']], hours: 25);
    $page->script("[...document.querySelector('[data-state-path=\"{$services}\"]').parentElement.querySelectorAll('button')].find(button => button.textContent.trim() === 'Paste block').click()");

    $page->assertSee('Nothing to paste here');

    expect(titlesShown($page, $services))->toBe(['What we do'])
        ->and($page->script("localStorage.getItem('filament-builder-extensions.workbench.block')"))->toBeNull();

    $page->assertNoJavaScriptErrors();
});

it('copies a position and pastes it into another section', function (): void {
    $page = visit('/document', ['viewport' => ['width' => 1400, 'height' => 1400]])
        ->assertSee('Add position');

    [$hardware, $options] = listsOf($page, 'positions');

    // Headless Chromium grants no clipboard: the copy lands in localStorage.
    $page->click("[data-state-path=\"{$hardware}\"] > ul > li:first-child [aria-label=\"Copy position\"]")
        ->assertSee('Copied');

    // "Paste" in the row below the second section's positions.
    $page->script("[...document.querySelector('[data-state-path=\"{$options}\"]').closest('li').querySelectorAll('button')].find(button => button.textContent.trim() === 'Paste').click()");

    expect(waitForTitles($page, $options, ['SIM card', 'Camera rental']))->toBe(['SIM card', 'Camera rental'])
        ->and(titlesShown($page, $hardware))->toBe(['Camera rental', 'Mounting']);

    $page->assertNoJavaScriptErrors();
});
