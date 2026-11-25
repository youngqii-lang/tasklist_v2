<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Role codes before and after, matching the codes used by SiMarsel.
     *
     * @var array<string, string>
     */
    private const RENAMES = [
        'admin' => 'ADM',
        'manager' => 'MGR',
        'supervisor' => 'SPV',
        'employee' => 'EMP',
        'freelance' => 'FRL',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('roles')->where('code_role', $from)->update(['code_role' => $to]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('roles')->where('code_role', $to)->update(['code_role' => $from]);
        }
    }
};
