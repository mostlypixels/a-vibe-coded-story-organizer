<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodexAlias extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'codex_entry_id',
        'alias',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CodexEntry::class, 'codex_entry_id');
    }
}
