<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scenes', function (Blueprint $table) {
            // A single revocable public share link per scene. Both columns are
            // nullable because most scenes are never shared; a null token means
            // "not shared". The token is stored raw (high-entropy, single-purpose)
            // so the public route can resolve a scene by token alone; the unique
            // index doubles as that lookup index.
            $table->string('share_token', 64)->nullable()->unique()->after('event_id');
            $table->timestamp('share_expires_at')->nullable()->after('share_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scenes', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn(['share_token', 'share_expires_at']);
        });
    }
};
