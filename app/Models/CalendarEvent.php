<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['division_id', 'creator_id', 'title', 'description', 'start_at', 'end_at'])]
class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Limit the query to events that take place, at least partly, between the two moments.
     *
     * @param  Builder<CalendarEvent>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('start_at', '<=', $to)
            ->where(fn (Builder $query) => $query
                ->where('end_at', '>=', $from)
                ->orWhere(fn (Builder $query) => $query->whereNull('end_at')->where('start_at', '>=', $from)));
    }

    /**
     * Limit the query to the events of a division plus the company-wide events. Without a division, every event is kept.
     *
     * @param  Builder<CalendarEvent>  $query
     */
    #[Scope]
    protected function sharedWith(Builder $query, ?int $divisionId): void
    {
        $query->when($divisionId, fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('division_id', $divisionId)
            ->orWhereNull('division_id')));
    }

    /**
     * Describe the event as shared calendar information.
     *
     * @return array{id: int, title: string, description: string|null, division: array{id: int, name: string}|null, start_at: string, end_at: string|null}
     */
    public function toSharedArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'division' => $this->division ? ['id' => $this->division->id, 'name' => $this->division->name] : null,
            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
        ];
    }

    /**
     * Get the organizing division, or null for a company-wide event.
     *
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the account that created the event.
     *
     * @return BelongsTo<Account, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'creator_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }
}
