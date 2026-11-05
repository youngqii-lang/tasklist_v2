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
#[Fillable(['task_id', 'submitter_id', 'type', 'title', 'file_path', 'file_name', 'mime_type', 'file_size', 'url', 'note', 'deleted_by'])]
class TaskEvidence extends Model
{
    /** @use HasFactory<TaskEvidenceFactory> */
    use HasFactory, SoftDeletes;

    public const string TYPE_FILE = 'file';

    public const string TYPE_LINK = 'link';

    /**
     * MIME types that browsers may show inline instead of downloading.
     *
     * @var list<string>
     */
    public const array PREVIEWABLE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    /**
     * Get the name to show for the evidence: the file name, or the link title falling back to the link.
     */
    public function displayName(): string
    {
        return $this->type === self::TYPE_FILE ? (string) $this->file_name : (string) ($this->title ?: $this->url);
    }

    /**
     * Determine whether the evidence is an image file, for thumbnails.
     */
    public function isImage(): bool
    {
        return $this->type === self::TYPE_FILE && str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * Determine whether the evidence file can be opened inline in the browser.
     */
    public function isPreviewable(): bool
    {
        return $this->type === self::TYPE_FILE && in_array($this->mime_type, self::PREVIEWABLE_MIME_TYPES, true);
    }

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
