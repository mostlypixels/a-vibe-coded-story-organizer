<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Support\FieldHash;
use App\Support\FindReplaceMatch;
use App\Support\FindReplacePreview;
use App\Support\FindReplaceQuery;
use App\Support\FindReplaceResult;
use App\Support\FindReplaceSceneMatches;
use App\Support\SearchScope;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Finds the matches of a find text in the scenes of one project, and replaces the chosen ones.
 *
 * The scenes come from the same query as the search page, in manuscript order.
 * SQL only skips scenes that cannot match; {@see SceneTextMatcher} decides every match.
 */
class SceneFindReplace
{
    /** The preview stops here. The author must narrow the scope to see the rest. */
    public const MAX_MATCHES = 2000;

    /** Characters of context on each side of a match. */
    private const CONTEXT_LENGTH = 60;

    /** The widest UTF-8 character, in bytes. A byte cut that wide always holds the characters. */
    private const MAX_CHARACTER_BYTES = 4;

    private const ELLIPSIS = "\u{2026}";

    public function __construct(
        private readonly ProjectSearch $search,
        private readonly SceneTextMatcher $matcher,
        private readonly RevisionRecorder $recorder,
        private readonly SceneReferenceMatcher $references,
    ) {}

    public function preview(Project $project, FindReplaceQuery $query, SearchScope $scope): FindReplacePreview
    {
        if ($query->isBlank()) {
            return new FindReplacePreview([]);
        }

        $books = $project->books()->get()
            ->each(fn (Book $book) => $book->setRelation('project', $project))
            ->keyBy('id');

        $scenes = $this->search->sceneQuery($project, $scope, [$query->find])
            ->with('chapter.act')
            ->get();

        $groups = [];
        $total = 0;

        foreach ($scenes as $scene) {
            $contents = (string) $scene->contents;
            $found = $this->matcher->matches($contents, $query->find, $query->matchCase, $query->wholeWord);

            if ($found === []) {
                continue;
            }

            $room = self::MAX_MATCHES - $total;

            // A scene with a match and no room left proves that the list is cut.
            if ($room <= 0) {
                return new FindReplacePreview($groups, capped: true);
            }

            $capped = count($found) > $room;

            $groups[] = new FindReplaceSceneMatches(
                scene: $scene,
                book: $books->get($scene->getAttribute('book_id')),
                hash: FieldHash::of($contents),
                matches: $this->withContext($contents, array_slice($found, 0, $room)),
            );

            if ($capped) {
                return new FindReplacePreview($groups, capped: true);
            }

            $total += count($found);
        }

        return new FindReplacePreview($groups);
    }

    /**
     * Replaces the chosen matches. Each scene saves in its own transaction, with one manual
     * revision, so an author undoes one scene at a time.
     *
     * A scene whose text changed after the preview is skipped, because its indexes may
     * point at other text. The other scenes still apply.
     *
     * > [!WARNING]
     * > The scene IDs come from the client. Only scenes of the project load; the rest is ignored.
     *
     * @param  array<int, array{hash: string, indexes: list<int>}>  $selection  keyed by scene ID
     */
    public function apply(Project $project, FindReplaceQuery $query, array $selection, User $user): FindReplaceResult
    {
        if ($query->isBlank() || $selection === []) {
            return new FindReplaceResult;
        }

        $scenes = $project->sceneQuery()->whereIn('scenes.id', array_keys($selection))->get()->keyBy('id');

        $replaced = 0;
        $changedScenes = 0;
        $skipped = [];

        foreach ($selection as $sceneId => $chosen) {
            $scene = $scenes->get($sceneId);

            if ($scene === null || $chosen['indexes'] === []) {
                continue;
            }

            try {
                $count = $this->replaceInScene($scene, $query, $chosen['hash'], $chosen['indexes'], $user);
            } catch (ValidationException) {
                $skipped[] = ['id' => $scene->id, 'name' => $scene->name];

                continue;
            }

            if ($count > 0) {
                $replaced += $count;
                $changedScenes++;
            }
        }

        return new FindReplaceResult($replaced, $changedScenes, $skipped);
    }

    /**
     * @param  list<int>  $indexes
     * @return int the matches that changed; no revision when it is 0
     *
     * @throws ValidationException When the contents no longer have the hash from the preview.
     */
    private function replaceInScene(Scene $scene, FindReplaceQuery $query, string $hash, array $indexes, User $user): int
    {
        $label = __('Replace ":find" with ":replace"', [
            'find' => Str::limit($this->flatten($query->find), 60),
            'replace' => Str::limit($this->flatten($query->replace), 60),
        ]);

        // Only the key of `$data` matters: it names the field to snapshot. The closure sets the value.
        return $this->recorder->saveWithManualCheckpoint($scene, ['contents' => ''], ['contents' => $hash], $user, function () use ($scene, $query, $indexes) {
            $contents = (string) $scene->contents;
            $found = $this->matcher->matches($contents, $query->find, $query->matchCase, $query->wholeWord);
            $count = 0;

            // From the end to the start, so the earlier offsets stay valid.
            foreach (array_reverse($indexes) as $index) {
                if (! isset($found[$index])) {
                    continue;
                }

                ['offset' => $offset, 'length' => $length] = $found[$index];

                // With match case off, `lane` can replace `Lane`. Only an equal text is a no-op.
                if (substr($contents, $offset, $length) === $query->replace) {
                    continue;
                }

                $contents = substr_replace($contents, $query->replace, $offset, $length);
                $count++;
            }

            if ($count > 0) {
                $scene->contents = $contents;
                $scene->save();
                $this->references->syncScene($scene);
            }

            return $count;
        }, $label);
    }

    /**
     * @param  list<array{offset: int, length: int}>  $found
     * @return list<FindReplaceMatch>
     */
    private function withContext(string $contents, array $found): array
    {
        $matches = [];

        foreach ($found as $index => ['offset' => $offset, 'length' => $length]) {
            $matches[] = new FindReplaceMatch(
                index: $index,
                offset: $offset,
                before: $this->contextBefore($contents, $offset),
                found: substr($contents, $offset, $length),
                after: $this->contextAfter($contents, $offset + $length),
            );
        }

        return $matches;
    }

    private function contextBefore(string $contents, int $offset): string
    {
        $start = max(0, $offset - self::CONTEXT_LENGTH * self::MAX_CHARACTER_BYTES);

        // Move forward to a character edge. mb_strcut() moves back, and then ends before the match.
        while ($start < $offset && (ord($contents[$start]) & 0xC0) === 0x80) {
            $start++;
        }

        $text = $this->flatten(substr($contents, $start, $offset - $start));
        $cut = $start > 0 || mb_strlen($text) > self::CONTEXT_LENGTH;

        return ($cut ? self::ELLIPSIS : '').mb_substr($text, -self::CONTEXT_LENGTH);
    }

    private function contextAfter(string $contents, int $end): string
    {
        $window = self::CONTEXT_LENGTH * self::MAX_CHARACTER_BYTES;
        $text = $this->flatten(mb_strcut($contents, $end, $window));
        $cut = $end + $window < strlen($contents) || mb_strlen($text) > self::CONTEXT_LENGTH;

        return mb_substr($text, 0, self::CONTEXT_LENGTH).($cut ? self::ELLIPSIS : '');
    }

    /** Line breaks become spaces, so each match stays on one line. */
    private function flatten(string $text): string
    {
        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }
}
