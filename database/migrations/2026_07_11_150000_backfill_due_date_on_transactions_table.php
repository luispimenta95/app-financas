<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE transactions SET due_date = date WHERE due_date IS NULL AND date IS NOT NULL');
    }

    public function down(): void
    {
        // Intentionally left blank: backfilled due dates should be kept.
    }
};
