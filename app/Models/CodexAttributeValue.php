<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodexAttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'codex_entry_id',
        'codex_attribute_id',
        'start_event_id',
        'value',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CodexEntry::class, 'codex_entry_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CodexAttribute::class, 'codex_attribute_id');
    }

    /**
     * The event this value takes effect from. Canonical ordering by
     * (event_datetime, events.id) lives in the AttributeTimeline service (task 02).
     */
    public function startEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'start_event_id');
    }
}
