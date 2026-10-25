<?php

namespace Workbench\App\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Workbench\App\Schemas\DocumentSchema;

/**
 * Panel-free host of the {@see DocumentSchema} form, for the feature tests.
 */
class DocumentForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(DocumentSchema::sampleState());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->components(DocumentSchema::components())
            ->statePath('data');
    }

    public function render(): View
    {
        return view('plain-form');
    }
}
