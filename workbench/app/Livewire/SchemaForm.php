<?php

namespace Workbench\App\Livewire;

use Closure;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Panel-free host of a form a test sets up (`SchemaForm::$components`, `SchemaForm::$state`),
 * for the feature tests of what the demo forms do not show.
 */
class SchemaForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var ?Closure(): array<SchemaComponent> */
    public static ?Closure $components = null;

    /** @var array<string, mixed> */
    public static array $state = [];

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(static::$state);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->components(value(static::$components) ?? [])
            ->statePath('data');
    }

    public function render(): View
    {
        return view('plain-form');
    }
}
