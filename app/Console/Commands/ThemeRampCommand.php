<?php

namespace App\Console\Commands;

use App\Support\ColorContrast;
use App\Support\Oklch;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Turns one anchor colour into an eleven-shade ramp of config-ready values, with the
 * contrast figures a human needs in order to pick shades out of it.
 *
 * ## An authoring tool, not application code
 *
 * Nothing computes a ramp while serving a request: a preset in `config/themes.php`
 * stores final values. This command's output is read, judged, and pasted by hand — which
 * is why the ramp logic lives here rather than in `app/Support`, where it would claim a
 * runtime role it does not have.
 *
 * ## What a ramp holds fixed
 *
 * Hue and chroma come from the anchor and stay put; only lightness moves, in equal steps
 * from LIGHTEST to DARKEST. Equal steps of OKLCH lightness are equal steps of *perceived*
 * lightness — that is the whole reason for working in this space, and it is the property
 * the app's five hand-eyeballed sRGB ramps never had (their 700→800 jump is visibly
 * smaller than 400→500). The anchor's own lightness is discarded; it contributes the hue
 * and the chroma, and the curve decides where each shade sits.
 *
 * `--max-chroma` exists for dark presets. Saturated colour at high lightness is what
 * makes a dark theme painful to look at, and ~0.12 is a comfortable cap; expressing that
 * as a single number is possible in OKLCH and hopeless in hex.
 *
 * ## Why it prints numbers instead of deciding
 *
 * Which shade is "the button" is a taste judgement about a whole palette, and only the
 * person authoring the preset can make it. The command's job is to make the consequence
 * of each choice visible: every shade is measured against the colours it will sit on
 * (white and black by default, or whatever `--against` is handed — paste a value straight
 * out of `config/themes.php`) and judged by App\Support\ColorContrast.
 */
class ThemeRampCommand extends Command
{
    protected $signature = 'theme:ramp
        {anchor : The colour whose hue and chroma the ramp holds — #rrggbb or oklch(l c h)}
        {--max-chroma= : Clamp every shade to at most this chroma (~0.12 suits a dark preset)}
        {--against=* : Measure each shade against these colours (default: white and black)}
        {--ceiling= : Contrast ceiling to judge against (default: config themes.contrast.default_ceiling)}
        {--non-text : Judge against the 3:1 non-text floor rather than the 4.5:1 text floor}';

    protected $description = 'Generate an eleven-shade OKLCH ramp from one anchor colour, with contrast verdicts, to paste into config/themes.php';

    /**
     * The shade keys, matching the ramps this project already reads (`ocean-500`).
     * Shades are an authoring vocabulary only: a preset stores flat role tokens.
     *
     * @var list<int>
     */
    private const SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    /** Lightness of shade 50 — near-white, but not white: white is its own token. */
    private const LIGHTEST = 0.97;

    /**
     * Lightness of shade 950. Deliberately below the app's current darkest colour
     * (`navy-950` sits at 0.24): a dark preset needs a page background *and* a sunken
     * well *and* a raised card below the midpoint, and a floor of 0.24 leaves no room
     * for three of them.
     */
    private const DARKEST = 0.15;

    public function handle(): int
    {
        try {
            $anchor = Oklch::fromCss($this->argument('anchor'));
            $backgrounds = $this->backgrounds();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $maxChroma = $this->option('max-chroma') === null ? null : (float) $this->option('max-chroma');

        if ($maxChroma !== null && $maxChroma <= 0) {
            $this->error('--max-chroma must be greater than zero.');

            return self::FAILURE;
        }

        $ceiling = (float) ($this->option('ceiling') ?? config('themes.contrast.default_ceiling'));
        $isText = ! $this->option('non-text');

        $this->line(sprintf(
            'Anchor %s — hue %s, chroma %s%s.',
            $this->argument('anchor'),
            round($anchor->h, 2),
            round($anchor->c, 4),
            $maxChroma !== null && $anchor->c > $maxChroma ? " (clamped to {$maxChroma})" : '',
        ));
        $this->line(sprintf(
            'Verdicts use the %s floor and a ceiling of %s.',
            $isText ? ColorContrast::TEXT_FLOOR.':1 text' : ColorContrast::NON_TEXT_FLOOR.':1 non-text',
            $ceiling,
        ));
        $this->newLine();

        $this->table(
            array_merge(['Shade', 'Value'], array_map(
                static fn (string $background): string => "on {$background}",
                array_keys($backgrounds),
            )),
            $this->rows($this->ramp($anchor, $maxChroma), $backgrounds, $isText, $ceiling),
        );

        $this->line('Paste the shades you want into config/themes.php as role token values.');

        return self::SUCCESS;
    }

    /**
     * The ramp: one hue, one chroma, eleven evenly spaced lightnesses.
     *
     * Each shade is fitted into sRGB before it is emitted, because a single chroma held
     * across the whole lightness range leaves the extremes outside the gamut for a
     * saturated hue. Fitting reduces chroma only — hue and lightness are untouched — so
     * the value printed is the colour the browser will actually paint, and the ratios
     * beside it describe that same colour.
     *
     * @return array<int, Oklch> shade key => colour
     */
    private function ramp(Oklch $anchor, ?float $maxChroma): array
    {
        $chroma = $maxChroma === null ? $anchor->c : min($anchor->c, $maxChroma);
        $stride = (self::LIGHTEST - self::DARKEST) / (count(self::SHADES) - 1);

        $ramp = [];

        foreach (self::SHADES as $step => $shade) {
            $ramp[$shade] = (new Oklch(self::LIGHTEST - $step * $stride, $chroma, $anchor->h))->fittedToSrgb();
        }

        return $ramp;
    }

    /**
     * @param  array<int, Oklch>  $ramp
     * @param  array<string, Oklch>  $backgrounds  the label the user typed => the colour
     * @return list<list<string>>
     */
    private function rows(array $ramp, array $backgrounds, bool $isText, float $ceiling): array
    {
        $rows = [];

        foreach ($ramp as $shade => $color) {
            $row = [(string) $shade, (string) $color];

            foreach ($backgrounds as $background) {
                $ratio = ColorContrast::ratio($color, $background);

                $row[] = sprintf(
                    '%5.2f  %s',
                    $ratio,
                    ColorContrast::verdict($ratio, $isText, $ceiling)->value,
                );
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The colours each shade is measured against, keyed by what the user typed so the
     * table header names something they recognise.
     *
     * @return array<string, Oklch>
     *
     * @throws InvalidArgumentException on a value that is neither notation
     */
    private function backgrounds(): array
    {
        $values = $this->option('against') ?: ['#ffffff', '#000000'];

        return array_combine(
            $values,
            array_map(static fn (string $value): Oklch => Oklch::fromCss($value), $values),
        );
    }
}
