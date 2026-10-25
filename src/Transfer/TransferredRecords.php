<?php

namespace Mmoollllee\FilamentBuilderExtensions\Transfer;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use WeakMap;

/**
 * A relationship's record dragged into another list of its group, saved there as the same
 * row under its new parent — not deleted in the list it left and created anew in the other,
 * with another id and without the columns its item has no field for.
 *
 * Filament saves a relationship repeater by its keys: an existing record whose key
 * `record-N` is missing from the state is deleted, a key that names none of its records is
 * created. The drop ({@see TransferItemAction}) keeps the item's key, so the save tells a
 * move from the state alone, though it comes in a later request: `record-N` is missing from
 * a list of the group that holds the record N, and present in another list of its model.
 *
 * Every relationship repeater of a group calls {@see assign()} before it saves, its items
 * first (`saveRelationshipsBeforeChildrenUsing()`, set by {@see TransferGroup}). The first
 * call of a save reads all lists of the group in the form, before any of them has saved, and
 * notes the moves. Each list then tells Filament's own `saveToRelationship()`, through the
 * records it caches, which records are its own: the list a record left lets it go, so it
 * does not delete it; the first list holding its key to save takes it, under its own parent,
 * and updates it as one of its records. Which of the two saves first does not matter, nor
 * whether the new parent is saved yet. Keys no list left — clones, pastes, new items — are
 * created as ever.
 */
final class TransferredRecords
{
    /** @var ?WeakMap<Schema, array<string, self>> the moves of each group, by form */
    private static ?WeakMap $forms = null;

    /** @var array<string, true> the keys a list has taken */
    private array $taken = [];

    /**
     * @param  array<string, array{record: Model, from: string, to: list<string>}>  $moves  by key: the record, the state path of the list it left and of the lists holding its key
     */
    private function __construct(private readonly array $moves) {}

    /**
     * Before the list saves: lets go of the records dragged out of it and takes those dragged
     * into it, in the records it caches — which Filament deletes, updates and creates by.
     */
    public static function assign(Repeater $list): void
    {
        if ((($group = TransferGroup::of($list)) === null) || (! self::savesRecords($list))) {
            return;
        }

        $transfers = self::inForm($list->getRootContainer(), $group);
        $statePath = $list->getStatePath();
        $items = $list->getRawState();
        $items = is_array($items) ? $items : [];
        $records = $list->getCachedExistingRecords();
        $hasTaken = false;

        // Each move checked against the state again: a form saved twice has loaded its records anew in between.
        foreach ($transfers->moves as $key => $move) {
            if ($move['from'] === $statePath) {
                if (! array_key_exists($key, $items)) {
                    $records->forget($key);
                }

                continue;
            }

            if (isset($transfers->taken[$key]) || (! in_array($statePath, $move['to'], true)) || (! array_key_exists($key, $items)) || $records->has($key)) {
                continue;
            }

            self::reparent($move['record'], $list->getRelationship());
            $records->put($key, $move['record']);
            $transfers->taken[$key] = true;
            $hasTaken = true;
        }

        if ($hasTaken) {
            // Built anew around their records, the items save what they hold of their own — a
            // list, tags, files — with them, as any record's item does before its list saves.
            $list->clearCachedChildSchemas();
        }
    }

    /** The moves of the group in the form, read at the first list of it to save. */
    private static function inForm(Schema $form, string $group): self
    {
        self::$forms ??= new WeakMap;

        $groups = self::$forms[$form] ?? [];
        $groups[$group] ??= self::read($form, $group);
        self::$forms[$form] = $groups;

        return $groups[$group];
    }

    /** Keys missing from a list of the group that holds their record, and present in another of its model. */
    private static function read(Schema $form, string $group): self
    {
        $lists = [];

        foreach (self::listsIn($form, $group) as $list) {
            $items = $list->getRawState();

            $lists[] = [
                'statePath' => $list->getStatePath(),
                'keys' => array_map(strval(...), array_keys(is_array($items) ? $items : [])),
                'records' => $list->getCachedExistingRecords(),
                'model' => $list->getRelatedModel(),
            ];
        }

        $moves = [];

        foreach ($lists as $source) {
            foreach ($source['records'] as $key => $record) {
                if (in_array((string) $key, $source['keys'], true)) {
                    continue;
                }

                $to = [];

                foreach ($lists as $target) {
                    if (($target['model'] === $source['model']) && in_array((string) $key, $target['keys'], true) && (! $target['records']->has($key))) {
                        $to[] = $target['statePath'];
                    }
                }

                if ($to !== []) {
                    $moves[(string) $key] = ['record' => $record, 'from' => $source['statePath'], 'to' => $to];
                }
            }
        }

        return new self($moves);
    }

    /**
     * The group's relationship repeaters in the form, in every item and layout.
     *
     * @return iterable<Repeater>
     */
    private static function listsIn(Schema $schema, string $group): iterable
    {
        foreach ($schema->getComponents(withActions: false, withHidden: true) as $component) {
            if (($component instanceof Repeater) && (TransferGroup::of($component) === $group) && self::savesRecords($component)) {
                yield $component;
            }

            foreach ($component->getChildSchemas(withHidden: true) as $childSchema) {
                yield from self::listsIn($childSchema, $group);
            }
        }
    }

    /**
     * Whether the list saves rows of its own, each under its parent by a foreign key: a
     * `BelongsToMany` list attaches records instead, as it creates them.
     */
    private static function savesRecords(Repeater $list): bool
    {
        return $list->hasRelationship() && ($list->getRelationship() instanceof HasOneOrMany);
    }

    /** Puts the record under the relationship's parent, unsaved: Filament saves it with the item. */
    private static function reparent(Model $record, HasOneOrMany $relationship): void
    {
        $record->setAttribute($relationship->getForeignKeyName(), $relationship->getParentKey());

        if ($relationship instanceof MorphOneOrMany) {
            $record->setAttribute($relationship->getMorphType(), $relationship->getMorphClass());
        }
    }
}
