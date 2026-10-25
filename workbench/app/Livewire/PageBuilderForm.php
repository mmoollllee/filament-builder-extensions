<?php

namespace Workbench\App\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Workbench\App\Schemas\PageBuilderSchema;

/**
 * Panel-free host of the {@see PageBuilderSchema} form, for the feature tests.
 */
class PageBuilderForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(PageBuilderSchema::sampleState());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->components(PageBuilderSchema::components())
            ->statePath('data');
    }

    public function render(): View
    {
        return view('plain-form');
    }
}
