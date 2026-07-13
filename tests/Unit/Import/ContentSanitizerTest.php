<?php

namespace Tests\Unit\Import;

use App\Exceptions\ImportValidationException;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Services\Import\ContentSanitizer;
use App\Services\StaticSiteExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Unit-level tests for ContentSanitizer (import task 03): the reject-on-violation
 * policy over the app's existing HtmlSanitizer/RichTextFields allow-list, for both
 * raw HTML fragments (description.html / notes.html) and Markdown (contents.md,
 * checked on its RENDERED output to close CommonMark's raw-HTML passthrough hole).
 *
 * RefreshDatabase is only needed by the real-export false-positive check at the
 * bottom; the string-level tests never touch the database.
 */
class ContentSanitizerTest extends TestCase
{
    use RefreshDatabase;

    private ContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = app(ContentSanitizer::class);
    }

    // ------------------------------------------------------------------
    // assertHtmlAllowed — happy path
    // ------------------------------------------------------------------

    public function test_html_using_only_allowed_tags_and_schemes_passes(): void
    {
        $this->sanitizer->assertHtmlAllowed(
            '<h2>Chapter notes</h2>'.
            '<p>Some <strong>bold</strong>, <em>italic</em>, and <s>struck</s> text with a '.
            '<a href="https://example.com/reference">link</a>.</p>'.
            '<ul><li>First</li><li>Second</li></ul>'.
            '<blockquote><p>A quote</p></blockquote>'.
            '<pre><code>code block</code></pre>'.
            '<hr />',
        );

        // Reaching here without an exception IS the assertion.
        $this->assertTrue(true);
    }

    public function test_an_empty_fragment_passes(): void
    {
        $this->sanitizer->assertHtmlAllowed('');

        $this->assertTrue(true);
    }

    public function test_prose_with_quotes_and_entities_is_not_a_false_positive(): void
    {
        // HTMLPurifier re-encodes inert text entities (&quot; becomes a literal
        // quote); that normalization must not read as "cleaning changed something".
        $this->sanitizer->assertHtmlAllowed('<p>She said &quot;run&quot; &amp; he did — twice.</p>');

        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    // assertHtmlAllowed — violations
    // ------------------------------------------------------------------

    public function test_html_containing_a_script_tag_is_rejected(): void
    {
        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('HTML content that is not allowed');

        $this->sanitizer->assertHtmlAllowed('<p>hello</p><script>alert(1)</script>');
    }

    public function test_html_containing_an_iframe_is_rejected(): void
    {
        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('HTML content that is not allowed');

        $this->sanitizer->assertHtmlAllowed('<iframe src="https://evil.example"></iframe>');
    }

    public function test_html_with_an_inline_event_handler_is_rejected(): void
    {
        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('HTML content that is not allowed');

        $this->sanitizer->assertHtmlAllowed('<p onclick="alert(1)">hello</p>');
    }

    public function test_html_with_a_javascript_scheme_link_is_rejected(): void
    {
        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('HTML content that is not allowed');

        $this->sanitizer->assertHtmlAllowed('<a href="javascript:alert(1)">click me</a>');
    }

    // ------------------------------------------------------------------
    // assertMarkdownAllowed — happy path
    // ------------------------------------------------------------------

    public function test_plain_commonmark_passes(): void
    {
        $this->sanitizer->assertMarkdownAllowed(<<<'MARKDOWN'
            # A heading

            Some *prose* with **emphasis**, a [link](https://example.com), and "quoted speech".

            > A blockquote.

            - one
            - two

            ```
            a plain code fence
            ```

            ---
            MARKDOWN);

        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    // assertMarkdownAllowed — violations
    // ------------------------------------------------------------------

    public function test_markdown_with_a_raw_disallowed_html_block_is_rejected(): void
    {
        // CommonMark passes raw HTML through to the rendered output verbatim —
        // this is the passthrough hole the rendered-output check exists to close.
        // (An <object> block, not <script>: the GFM renderer Str::markdown() uses
        // escapes its short disallowed-raw-HTML list — script/iframe/style/... —
        // to inert text at render time, so those never SURVIVE rendering; tags
        // outside that list, like <object>, pass through raw and must reject.)
        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('HTML content that is not allowed');

        $this->sanitizer->assertMarkdownAllowed("Some prose.\n\n<object data=\"evil.swf\"></object>\n");
    }

    public function test_markdown_whose_script_tag_is_escaped_by_the_renderer_passes(): void
    {
        // The flip side of the previous test, pinned so a renderer change is
        // caught: GFM escapes a raw <script> to visible "&lt;script>" text, so
        // the rendered output contains no executable markup — exactly what the
        // app itself would display for this scene. Safe, therefore allowed.
        $this->sanitizer->assertMarkdownAllowed("Some prose.\n\n<script>alert(1)</script>\n");

        $this->assertTrue(true);
    }

    public function test_markdown_with_a_raw_disallowed_inline_tag_is_rejected(): void
    {
        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('HTML content that is not allowed');

        $this->sanitizer->assertMarkdownAllowed('A portrait: <img src="x" onerror="alert(1)"> inline.');
    }

    public function test_unparseable_markdown_is_rejected(): void
    {
        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('could not be read as valid Markdown');

        // Invalid UTF-8 bytes — CommonMark refuses to parse them at all, which
        // is the existing ValidMarkdown rule's failure mode.
        $this->sanitizer->assertMarkdownAllowed("\xB1\x31 not utf-8");
    }

    // ------------------------------------------------------------------
    // No false positives on the app's own export output
    // ------------------------------------------------------------------

    public function test_a_real_exported_description_notes_and_contents_pass_unchanged(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Sanitizer round trip']);
        $act = Act::factory()->for($project)->create(['name' => 'Act one']);
        $chapter = Chapter::factory()->for($act)->create(['name' => 'Chapter one']);

        // description/notes pass through the SanitizesRichHtml mutator on save,
        // exactly as a normally-authored scene's would; contents stays raw Markdown.
        Scene::factory()->for($chapter)->create([
            'name' => 'Scene one',
            'description' => '<p>The <strong>inciting</strong> incident — with a '.
                '<a href="https://example.com/notes">reference</a> &amp; "quotes".</p>',
            'notes' => '<h3>Reminders</h3><ul><li>Fix the pacing</li><li>Check the <em>timeline</em></li></ul>',
            'contents' => "# The reveal\n\nShe said \"run\" & *he did*.\n\n> No turning back.\n\n- door\n- corridor\n",
        ]);

        $zipPath = (new StaticSiteExporter)->export($project->fresh(), false);

        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($zipPath));

            $sceneFiles = $this->sceneContentFiles($zip);
            $zip->close();

            foreach (['description.html', 'notes.html'] as $fragment) {
                $this->assertArrayHasKey($fragment, $sceneFiles);
                $this->sanitizer->assertHtmlAllowed($sceneFiles[$fragment]);
            }

            $this->assertArrayHasKey('contents.md', $sceneFiles);
            $this->sanitizer->assertMarkdownAllowed($sceneFiles['contents.md']);
        } finally {
            @unlink($zipPath);
        }
    }

    /**
     * Pull the exported scene's three content files out of the archive, keyed
     * by basename — located by suffix so the test doesn't depend on the
     * exporter's directory-slug scheme.
     *
     * @return array<string, string>
     */
    private function sceneContentFiles(ZipArchive $zip): array
    {
        $files = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            $basename = basename($name);

            if (str_contains($name, '/scenes/') && in_array($basename, ['description.html', 'notes.html', 'contents.md'], true)) {
                $files[$basename] = (string) $zip->getFromIndex($index);
            }
        }

        return $files;
    }
}
