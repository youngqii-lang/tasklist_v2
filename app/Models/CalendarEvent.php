<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An agenda on the shared schedule (Timeline and Calendar), next to the tasks' own dates. The only source of
 * agendas are stored in calendar_events.
 *
 * The access rules of the existing Schedule/Calendar: a shared agenda (is_shared) is visible to every active
 * account; an internal one to Admin and to the accounts of its target divisions only, so to Admin alone when it has
 * none. division_id is the organising division and does not grant access.
 */
#[Fillable(['division_id', 'creator_id', 'title', 'description', 'is_shared', 'start_at', 'end_at'])]
class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    /**
     * The organising division, if any.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * The divisions an internal agenda is meant for.
     */
    public function targetDivisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class, 'calendar_event_divisions')->orderBy('divisions.id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'creator_id');
    }

    /**
     * Agendas that overlap the period; one without an end lasts its start day only.
     */
    public function scopeWithin(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('start_at', '<=', $to->copy()->endOfDay()->toDateTimeString())
            ->whereRaw('coalesce(end_at, start_at) >= ?', [$from->copy()->startOfDay()->toDateTimeString()]);
    }

    /**
     * Focus on a division: the agendas it organises or is a target of, plus the shared agendas without an
     * organiser. No division keeps every agenda. Visibility is applied separately, so the focus never widens it.
     */
    public function scopeForDivision(Builder $query, ?string $division): void
    {
        $query->when($division, fn (Builder $query, string $division) => $query->where(fn (Builder $query) => $query
            ->whereHas('division', fn (Builder $query) => $query->where('code_division', $division))
            ->orWhereHas('targetDivisions', fn (Builder $query) => $query->where('code_division', $division))
            ->orWhere(fn (Builder $query) => $query->where('is_shared', true)->whereNull('division_id'))));
    }

    /**
     * is_shared decides first: a shared agenda is visible to every active account. An internal agenda is visible
     * to Admin and to the accounts of its target divisions; every other role, Manager included, sees only the
     * internal agendas of its own division.
     */
    public function scopeVisibleTo(Builder $query, Account $account): void
    {
        if (! $account->is_active) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($account->isAdmin()) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->where('is_shared', true)
            ->orWhereHas('targetDivisions', fn (Builder $query) => $query->whereKey($account->division_id ?? 0)));
    }
}
