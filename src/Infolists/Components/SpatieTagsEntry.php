<?php

namespace Filament\Infolists\Components;

use Closure;
use Filament\SpatieLaravelTagsPlugin\Types\AllTagTypes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class SpatieTagsEntry extends TextEntry
{
    protected string | Closure | AllTagTypes | null $type;

    protected Model $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type(new AllTagTypes());

        $this->badge();
    }

    /**
     * @return array<string>
     */
    public function getState(): array
    {
        $state = parent::getState();
        $job = $this->getJob();
        if ($state && (! $state instanceof Collection)) {
            return $state;
        }

        $record = $this->getRecord();

        if (! $record) {
            return [];
        }

        if ($this->hasRelationship($record)) {
            $record = $this->getRelationshipResults($record);
        }

        $records = Arr::wrap($record);

        $state = [];

        foreach ($records as $record) {
            /** @var Model $record */
            if (! (method_exists($record, 'tags') && method_exists($record, 'tagsWithType'))) {
                continue;
            }

            $type = $this->getType();

            if ($this->isAnyTagTypeAllowed()) {
                $tags = $record->getRelationValue('tags');
            } else {
                $tags = $record->tagsWithType($job, $type);
            }

            $state = [
                ...$state,
                ...$tags->pluck('name')->all(),
            ];
        }

        return array_unique($state);
    }

    public function type(string | Closure | AllTagTypes | null $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function job(Model $job): static
    {
        $this->job = $job;

        return $this;
    }

    public function getType(): string | AllTagTypes | null
    {
        return $this->evaluate($this->type);
    }

    public function getJob(): int
    {
        return $this->job->id;
    }

    public function isAnyTagTypeAllowed(): bool
    {
        return $this->getType() instanceof AllTagTypes;
    }
}
