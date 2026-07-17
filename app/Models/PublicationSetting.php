<?php

namespace App\Models;

use App\Enums\ChapterTitleFormat;
use App\Enums\DividerType;
use App\Enums\TableOfContentsDepth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Publication settings for an EPUB export — toggles, formatting choices, and appendix options.
 *
 * One row per project; lazy-loaded via Project::publicationSettingOrDefault().
 * No auto-creation; an unsaved default is returned when no row exists.
 * See data-model.md and architecture.md for the full rationale.
 */
class PublicationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'include_project_cover',
        'include_scene_titles',
        'include_act_descriptions',
        'include_chapter_descriptions',
        'include_scene_descriptions',
        'include_dedication',
        'include_acknowledgements',
        'include_preface',
        'include_postface',
        'include_author',
        'include_publisher',
        'include_rights',
        'include_isbn',
        'chapter_title_format',
        'table_of_contents_depth',
        'divider_type',
        'include_codex_appendix',
        'appendix_entry_types',
        'appendix_include_images',
    ];

    protected $casts = [
        'include_project_cover' => 'boolean',
        'include_scene_titles' => 'boolean',
        'include_act_descriptions' => 'boolean',
        'include_chapter_descriptions' => 'boolean',
        'include_scene_descriptions' => 'boolean',
        'include_dedication' => 'boolean',
        'include_acknowledgements' => 'boolean',
        'include_preface' => 'boolean',
        'include_postface' => 'boolean',
        'include_author' => 'boolean',
        'include_publisher' => 'boolean',
        'include_rights' => 'boolean',
        'include_isbn' => 'boolean',
        'chapter_title_format' => ChapterTitleFormat::class,
        'table_of_contents_depth' => TableOfContentsDepth::class,
        'divider_type' => DividerType::class,
        'include_codex_appendix' => 'boolean',
        'appendix_entry_types' => 'array',
        'appendix_include_images' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
