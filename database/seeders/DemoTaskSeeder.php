<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Division;
use App\Models\Platform;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCategory;
use App\Models\TaskEvidence;
use App\Models\TaskReview;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoTaskSeeder extends Seeder
{
    /**
     * Demo tasks, five per division. Dates are day offsets from today, so some tasks are overdue or due today.
     *
     * The PIC is the division's `staff` or `freelance` demo account. Tasks in review or done use a category
     * that requires a review, and come with an evidence link.
     *
     * @var list<array{division: string, title: string, category: string, platform: string, priority: string, status: string, start: int|null, deadline: int|null, pic: string|null}>
     */
    public const array TASKS = [
        ['division' => 'SEO', 'title' => 'Riset keyword JADIASN 2027', 'category' => 'Riset', 'platform' => 'JADIASN', 'priority' => Task::PRIORITY_MEDIUM, 'status' => Task::STATUS_BACKLOG, 'start' => null, 'deadline' => null, 'pic' => null],
        ['division' => 'SEO', 'title' => 'Optimasi meta tag landing page JADIBUMN', 'category' => 'Konten', 'platform' => 'JADIBUMN', 'priority' => Task::PRIORITY_HIGH, 'status' => Task::STATUS_TODO, 'start' => 0, 'deadline' => 3, 'pic' => 'staff'],
        ['division' => 'SEO', 'title' => 'Artikel SEO tips lolos SKD CPNS', 'category' => 'Konten', 'platform' => 'JADIASN', 'priority' => Task::PRIORITY_URGENT, 'status' => Task::STATUS_IN_PROGRESS, 'start' => -5, 'deadline' => -1, 'pic' => 'staff'],
        ['division' => 'SEO', 'title' => 'Audit backlink website CEREBRUM', 'category' => 'Development', 'platform' => 'CEREBRUM', 'priority' => Task::PRIORITY_MEDIUM, 'status' => Task::STATUS_READY_REVIEW, 'start' => -7, 'deadline' => 2, 'pic' => 'freelance'],
        ['division' => 'SEO', 'title' => 'Artikel pilar persiapan psikotes kerja', 'category' => 'Konten', 'platform' => 'PSIKOTESKERJA', 'priority' => Task::PRIORITY_MEDIUM, 'status' => Task::STATUS_DONE, 'start' => -14, 'deadline' => -3, 'pic' => 'freelance'],

        ['division' => 'MKT', 'title' => 'Rencana campaign Harbolnas 12.12', 'category' => 'Campaign', 'platform' => 'CEREBRUM', 'priority' => Task::PRIORITY_HIGH, 'status' => Task::STATUS_BACKLOG, 'start' => 5, 'deadline' => 20, 'pic' => null],
        ['division' => 'MKT', 'title' => 'Jadwal posting Instagram JADIPOLISI', 'category' => 'Konten', 'platform' => 'JADIPOLISI', 'priority' => Task::PRIORITY_MEDIUM, 'status' => Task::STATUS_TODO, 'start' => 1, 'deadline' => 7, 'pic' => 'freelance'],
        ['division' => 'MKT', 'title' => 'Iklan Meta Ads pendaftaran JADIPPPK', 'category' => 'Campaign', 'platform' => 'JADIPPPK', 'priority' => Task::PRIORITY_URGENT, 'status' => Task::STATUS_IN_PROGRESS, 'start' => -3, 'deadline' => 0, 'pic' => 'staff'],
        ['division' => 'MKT', 'title' => 'Video promo try out JADIPRAJURIT', 'category' => 'Konten', 'platform' => 'JADIPRAJURIT', 'priority' => Task::PRIORITY_HIGH, 'status' => Task::STATUS_ON_REVIEW, 'start' => -10, 'deadline' => 1, 'pic' => 'staff'],
        ['division' => 'MKT', 'title' => 'Email blast promo JADIBEASISWA', 'category' => 'Campaign', 'platform' => 'JADIBEASISWA', 'priority' => Task::PRIORITY_LOW, 'status' => Task::STATUS_DONE, 'start' => -12, 'deadline' => -5, 'pic' => 'freelance'],

        ['division' => 'SLS', 'title' => 'Follow up leads webinar JADIOJK', 'category' => 'Operasional', 'platform' => 'JADIOJK', 'priority' => Task::PRIORITY_HIGH, 'status' => Task::STATUS_TODO, 'start' => -2, 'deadline' => -1, 'pic' => 'staff'],
        ['division' => 'SLS', 'title' => 'Rekap penjualan paket bulan lalu', 'category' => 'Administrasi', 'platform' => 'CEREBRUM', 'priority' => Task::PRIORITY_MEDIUM, 'status' => Task::STATUS_IN_PROGRESS, 'start' => -4, 'deadline' => 2, 'pic' => 'staff'],
        ['division' => 'SLS', 'title' => 'Penawaran kerja sama kampus TOEFLACADEMY', 'category' => 'Riset', 'platform' => 'TOEFLACADEMY', 'priority' => Task::PRIORITY_MEDIUM, 'status' => Task::STATUS_IN_PROGRESS, 'start' => -1, 'deadline' => 10, 'pic' => 'freelance'],
        ['division' => 'SLS', 'title' => 'Materi presentasi B2B JADISEKDIN', 'category' => 'Desain', 'platform' => 'JADISEKDIN', 'priority' => Task::PRIORITY_HIGH, 'status' => Task::STATUS_READY_REVIEW, 'start' => -6, 'deadline' => 0, 'pic' => 'freelance'],
        ['division' => 'SLS', 'title' => 'Script closing paket bimbel JAGOTPA', 'category' => 'Konten', 'platform' => 'JAGOTPA', 'priority' => Task::PRIORITY_LOW, 'status' => Task::STATUS_DONE, 'start' => -15, 'deadline' => -7, 'pic' => 'staff'],

        ['division' => 'CRT', 'title' => 'Konsep maskot baru CEREBRUM', 'category' => 'Desain', 'platform' => 'CEREBRUM', 'priority' => Task::PRIORITY_LOW, 'status' => Task::STATUS_BACKLOG, 'start' => null, 'deadline' => 30, 'pic' => 'staff'],
        ['division' => 'CRT', 'title' => 'Desain feed Instagram JADIPCPM', 'category' => 'Desain', 'platform' => 'JADIPCPM', 'priority' => Task::PRIORITY_MEDIUM, 'status' => Task::STATUS_TODO, 'start' => 2, 'deadline' => 9, 'pic' => 'freelance'],
        ['division' => 'CRT', 'title' => 'Thumbnail YouTube kelas JADIASN', 'category' => 'Desain', 'platform' => 'JADIASN', 'priority' => Task::PRIORITY_URGENT, 'status' => Task::STATUS_IN_PROGRESS, 'start' => -2, 'deadline' => 1, 'pic' => 'staff'],
        ['division' => 'CRT', 'title' => 'Banner promo JADIBUMN', 'category' => 'Desain', 'platform' => 'JADIBUMN', 'priority' => Task::PRIORITY_HIGH, 'status' => Task::STATUS_READY_REVIEW, 'start' => -5, 'deadline' => 1, 'pic' => 'staff'],
        ['division' => 'CRT', 'title' => 'Motion graphic iklan JADIPOLISI', 'category' => 'Konten', 'platform' => 'JADIPOLISI', 'priority' => Task::PRIORITY_URGENT, 'status' => Task::STATUS_ON_REVIEW, 'start' => -8, 'deadline' => -2, 'pic' => 'freelance'],
    ];

    /**
     * Seed the demo tasks with their PIC, evidence, review and history. Tasks that already exist are skipped.
     */
    public function run(): void
    {
        $divisions = Division::pluck('id', 'code_division');
        $categories = TaskCategory::all()->keyBy('name');
        $platforms = Platform::pluck('id', 'name');
        $accounts = Account::all()->keyBy('username');
        $manager = $accounts->get('manager');

        foreach (self::TASKS as $definition) {
            if (Task::withTrashed()->where('title', $definition['title'])->exists()) {
                continue;
            }

            $divisionCode = $definition['division'];
            $creator = $divisionCode === 'MKT' && $manager !== null
                ? $manager
                : $accounts[DemoAccountSeeder::username('spv', $divisionCode)];
            $pic = $definition['pic'] ? $accounts[DemoAccountSeeder::username($definition['pic'], $divisionCode)] : null;
            $category = $categories[$definition['category']];

            DB::transaction(fn () => $this->createTask($definition, $divisions[$divisionCode], $category, $platforms[$definition['platform']], $creator, $pic));
        }
    }

    /**
     * Create one demo task in a state its workflow could have reached.
     *
     * @param  array{division: string, title: string, category: string, platform: string, priority: string, status: string, start: int|null, deadline: int|null, pic: string|null}  $definition
     */
    protected function createTask(array $definition, int $divisionId, TaskCategory $category, int $platformId, Account $creator, ?Account $pic): void
    {
        $status = $definition['status'];
        $deadline = self::day($definition['deadline']);

        $task = Task::create([
            'title' => $definition['title'],
            'description' => "Contoh tugas divisi untuk platform {$definition['platform']}.",
            'division_id' => $divisionId,
            'category_id' => $category->id,
            'platform_id' => $platformId,
            'creator_id' => $creator->id,
            'priority' => $definition['priority'],
            'status' => $status,
            'start_date' => self::day($definition['start']),
            'deadline' => $deadline,
            'requires_review' => $category->requires_review,
            'evidence_required' => $category->evidence_required,
            'completed_at' => $status === Task::STATUS_DONE ? $deadline->copy()->subDay()->setTime(16, 0) : null,
        ]);

        if ($pic !== null) {
            $task->assignments()->create([
                'account_id' => $pic->id,
                'assigned_by' => $creator->id,
                'assigned_at' => now(),
            ]);
        }

        $task->recordActivity($creator, TaskActivity::ACTION_CREATED, [
            'title' => ['from' => null, 'to' => $task->title],
            'status' => ['from' => null, 'to' => $status],
            'pics' => ['from' => [], 'to' => $pic ? [['id' => $pic->id, 'fullname' => $pic->fullname]] : []],
        ]);

        if ($pic === null || ! in_array($status, Task::REVIEW_STAGE_STATUSES, true)) {
            return;
        }

        $evidence = $task->evidences()->create([
            'submitter_id' => $pic->id,
            'type' => TaskEvidence::TYPE_LINK,
            'title' => 'Hasil pekerjaan',
            'url' => 'https://example.com/evidence/'.Str::slug($task->title),
        ]);

        $task->recordActivity($pic, TaskActivity::ACTION_EVIDENCE_ADDED, [
            'evidence' => ['id' => $evidence->id, 'type' => $evidence->type, 'name' => $evidence->displayName()],
        ]);

        if ($status === Task::STATUS_DONE) {
            $task->reviews()->create([
                'reviewer_id' => $creator->id,
                'decision' => TaskReview::DECISION_APPROVED,
                'note' => 'Sudah sesuai.',
            ]);

            $task->recordActivity($creator, TaskActivity::ACTION_APPROVED, [
                'status' => ['from' => Task::STATUS_ON_REVIEW, 'to' => Task::STATUS_DONE],
                'note' => 'Sudah sesuai.',
            ]);
        }
    }

    /**
     * Resolve a day offset from today, or null when the task has no such date.
     */
    protected static function day(?int $offset): ?Carbon
    {
        return $offset === null ? null : today()->addDays($offset);
    }
}
