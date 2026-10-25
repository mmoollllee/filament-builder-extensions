<?php

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use Livewire\Livewire;
use Workbench\App\Livewire\NestedBuilderForm;

/**
 * `->title()` on a Builder block or a Repeater row: an editable input in the item's header
 * in place of its label, carried over from mmoollllee/filament-builder-title.
 */
function repeaterItemLabel(Repeater $repeater): Closure
{
    return (new ReflectionProperty(Repeater::class, 'itemLabel'))->getValue($repeater);
}

/** A row container whose state path is `data.specs.abc-123`, with an empty error bag. */
function repeaterRow(string $statePath = 'data.specs.abc-123', array $state = []): object
{
    return new class($statePath, $state)
    {
        public function __construct(private string $statePath, private array $state) {}

        public function getStatePath(): string
        {
            return $this->statePath;
        }

        public function getRawState(): array
        {
            return $this->state;
        }

        public function getLivewire(): object
        {
            return new class
            {
                public function getErrorBag(): MessageBag
                {
                    return new MessageBag;
                }
            };
        }
    };
}

it('registers the title macro on blocks and repeaters, each returning its component', function () {
    expect(Block::hasMacro('title'))->toBeTrue()
        ->and(Repeater::hasMacro('title'))->toBeTrue()
        ->and(Block::make('test')->schema([TextInput::make('heading')])->title('heading'))->toBeInstanceOf(Block::class)
        ->and(Repeater::make('specs')->schema([Hidden::make('label')])->title('label'))->toBeInstanceOf(Repeater::class);
});

it('labels a block in the picker with its humanised name', function () {
    $block = Block::make('hero_section')
        ->schema([TextInput::make('heading')])
        ->title('heading');

    expect($block->getLabel(null, null))->toBe('Hero section');
});

it('keeps a label given before the macro, for the picker and as the placeholder', function () {
    // ->title() replaces the block's label with its own closure, so a label set beforehand
    // has to survive that — or a block called 'Sektion' everywhere else advertises itself
    // as "Section".
    $block = Block::make('section')
        ->label('Sektion')
        ->schema([TextInput::make('heading')])
        ->title('heading');

    expect($block->getLabel(null, null))->toBe('Sektion')
        ->and((string) $block->getLabel(['heading' => ''], 'item-uuid'))->toContain('placeholder="Sektion"');
});

it('keeps a label given as a closure or as HTML', function () {
    $closure = Block::make('section')->label(fn (): string => 'Sektion')->schema([TextInput::make('heading')])->title('heading');
    $html = Block::make('section')->label(new HtmlString('<b>Sektion</b>'))->schema([TextInput::make('heading')])->title('heading');

    expect($closure->getLabel(null, null))->toBe('Sektion')
        ->and((string) $closure->getLabel(['heading' => ''], 'item-uuid'))->toContain('placeholder="Sektion"')
        ->and((string) $html->getLabel(null, null))->toBe('<b>Sektion</b>')
        ->and((string) $html->getLabel(['heading' => ''], 'item-uuid'))->toContain('placeholder="Sektion"');
});

it('renders an input bound to nothing for a block outside a builder', function () {
    $block = Block::make('text')
        ->schema([TextInput::make('title')])
        ->title('title', placeholder: 'Enter title', suffix: 'Text');

    $label = $block->getLabel(['title' => 'Hello'], 'item-uuid');

    expect($label)->toBeInstanceOf(HtmlString::class)
        ->and((string) $label)
        ->toContain('fi-builder-title-input')
        ->toContain('placeholder="Enter title"')
        ->not->toContain('$entangle(')
        // The title as text, for screen readers' reorder announcements: Filament strips the label to its text.
        ->and(trim(preg_replace('/\s+/', ' ', strip_tags((string) $label))))->toBe('Hello Text');
});

it('binds a repeater row title to the flat row path', function () {
    $repeater = Repeater::make('specs')
        ->schema([Hidden::make('beschriftung')])
        ->title('beschriftung', placeholder: 'Bezeichnung');

    $html = (string) repeaterItemLabel($repeater)(repeaterRow(state: ['beschriftung' => 'Höhe']));

    // "{repeater}.{uuid}.{field}", not the Builder's "{…}.{uuid}.data.{field}".
    expect($html)
        ->toContain('fi-builder-title-wrapper')
        ->toContain('placeholder="Bezeichnung"')
        ->toContain("\$entangle('data.specs.abc-123.beschriftung', false)")
        ->not->toContain('.data.beschriftung')
        ->and(trim(strip_tags($html)))->toBe('Höhe');
});

it('gives a repeater row without a state path no title', function () {
    $repeater = Repeater::make('specs')
        ->schema([Hidden::make('beschriftung')])
        ->title('beschriftung');

    expect(repeaterItemLabel($repeater)(repeaterRow('')))->toBeNull();
});

it('binds the titles of nested blocks on the server, as those at the top', function () {
    $form = Livewire::test(NestedBuilderForm::class);
    $html = html_entity_decode($form->html(), ENT_QUOTES);
    $section = array_key_first($form->get('data.content'));
    $text = array_key_first($form->get("data.content.{$section}.data.blocks"));

    // Each title knows its field's path, the nested block's through the builder it is rendered in.
    expect(substr_count($html, 'fi-builder-title-input'))->toBe(3)
        ->and(substr_count($html, '$entangle('))->toBe(3)
        ->and($html)->toContain("\$entangle('data.content.{$section}.data.blocks.{$text}.data.title'");
});
