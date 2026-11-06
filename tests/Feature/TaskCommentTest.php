<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskComment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCommentTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Task $task;

    protected Account $pic;

    protected Account $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
        $this->task = Task::factory()->for($this->division)->create(['status' => Task::STATUS_ON_REVIEW]);
        $this->pic = Account::factory()->employee()->for($this->division)->create();
        $this->reviewer = Account::factory()->supervisor()->for($this->division)->create();
        TaskAssignment::factory()->for($this->task)->for($this->pic, 'account')->create();
    }

    public function test_reviewer_and_pic_hold_a_two_way_discussion(): void
    {
        $commentId = $this->actingAs($this->reviewer)
            ->postJson(route('api.tasks.comments.store', $this->task), ['content' => 'Tolong cek ukuran banner.'])
            ->assertCreated()
            ->assertJsonPath('message', 'Komentar berhasil dikirim.')
            ->assertJsonPath('data.author.id', $this->reviewer->id)
            ->assertJsonPath('data.author_role', 'Supervisor')
            ->json('data.id');

        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.comments.store', $this->task), ['content' => 'Sudah saya perbaiki.', 'parent_comment_id' => $commentId])
            ->assertCreated()
            ->assertJsonPath('data.parent_comment_id', $commentId)
            ->assertJsonPath('data.author_role', 'Employee');

        $this->actingAs($this->reviewer)
            ->postJson(route('api.tasks.comments.store', $this->task), ['content' => 'Oke, terima kasih.', 'parent_comment_id' => $commentId])
            ->assertCreated();

        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.comments.index', $this->task))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'Tolong cek ukuran banner.')
            ->assertJsonPath('data.0.replies.*.content', ['Sudah saya perbaiki.', 'Oke, terima kasih.']);
    }

    public function test_reply_to_a_reply_stays_in_the_same_thread(): void
    {
        $root = TaskComment::factory()->for($this->task)->create();
        $reply = TaskComment::factory()->for($this->task)->create(['parent_comment_id' => $root->id]);

        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.comments.store', $this->task), ['content' => 'Balasan lagi', 'parent_comment_id' => $reply->id])
            ->assertCreated()
            ->assertJsonPath('data.parent_comment_id', $root->id);
    }

    public function test_reply_must_belong_to_the_same_task(): void
    {
        $foreignComment = TaskComment::factory()->create();

        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.comments.store', $this->task), ['content' => 'Balasan', 'parent_comment_id' => $foreignComment->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_comment_id' => 'Komentar yang dibalas tidak ditemukan pada tugas ini.']);
    }

    public function test_empty_comment_is_rejected(): void
    {
        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.comments.store', $this->task), ['content' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content' => 'Komentar tidak boleh kosong.']);
    }

    public function test_read_only_users_cannot_comment(): void
    {
        $readOnly = [
            Account::factory()->employee()->for($this->division)->create(),
            Account::factory()->manager()->create(),
        ];

        foreach ($readOnly as $account) {
            $this->actingAs($account)
                ->postJson(route('api.tasks.comments.store', $this->task), ['content' => 'Halo'])
                ->assertForbidden();

            $this->actingAs($account)->getJson(route('api.tasks.comments.index', $this->task))->assertOk();
        }

        $this->assertDatabaseCount('task_comments', 0);
    }

    public function test_comment_does_not_change_the_task(): void
    {
        $this->actingAs($this->reviewer)
            ->postJson(route('api.tasks.comments.store', $this->task), ['content' => 'Catatan diskusi'])
            ->assertCreated();

        $task = $this->task->fresh();
        $this->assertSame(Task::STATUS_ON_REVIEW, $task->status);
        $this->assertSame(1, $task->version);
        $this->assertDatabaseCount('task_reviews', 0);
    }

    public function test_comments_stay_readable_but_closed_after_done(): void
    {
        TaskComment::factory()->for($this->task)->create(['content' => 'Diskusi lama']);
        $this->task->update(['status' => Task::STATUS_DONE]);

        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.comments.store', $this->task), ['content' => 'Tambahan'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Komentar tidak dapat ditambahkan pada tugas yang sudah selesai.');

        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.comments.index', $this->task))
            ->assertJsonPath('data.0.content', 'Diskusi lama');

        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.show', $this->task))
            ->assertJsonPath('data.abilities.comment', false);
    }

    public function test_comments_remain_after_the_pic_changes(): void
    {
        TaskComment::factory()->for($this->task)->for($this->pic, 'author')->create();
        $this->task->update(['status' => Task::STATUS_IN_PROGRESS]);
        $newPic = Account::factory()->employee()->for($this->division)->create();

        $this->actingAs($this->reviewer)
            ->putJson(route('api.tasks.pics.update', $this->task), ['pic_ids' => [$newPic->id], 'version' => 1])
            ->assertOk();

        $this->actingAs($newPic)
            ->getJson(route('api.tasks.comments.index', $this->task))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author.id', $this->pic->id);
    }

    public function test_task_exposes_the_comment_ability(): void
    {
        $this->actingAs($this->pic)->getJson(route('api.tasks.show', $this->task))->assertJsonPath('data.abilities.comment', true);
        $this->actingAs(Account::factory()->freelance()->create())
            ->getJson(route('api.tasks.show', $this->task))
            ->assertJsonPath('data.abilities.comment', false);
    }
}
