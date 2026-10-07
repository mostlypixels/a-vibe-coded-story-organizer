<?php

namespace App\Models\Contracts;

use App\Models\Concerns\HasRevisions;
use App\Models\Project;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A model with revision history. The {@see HasRevisions} trait supplies the methods.
 *
 * Revision code types its entity as `Model&Revisionable`, so static analysis can
 * check the calls that only these models support.
 */
interface Revisionable
{
    /** The Project that owns the revisions, and the authorization boundary for them. */
    public function revisionProject(): Project;

    /** @return MorphMany<Revision, Model> */
    public function revisions(): MorphMany;

    public function revisionDisplayName(): string;

    public static function revisionDisplayColumn(): string;
}
