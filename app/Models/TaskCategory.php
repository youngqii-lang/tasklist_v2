<?php

namespace App\Models;

use Database\Factories\TaskCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'requires_review', 'evidence_required'])]
class TaskCategory extends Model
{
    /** @use HasFactory<TaskCategoryFactory> */
    use HasFactory;

    /**
     * Get the tasks in this category.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'category_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_review' => 'boolean',
            'evidence_required' => 'boolean',
        ];
    }
}
