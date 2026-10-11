<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenes', function (Blueprint $table) {
            // The viewpoint is optional; deleting the character clears it.
            $table->foreignId('viewpoint_character_id')->nullable()->after('event_id')
                ->constrained('codex_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scenes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('viewpoint_character_id');
        });
    }
};
