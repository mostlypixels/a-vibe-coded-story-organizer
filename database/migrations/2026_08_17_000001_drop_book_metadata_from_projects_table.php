<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the publication metadata that now belongs to a `Book`.
 *
 * Every reader moved with the feature: the EPUB and static-site exporters, the
 * import graph, the edit form and the Story overview all read the book's copies.
 * The columns are the last trace of the project-wide book.
 *
 * Destructive with no backfill, the pre-V1 rule: the only data is the seed, so
 * reseed with `php artisan migrate:fresh --seed`. `down()` restores the schema,
 * never the values.
 */
return new class extends Migration
{
    /**
     * The moved columns, in their original `projects` order.
     *
     * @var array<int, string>
     */
    private const MOVED_COLUMNS = [
        'language',
        'author',
        'publisher',
        'rights',
        'isbn',
        'dedication',
        'acknowledgements',
        'preface',
        'postface',
        'overview_render_mode',
    ];

    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(self::MOVED_COLUMNS);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('language', 10)->default('en');
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->longText('rights')->nullable();
            $table->string('isbn', 17)->nullable();
            $table->longText('dedication')->nullable();
            $table->longText('acknowledgements')->nullable();
            $table->longText('preface')->nullable();
            $table->longText('postface')->nullable();
            $table->string('overview_render_mode')->default('chapter');
        });
    }
};
