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
use Workbench\App\Models\Document;
use Workbench\App\Schemas\DocumentSchema;

/**
 * Panel-free host of the {@see DocumentSchema} form for a {@see Document}'s rows, saved as an
 * edit page saves it, for the feature tests. A test can set up components of its own
 * (`DocumentRecordForm::$components`).
 */
class DocumentRecordForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var ?Closure(): array<SchemaComponent> */
    public static ?Closure $components = null;

    public Document $record;

    public ?array $data = [];

    /** Filled from the record, as an edit page fills it: the relationship repeaters load their rows. */
    public function mount(): void
    {
        $this->form->fill($this->record->attributesToArray());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->components(value(static::$components) ?? DocumentSchema::components(relationships: true))
            ->model($this->record)
            ->statePath('data');
    }

    /** Validates, saves the relationships and loads them anew, as an edit page's `getState()` does. */
    public function save(): void
    {
        $this->form->getState();
    }

    public function render(): View
    {
        return view('plain-form');
    }
}
