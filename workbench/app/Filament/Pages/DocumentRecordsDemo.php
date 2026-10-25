<?php

namespace Workbench\App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;
use Workbench\App\Models\Document;
use Workbench\App\Schemas\DocumentSchema;

/**
 * The positions between sections as a document's rows: a position dragged into another
 * section and saved is the same row there.
 */
class DocumentRecordsDemo extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Lists';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Repeater: positions as rows';

    protected static ?string $slug = 'document-records';

    protected string $view = 'filament-builder-demo';

    public ?Document $record = null;

    public ?array $data = [];

    public function mount(): void
    {
        $this->record = Document::query()->first() ?? Document::sample();

        $this->form->fill($this->record->attributesToArray());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->components(DocumentSchema::components(relationships: true))
            ->model($this->record)
            ->statePath('data');
    }

    public function save(): void
    {
        $this->form->getState();

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }
}
