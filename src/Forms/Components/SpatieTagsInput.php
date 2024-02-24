<?php

namespace Filament\Forms\Components;

use Closure;
use Filament\SpatieLaravelTagsPlugin\Types\AllTagTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Tags\Tag;

class SpatieTagsInput extends TagsInput
{
    protected string | Closure | AllTagTypes | null $type;

    protected $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type(new AllTagTypes());

        $this->loadStateFromRelationshipsUsing(static function (SpatieTagsInput $component, ?Model $record): void {
            if (! method_exists($record, 'tagsWithType')) {
                return;
            }

            $type = $component->getType();
            $record->load('tags');

            if ($component->isAnyTagTypeAllowed()) {
                $tags = $record->getRelationValue('tags');
            } else {
                $job = $component->getJob();
                $tags = $record->tagsWithType($job, $type);
                dd($tags);
            }

            $component->state($tags->pluck('name')->all());
        });

        $this->saveRelationshipsUsing(static function (SpatieTagsInput $component, ?Model $record, array $state) {
            if (! (method_exists($record, 'syncTagsWithType') && method_exists($record, 'syncTags'))) {
                return;
            }

            if (
                ($type = $component->getType()) &&
                (! $component->isAnyTagTypeAllowed())
            ) {
                $job = $component->getJob();

                $record->syncTagsWithType($job->id, $state, $type);

                return;
            }

            $component->syncTagsWithAnyType($record, $state);
        });

        $this->dehydrated(false);
    }

    /**
     * Syncs tags with the record without taking types into account. This avoids recreating existing tags with an empty type.
     * Spatie's `HasTags` trait does not have functionality for this behaviour.
     *
     * @param  array<string>  $state
     */
    protected function syncTagsWithAnyType(?Model $record, array $state): void
    {
        if (! ($record && method_exists($record, 'tags'))) {
            return;
        }

        $tagClassName = config('tags.tag_model', Tag::class);

        $tags = collect($state)->map(function ($tagName) use ($tagClassName) {
            $locale = $tagClassName::getLocale();
            $job = $this->getJob();
            $tag = $tagClassName::findFromStringOfAnyType($job->id, $tagName, $locale);

            if ($tag?->isEmpty() ?? true) {
                $tag = $tagClassName::create([
                    'name' => [$locale => $tagName],
                ]);
            }

            return $tag;
        })->flatten();

        $record->tags()->sync($tags->pluck('id'));
    }

    public function type(string | Closure | AllTagTypes | null $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function job(Model $model)
    {
        $this->job = $model;

        return $this;
    }

    public function getSuggestions(): array
    {
        if ($this->suggestions !== null) {
            return parent::getSuggestions();
        }

        $model = $this->getModel();
        $tagClass = $model ? $model::getTagClassName() : config('tags.tag_model', Tag::class);
        $type = $this->getType();
        $query = $tagClass::query();

        if (! $this->isAnyTagTypeAllowed()) {
            $query->when(
                filled($type),
                fn (Builder $query) => $query->where('type', $type),
                fn (Builder $query) => $query->where('type', null),
                fn (Builder $query) => $query->where('job_id', $this->getJob()->id)
            );
        }
        dd($query);
        return $query->pluck('name')->all();
    }

    public function getType(): string | AllTagTypes | null
    {
        return $this->evaluate($this->type);
    }


    public function getJob()
    {
        return $this->job;
    }

    public function isAnyTagTypeAllowed(): bool
    {
        return $this->getType() instanceof AllTagTypes;
    }
}
