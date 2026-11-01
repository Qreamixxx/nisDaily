<?php
declare(strict_types=1);

namespace App;

/**
 * Tiny server-side SVG charts: no JavaScript library, nothing to download, works offline.
 * Every bar and point has a <title> (hover text) and the numbers are also shown in a table by the page.
 */
final class Chart
{
    private const W = 560;
    private const H = 240;
    private const PAD_L = 40;
    private const PAD_B = 34;
    private const PAD_T = 14;
    private const PAD_R = 12;

    /**
     * @param string[] $labels
     * @param float[]  $values
     * @param ?float[] $reference optional second series drawn as a line (e.g. class average)
     */
    public static function bars(array $labels, array $values, float $max, string $ariaLabel, ?array $reference = null, string $unit = ''): string
    {
        $n = count($values);
        if ($n === 0 || $max <= 0) {
            return '';
        }
        $plotW = self::W - self::PAD_L - self::PAD_R;
        $plotH = self::H - self::PAD_T - self::PAD_B;
        $slot = $plotW / $n;
        $bw = min(48.0, $slot * 0.6);
        $svg = self::open($ariaLabel) . self::grid($max, $plotW, $plotH);
        foreach ($values as $i => $v) {
            $h = max(0.0, min(1.0, $v / $max)) * $plotH;
            $x = self::PAD_L + $slot * $i + ($slot - $bw) / 2;
            $y = self::PAD_T + $plotH - $h;
            $svg .= sprintf(
                '<rect class="chart-bar" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3"><title>%s: %s%s</title></rect>',
                $x, $y, $bw, $h, e($labels[$i]), e(self::num($v)), e($unit)
            );
            $svg .= sprintf('<text class="chart-val" x="%.1f" y="%.1f" text-anchor="middle">%s</text>', $x + $bw / 2, max(11.0, $y - 4), e(self::num($v)));
            $svg .= sprintf('<text class="chart-lbl" x="%.1f" y="%d" text-anchor="middle">%s</text>', $x + $bw / 2, self::H - 12, e(self::short($labels[$i], (int)($slot / 6.5))));
        }
        if ($reference) {
            $pts = [];
            foreach ($reference as $i => $r) {
                if ($r === null) {
                    continue;
                }
                $pts[] = [self::PAD_L + $slot * $i + $slot / 2, self::PAD_T + $plotH - max(0.0, min(1.0, $r / $max)) * $plotH, $r, $labels[$i]];
            }
            $svg .= self::polyline($pts, 'chart-ref', $unit);
        }
        return $svg . '</svg>';
    }

    /** @param string[] $labels @param float[] $values */
    public static function line(array $labels, array $values, float $max, string $ariaLabel, string $unit = ''): string
    {
        $n = count($values);
        if ($n === 0 || $max <= 0) {
            return '';
        }
        $plotW = self::W - self::PAD_L - self::PAD_R;
        $plotH = self::H - self::PAD_T - self::PAD_B;
        $slot = $plotW / $n;
        $svg = self::open($ariaLabel) . self::grid($max, $plotW, $plotH);
        $pts = [];
        foreach ($values as $i => $v) {
            $x = self::PAD_L + $slot * $i + $slot / 2;
            $pts[] = [$x, self::PAD_T + $plotH - max(0.0, min(1.0, $v / $max)) * $plotH, $v, $labels[$i]];
            $svg .= sprintf('<text class="chart-lbl" x="%.1f" y="%d" text-anchor="middle">%s</text>', $x, self::H - 12, e(self::short($labels[$i], (int)($slot / 6.5))));
        }
        return $svg . self::polyline($pts, 'chart-line', $unit) . '</svg>';
    }

    /**
     * Several lines over the same x labels (e.g. the student and the class average, exam after exam).
     * @param string[] $labels
     * @param array<int, array{class:string, values:array<int, ?float>, name:string}> $series class = chart-line | chart-ref
     */
    public static function lines(array $labels, array $series, float $max, string $ariaLabel, string $unit = '', float $min = 0.0): string
    {
        $n = count($labels);
        if ($n === 0 || $max <= 0) {
            return '';
        }
        $plotW = self::W - self::PAD_L - self::PAD_R;
        $plotH = self::H - self::PAD_T - self::PAD_B;
        $slot = $plotW / $n;
        $range = max(1.0, $max - $min);
        $svg = self::open($ariaLabel) . self::grid($max, $plotW, $plotH, $min);
        foreach ($labels as $i => $label) {
            $svg .= sprintf('<text class="chart-lbl" x="%.1f" y="%d" text-anchor="middle">%s</text>', self::PAD_L + $slot * $i + $slot / 2, self::H - 12, e(self::short($label, (int)($slot / 6.5))));
        }
        foreach ($series as $s) {
            $pts = [];
            foreach ($s['values'] as $i => $v) {
                if ($v === null) {
                    continue;
                }
                $pts[] = [self::PAD_L + $slot * $i + $slot / 2, self::PAD_T + $plotH - max(0.0, min(1.0, ($v - $min) / $range)) * $plotH, $v, $labels[$i] . ' (' . $s['name'] . ')'];
            }
            $svg .= self::polyline($pts, $s['class'], $unit);
        }
        return $svg . '</svg>';
    }

    private static function open(string $aria): string
    {
        return sprintf('<svg class="chart" viewBox="0 0 %d %d" role="img" aria-label="%s">', self::W, self::H, e($aria));
    }

    private static function grid(float $max, float $plotW, float $plotH, float $min = 0.0): string
    {
        $svg = '';
        foreach ([0, 0.25, 0.5, 0.75, 1] as $f) {
            $y = self::PAD_T + $plotH - $f * $plotH;
            $svg .= sprintf('<line class="chart-grid" x1="%d" x2="%.1f" y1="%.1f" y2="%.1f"/>', self::PAD_L, self::PAD_L + $plotW, $y, $y);
            $svg .= sprintf('<text class="chart-axis" x="%d" y="%.1f" text-anchor="end">%s</text>', self::PAD_L - 6, $y + 4, e(self::num($min + ($max - $min) * $f)));
        }
        return $svg;
    }

    private static function polyline(array $pts, string $class, string $unit): string
    {
        if (!$pts) {
            return '';
        }
        $d = implode(' ', array_map(fn($p) => sprintf('%.1f,%.1f', $p[0], $p[1]), $pts));
        $svg = sprintf('<polyline class="%s" points="%s" fill="none"/>', $class, $d);
        foreach ($pts as $p) {
            $svg .= sprintf('<circle class="%s-dot" cx="%.1f" cy="%.1f" r="4"><title>%s: %s%s</title></circle>', $class, $p[0], $p[1], e($p[3]), e(self::num($p[2])), e($unit));
        }
        return $svg;
    }

    private static function num(float $v): string
    {
        return num($v);
    }

    private static function short(string $s, int $chars): string
    {
        $chars = max(3, $chars);
        return mb_strlen($s) > $chars ? mb_substr($s, 0, $chars - 1) . '…' : $s;
    }
}
