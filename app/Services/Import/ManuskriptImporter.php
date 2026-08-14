<?php

namespace App\Services\Import;

use App\Enums\CodexEntryType;
use App\Exceptions\ImportValidationException;
use App\Exceptions\ManuskriptImportException;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Concerns\SanitizesRichHtml;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns an exploded Manuskript project directory into one new {@see Project}.
 *
 * One method per graph level, following {@see ProjectGraphImporter}'s
 * precedent, but with a single {@see DB::transaction()} around the whole
 * import rather than one per phase: unlike a resumable archive import, a
 * local directory can simply be re-imported on failure, so there is nothing
 * to check-point.
 *
 * This class reaches the project, its single act, chapters, scenes and
 * characters.
 */
class ManuskriptImporter
{
    public function __construct(private ContentSanitizer $contentSanitizer) {}

    public function import(string $path, User $user, ?string $name, ?string $actName): ManuskriptImportResult
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        // Gate: fail before writing anything for a directory that is not an
        // exploded Manuskript project.
        if (! is_file("{$path}/MANUSKRIPT") || ! is_dir("{$path}/outline")) {
            throw ManuskriptImportException::notAManuskriptProject($path);
        }

        $infos = new ManuskriptFile("{$path}/infos.txt");

        $projectName = $name ?? $infos->headerValue('title')
            ?? throw ManuskriptImportException::missingProjectName();

        return DB::transaction(function () use ($user, $projectName, $infos, $actName, $path): ManuskriptImportResult {
            $project = $user->projects()->create([
                'name' => $projectName,
                'author' => $infos->headerValue('author'),
            ]);

            // Project::booted() already creates the main plotline and the
            // Start/End fixed events on this create() — never duplicated here.
            $act = $project->acts()->create([
                'name' => $actName ?? 'Act 1',
            ]);

            $result = new ManuskriptImportResult($project, $act);

            $this->importChapters($act, "{$path}/outline", $result);
            $this->importCharacters($project, "{$path}/characters", $result);

            return $result;
        });
    }

    /**
     * Chapters are the directories directly under `outline/`, sorted by their
     * numeric `NN-` prefix. Any file loose in `outline/` itself is not part
     * of the chapter tree Manuskript renders, so it is skipped and counted
     * rather than guessed at.
     */
    private function importChapters(Act $act, string $outlinePath, ManuskriptImportResult $result): void
    {
        $chapterDirs = [];

        foreach ($this->listDirectory($outlinePath) as $entry) {
            if (is_dir("{$outlinePath}/{$entry}")) {
                $chapterDirs[] = $entry;
            } else {
                $result->skipped[] = "Loose file under outline/: {$entry}";
            }
        }

        usort($chapterDirs, fn (string $a, string $b): int => $this->numericPrefix($a) <=> $this->numericPrefix($b));

        foreach ($chapterDirs as $dirName) {
            $this->importChapter($act, "{$outlinePath}/{$dirName}", $dirName, $result);
        }
    }

    private function importChapter(Act $act, string $chapterPath, string $dirName, ManuskriptImportResult $result): void
    {
        $folderPath = "{$chapterPath}/folder.txt";
        $title = is_file($folderPath) ? (new ManuskriptFile($folderPath))->headerValue('title') : null;
        $name = $this->firstNonEmpty($title, $this->fallbackName($dirName));

        $chapter = $act->chapters()->create(['name' => $name]);
        $result->chapterCount++;

        $sceneFiles = [];

        foreach ($this->listDirectory($chapterPath) as $entry) {
            if ($entry === 'folder.txt') {
                continue;
            }

            if (is_file("{$chapterPath}/{$entry}") && str_ends_with($entry, '.md')) {
                $sceneFiles[] = $entry;
            } else {
                $result->skipped[] = "Non-scene entry in chapter \"{$name}\": {$entry}";
            }
        }

        usort($sceneFiles, fn (string $a, string $b): int => $this->numericPrefix($a) <=> $this->numericPrefix($b));

        foreach ($sceneFiles as $sceneFile) {
            $this->importScene($chapter, "{$chapterPath}/{$sceneFile}", $sceneFile, $result);
        }
    }

    private function importScene(Chapter $chapter, string $filePath, string $fileName, ManuskriptImportResult $result): void
    {
        $file = new ManuskriptFile($filePath);
        $name = $this->firstNonEmpty($file->headerValue('title'), $this->fallbackName($fileName));

        $chapter->scenes()->create([
            'name' => $name,
            'contents' => $this->sanitizeMarkdown($file->body(), $result),
        ]);

        $result->sceneCount++;
    }

    /**
     * `characters/*.txt`, sorted numerically, one {@see CodexEntry} of type
     * `character` per file. A file with no `Name` field is skipped and
     * counted — it cannot make an entry with no name.
     */
    private function importCharacters(Project $project, string $charactersPath, ManuskriptImportResult $result): void
    {
        if (! is_dir($charactersPath)) {
            return;
        }

        $characterFiles = [];

        foreach ($this->listDirectory($charactersPath) as $entry) {
            if (is_file("{$charactersPath}/{$entry}") && str_ends_with($entry, '.txt')) {
                $characterFiles[] = $entry;
            } else {
                $result->skipped[] = "Non-character entry in characters/: {$entry}";
            }
        }

        usort($characterFiles, fn (string $a, string $b): int => $this->numericPrefix($a) <=> $this->numericPrefix($b));

        foreach ($characterFiles as $fileName) {
            $this->importCharacter($project, "{$charactersPath}/{$fileName}", $fileName, $result);
        }
    }

    private function importCharacter(Project $project, string $filePath, string $fileName, ManuskriptImportResult $result): void
    {
        $file = new ManuskriptFile($filePath);
        $name = trim((string) $file->headerValue('name'));

        if ($name === '') {
            $result->skipped[] = "Character file with no Name: {$fileName}";

            return;
        }

        $project->codexEntries()->create([
            'type' => CodexEntryType::Character,
            'name' => $name,
            'description' => $this->buildCharacterDescription($file),
        ]);

        $result->characterCount++;
    }

    /**
     * `ID`, `Color`, `Importance`, `POV` are Manuskript bookkeeping and never
     * reach the description; `Name` is the entry name, not a field. Every
     * other field whose trimmed value is neither empty nor `?` (Manuskript's
     * "not filled in" placeholder) becomes `<h3>Key</h3>` followed by one
     * `<p>` per blank-line block, with a single newline inside a block as
     * `<br>`. Values are escaped first so the result survives
     * {@see SanitizesRichHtml} unchanged.
     */
    private function buildCharacterDescription(ManuskriptFile $file): ?string
    {
        $skippedKeys = ['name', 'id', 'color', 'importance', 'pov'];
        $sections = [];

        foreach ($file->header() as $key => $value) {
            if (in_array($key, $skippedKeys, true)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed === '' || $trimmed === '?') {
                continue;
            }

            $blocks = preg_split('/\n\n+/', $trimmed) ?: [$trimmed];
            $paragraphs = array_map(
                fn (string $block): string => '<p>'.str_replace("\n", '<br>', e($block)).'</p>',
                $blocks
            );

            $sections[] = '<h3>'.e(ucfirst($key)).'</h3>'.implode('', $paragraphs);
        }

        return $sections === [] ? null : implode('', $sections);
    }

    /**
     * Run each blank-line-separated block of the body through
     * {@see ContentSanitizer::assertMarkdownAllowed()}. A block that fails —
     * raw HTML outside the rich-text allow-list, the "passthrough hole" — is
     * replaced with a marker line and counted; it never aborts the import,
     * because the source is the writer's own directory and one stray line
     * must not block a migration.
     */
    private function sanitizeMarkdown(string $body, ManuskriptImportResult $result): string
    {
        $blocks = preg_split('/\n\n+/', $body) ?: [$body];

        foreach ($blocks as $index => $block) {
            try {
                $this->contentSanitizer->assertMarkdownAllowed($block);
            } catch (ImportValidationException) {
                $blocks[$index] = '[INVALID CONTENT REMOVED]';
                $result->disallowedContentCount++;
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @return array<int, string> directory entries, `.`/`..` excluded
     */
    private function listDirectory(string $path): array
    {
        $entries = scandir($path);

        if ($entries === false) {
            return [];
        }

        return array_values(array_diff($entries, ['.', '..']));
    }

    private function numericPrefix(string $name): int
    {
        return preg_match('/^(\d+)-/', $name, $matches) === 1 ? (int) $matches[1] : 0;
    }

    /**
     * The filename fallback for a chapter or scene name: the source prefix
     * stripped, the extension stripped, and `_`/`-` turned back into spaces.
     */
    private function fallbackName(string $baseName): string
    {
        $stripped = preg_replace('/\.md$/', '', $baseName);
        $stripped = preg_replace('/^\d+-/', '', (string) $stripped);

        return trim(str_replace(['_', '-'], ' ', (string) $stripped));
    }

    private function firstNonEmpty(?string $value, string $fallback): string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : $fallback;
    }
}
