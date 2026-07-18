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

    /**
     * Every valid `section_order` component key, in default reading order.
     * The single source of truth for the sortable list's membership — the
     * config form's validation rule, the model's reorder helpers, and the
     * lazy default in Project::publicationSettingOrDefault() all read this
     * instead of repeating the literal list (CLAUDE.md: no magic strings).
     *
     * @var array<int, string>
     */
    public const SECTION_KEYS = [
        'title',
        'dedication',
        'acknowledgements',
        'preface',
        'toc',
        'body',
        'postface',
        'appendix',
    ];

    /**
     * The section key that always renders first and can never be reordered.
     */
    public const PINNED_FIRST_SECTION = 'title';

    protected $fillable = [
        'project_id',
        'include_project_cover',
        'include_chapter_covers',
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
        'section_order',
        'include_codex_appendix',
        'appendix_entry_types',
        'appendix_include_images',
    ];

    protected $casts = [
        'include_project_cover' => 'boolean',
        'include_chapter_covers' => 'boolean',
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
        'section_order' => 'array',
        'include_codex_appendix' => 'boolean',
        'appendix_entry_types' => 'array',
        'appendix_include_images' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Move a front/back-matter section one step earlier in `section_order`.
     * A no-op for the pinned `title` section, an unknown key, or a section
     * already at the front of the reorderable range (index 1).
     */
    public function moveSectionUp(string $section): void
    {
        $this->swapSectionPosition($section, -1);
    }

    /**
     * Move a front/back-matter section one step later in `section_order`.
     */
    public function moveSectionDown(string $section): void
    {
        $this->swapSectionPosition($section, 1);
    }

    /**
     * Swap `$section` with its neighbour `$offset` positions away in the
     * ordered list, unless that would move it past index 0 — the pinned
     * `title` slot — or off either end of the list.
     */
    private function swapSectionPosition(string $section, int $offset): void
    {
        if ($section === self::PINNED_FIRST_SECTION) {
            return;
        }

        $order = $this->section_order ?? self::SECTION_KEYS;
        $index = array_search($section, $order, true);

        if ($index === false) {
            return;
        }

        $swapWith = $index + $offset;

        if ($swapWith <= 0 || $swapWith >= count($order)) {
            return;
        }

        [$order[$index], $order[$swapWith]] = [$order[$swapWith], $order[$index]];

        $this->section_order = $order;
    }
}
