<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'name',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsToMany<CodexEntry, $this> */
    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(CodexEntry::class);
    }
}
