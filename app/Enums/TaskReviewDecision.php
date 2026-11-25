<?php

namespace App\Enums;

use App\Models\TaskActivity;

enum TaskReviewDecision: string
{
    case Approve = 'approve';
    case Revision = 'revision';

    /**
     * Approval finishes the task; a revision sends it back to Back Log, from where its PICs work through the
     * workflow again until Ready Review.
     */
    public function nextStatus(): TaskStatus
    {
        return match ($this) {
            self::Approve => TaskStatus::Done,
            self::Revision => TaskStatus::Backlog,
        };
    }

    public function activityAction(): string
    {
        return match ($this) {
            self::Approve => TaskActivity::APPROVED,
            self::Revision => TaskActivity::REVISION_REQUESTED,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Approve => 'Approve',
            self::Revision => 'Request Revision',
        };
    }
}
