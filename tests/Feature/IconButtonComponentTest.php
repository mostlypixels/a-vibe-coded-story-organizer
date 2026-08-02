<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `<x-icon-button>` — the one place the app's icon-only control styling lives —
 * and the seven wrappers that compose it.
 *
 * Rendered standalone via `Blade::render()`, following {@see DiffComponentTest}'s
 * precedent. These assertions exist because the eight wrappers previously each
 * re-typed the same Tailwind string: a feature test that only checks a page
 * returns 200 cannot tell a correctly-styled button from a differently-red one,
 * and nothing else in the suite renders these components at all.
 *
 * Two things are load-bearing enough to pin down here:
 *
 *   - **Every icon button has an accessible name.** A bare glyph is not a label,
 *     so each one must carry both a `title` and an `sr-only` span.
 *   - **Blade directives survive inside a component tag.** The wrappers pass
 *     `@disabled(...)` and conditional `href`/`download` attributes *through*
 *     `<x-icon-button ...>`, which the component-tag compiler handles differently
 *     from a plain element. If that ever stops working the buttons still render —
 *     they just quietly stop being disabled, which is precisely the kind of
 *     regression only an assertion catches.
 */
class IconButtonComponentTest extends TestCase
{
    /**
     * Render a template, asserting the component tags in it actually compiled.
     *
     * That guard is the whole reason this helper exists. When the tag compiler
     * fails to match an `<x-…>` tag it does not error — it leaves the tag on the
     * page as text, attributes and all. Every "does the output contain
     * href=…/title=…" assertion below would then pass against literal source,
     * testing nothing. Asserting the tag is *gone* is what makes the rest mean
     * something.
     */
    private function render(string $template, array $data = []): string
    {
        $rendered = Blade::render($template, $data);

        $this->assertStringNotContainsString(
            '<x-',
            $rendered,
            'A component tag was emitted as literal text instead of being compiled. '
            .'The usual cause is a Blade directive (@disabled, @if) used as an attribute '
            .'inside an <x-…> tag — use a bound attribute (:disabled="…") instead.',
        );

        return $rendered;
    }

    public function test_the_base_component_carries_its_shape_icon_and_accessible_name(): void
    {
        $rendered = $this->render('<x-icon-button icon="pencil" :label="$label" />', ['label' => 'Edit']);

        $this->assertStringContainsString('inline-flex items-center justify-center p-1.5 rounded-md', $rendered);
        // The outline variant's link colour, the app default.
        $this->assertStringContainsString('border-link', $rendered);
        // Both naming channels, not one: a title alone is not reliably announced.
        $this->assertStringContainsString('title="Edit"', $rendered);
        $this->assertStringContainsString('<span class="sr-only">Edit</span>', $rendered);
        // The glyph itself resolved through x-dynamic-component.
        $this->assertStringContainsString('<svg', $rendered);
        $this->assertStringContainsString('h-4 w-4', $rendered);
        $this->assertStringContainsString('<button', $rendered);
    }

    public function test_as_renders_a_link_instead_of_a_button(): void
    {
        $rendered = $this->render('<x-icon-button as="a" icon="eye" label="View" href="/somewhere" />');

        $this->assertStringContainsString('<a', $rendered);
        $this->assertStringContainsString('href="/somewhere"', $rendered);
        $this->assertStringNotContainsString('<button', $rendered);
    }

    /**
     * The disabled look is a `disabled:` CSS variant rather than a variant the
     * caller selects, so the styling cannot disagree with whether the control
     * actually works — and the Story overview's JS toggle restyles for free.
     */
    public function test_the_ghost_variant_styles_its_own_disabled_state(): void
    {
        $rendered = $this->render('<x-icon-button variant="ghost" icon="chevron-up" label="Move up" />');

        $this->assertStringContainsString('text-content-subtle', $rendered);
        // Dimmed with opacity, not a paler token: `content-subtle` is already the
        // faintest content token, and the flat vocabulary has no step below it.
        $this->assertStringContainsString('disabled:opacity-25', $rendered);
        $this->assertStringContainsString('disabled:cursor-not-allowed', $rendered);
        // Never the bordered outline — ghost controls are borderless.
        $this->assertStringNotContainsString('border-link', $rendered);
    }

    /**
     * A bound boolean is how the wrappers disable the button — see the warning in
     * `icon-move-button.blade.php` for why it cannot be `@disabled(…)`. The
     * attribute bag drops `false` and expands `true` to `disabled="disabled"`.
     */
    public function test_a_bound_boolean_disables_the_button_and_false_leaves_it_alone(): void
    {
        $disabled = $this->render('<x-icon-button variant="ghost" icon="chevron-up" label="Move up" :disabled="true" />');
        $enabled = $this->render('<x-icon-button variant="ghost" icon="chevron-up" label="Move up" :disabled="false" />');

        // Assert the attribute's own rendered form, not the bare word: the
        // `disabled:` utility classes contain "disabled" too.
        $this->assertStringContainsString('disabled="disabled"', $disabled);
        $this->assertStringNotContainsString('disabled="disabled"', $enabled);
    }

    public function test_the_light_variant_is_for_dark_backdrops(): void
    {
        $rendered = $this->render('<x-icon-close-button variant="light" class="absolute" />');

        $this->assertStringContainsString('border-nav-content', $rendered);
        $this->assertStringContainsString('text-nav-content', $rendered);
        // Caller-supplied classes still land alongside the variant's.
        $this->assertStringContainsString('absolute', $rendered);
    }

    public function test_the_delete_button_posts_a_delete_behind_a_confirm(): void
    {
        $rendered = $this->render('<x-icon-delete-button action="/acts/1" :confirm="$confirm" />', [
            'confirm' => 'Are you sure?',
        ]);

        $this->assertStringContainsString('action="/acts/1"', $rendered);
        $this->assertStringContainsString('name="_method" value="DELETE"', $rendered);
        $this->assertStringContainsString('return confirm(', $rendered);
        // Destructive, so danger rather than the default outline.
        $this->assertStringContainsString('border-danger', $rendered);
    }

    public function test_the_move_button_posts_a_patch_and_picks_its_glyph_from_the_direction(): void
    {
        $up = $this->render('<x-icon-move-button direction="up" action="/scenes/1/move-up" />');
        $down = $this->render('<x-icon-move-button direction="down" action="/scenes/1/move-down" :disabled="true" />');

        $this->assertStringContainsString('name="_method" value="PATCH"', $up);
        $this->assertStringContainsString('title="Move up"', $up);
        $this->assertStringNotContainsString('disabled="disabled"', $up);

        $this->assertStringContainsString('title="Move down"', $down);
        $this->assertStringContainsString('disabled="disabled"', $down);

        // The two directions must not render the same glyph. Asserted on the class
        // Tabler stamps on its <svg>, not on path data — every Tabler icon opens
        // with the same transparent 24×24 spacer path, so comparing the first path
        // would call any two icons identical.
        $this->assertStringContainsString('icon-tabler-chevron-up', $up);
        $this->assertStringContainsString('icon-tabler-chevron-down', $down);
    }

    public function test_the_dialog_button_opens_a_modal_rather_than_submitting(): void
    {
        $rendered = $this->render('<x-icon-dialog-button modal="delete-act-7" />');

        $this->assertStringContainsString("open-modal', 'delete-act-7'", $rendered);
        $this->assertStringContainsString('type="button"', $rendered);
        // Same danger colour as the plain delete button — the whole point of composing the base.
        $this->assertStringContainsString('border-danger', $rendered);
        // It must not be a form: this one defers the destruction to the dialog.
        $this->assertStringNotContainsString('<form', $rendered);
    }

    /**
     * The download link's `href`/`download` are optional so an Alpine-bound target
     * can be passed instead — a conditional attribute inside a component tag, which
     * is the other construct the tag compiler treats specially.
     */
    public function test_the_download_button_supports_static_and_alpine_bound_targets(): void
    {
        $static = $this->render('<x-icon-download-button href="/f.zip" download="story.zip" />');
        $bare = $this->render('<x-icon-download-button href="/f.zip" :download="true" />');
        $alpine = $this->render('<x-icon-download-button x-bind:href="preview?.url" x-bind:download="preview?.name" />');

        $this->assertStringContainsString('href="/f.zip"', $static);
        $this->assertStringContainsString('download="story.zip"', $static);

        // `:download="true"` renders as `download="download"` — the attribute bag's
        // expansion of a boolean. Equivalent to the bare `download` this component
        // used to emit: the attribute's *presence* is what the browser acts on, and
        // any value (including its own name) means "download rather than navigate".
        $this->assertStringContainsString('download="download"', $bare);

        $this->assertStringContainsString('x-bind:href="preview?.url"', $alpine);
        // No static href at all when only the Alpine binding was given, so the
        // browser never has a real URL to fall back to before Alpine boots.
        $this->assertStringNotContainsString(' href="', $alpine);
    }

    public function test_the_save_button_submits_and_is_focus_visible(): void
    {
        $rendered = $this->render('<x-icon-save-button />');

        $this->assertStringContainsString('type="submit"', $rendered);
        // A form's primary action must have an unmistakable keyboard focus state.
        $this->assertStringContainsString('focus:ring-2', $rendered);
    }
}
