<?php

namespace Workbench\App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;
use Workbench\App\Schemas\DocumentSchema;

class DocumentDemo extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Lists';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Repeater: positions between sections';

    protected static ?string $slug = 'document';

    protected string $view = 'filament-builder-demo';

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

    public function save(): void
    {
        $this->form->getState();
    }
}
