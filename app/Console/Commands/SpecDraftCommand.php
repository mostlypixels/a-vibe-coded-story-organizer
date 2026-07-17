<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

/**
 * Scaffolds a stage-1 draft spec at `.specs/draft/<name>/spec.md`.
 *
 * The `.specs/` lifecycle (draft → expanded → planned → shipped, see
 * .specs/README.md) requires the folder location and the `status:` frontmatter
 * to agree, and feature names to be unique across the whole tree —
 * tests/Unit/SpecsStatusConsistencyTest fails the build otherwise. This
 * command encodes those rules once so a hand-made draft can't get them wrong.
 *
 * Called by the `draft-spec` skill (.claude/skills/draft-spec/SKILL.md), which
 * then replaces the placeholder body with the real spec content. A human can
 * also run it bare: PromptsForMissingInput asks for the name, and handle()
 * prompts for the description when the session is interactive.
 */
class SpecDraftCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'spec:draft
        {name? : Kebab-case feature name, e.g. plotline-merge}
        {--description= : Short description used as the spec body}';

    protected $description = 'Scaffold a new stage-1 draft spec under .specs/draft/<name>/';

    /**
     * Body written when no --description is given: the draft-spec skill's own
     * guidance for what a draft body should contain, so the author (human or
     * skill) knows exactly what to replace the placeholder with.
     */
    private const PLACEHOLDER_BODY = '<A few short paragraphs: the problem, the goals / non-goals, and a rough approach. '
        .'Concrete but not exhaustive — the detailed design is generated later by /mp-spec-expander. '
        .'Reference existing files and conventions rather than inventing new ones.>';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        // Kebab-case keeps folder names glob- and URL-safe, and matches every
        // existing feature under .specs/ (e.g. `plotline-merge`).
        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name)) {
            $this->error("'$name' is not a valid spec name: use kebab-case (lowercase letters, digits and single hyphens, e.g. 'plotline-merge').");

            return self::FAILURE;
        }

        // Feature names must be unique across the WHOLE tree, not just under
        // draft/ — a duplicate anywhere fails SpecsStatusConsistencyTest. The
        // two globs are the canonical lookup from .specs/README.md: drafts sit
        // at draft/<name>/, later stages at <status>/<YYYY-MM>/<name>/.
        $specsRoot = config('specs.path');
        $existing = array_merge(
            File::isDirectory("$specsRoot/draft/$name") ? ["$specsRoot/draft/$name"] : [],
            File::glob("$specsRoot/*/*/$name", GLOB_ONLYDIR)
        );

        if ($existing !== []) {
            $this->error(
                "A feature named '$name' already exists at ".implode(', ', $existing).'. '
                .'Feature names are unique across the whole .specs/ tree — pick a distinct name '
                .'(see .specs/README.md → Name-collision handling).'
            );

            return self::FAILURE;
        }

        $description = $this->option('description');

        // Agents pass --description and never get prompted; a human in an
        // interactive terminal is offered the prompt but may leave it empty,
        // in which case the placeholder tells them what to write.
        if ($description === null && $this->input->isInteractive()) {
            $description = text(
                label: 'Short description (leave empty for a placeholder)',
                hint: 'One or two sentences: the problem and the rough approach.'
            );
        }

        $body = filled($description) ? $description : self::PLACEHOLDER_BODY;
        $title = Str::headline($name);

        File::ensureDirectoryExists("$specsRoot/draft/$name");
        File::put(
            "$specsRoot/draft/$name/spec.md",
            "---\nstatus: draft\n---\n\n# $title\n\n$body\n"
        );

        $this->info("Created .specs/draft/$name/spec.md");
        $this->line("Next step: /mp-spec-expander $name");

        return self::SUCCESS;
    }
}
