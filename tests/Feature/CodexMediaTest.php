<?php

namespace Tests\Feature;

use App\Enums\CodexMediaCollection;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CodexMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Every media test writes to a faked public disk so nothing touches real storage.
        Storage::fake('public');
    }

    public function test_uploading_a_cover_creates_the_single_cover_row(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melusine',
            'cover' => UploadedFile::fake()->image('cover.jpg'),
        ])->assertRedirect();

        $cover = $entry->cover()->first();
        $this->assertNotNull($cover);
        $this->assertSame(CodexMediaCollection::Cover, $cover->collection);
        $this->assertSame(1, $entry->media()->where('collection', CodexMediaCollection::Cover)->count());
        Storage::disk('public')->assertExists($cover->path);
    }

    public function test_uploading_a_new_cover_replaces_the_row_and_deletes_the_old_file(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melusine',
            'cover' => UploadedFile::fake()->image('first.jpg'),
        ]);

        $oldCover = $entry->cover()->first();
        $oldPath = $oldCover->path;

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melusine',
            'cover' => UploadedFile::fake()->image('second.jpg'),
        ]);

        $newCover = $entry->cover()->first();

        // Still exactly one Cover row, and it is a different, freshly stored file.
        $this->assertSame(1, $entry->media()->where('collection', CodexMediaCollection::Cover)->count());
        $this->assertNotSame($oldCover->id, $newCover->id);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newCover->path);
    }

    public function test_reference_images_and_files_get_independent_positions_per_collection(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melusine',
            'reference_images' => [
                UploadedFile::fake()->image('img-a.jpg'),
                UploadedFile::fake()->image('img-b.jpg'),
            ],
            'reference_files' => [
                UploadedFile::fake()->create('notes.pdf', 100),
                UploadedFile::fake()->create('bio.txt', 20),
            ],
        ])->assertRedirect();

        $images = $entry->media()
            ->where('collection', CodexMediaCollection::ReferenceImage)
            ->orderBy('position')->pluck('position')->all();
        $files = $entry->media()
            ->where('collection', CodexMediaCollection::ReferenceFile)
            ->orderBy('position')->pluck('position')->all();

        // Each collection numbers from 1 independently; they do not interleave.
        $this->assertSame([1, 2], $images);
        $this->assertSame([1, 2], $files);
    }

    public function test_oversized_upload_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melusine',
            'cover' => UploadedFile::fake()->image('huge.jpg')->size(6000),
        ])->assertSessionHasErrors('cover');

        $this->assertSame(0, $entry->media()->count());
    }

    public function test_disallowed_mime_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melusine',
            'reference_files' => [UploadedFile::fake()->create('malware.exe', 10)],
        ])->assertSessionHasErrors('reference_files.0');

        $this->assertSame(0, $entry->media()->count());
    }

    public function test_removing_media_deletes_the_row_and_the_file(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melusine',
            'reference_images' => [UploadedFile::fake()->image('doomed.jpg')],
        ]);

        $image = $entry->media()->firstOrFail();
        Storage::disk('public')->assertExists($image->path);

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melusine',
            'remove_media' => [$image->id],
        ])->assertRedirect();

        $this->assertNull(CodexMedia::find($image->id));
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_remove_media_id_from_another_entry_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $otherEntry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Raymondin']);
        $foreignMedia = CodexMedia::factory()->for($otherEntry, 'entry')->create();

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melusine',
            'remove_media' => [$foreignMedia->id],
        ])->assertSessionHasErrors('remove_media.0');

        // The other entry's media row is untouched.
        $this->assertNotNull(CodexMedia::find($foreignMedia->id));
    }

    public function test_destroying_an_entry_removes_all_its_media_files(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'Melusine',
            'cover' => UploadedFile::fake()->image('cover.jpg'),
            'reference_files' => [UploadedFile::fake()->create('notes.pdf', 100)],
        ]);

        $paths = $entry->media()->pluck('path')->all();
        $this->assertCount(2, $paths);

        $this->actingAs($user)->delete(route('codex.destroy', $entry))->assertRedirect();

        $this->assertNull($entry->fresh());
        $this->assertSame(0, CodexMedia::where('codex_entry_id', $entry->id)->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_non_owner_cannot_upload_or_remove_media(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($other)->put(route('codex.update', $entry), [
            'name' => 'Melusine',
            'cover' => UploadedFile::fake()->image('sneaky.jpg'),
        ])->assertForbidden();

        $this->assertSame(0, $entry->media()->count());
    }
}
