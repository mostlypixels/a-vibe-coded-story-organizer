<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Scene;
use Illuminate\Support\Facades\Log;
use Normalizer;

/**
 * Recomputes the derived `scene_codex_entry` cache: which codex entries a scene's
 * contents reference, by whole-word, case-SENSITIVE, Unicode-aware matching of each
 * entry's name and eligible aliases against `Scene::contents`.
 *
 * This is the single home of the matching rule (see documentation/architecture.md →
 * "Scene references"). It is a plain service — not a model booted() hook — because it is
 * application workflow: it compares before/after alias state at the call sites and, for a
 * project-wide rescan, touches many scenes at once. A hook cannot express that, and it
 * would silently no-op under the seeder's WithoutModelEvents (same reasoning as
 * AttributeTimeline / CodexMediaService).
 *
 * Every call is a full sync() for its scope — never an incremental attach/detach — so the
 * pivot can never drift from "what should match" (the invariant in data-model.md).
 */
class SceneReferenceMatcher
{
    /**
     * Aliases shorter than this (in Unicode characters) never drive a match — a confirmed
     * decision to cut false positives from short aliases colliding with ordinary words.
     * An entry's `name` has no such floor: it is always eligible regardless of length.
     */
    private const MINIMUM_ALIAS_LENGTH = 3;

    /**
     * Recompute one scene's full reference set against every codex entry in its project.
     * Full resync: replaces the scene's entire pivot set, dropping any stale rows.
     */
    public function syncScene(Scene $scene): void
    {
        $project = $scene->chapter->act->project;

        $candidates = $this->buildCandidates($project);

        $scene->codexReferences()->sync($this->matchScene($scene, $candidates));
    }

    /**
     * Recompute every scene in the project against the current name/alias set — the path
     * taken when a codex entry's aliases or name change. The candidate map/regex is built
     * once and reused across all scenes (not rebuilt per scene) to avoid an N+1.
     */
    public function syncProject(Project $project): void
    {
        $candidates = $this->buildCandidates($project);

        // Scenes hang off the project via chapter → act; reuse the same walk the
        // SceneController index uses rather than adding a hasManyThrough.
        $scenes = Scene::query()
            ->whereHas('chapter.act', fn ($query) => $query->where('project_id', $project->id))
            ->get();

        foreach ($scenes as $scene) {
            $scene->codexReferences()->sync($this->matchScene($scene, $candidates));
        }
    }

    /**
     * Build the per-project matching data once: a combined whole-word regex over every
     * eligible term, plus a map from the exact-case (NFC-normalized) term back to the set
     * of codex_entry ids that declared it.
     *
     * The map value is a set of ids, never a single id: two entries in the same project may
     * legitimately share an identical name/alias, and per the "both link independently"
     * decision each must resolve when that term matches.
     *
     * Returns ['pattern' => ?string, 'map' => array<string, array<int, int>>]. `pattern`
     * is null when the project has no eligible terms (nothing can match → sync to empty).
     *
     * @return array{pattern: ?string, map: array<string, array<int, int>>}
     */
    private function buildCandidates(Project $project): array
    {
        // One query for the entries, one for all their aliases (no N+1 in the loop).
        $entries = $project->codexEntries()->with('aliases')->get();

        /** @var array<string, array<int, int>> $map */
        $map = [];

        foreach ($entries as $entry) {
            // Name is always eligible, no length floor.
            $this->addTerm($map, $entry->id, $entry->name, isAlias: false);

            foreach ($entry->aliases as $alias) {
                $this->addTerm($map, $entry->id, $alias->alias, isAlias: true);
            }
        }

        if ($map === []) {
            return ['pattern' => null, 'map' => []];
        }

        // Single combined regex: an alternation of all quoted terms wrapped in one
        // capturing group, bounded by Unicode-aware whole-word lookaround. Order of the
        // alternatives is irrelevant — the boundaries alone stop a short term (e.g. "Mel")
        // matching inside a longer one (e.g. "Melody"), so no length-sorting is needed.
        //
        // Hyphen is part of the word, not a boundary: it is included in the boundary class
        // so "Jean" never matches inside "Jean-Luc". The `u` modifier makes \p{L}/\p{N}
        // Unicode-aware; there is deliberately NO `i` flag — matching is case-sensitive.
        $quotedTerms = array_map(
            fn (string $term): string => preg_quote($term, '/'),
            array_keys($map),
        );

        $pattern = '/(?<![\p{L}\p{N}\-])('.implode('|', $quotedTerms).')(?![\p{L}\p{N}\-])/u';

        return ['pattern' => $pattern, 'map' => $map];
    }

    /**
     * Normalize a term to NFC, apply the alias length floor, and register it in the map
     * under the codex entry that declared it. Empty or un-normalizable terms are skipped.
     *
     * @param  array<string, array<int, int>>  $map
     */
    private function addTerm(array &$map, int $entryId, ?string $term, bool $isAlias): void
    {
        if ($term === null) {
            return;
        }

        $normalized = $this->normalize($term);

        // false = malformed UTF-8 for this term; '' = whitespace-only. Either way it can
        // never be a meaningful whole-word match, so drop it rather than poison the regex.
        if ($normalized === false || $normalized === '') {
            return;
        }

        if ($isAlias && mb_strlen($normalized) < self::MINIMUM_ALIAS_LENGTH) {
            return;
        }

        // Keyed by id so an entry whose name equals one of its own aliases is stored once.
        $map[$normalized][$entryId] = $entryId;
    }

    /**
     * Resolve the set of codex_entry ids a scene's contents reference under the given
     * candidate data. Returns a plain list of ids suitable for sync().
     *
     * Empty/null contents, a project with no eligible terms, or malformed UTF-8 all yield
     * an empty set with no error — a bad scene must never block its own save.
     *
     * @param  array{pattern: ?string, map: array<string, array<int, int>>}  $candidates
     * @return array<int, int>
     */
    private function matchScene(Scene $scene, array $candidates): array
    {
        if ($candidates['pattern'] === null) {
            return [];
        }

        $contents = $scene->contents;

        if ($contents === null || $contents === '') {
            return [];
        }

        $normalized = $this->normalize($contents);

        // Normalizer returns false on malformed UTF-8. Log and treat as "no matches" so the
        // scene ends up unlinked but the caller's save still succeeds.
        if ($normalized === false) {
            $this->logUnmatchable($scene);

            return [];
        }

        // preg_match_all with the `u` modifier returns false (a hard failure, not "zero
        // matches") on invalid UTF-8 that survived normalization — check for it explicitly.
        $result = preg_match_all($candidates['pattern'], $normalized, $matches, PREG_OFFSET_CAPTURE);

        if ($result === false) {
            $this->logUnmatchable($scene);

            return [];
        }

        // Each matched substring is, by case-sensitivity, the term's exact stored (NFC)
        // text — look it up directly in the candidate map. A set keyed by id dedupes an
        // entry that matched via several of its terms.
        $matchedIds = [];

        foreach ($matches[1] as [$matchedText]) {
            foreach ($candidates['map'][$matchedText] ?? [] as $entryId) {
                $matchedIds[$entryId] = $entryId;
            }
        }

        return array_values($matchedIds);
    }

    /**
     * Normalize a value to Unicode NFC so visually-identical accented text from different
     * input sources (macOS/Word exports often emit NFD) compares byte-equal. Returns false
     * on malformed UTF-8.
     */
    private function normalize(string $value): string|false
    {
        return Normalizer::normalize($value, Normalizer::FORM_C);
    }

    /**
     * A scene whose contents could not be matched (malformed UTF-8). The sync degrades to
     * "no references" for this scene; it is never fatal, so we warn rather than throw.
     */
    private function logUnmatchable(Scene $scene): void
    {
        Log::warning('Scene contents were not valid UTF-8; skipped codex reference matching.', [
            'scene_id' => $scene->id,
        ]);
    }
}
