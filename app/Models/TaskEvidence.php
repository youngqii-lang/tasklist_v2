<?php

namespace App\Models;

use Database\Factories\TaskEvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('task_evidences')]
#[Fillable(['task_id', 'submitter_id', 'type', 'file_path', 'file_name', 'mime_type', 'file_size', 'url', 'note', 'deleted_by'])]
class TaskEvidence extends Model
{
    /** @use HasFactory<TaskEvidenceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the task the evidence belongs to.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the account that submitted the evidence.
     *
     * @return BelongsTo<Account, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'submitter_id');
    }

    /**
     * Get the account that soft deleted the evidence.
     *
     * @return BelongsTo<Account, $this>
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deleted_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }
}
