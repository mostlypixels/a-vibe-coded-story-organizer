<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // `running_since` is the run claim: one run per import. `queued_at` replaces
    // the `queued` flag, because a time lets the page see a lost job.
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('queued');
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->timestamp('running_since')->nullable()->after('id_maps');
            $table->timestamp('queued_at')->nullable()->after('running_since');
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn(['running_since', 'queued_at']);
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->boolean('queued')->default(false)->after('id_maps');
        });
    }
};
