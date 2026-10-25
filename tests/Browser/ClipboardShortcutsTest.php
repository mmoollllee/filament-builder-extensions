<?php

/**
 * ⌘C / ⌘V (Ctrl elsewhere) on a selected item in Chromium: the browser copies and pastes
 * itself, out of and into a textarea focused for that instant. In a field, or with text
 * selected, the keys stay the browser's.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Playwright;
use Workbench\Database\Factories\UserFactory;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Playwright::setTimeout(20000);

    Artisan::call('filament:assets');

    $this->actingAs(UserFactory::new()->create());
});

/** Marks the `$index`-th item of the list at `$statePath` (the item keeps its own attributes across renders) and returns its selector. */
function markItem(AwaitableWebpage $page, string $statePath, int $index, string $as): string
{
    $page->script("document.querySelectorAll('[data-state-path=\"{$statePath}\"] > ul > [x-sortable-item]')[{$index}].dataset.test = '{$as}'");

    return "[data-test=\"{$as}\"]";
}

/** Whether the item has the selection's frame. */
function isSelected(AwaitableWebpage $page, string $item): bool
{
    return $page->script("getComputedStyle(document.querySelector('{$item}')).outlineStyle !== 'none'");
}

/**
 * The titles of the items that have the selection's frame.
 *
 * @return list<?string>
 */
function framedTitles(AwaitableWebpage $page): array
{
    return $page->script("[...document.querySelectorAll('[x-sortable-item]')].filter(item => getComputedStyle(item).outlineStyle !== 'none').map(item => item.querySelector('.fi-builder-title-input')?.value)");
}

it('copies the selected block with ⌘C and pastes it after another with ⌘V', function (): void {
    $page = visit('/page-builder', ['viewport' => ['width' => 1400, 'height' => 1400], 'permissions' => ['clipboard-read', 'clipboard-write']])
        ->assertSee('Add block');

    [$intro, $services] = listsOf($page, 'blocks');
    $welcome = markItem($page, $intro, 0, 'welcome');

    clickBesideFields($page, $welcome);

    expect(isSelected($page, $welcome))->toBeTrue();

    playwright($page, 'keyboardPress', ['key' => 'ControlOrMeta+c']);
    $page->assertSee('Copied');

    // The browser's own copy: the block as Filament holds it, its kind beside it.
    expect($page->script('navigator.clipboard.readText().then(text => JSON.parse(text))'))
        ->toMatchArray(['kind' => 'workbench.block', 'type' => 'text'])
        ->data->title->toBe('Welcome');

    clickBesideFields($page, markItem($page, $services, 0, 'what-we-do'));
    playwright($page, 'keyboardPress', ['key' => 'ControlOrMeta+v']);

    expect(waitForTitles($page, $services, ['What we do', 'Welcome']))->toBe(['What we do', 'Welcome']);

    // The pasted block is the selected one now.
    $page->wait(0.3);

    expect(framedTitles($page))->toBe(['Welcome']);

    // A render of the page's own component keeps the selection: the block keeps its frame.
    $page->script("Livewire.find(document.querySelector('[data-clipboard-shortcuts]').closest('[wire\\\\:id]').getAttribute('wire:id')).\$refresh()");
    $page->wait(1);

    expect(framedTitles($page))->toBe(['Welcome']);

    // ⌘V again pastes after it.
    playwright($page, 'keyboardPress', ['key' => 'ControlOrMeta+v']);

    expect(waitForTitles($page, $services, ['What we do', 'Welcome', 'Welcome']))->toBe(['What we do', 'Welcome', 'Welcome']);

    $page->assertNoJavaScriptErrors();
});

it('takes the key in the place of C on a layout without Latin letters', function (): void {
    $page = visit('/page-builder', ['viewport' => ['width' => 1400, 'height' => 1400]])
        ->assertSee('Add block');

    [$intro] = listsOf($page, 'blocks');
    $welcome = markItem($page, $intro, 0, 'welcome');

    clickBesideFields($page, $welcome);

    // A Russian layout: the key in the place of C types "с".
    $page->script("document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 'с', code: 'KeyC', ctrlKey: true, metaKey: true, bubbles: true }))");

    $page->assertSee('Copied');

    expect(keptCopy($page, 'workbench.block'))->toMatchArray(['kind' => 'workbench.block', 'type' => 'text']);

    $page->assertNoJavaScriptErrors();
});

it('copies and pastes with the keys in a modal, inside its focus trap', function (): void {
    $page = visit('/repeater-titles', ['viewport' => ['width' => 1400, 'height' => 1400], 'permissions' => ['clipboard-read', 'clipboard-write']])
        ->assertSee('Repeater titles');

    $page->click('Edit in a modal');

    $rows = 'mountedActions.0.data.specs';

    foreach (range(1, 50) as $attempt) {
        if ($page->script("!! document.querySelector('[data-state-path=\"{$rows}\"] [x-sortable-item]')")) {
            break;
        }

        $page->wait(0.1);
    }

    $height = markItem($page, $rows, 0, 'height');

    clickBesideFields($page, $height);
    playwright($page, 'keyboardPress', ['key' => 'ControlOrMeta+c']);

    // The browser's own copy, though the modal keeps the focus to itself.
    expect($page->script('navigator.clipboard.readText().then(text => JSON.parse(text))'))
        ->toMatchArray(['kind' => 'workbench.spec'])
        ->item->beschriftung->toBe('Working height');

    playwright($page, 'keyboardPress', ['key' => 'ControlOrMeta+v']);

    expect(waitForTitles($page, $rows, ['Working height', 'Working height', 'Weight']))->toBe(['Working height', 'Working height', 'Weight']);

    $page->assertNoJavaScriptErrors();
});

it('leaves ⌘C and ⌘V to a field and to selected text', function (): void {
    $page = visit('/page-builder', ['viewport' => ['width' => 1400, 'height' => 1400], 'permissions' => ['clipboard-read', 'clipboard-write']])
        ->assertSee('Add block');

    [$intro] = listsOf($page, 'blocks');
    $welcome = markItem($page, $intro, 0, 'welcome');

    // A click into a field lets the block go: the keys copy and paste its text.
    clickBesideFields($page, $welcome);
    $page->click("{$welcome} textarea");

    expect(isSelected($page, $welcome))->toBeFalse();

    playwright($page, 'keyboardPress', ['key' => 'ControlOrMeta+a']);
    playwright($page, 'keyboardPress', ['key' => 'ControlOrMeta+c']);

    expect($page->script('navigator.clipboard.readText()'))->toBe('Hello there.');

    // Text selected on the page while a block is selected: the text is what ⌘C copies.
    clickBesideFields($page, $welcome);
    $page->script("getSelection().selectAllChildren(document.querySelector('.fi-header-heading'))");
    playwright($page, 'keyboardPress', ['key' => 'ControlOrMeta+c']);

    expect(isSelected($page, $welcome))->toBeTrue()
        ->and(trim($page->script('navigator.clipboard.readText()')))->toBe('Builder: blocks between sections')
        ->and($page->script("localStorage.getItem('filament-builder-extensions.workbench.block')"))->toBeNull();

    $page->assertDontSee('Copied');

    // Escape lets the block go.
    $page->script('getSelection().removeAllRanges()');
    playwright($page, 'keyboardPress', ['key' => 'Escape']);

    expect(isSelected($page, $welcome))->toBeFalse();

    $page->assertNoJavaScriptErrors();
});
