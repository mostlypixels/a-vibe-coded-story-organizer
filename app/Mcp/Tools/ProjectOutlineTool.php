<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Support\StoryNumbering;
use App\Support\WordCountFormat;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('project-outline')]
#[Description('Print the story tree of a project: book, act, chapter, scene, with IDs, display numbers, status and word counts. Pass a book ID to print only that book.')]
#[IsReadOnly]
class ProjectOutlineTool extends Tool
{
    use ReturnsToolErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->integer()->description('Project ID.')->required(),
            'book' => $schema->integer()->description('Book ID. Leave out for every book.'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->guard(function () use ($request): Response {
            $data = $request->validate([
                'project' => ['required', 'integer'],
                'book' => ['nullable', 'integer'],
            ]);

            $project = Project::query()->findOrFail($data['project']);
            $books = $project->books()->orderBy('position')->orderBy('id');

            if (isset($data['book'])) {
                $project->books()->findOrFail($data['book']);
                $books->whereKey($data['book']);
            }

            // Scene contents stay in the database: an outline never reads them.
            $books = $books->with(['acts' => fn (Relation $query) => $query->orderBy('position')->orderBy('id')])
                ->with(['acts.chapters' => fn (Relation $query) => $query->orderBy('position')->orderBy('id')])
                ->with(['acts.chapters.scenes' => fn (Relation $query) => $query
                    ->select(['id', 'chapter_id', 'name', 'status', 'position', 'word_count'])
                    ->orderBy('position')->orderBy('id')])
                ->get();

            $lines = ["# {$project->name} (#{$project->id})"];

            foreach ($books as $book) {
                $book->setRelation('project', $project);
                array_push($lines, ...$this->bookLines($book));
            }

            if ($books->isEmpty()) {
                $lines[] = '';
                $lines[] = 'No books.';
            }

            return Response::text(implode("\n", $lines));
        });
    }

    /** @return list<string> */
    private function bookLines(Book $book): array
    {
        $numbering = StoryNumbering::fromActs($book->acts);
        $words = $book->acts->sum(fn (Act $act) => $act->chapters->sum(fn (Chapter $chapter) => $chapter->scenes->sum('word_count')));

        $lines = ['', "## {$book->displayName()} (book #{$book->id}, ".WordCountFormat::text((int) $words).')'];

        if ($book->acts->isEmpty()) {
            $lines[] = '';
            $lines[] = 'No acts.';
        }

        foreach ($book->acts as $act) {
            array_push($lines, '', "### {$numbering->actLabel($act)} (#{$act->id})");

            foreach ($act->chapters as $chapter) {
                array_push($lines, '', "#### {$numbering->chapterLabel($chapter)} (#{$chapter->id})", '');

                if ($chapter->scenes->isEmpty()) {
                    $lines[] = 'No scenes.';
                }

                foreach ($chapter->scenes as $scene) {
                    $lines[] = $this->sceneLine($numbering, $scene);
                }
            }
        }

        return $lines;
    }

    private function sceneLine(StoryNumbering $numbering, Scene $scene): string
    {
        return "- #{$scene->id} {$numbering->sceneLabel($scene)} [{$scene->status->label()}, ".WordCountFormat::text($scene->word_count).']';
    }
}
