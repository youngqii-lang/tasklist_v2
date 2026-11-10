<?php

namespace Database\Seeders;

use App\Models\TaskCategory;
use Illuminate\Database\Seeder;

class TaskCategorySeeder extends Seeder
{
    /**
     * The task categories with whether they require a review and an evidence.
     *
     * @var array<string, array{0: bool, 1: bool}>
     */
    public const array CATEGORIES = [
        'Konten' => [true, true],
        'Desain' => [true, true],
        'Development' => [true, true],
        'Campaign' => [true, true],
        'Riset' => [false, true],
        'Administrasi' => [false, false],
        'Operasional' => [false, false],
    ];

    /**
     * Seed the task categories and their review and evidence requirements.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $name => [$requiresReview, $evidenceRequired]) {
            TaskCategory::updateOrCreate(['name' => $name], [
                'requires_review' => $requiresReview,
                'evidence_required' => $evidenceRequired,
            ]);
        }
    }
}
