# Changelog

## 0.2.0 — 2026-10-25

- Transfer: a relationship's record dragged into another list of its group keeps its row. Saving puts it under its new parent instead of deleting it in the list it left and creating it anew in the other, so it keeps its id and the columns its item has no field for, and its own relationships save with it — whichever of the two lists saves first, and also into a parent added before saving. Clones and pastes are still created as new rows. See [Relationship repeaters](README.md#relationship-repeaters) for how, and what stays as Filament saves it.
- `->transferGroup()` now sets `saveRelationshipsBeforeChildrenUsing()` on a Repeater. A callback of your own set after it replaces the package's.
- Workbench: positions between sections also as a document's rows, saved to the database ("Repeater: positions as rows").

## 0.1.0 — 2026-10-25

First release, replacing `mmoollllee/filament-builder-title`: editable titles in item headers (`->title()`), dragging items between lists (`->transferGroup()`), copy and paste (`CopyItemAction`, `PasteItemAction`), ⌘C / ⌘V on a selected item (`->clipboardShortcuts()`), and adding items from outside the field (`Items::add()`, `Items::beside()`).
