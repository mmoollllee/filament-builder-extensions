<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Schemas\DocumentSchema;

/**
 * A document whose sections hold its positions, as rows: the {@see DocumentSchema} form
 * saved through relationship repeaters.
 */
class Document extends Model
{
    protected $fillable = ['title'];

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /** The document of {@see DocumentSchema::sampleState()}, as rows. */
    public static function sample(): self
    {
        $document = self::create(['title' => 'Sample document']);

        foreach (array_values(DocumentSchema::sampleState()['sections']) as $sectionIndex => $section) {
            $sectionRecord = $document->sections()->create(['title' => $section['title'], 'sort' => $sectionIndex + 1]);

            foreach (array_values($section['positions']) as $positionIndex => $position) {
                $sectionRecord->positions()->create([...$position, 'sort' => $positionIndex + 1]);
            }
        }

        return $document;
    }
}
