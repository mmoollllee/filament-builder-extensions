# Filament Builder Extensions

Opt-in tools for the items of Filament **Builder** and **Repeater** fields:

| Tool | Use it with | What it does |
| --- | --- | --- |
| [Titles](#titles) | `->title('field')` on a `Block` or `Repeater` | an editable title in the item's header instead of its label |
| [Transfer](#dragging-items-between-lists) | `->transferGroup('name')` on a `Builder` or `Repeater` | drag items between lists of the same group, nested lists included; a relationship's record keeps its row |
| [Clipboard](#copy-and-paste) | `CopyItemAction`, `PasteItemAction` | copy an item and paste it into another list, record or tab |
| [Keyboard](#keyboard-c--v) | `->clipboardShortcuts()` on a `Builder` or `Repeater` | ⌘C / ⌘V on a selected item, with the list's own copy and paste actions |
| [Adding from outside](#adding-items-from-outside-the-field) | `Items::add()`, `Items::beside()` | add an item from an action beside the list, its fields at their defaults |

Nothing changes until a field asks for it: no Filament view is overridden and no field is reconfigured. The stylesheet only styles the package's own classes, and the script loads only where a field or action needs it (`x-load-js`).

This package replaces [`mmoollllee/filament-builder-title`](https://github.com/mmoollllee/filament-builder-title): the `->title()` macros work as before (see [Upgrading](#upgrading-from-filament-builder-title)).

## Requirements

- PHP 8.2+
- Filament v5 (the feature and browser tests pass on 5.7.5, 5.8.4 and 5.10.3)

## Installation

```bash
composer require mmoollllee/filament-builder-extensions
php artisan filament:assets
```

## Titles

`->title()` renders an input in a Builder block's or a Repeater row's header, bound to one of the item's fields, so items can be named without opening them. Bind the field as `Hidden::make()` in the schema (Filament drops hidden *fields* such as `TextInput::make()->hidden()` from the state).

```php
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

Block::make('section_header')
    ->label('Header')
    ->schema([
        Hidden::make('heading'),
        TextInput::make('subheading'),
    ])
    ->title('heading', placeholder: 'Section title', suffix: 'Header');

Repeater::make('specs')
    ->schema([
        Hidden::make('label'),
        TextInput::make('value'),
    ])
    ->title('label', placeholder: 'Row label');
```

| Parameter | Default | |
| --- | --- | --- |
| `field` | — | the field of the item the input edits |
| `placeholder` | the block's label (else its humanised name) / the humanised field name | shown while the title is empty |
| `suffix` | none | static text after the input; a click on it focuses the input |

- `->title()` takes the place of the block's label in its header (the block picker keeps the label set before it, text, HTML or a closure) and of a Repeater's `->itemLabel()`; don't set both.
- It works in collapsed items, in nested builders and in blocks with previews, whose title is sent at once, so the preview shows it. A validation error of the field shows in the header. Where Filament reads the label as text — its reorder announcements to screen readers — the title is the text.
- Repeater titles need the default layout: `->table()` and `->simple()` render no item label.

To render the same input from your own `itemLabel()` closure, use `ItemTitle::input(['wireModel' => …, 'value' => …, 'placeholder' => …, 'suffix' => …, 'error' => ItemTitle::firstError($container, $wireModel)])` (`'live' => true` sends each change at once).

## Dragging items between lists

```php
Repeater::make('sections')
    ->schema([
        TextInput::make('title'),
        Repeater::make('positions')
            ->schema([/* … */])
            ->transferGroup('positions'),
    ]);
```

Every list of a group — here, the positions of every section — accepts the items of the others: drag an item by its handle into another list, including an empty one (a "Drop here" zone stands in for it while an item is dragged). Within a list, Filament's own reordering works as before.

The drop is carried out on the server by the target list's `transferItem` action, mounted as Filament mounts its own reorder. It checks both lists against the form before it moves anything:

- both are fields of the same form, of the same kind (Builder or Repeater) and in the same group, and both are reorderable by drag and drop (so neither is disabled);
- the source is deletable: an item that leaves a list is gone from it, as if deleted there (a relationship's record goes along, see [below](#relationship-repeaters)). A list with `->deletable(false)` gives no item away (it cannot even be dragged out), but takes the others';
- the item is one of the source's;
- a Builder offers the item's block, as its block picker would (not once the block is there as often as its own `maxItems()` allows), and the target is not at its `maxItems()`.

A drop the target cannot take (a block it does not offer, a full list) puts the item back and says so. The item keeps its key and its state, and a relationship's record its row. Where the target holds the item's key already — a copy or a clone of the item's list keeps the keys of the items in it — the item takes a key of its own there.

Lists only exchange items within a group and within one Livewire component. The group can be a closure (`->transferGroup(fn (Get $get) => …)`); `null` turns the tool off.

The macro puts its attributes on the field's wrapper through `extraAttributes(…, merge: true)`. Call `->extraAttributes()` after it with `merge: true` too, or it replaces them and the list leaves its group.

### Relationship repeaters

A record dragged into another relationship repeater of its group is saved there as the same row, under its new parent. It keeps its id and the columns its item has no field for — the contract a position bills, the line it was copied from —, and whatever refers to it still does.

```php
Repeater::make('sections')
    ->relationship()
    ->orderColumn('sort')
    ->schema([
        Hidden::make('title'),
        Repeater::make('positions')
            ->relationship()
            ->orderColumn('sort')
            ->transferGroup('positions')
            ->schema([/* … */]),
    ]);
```

Left to itself, Filament would save the move as a deletion and a new row: it saves a relationship repeater by its keys, deleting an existing record whose key `record-N` is missing from the state and creating one for a key that names none of its records.

The drop keeps the item's key, so the save tells a move from the form's state alone, though the drop and the save are separate requests: `record-N` is missing from a list of the group that holds the record N, and present in another list of the same model. Right before the first list of the group saves, all of them are read. Then the list the record left does not delete it, and the first list holding its key to save takes it over: it puts the record under its own parent (its foreign key, and a morph type) and Filament updates it as one of its own records — with the item's state, the order column, `mutateRelationshipDataBeforeSaveUsing()` and `afterUpdate()`. The item's own relationships — a repeater inside it, tags, files — save with the record. It does not matter which of the two lists saves first, nor whether the new parent was added before saving.

How: the macro gives a relationship repeater a `saveRelationshipsBeforeChildrenUsing()` callback, which runs right before the list's items and the list itself save. It only tells Filament's own `saveToRelationship()`, through the records the list caches, which records are the list's; Filament does all the saving. The other ways in fall short:

- a `saveRelationshipsUsing()` of the package's: `->relationship()` sets that callback, and replaces the package's when it is called after `->transferGroup()`. And it runs only after the list's items have saved their own relationships: a moved item, new to the list until then, would have skipped its own;
- a deletion the list a record left defers until another list of the group has taken it: a form's save has no moment after its last list to run it in, and a record its new list took first is no longer the old list's to delete anyway.

What stays as Filament saves it:

- Keys no list left — clones, pastes, new items — are created. A list cloned with the item still in it holds the item's key too: the first list to save takes the record, the other one's item is created.
- A record dragged out of an item that is deleted in the same save — positions dragged out of a section that is then removed — is not traced: the list it left is no longer in the form. The moved item is created where it was dropped, and the record goes with its old parent. Save before removing the emptied item.
- Until the form is saved, the moved item is new to its list: what asks for its record — `unique(ignoreRecord: true)`, a field shown on new items only — gets none.
- `BelongsToMany` repeaters, moves between lists of different models, plain repeaters and builders.

A `saveRelationshipsBeforeChildrenUsing()` of your own on the list replaces the package's when set after `->transferGroup()`.

## Copy and paste

```php
use Mmoollllee\FilamentBuilderExtensions\Clipboard\CopyItemAction;
use Mmoollllee\FilamentBuilderExtensions\Clipboard\PasteItemAction;

Builder::make('blocks')
    ->extraItemActions([
        fn () => CopyItemAction::make()->kind('cms.block'),
        fn () => PasteItemAction::make('pasteAfter')->kind('cms.block'),   // right after this item
    ])
    ->belowContent([
        PasteItemAction::make()->kind('cms.block')->link(),                 // last in the list
    ])
    ->blocks([/* … */]);
```

Give `extraItemActions()` and `registerActions()` the actions as closures, as above. A list inside another list's items — the blocks of a page's sections — is cloned for every item, but the action objects it was given are not: one object shared by all clones acts on whichever list cached it last, and a paste in one section lands in another. (`belowContent()` and `aboveContent()` clone their actions with the list.)

`CopyItemAction` copies the item — its state as the form holds it, unsaved edits included — as JSON to the clipboard and to `localStorage`, which keeps it for a day where the browser refuses the clipboard (no secure context, no permission). It runs in the browser alone.

`PasteItemAction` reads it back in the browser — the clipboard's copy, else the last one in `localStorage`, if it was copied in the last 24 hours — and mounts itself with what it found, so it needs no view of its own. An older copy in `localStorage` is not pasted but removed, so it does not stay in the browser for whoever uses it next. Where it pastes:

- among a list's `extraItemActions()`: right after that item;
- in the list's own `belowContent()` / `aboveContent()`: last;
- anywhere else, e.g. in a row of actions beside the list: last in the list named with `->into('name')` (see [`Items::beside()`](#adding-items-from-outside-the-field)).

A paste only takes a copy of its own **kind** (`->kind()`, default: the list's name), so a block never lands among an invoice's positions. Copies travel as JSON, `{kind, item}`; a Builder item stays as Filament holds it, `{kind, type, data}`, so a paste into a Builder also takes a bare `{type, data}` — what copies made without the package hold.

What arrives was sent by the browser and is taken on no trust:

- a Builder item has to be of a block the builder offers, as its block picker would;
- by default only the keys the item's fields have are kept, the other fields get their defaults;
- `->sanitizeUsing()` decides instead: the closure gets the copied item (`$item`: a Repeater row's state, a Builder's `['type' => …, 'data' => […]]`) and the target (`$list`), and returns what to add in the same shape, or `null` to refuse;
- either way the lists inside the item are a copy's: their items get keys of their own, and a Builder among them keeps only items of a block it has — Filament would not show the others, but save them.

```php
PasteItemAction::make('pastePosition')
    ->kind('billing.position')
    ->into('lines')
    ->sanitizeUsing(fn (array $item): array => [
        'title' => is_string($item['title'] ?? null) ? $item['title'] : null,
        'quantity' => is_numeric($item['quantity'] ?? null) ? $item['quantity'] : 1,
    ]);
```

Labels and messages are translated (en, de) and can be changed as on any action: `->label()`, `->successNotificationTitle()` (shown after copying; after pasting, when set), `->failureNotificationTitle()` (nothing suitable found, or refused).

To offer the paste where Filament renders no actions — say, as an entry of your own block picker — register it on the list and run its click handler there, loading the script with it:

```php
Builder::make('blocks')->registerActions([fn () => PasteItemAction::make()->kind('cms.block')]);
```

```blade
{{-- $pasteAction = $getAction('pasteItem'), invoked with ['item' => $key] to paste after that item --}}
<button
    type="button"
    x-load-js="{{ json_encode([\Mmoollllee\FilamentBuilderExtensions\FilamentBuilderExtensionsServiceProvider::scriptSrc()]) }}"
    x-on:click="{{ $pasteAction->getAlpineClickHandler() }}"
>{{ $pasteAction->getLabel() }}</button>
```

## Keyboard: ⌘C / ⌘V

```php
Builder::make('blocks')
    ->extraItemActions([fn () => CopyItemAction::make()->kind('cms.block')])
    ->registerActions([fn () => PasteItemAction::make()->kind('cms.block')])
    ->clipboardShortcuts();
```

With `->clipboardShortcuts()`, a click on an item — outside its fields — selects it (it gets a frame); ⌘C copies it and ⌘V pastes after it (Ctrl+C / Ctrl+V on Windows and Linux, on any keyboard layout). The pasted item is selected then, so ⌘V again pastes after it. Escape, or a click elsewhere, lets the item go. In a field, a rich editor, or with text selected, the keys copy and paste text as ever. The selection outlasts the form's renders, partial ones included.

The keys use the list's own actions, with their kind, their checks and their messages: its `CopyItemAction` (among its item actions) and its `PasteItemAction` (among its item actions, registered on it with `->registerActions()`, or in its `aboveContent()` / `belowContent()`). A paste action beside the list (`->into()`) does not count: register one on the list for the keys.

The browser copies and pastes by itself, out of and into a textarea focused for that instant, inside the list, so a modal's focus trap lets it be: no clipboard permission is asked, and it works without a secure context and in Safari.

## Adding items from outside the field

A list's own add button offers one kind of item. A row of actions beside it can offer several — a free position, one from a catalogue, a discount — and serve several lists:

```php
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Mmoollllee\FilamentBuilderExtensions\Items;

Repeater::make('lines')->addable(false)->schema([/* … */]),
Actions::make([
    Action::make('addLine')
        ->action(fn (Actions $component) => Items::add(Items::beside($component, 'lines'))),
    Action::make('addDiscount')
        ->action(fn (Actions $component) => Items::add(Items::beside($component, 'lines'), ['kind' => 'discount'])),
]),
```

- `Items::beside($component, 'lines')` finds the Builder or Repeater `lines` beside a component by its state path, as `$get('lines')` finds a field: in the same item or form, through any layout components around either; `'../lines'` looks one level up.
- `Items::add($list, $state = [], block: null, after: null)` adds an item as the list's own button does, its fields at their defaults, `$state` over them; a Builder needs the `block`. `$state` can be a closure that gets the defaults. The item goes last, or right after the item `after`. It returns the new item's key.

## Upgrading from filament-builder-title

1. `composer remove mmoollllee/filament-builder-title` and `composer require mmoollllee/filament-builder-extensions` (the two conflict: both define `->title()`), then `php artisan filament:assets`.
2. `->title()` needs no change. Direct calls move: `FilamentBuilderTitleServiceProvider::titleInput()` → `Mmoollllee\FilamentBuilderExtensions\Title\ItemTitle::input()`, `::firstError()` → `ItemTitle::firstError()`.
3. The CSS classes (`fi-builder-title-*`) are unchanged; a published view moves from `views/vendor/filament-builder-title` to `views/vendor/filament-builder-extensions`.

## Local development and demo

The package ships an [Orchestra Testbench](https://packages.tools/testbench) workbench:

```bash
composer install
npm install                        # Playwright, for the browser tests
npx playwright install chromium    # …and the browser it drives
composer serve                     # builds the workbench and serves it
```

Open http://localhost:8000/login (credentials prefilled). The pages under "Lists" show the transfer, clipboard and add tools on Repeaters (positions between an invoice's sections, also as a document's rows, saved to the database) and Builders (blocks between a page's sections, one block kind refused, one image per section at most); the pages under "Titles" show the titles, the repeater page also in a modal.

```bash
composer test                   # all tests
vendor/bin/pest tests/Feature   # without a browser
vendor/bin/pest tests/Browser   # Chromium: dragging, clipboard
```

## Publishing the views

```bash
php artisan vendor:publish --tag=filament-builder-extensions-views
```

## License

MIT
