<?php

namespace Workbench\App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;
use Workbench\App\Schemas\PageBuilderSchema;

class PageBuilderDemo extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static string|UnitEnum|null $navigationGroup = 'Lists';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Builder: blocks between sections';

    protected static ?string $slug = 'page-builder';

    protected string $view = 'filament-builder-demo';

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

    public function save(): void
    {
        $this->form->getState();
    }
}
