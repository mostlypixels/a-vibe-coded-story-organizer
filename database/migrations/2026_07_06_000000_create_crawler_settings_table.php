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
        // Singleton table: the whole application shares exactly one crawler policy
        // (one website -> one robots.txt). Unlike every other domain table it has no
        // project_id / user_id — it is global. Always read it through
        // CrawlerSetting::current(), never create a second row.
        Schema::create('crawler_settings', function (Blueprint $table) {
            $table->id();

            // Hidden mode toggle. Default true = hidden from crawlers (the spec's
            // safe default). This column default is a backstop for direct inserts;
            // the documented source of truth for the lazy-create path is
            // config('crawlers.default_enabled'). Keep the two values equal.
            $table->boolean('enabled')->default(true);

            // User-agent terms that stay allowed while hidden mode is on. Stored as
            // a JSON array of plain strings, edited as a textarea (one term per line).
            $table->json('user_agent_whitelist')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crawler_settings');
    }
};
