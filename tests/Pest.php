<?php

use Mmoollllee\FilamentBuilderExtensions\Tests\TestCase;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Client;
use Pest\Browser\Playwright\Page;

pest()->extend(TestCase::class)->in('Feature', 'Browser');

/**
 * The key of an item among `$items`, found by its title (a Builder item's in its data):
 * filling a form gives every item a UUID of its own.
 *
 * @param  array<string, array<string, mixed>>  $items
 */
function keyOf(array $items, string $title): string
{
    foreach ($items as $key => $item) {
        if ((($item['data']['title'] ?? $item['title'] ?? null) === $title)) {
            return (string) $key;
        }
    }

    throw new RuntimeException("No item [{$title}].");
}

/**
 * The titles of `$items` in their order.
 *
 * @param  array<string, array<string, mixed>>|null  $items
 * @return list<?string>
 */
function titlesOf(?array $items): array
{
    return array_values(array_map(fn (array $item): ?string => $item['data']['title'] ?? $item['title'] ?? null, $items ?? []));
}

/**
 * The keys and titles of `$items` and of the lists inside them, in their order: what a
 * transfer moves.
 *
 * @param  array<string, array<string, mixed>>|null  $items
 * @return array<string, mixed>
 */
function outline(?array $items): array
{
    $outline = [];

    foreach ($items ?? [] as $key => $item) {
        $fields = $item['data'] ?? $item;
        $lists = array_filter($fields, fn (mixed $value): bool => is_array($value) && (($value === []) || is_array(reset($value))));

        $outline[$key] = [
            'title' => $fields['title'] ?? null,
            ...array_map(outline(...), $lists),
        ];
    }

    return $outline;
}

/** The value of `$attribute` on the field wrapper of the list at `$statePath`, as the browser reads it. */
function wrapperAttribute(string $html, string $statePath, string $attribute): ?string
{
    $document = new DOMDocument;

    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
    libxml_clear_errors();

    $wrapper = (new DOMXPath($document))->query("//*[@data-state-path='{$statePath}']")->item(0);

    return ($wrapper instanceof DOMElement) && $wrapper->hasAttribute($attribute) ? $wrapper->getAttribute($attribute) : null;
}

/** The key the field wrapper of the list at `$statePath` hands the script. */
function transferKeyOf(string $html, string $statePath): string
{
    return wrapperAttribute($html, $statePath, 'data-transfer-key') ?? throw new RuntimeException("No list at [{$statePath}] in a transfer group.");
}

/**
 * What the click handlers of clipboard actions hand the script (`copy` or `paste`): the
 * configuration objects, decoded as the browser decodes them.
 *
 * @return list<array<string, mixed>>
 */
function clipboardCalls(string $html, string $method): array
{
    preg_match_all('/FilamentBuilderExtensions\?\.'.$method.'\(\$wire, JSON\.parse\(\'(.*?)\'\)\)/', $html, $matches);

    return array_map(fn (string $body): array => json_decode(json_decode('"'.$body.'"'), true), $matches[1]);
}

/**
 * Playwright's own mouse and keyboard, driven through the page the plugin wraps: the plugin
 * has neither, and its keys() reads a selector like `body` as text.
 *
 * @param  array<string, mixed>  $params
 */
function playwright(AwaitableWebpage $page, string $method, array $params = []): void
{
    /** @var Page $playwright */
    $playwright = (fn (): Page => $this->page)->call($page);
    $guid = (fn (): string => $this->guid)->call($playwright);

    foreach (Client::instance()->execute($guid, $method, $params) as $message) {
        // Done once Playwright answers.
    }
}

/**
 * Drags as a hand does, the pointer moving on in steps: SortableJS starts a native drag
 * only then, and neither the plugin's drag() nor Playwright's dragTo() gets there. The
 * target is measured once the drag is on, when a zone stands in for an empty list.
 */
function dragInSteps(AwaitableWebpage $page, string $from, string $to): void
{
    $centre = fn (string $selector): array => $page->script('(() => { const box = document.querySelector('.json_encode($selector).').getBoundingClientRect(); return [box.x + box.width / 2, box.y + box.height / 2] })()');

    [$fromX, $fromY] = $centre($from);

    playwright($page, 'mouseMove', ['x' => $fromX, 'y' => $fromY]);
    playwright($page, 'mouseDown');
    playwright($page, 'mouseMove', ['x' => $fromX + 5, 'y' => $fromY + 5, 'steps' => 5]);

    [$toX, $toY] = $centre($to);

    playwright($page, 'mouseMove', ['x' => $toX, 'y' => $toY, 'steps' => 20]);

    // SortableJS ignores a drag over items it is still animating: rest, then move on the target again.
    $page->wait(0.4);
    playwright($page, 'mouseMove', ['x' => $toX, 'y' => $toY + 2, 'steps' => 2]);
    $page->wait(0.4);
    playwright($page, 'mouseMove', ['x' => $toX, 'y' => $toY + 4, 'steps' => 2]);
    playwright($page, 'mouseUp');
}

/** A click on the item `$item` (a selector) as a hand makes it: in its content, beside its fields. */
function clickBesideFields(AwaitableWebpage $page, string $item): void
{
    [$x, $y] = $page->script('(() => { const content = document.querySelector('.json_encode($item).').querySelector(":scope > .fi-fo-builder-item-content, :scope > .fi-fo-repeater-item-content"); const box = content.getBoundingClientRect(); return [box.x + 6, box.y + 6] })()');

    playwright($page, 'mouseClick', ['x' => $x, 'y' => $y]);
}

/**
 * The state paths of the lists of a transfer group, in the page's order.
 *
 * @return list<string>
 */
function listsOf(AwaitableWebpage $page, string $group): array
{
    return $page->script("[...document.querySelectorAll('[data-transfer-group=\"{$group}\"]')].map(list => list.dataset.statePath)");
}

/**
 * The titles the list at `$statePath` shows, in its order.
 *
 * @return list<string>
 */
function titlesShown(AwaitableWebpage $page, string $statePath): array
{
    return $page->script("[...document.querySelectorAll('[data-state-path=\"{$statePath}\"] > ul > [x-sortable-item]')].map(item => item.querySelector('.fi-builder-title-input')?.value)");
}

/** Waits up to five seconds for the list at `$statePath` to show these titles. */
function waitForTitles(AwaitableWebpage $page, string $statePath, array $titles): array
{
    foreach (range(1, 50) as $attempt) {
        if (($shown = titlesShown($page, $statePath)) === $titles) {
            return $shown;
        }

        $page->wait(0.1);
    }

    return titlesShown($page, $statePath);
}

/**
 * The copy of `$kind` localStorage keeps for a paste, null for none.
 *
 * @return ?array<string, mixed>
 */
function keptCopy(AwaitableWebpage $page, string $kind): ?array
{
    return $page->script("JSON.parse(JSON.parse(localStorage.getItem('filament-builder-extensions.{$kind}'))?.payload ?? 'null')");
}

/**
 * Puts a copy of `$kind` into localStorage, as a copy made `$hours` ago leaves it there.
 *
 * @param  array<string, mixed>  $payload
 */
function keepCopy(AwaitableWebpage $page, string $kind, array $payload, int $hours = 0): void
{
    $kept = json_encode(json_encode(['payload' => json_encode($payload)]));

    $page->script("localStorage.setItem('filament-builder-extensions.{$kind}', JSON.stringify({ ...JSON.parse({$kept}), copied: Date.now() - {$hours} * 3600 * 1000 }))");
}
