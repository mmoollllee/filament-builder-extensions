<?php

/**
 * `->title()` in Chromium for a nested block with a preview: the input is bound to its field,
 * which the server knows through the block Filament renders, and sends a new title at once,
 * so the preview shows it.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Pest\Browser\Playwright\Playwright;
use Workbench\Database\Factories\UserFactory;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Playwright::setTimeout(20000);

    Artisan::call('filament:assets');

    $this->actingAs(UserFactory::new()->create());
});

it('shows and writes the title of a nested block with a preview, which shows it at once', function (): void {
    $page = visit('/nested-builder-titles', ['viewport' => ['width' => 1400, 'height' => 1400]])
        ->assertSee('Nested builder titles with previews');

    $input = '[placeholder="Text title…"]';
    $preview = "document.querySelector('{$input}').closest('[x-sortable-item]').querySelector('.fbt-block-preview')?.textContent.trim()";

    foreach (range(1, 50) as $attempt) {
        if ($page->script("document.querySelector('{$input}')?.value") === 'Nested text') {
            break;
        }

        $page->wait(0.1);
    }

    expect($page->script("document.querySelector('{$input}').value"))->toBe('Nested text');

    $page->script("(() => { const input = document.querySelector('{$input}'); input.value = 'Renamed'; input.dispatchEvent(new Event('change')) })()");

    // The server renders the preview anew, with the new title.
    foreach (range(1, 50) as $attempt) {
        if ($page->script($preview) === 'Renamed') {
            break;
        }

        $page->wait(0.1);
    }

    expect($page->script($preview))->toBe('Renamed')
        ->and($page->script("document.querySelector('{$input}').value"))->toBe('Renamed');

    $page->assertNoJavaScriptErrors();
});
