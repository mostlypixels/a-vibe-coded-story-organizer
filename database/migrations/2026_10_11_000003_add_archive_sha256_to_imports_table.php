<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The hash finds a repeat upload of the same archive. Null for rows made before it.
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->char('archive_sha256', 64)->nullable()->after('archive_original_name');
            $table->index(['user_id', 'archive_sha256']);
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'archive_sha256']);
            $table->dropColumn('archive_sha256');
        });
    }
};
