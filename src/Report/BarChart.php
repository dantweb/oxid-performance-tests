<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Report;

/**
 * The report's charts as static SVG (spec in → SVG string out). Static because they are embedded in Markdown, where
 * there is no hover: every bar carries its value and unit as a label and a <title>, and the report repeats the
 * numbers in tables. Colours: categorical slots 1–3 of the dataviz reference palette, validated on the light surface.
 */
final class BarChart
{
    public const SERIES_COLORS = ['#2a78d6', '#eb6834', '#1baf7a'];

    private const SURFACE = '#fcfcfb';
    private const TEXT_PRIMARY = '#0b0b0b';
    private const TEXT_SECONDARY = '#52514e';
    private const GRID = '#e4e3df';
    private const WIDTH = 1000;
    private const LABEL_RIGHT = 390;
    private const PLOT_LEFT = 402;
    private const PLOT_WIDTH = 480;
    private const BAR = 12;
    private const GAP = 2;
    private const GROUP_GAP = 14;
    private const TOP = 96;

    public function render(ChartSpec $spec): string
    {
        $scale = $this->scaleFor($spec);
        $groupHeight = count($spec->series) * (self::BAR + self::GAP) - self::GAP;
        $plotHeight = count($spec->groups) * ($groupHeight + self::GROUP_GAP);
        $height = self::TOP + $plotHeight + 52;
        $axisTitle = $spec->axisLabel . ($scale->log ? ' (log scale)' : '');

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" '
                . 'font-family="system-ui, -apple-system, \'Segoe UI\', sans-serif" role="img" aria-label="%s">',
            self::WIDTH,
            $height,
            self::WIDTH,
            $height,
            $this->escape($spec->title),
        )
            . '<rect width="100%" height="100%" fill="' . self::SURFACE . '"/>'
            . $this->text(16, 28, 16, self::TEXT_PRIMARY, $spec->title, ' font-weight="600"')
            . $this->text(16, 48, 12, self::TEXT_SECONDARY, $spec->note)
            . $this->legend($spec)
            . $this->grid($scale, $plotHeight)
            . sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="1"/>',
                self::PLOT_LEFT,
                self::TOP - 6,
                self::PLOT_LEFT,
                self::TOP + $plotHeight,
                self::TEXT_SECONDARY,
            )
            . $this->groups($spec, $scale, $groupHeight)
            . $this->text(
                self::PLOT_LEFT + self::PLOT_WIDTH / 2,
                self::TOP + $plotHeight + 36,
                12,
                self::TEXT_SECONDARY,
                $axisTitle,
                ' data-axis-title="" text-anchor="middle"',
            )
            . "</svg>\n";
    }

    private function scaleFor(ChartSpec $spec): Scale
    {
        $values = [];
        foreach ($spec->groups as $group) {
            foreach ($group->bars as $bar) {
                if ($bar->value !== null) {
                    array_push($values, $bar->value, $bar->whisker ?? $bar->value);
                }
            }
        }
        $values = array_filter($values, static fn (float $value): bool => $value > 0);
        $max = max([...$values, 1.0]);
        $min = min([...$values, $max]);

        return $max / $min > 100
            ? Scale::logarithmic($min, $max, self::PLOT_WIDTH)
            : Scale::linear($max, self::PLOT_WIDTH);
    }

    private function grid(Scale $scale, int $plotHeight): string
    {
        $grid = '';
        foreach ($scale->ticks as $tick) {
            $x = self::PLOT_LEFT + $scale->length($tick);
            $grid .= sprintf(
                '<line x1="%s" y1="%d" x2="%s" y2="%d" stroke="%s" stroke-width="1"/>',
                $x,
                self::TOP - 6,
                $x,
                self::TOP + $plotHeight,
                self::GRID,
            )
                . $this->text(
                    $x,
                    self::TOP + $plotHeight + 16,
                    11,
                    self::TEXT_SECONDARY,
                    NumberFormat::value($tick),
                    ' text-anchor="middle"',
                );
        }

        return $grid;
    }

    private function legend(ChartSpec $spec): string
    {
        $x = self::PLOT_LEFT;
        $legend = '';
        foreach ($spec->series as $i => $series) {
            $legend .= sprintf(
                '<rect x="%d" y="58" width="12" height="12" rx="2" fill="%s"/>',
                $x,
                self::SERIES_COLORS[$i],
            )
                . $this->text($x + 18, 68, 12, self::TEXT_PRIMARY, $series, ' data-legend=""');
            $x += 30 + mb_strlen($series) * 8;
        }

        return $legend;
    }

    private function groups(ChartSpec $spec, Scale $scale, int $groupHeight): string
    {
        $svg = '';
        foreach ($spec->groups as $g => $group) {
            $top = self::TOP + $g * ($groupHeight + self::GROUP_GAP);
            $bars = '';
            foreach ($group->bars as $bar) {
                $offset = array_search($bar->series, $spec->series, true) * (self::BAR + self::GAP);
                $bars .= $this->bar($spec, $scale, $group, $bar, $top + $offset);
            }
            $svg .= sprintf('<g data-group="%s">', $this->escape($group->label))
                . $this->text(
                    self::LABEL_RIGHT,
                    $top + $groupHeight / 2 + 4,
                    12,
                    self::TEXT_PRIMARY,
                    $group->label,
                    ' text-anchor="end"',
                )
                . $bars . '</g>';
        }

        return $svg;
    }

    private function bar(ChartSpec $spec, Scale $scale, BarGroup $group, Bar $bar, int $y): string
    {
        if ($bar->value === null) {
            $missing = $bar->note === null ? 'n/a' : "n/a – {$bar->note}";

            return $this->text(
                self::PLOT_LEFT + 4,
                $y + self::BAR - 2,
                11,
                self::TEXT_SECONDARY,
                $missing,
                ' data-label=""',
            );
        }
        $length = max($scale->length($bar->value), 1.0);
        $whiskerEnd = $bar->whisker === null ? $length : max($scale->length($bar->whisker), $length);
        $title = sprintf('%s · %s: %s %s', $group->label, $bar->series, NumberFormat::value($bar->value), $spec->unit)
            . ($bar->whisker === null ? '' : sprintf(' (p95 %s %s)', NumberFormat::value($bar->whisker), $spec->unit));
        $color = self::SERIES_COLORS[(int) array_search($bar->series, $spec->series, true)];

        return '<g><title>' . $this->escape($title) . '</title>'
            . sprintf(
                '<path data-bar="" data-value="%s" data-length="%.3f" d="%s" fill="%s"/>',
                $bar->value,
                $length,
                $this->barPath(self::PLOT_LEFT, $y, $length),
                $color,
            )
            . $this->whisker($bar, $y, $length, $whiskerEnd)
            . $this->text(
                self::PLOT_LEFT + $whiskerEnd + 6,
                $y + self::BAR - 2,
                11,
                self::TEXT_PRIMARY,
                NumberFormat::value($bar->value) . ' ' . $spec->unit,
                ' data-label=""',
            )
            . '</g>';
    }

    private function whisker(Bar $bar, int $y, float $length, float $whiskerEnd): string
    {
        if ($bar->whisker === null) {
            return '';
        }
        $middle = $y + self::BAR / 2;

        return sprintf(
            '<path data-whisker="" d="M%s %sH%sM%s %sv8" stroke="%s" stroke-width="1" fill="none"/>',
            self::PLOT_LEFT + $length,
            $middle,
            self::PLOT_LEFT + $whiskerEnd,
            self::PLOT_LEFT + $whiskerEnd,
            $middle - 4,
            self::TEXT_SECONDARY,
        );
    }

    /** A bar anchored at the baseline with a 4px rounded data end. */
    private function barPath(int $x, int $y, float $length): string
    {
        $r = min(4, $length / 2, self::BAR / 2);

        return sprintf(
            'M%d %dh%.1fa%s %s 0 0 1 %s %sv%sa%s %s 0 0 1 %s %sh%.1fz',
            $x,
            $y,
            $length - $r,
            $r,
            $r,
            $r,
            $r,
            self::BAR - 2 * $r,
            $r,
            $r,
            -$r,
            $r,
            -($length - $r),
        );
    }

    private function text(float $x, float $y, int $size, string $fill, string $content, string $extra = ''): string
    {
        return sprintf(
            '<text x="%s" y="%s" font-size="%d"%s fill="%s">%s</text>',
            $x,
            $y,
            $size,
            $extra,
            $fill,
            $this->escape($content),
        );
    }

    private function escape(string $text): string
    {
        return str_replace(['&', '<', '>', '"'], ['&amp;', '&lt;', '&gt;', '&quot;'], $text);
    }
}
