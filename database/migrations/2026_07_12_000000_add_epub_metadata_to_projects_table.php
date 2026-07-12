<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the six publication-metadata columns the epub export maps to OPF
     * Dublin Core fields. `language` is NOT NULL with a DB-level default so every
     * existing project gets a valid BCP-47 code with no backfill script; the rest
     * are nullable because they are optional in the OPF and omitted when absent.
     * `cover_image` is a plain nullable path column (storage path on the `public`
     * disk), not a CodexMedia-style tracking row — a single image needs no
     * position/collection bookkeeping.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('language', 10)->default('en')->after('description');
            $table->string('author')->nullable()->after('language');
            $table->string('publisher')->nullable()->after('author');
            $table->text('rights')->nullable()->after('publisher');
            $table->string('isbn', 17)->nullable()->after('rights');
            $table->string('cover_image')->nullable()->after('isbn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'language',
                'author',
                'publisher',
                'rights',
                'isbn',
                'cover_image',
            ]);
        });
    }
};
