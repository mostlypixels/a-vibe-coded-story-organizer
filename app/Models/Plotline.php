<?php

namespace App\Models;

use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\SanitizesRichHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plotline extends Model
{
    use HasFactory;
    use HasRevisions;
    use SanitizesRichHtml;

    protected $fillable = [
        'name',
        'description',
        'is_main',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The project that owns this plotline's revisions (see HasRevisions).
     */
    public function revisionProject(): Project
    {
        return $this->project;
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }
}
