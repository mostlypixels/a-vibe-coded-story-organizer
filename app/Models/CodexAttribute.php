<?php

namespace App\Models;

use App\Enums\CodexEntryType;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodexAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'applies_to',
        'position',
    ];

    protected $casts = [
        // A collection of CodexEntryType enums (stored as a JSON array).
        'applies_to' => AsEnumCollection::class.':'.CodexEntryType::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(CodexAttributeValue::class);
    }

    /**
     * Whether this attribute should appear on sheets of the given entry type.
     */
    public function appliesTo(CodexEntryType $type): bool
    {
        return $this->applies_to->contains($type);
    }

    protected static function booted(): void
    {
        static::creating(function (CodexAttribute $attribute) {
            // Display order on the sheet, scoped to the project.
            if (is_null($attribute->position)) {
                $attribute->position = static::where('project_id', $attribute->project_id)->max('position') + 1;
            }
        });
    }
}
