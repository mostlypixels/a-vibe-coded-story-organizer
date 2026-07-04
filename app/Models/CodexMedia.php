<?php

namespace App\Models;

use App\Enums\CodexMediaCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CodexMedia extends Model
{
    use HasFactory;

    protected $table = 'codex_media';

    protected $fillable = [
        'codex_entry_id',
        'collection',
        'path',
        'original_name',
        'mime_type',
        'size',
        'position',
    ];

    protected $casts = [
        'collection' => CodexMediaCollection::class,
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CodexEntry::class, 'codex_entry_id');
    }

    /**
     * Public URL of the stored file (needs `php artisan storage:link` locally).
     * Kept on the model so views/index thumbnails don't build the path by hand.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    protected static function booted(): void
    {
        static::creating(function (CodexMedia $media) {
            // Position is scoped to (entry, collection) so reference images and
            // reference files each get their own independent sequence.
            if (is_null($media->position)) {
                $media->position = static::where('codex_entry_id', $media->codex_entry_id)
                    ->where('collection', $media->collection)
                    ->max('position') + 1;
            }
        });
    }
}
