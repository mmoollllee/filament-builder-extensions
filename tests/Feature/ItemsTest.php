<?php

use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Livewire\Livewire;
use Mmoollllee\FilamentBuilderExtensions\Items;
use Workbench\App\Livewire\DocumentForm;
use Workbench\App\Livewire\SchemaForm;

/**
 * `Items::add()` and `Items::beside()`: an item added from a row of actions beside the list,
 * as the list's own button adds it, its fields at their defaults.
 */

/** What a click on the action `$name` mounts, in the `Actions` row whose key ends in `$key`. */
function actionsRowCall(string $html, string $name, string $key): array
{
    preg_match_all('/wire:click="mountAction\(\''.$name.'\', (.*?), JSON\.parse\(\'(.*?)\'\)\)"/', $html, $matches, PREG_SET_ORDER);

    foreach ($matches as [, $arguments, $context]) {
        $context = json_decode(json_decode('"'.$context.'"'), true);

        if (str_ends_with($context['schemaComponent'], $key)) {
            return [$name, json_decode($arguments, true) ?? [], $context];
        }
    }

    throw new RuntimeException("No action [{$name}] in [{$key}].");
}

it('adds a position to the list beside the row, its fields at their defaults', function () {
    $form = Livewire::test(DocumentForm::class);
    $options = keyOf($form->get('data.sections'), 'Options');

    $form->call('mountAction', ...actionsRowCall($form->html(), 'addPosition', $options));

    $positions = $form->get("data.sections.{$options}.positions");

    expect(titlesOf($positions))->toBe(['SIM card', null])
        ->and(end($positions))->toEqual(['title' => null, 'quantity' => 1, 'price' => null]);
});

it('adds a section with a state over its defaults', function () {
    $form = Livewire::test(DocumentForm::class);

    $form->call('mountAction', ...actionsRowCall($form->html(), 'addSection', 'form'));

    expect(titlesOf($form->get('data.sections')))->toBe(['Hardware', 'Options', 'Empty section', 'New section']);
});

it('needs the block to add to a builder', function () {
    expect(fn () => Items::add(Builder::make('content')->blocks([Block::make('text')->schema([TextInput::make('body')])])))
        ->toThrow(LogicException::class, 'The builder [content] has no block [].');
});

it('finds the list beside a row of actions through layout components around either', function () {
    SchemaForm::$state = ['items' => [['name' => 'First']]];
    SchemaForm::$components = fn (): array => [
        Section::make()->schema([
            Repeater::make('items')->schema([TextInput::make('name')]),
        ]),
        Grid::make()->schema([
            Actions::make([
                Action::make('addItem')
                    ->action(fn (Actions $component) => Items::add(Items::beside($component, 'items'), ['name' => 'Added'])),
            ]),
        ]),
    ];

    $form = Livewire::test(SchemaForm::class);

    $form->call('mountAction', ...actionsRowCall($form->html(), 'addItem', 'form'));

    expect(array_column($form->get('data.items'), 'name'))->toBe(['First', 'Added']);
});
