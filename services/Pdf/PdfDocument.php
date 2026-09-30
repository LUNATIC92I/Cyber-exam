<?php
declare(strict_types=1);

namespace App\Services\Pdf;

/**
 * Lightweight vector PDF 1.4 writer (no external dependency).
 * Supports: base-14 fonts (WinAnsi), text with alignment/letter-spacing,
 * rectangles, rounded rectangles, circles, polygons, bezier paths, axial
 * gradients, transparency, clipping and text on a circular arc.
 *
 * The public API uses a TOP-LEFT origin in points (1/72 inch).
 */
final class PdfDocument
{
    private const FONTS = [
        'helvetica' => 'Helvetica', 'helvetica-bold' => 'Helvetica-Bold',
        'times' => 'Times-Roman', 'times-bold' => 'Times-Bold', 'times-italic' => 'Times-Italic',
        'times-bolditalic' => 'Times-BoldItalic', 'courier' => 'Courier', 'courier-bold' => 'Courier-Bold',
    ];

    private float $w;
    private float $h;
    private string $stream = '';
    /** @var array<string,string> font key => resource name */
    private array $usedFonts = [];
    /** @var array<string,float> alpha => gs name */
    private array $alphaStates = [];
    /** @var array<int,array> */
    private array $shadings = [];
    private static ?array $metrics = null;
    private array $info = [];

    public function __construct(float $width = 841.89, float $height = 595.28)
    {
        $this->w = $width;
        $this->h = $height;
        self::$metrics ??= require __DIR__ . '/font-metrics.php';
    }

    public function width(): float
    {
        return $this->w;
    }

    public function height(): float
    {
        return $this->h;
    }

    public function setInfo(string $title, string $subject = '', string $keywords = ''): void
    {
        $this->info = ['Title' => $title, 'Subject' => $subject, 'Keywords' => $keywords];
    }

    // ---------------------------------------------------------------- graphics state

    public function save(): self
    {
        $this->stream .= "q\n";
        return $this;
    }

    public function restore(): self
    {
        $this->stream .= "Q\n";
        return $this;
    }

    public function fill(array $rgb): self
    {
        $this->stream .= sprintf("%.4F %.4F %.4F rg\n", $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
        return $this;
    }

    public function stroke(array $rgb): self
    {
        $this->stream .= sprintf("%.4F %.4F %.4F RG\n", $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
        return $this;
    }

    public function lineWidth(float $w): self
    {
        $this->stream .= sprintf("%.3F w\n", $w);
        return $this;
    }

    public function dash(array $pattern = [], float $phase = 0): self
    {
        $this->stream .= '[' . implode(' ', array_map(fn ($v) => sprintf('%.2F', $v), $pattern)) . '] ' . sprintf('%.2F', $phase) . " d\n";
        return $this;
    }

    /** Sets fill and stroke opacity (0..1). */
    public function alpha(float $a): self
    {
        $key = sprintf('%.3F', $a);
        if (!isset($this->alphaStates[$key])) {
            $this->alphaStates[$key] = 'GS' . (count($this->alphaStates) + 1);
        }
        $this->stream .= '/' . $this->alphaStates[$key] . " gs\n";
        return $this;
    }

    // ---------------------------------------------------------------------- shapes

    private function y(float $y): float
    {
        return $this->h - $y;
    }

    private static function op(string $style): string
    {
        return match ($style) {
            'F' => 'f', 'S' => 'S', 'FS', 'DF', 'B' => 'B', 'N' => 'n', default => 'f',
        };
    }

    public function rect(float $x, float $y, float $w, float $h, string $style = 'F'): self
    {
        $this->stream .= sprintf("%.3F %.3F %.3F %.3F re %s\n", $x, $this->y($y + $h), $w, $h, self::op($style));
        return $this;
    }

    public function roundedRect(float $x, float $y, float $w, float $h, float $r, string $style = 'F'): self
    {
        $this->roundedRectPath($x, $y, $w, $h, $r);
        $this->stream .= self::op($style) . "\n";
        return $this;
    }

    private function roundedRectPath(float $x, float $y, float $w, float $h, float $r): void
    {
        $k = 0.5523 * $r;
        $x2 = $x + $w;
        $y2 = $y + $h;
        $p = fn (float $px, float $py) => sprintf('%.3F %.3F', $px, $this->y($py));
        $s = $p($x + $r, $y) . " m\n";
        $s .= $p($x2 - $r, $y) . " l\n";
        $s .= $p($x2 - $r + $k, $y) . ' ' . $p($x2, $y + $r - $k) . ' ' . $p($x2, $y + $r) . " c\n";
        $s .= $p($x2, $y2 - $r) . " l\n";
        $s .= $p($x2, $y2 - $r + $k) . ' ' . $p($x2 - $r + $k, $y2) . ' ' . $p($x2 - $r, $y2) . " c\n";
        $s .= $p($x + $r, $y2) . " l\n";
        $s .= $p($x + $r - $k, $y2) . ' ' . $p($x, $y2 - $r + $k) . ' ' . $p($x, $y2 - $r) . " c\n";
        $s .= $p($x, $y + $r) . " l\n";
        $s .= $p($x, $y + $r - $k) . ' ' . $p($x + $r - $k, $y) . ' ' . $p($x + $r, $y) . " c\nh\n";
        $this->stream .= $s;
    }

    public function line(float $x1, float $y1, float $x2, float $y2): self
    {
        $this->stream .= sprintf("%.3F %.3F m %.3F %.3F l S\n", $x1, $this->y($y1), $x2, $this->y($y2));
        return $this;
    }

    public function circle(float $cx, float $cy, float $r, string $style = 'F'): self
    {
        return $this->ellipse($cx, $cy, $r, $r, $style);
    }

    public function ellipse(float $cx, float $cy, float $rx, float $ry, string $style = 'F'): self
    {
        $this->ellipsePath($cx, $cy, $rx, $ry);
        $this->stream .= self::op($style) . "\n";
        return $this;
    }

    private function ellipsePath(float $cx, float $cy, float $rx, float $ry): void
    {
        $kx = 0.5523 * $rx;
        $ky = 0.5523 * $ry;
        $cy = $this->y($cy);
        $this->stream .= sprintf(
            "%.3F %.3F m %.3F %.3F %.3F %.3F %.3F %.3F c %.3F %.3F %.3F %.3F %.3F %.3F c %.3F %.3F %.3F %.3F %.3F %.3F c %.3F %.3F %.3F %.3F %.3F %.3F c h\n",
            $cx + $rx, $cy,
            $cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry,
            $cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy,
            $cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry,
            $cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy
        );
    }

    /** @param array<int,array{0:float,1:float}> $points */
    public function polygon(array $points, string $style = 'F', bool $close = true): self
    {
        $s = '';
        foreach ($points as $i => [$px, $py]) {
            $s .= sprintf('%.3F %.3F %s ', $px, $this->y($py), $i === 0 ? 'm' : 'l');
        }
        $this->stream .= $s . ($close ? 'h ' : '') . self::op($style) . "\n";
        return $this;
    }

    /**
     * Cubic bezier path. Segments: ['M', x, y] | ['L', x, y] | ['C', x1, y1, x2, y2, x, y]
     */
    public function path(array $segments, string $style = 'S'): self
    {
        $s = '';
        foreach ($segments as $seg) {
            $t = array_shift($seg);
            $s .= match ($t) {
                'M' => sprintf('%.3F %.3F m ', $seg[0], $this->y($seg[1])),
                'L' => sprintf('%.3F %.3F l ', $seg[0], $this->y($seg[1])),
                'C' => sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c ', $seg[0], $this->y($seg[1]), $seg[2], $this->y($seg[3]), $seg[4], $this->y($seg[5])),
                'Z' => 'h ',
            };
        }
        $this->stream .= $s . self::op($style) . "\n";
        return $this;
    }

    /** Fills a rectangle (optionally rounded) with a linear gradient between two colours. */
    public function gradientRect(float $x, float $y, float $w, float $h, array $from, array $to, bool $horizontal = true, float $radius = 0): self
    {
        $name = 'Sh' . (count($this->shadings) + 1);
        $coords = $horizontal
            ? [$x, $this->y($y), $x + $w, $this->y($y)]
            : [$x, $this->y($y), $x, $this->y($y + $h)];
        $this->shadings[] = ['name' => $name, 'coords' => $coords, 'from' => $from, 'to' => $to];
        $this->save();
        if ($radius > 0) {
            $this->roundedRectPath($x, $y, $w, $h, $radius);
        } else {
            $this->stream .= sprintf("%.3F %.3F %.3F %.3F re\n", $x, $this->y($y + $h), $w, $h);
        }
        $this->stream .= "W n\n/{$name} sh\n";
        return $this->restore();
    }

    // ------------------------------------------------------------------------ text

    public static function encode(string $utf8): string
    {
        $out = '';
        $translit = class_exists(\Transliterator::class) ? \Transliterator::create('Any-Latin; Latin-ASCII') : null;
        foreach (mb_str_split($utf8) as $ch) {
            $conv = @mb_convert_encoding($ch, 'Windows-1252', 'UTF-8');
            if ($conv === '?' && $ch !== '?') {
                $conv = $translit ? (string) $translit->transliterate($ch) : '?';
                $conv = @mb_convert_encoding($conv, 'Windows-1252', 'UTF-8');
            }
            $out .= $conv;
        }
        return $out;
    }

    private function fontResource(string $font): string
    {
        $font = strtolower($font);
        if (!isset(self::FONTS[$font])) {
            throw new \InvalidArgumentException("Unknown font {$font}");
        }
        if (!isset($this->usedFonts[$font])) {
            $this->usedFonts[$font] = 'F' . (count($this->usedFonts) + 1);
        }
        return $this->usedFonts[$font];
    }

    public function textWidth(string $text, string $font, float $size, float $spacing = 0): float
    {
        $widths = self::$metrics[self::FONTS[strtolower($font)]];
        $enc = self::encode($text);
        $w = 0;
        $len = strlen($enc);
        for ($i = 0; $i < $len; $i++) {
            $w += $widths[ord($enc[$i])];
        }
        return $w * $size / 1000 + max(0, $len - 1) * $spacing;
    }

    private static function escape(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $s);
    }

    /**
     * Draws a single line of text. $y is the BASELINE (top-left origin).
     * $align: L | C | R  (x is the left edge, centre or right edge).
     */
    public function text(float $x, float $y, string $text, string $font, float $size, array $rgb, string $align = 'L', float $spacing = 0): self
    {
        $tw = $this->textWidth($text, $font, $size, $spacing);
        $x = match ($align) {
            'C' => $x - $tw / 2,
            'R' => $x - $tw,
            default => $x,
        };
        $res = $this->fontResource($font);
        $this->stream .= sprintf("BT /%s %.2F Tf %.3F Tc %.4F %.4F %.4F rg %.3F %.3F Td (%s) Tj ET\n",
            $res, $size, $spacing, $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255, $x, $this->y($y), self::escape(self::encode($text)));
        return $this;
    }

    /** Shrinks the font size until the text fits the given width. Returns the size used. */
    public function fitText(float $x, float $y, string $text, string $font, float $size, float $maxWidth, array $rgb, string $align = 'C', float $minSize = 10, float $spacing = 0): float
    {
        while ($size > $minSize && $this->textWidth($text, $font, $size, $spacing) > $maxWidth) {
            $size -= 0.5;
        }
        $this->text($x, $y, $text, $font, $size, $rgb, $align, $spacing);
        return $size;
    }

    /**
     * Places text along a circular arc, centred on $centerAngle (degrees, 90 = top),
     * reading clockwise when $top is true (top arc) or counter-clockwise for a bottom arc.
     */
    public function textOnArc(float $cx, float $cy, float $radius, string $text, string $font, float $size, array $rgb, float $centerAngle = 90, bool $top = true, float $spacing = 0): self
    {
        $res = $this->fontResource($font);
        $widths = self::$metrics[self::FONTS[strtolower($font)]];
        $enc = self::encode($text);
        $total = $this->textWidth($text, $font, $size, $spacing);
        $arcTotal = $total / $radius; // radians
        $pcx = $cx;
        $pcy = $this->y($cy);
        $angle = deg2rad($centerAngle) + ($top ? $arcTotal / 2 : -$arcTotal / 2);
        $s = sprintf("BT /%s %.2F Tf %.4F %.4F %.4F rg\n", $res, $size, $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
        $len = strlen($enc);
        for ($i = 0; $i < $len; $i++) {
            $cw = $widths[ord($enc[$i])] * $size / 1000;
            $half = ($cw / 2) / $radius;
            $mid = $top ? $angle - $half : $angle + $half;
            // Character baseline direction is tangent to the circle.
            $rot = $top ? $mid - M_PI / 2 : $mid + M_PI / 2;
            $px = $pcx + $radius * cos($mid);
            $py = $pcy + $radius * sin($mid);
            // shift back by half the glyph width along the tangent so the glyph is centred on $mid
            $ox = $px - cos($rot) * $cw / 2;
            $oy = $py - sin($rot) * $cw / 2;
            $s .= sprintf("%.5F %.5F %.5F %.5F %.3F %.3F Tm (%s) Tj\n", cos($rot), sin($rot), -sin($rot), cos($rot), $ox, $oy, self::escape($enc[$i]));
            $step = ($cw + $spacing) / $radius;
            $angle = $top ? $angle - $step : $angle + $step;
        }
        $this->stream .= $s . "ET\n";
        return $this;
    }

    /** Draws a boolean module matrix (e.g. a QR code) as vector squares. */
    public function matrix(array $matrix, float $x, float $y, float $moduleSize, array $rgb): self
    {
        $this->fill($rgb);
        $s = '';
        foreach ($matrix as $row => $cols) {
            $run = -1;
            $n = count($cols);
            for ($col = 0; $col <= $n; $col++) {
                $dark = $col < $n && $cols[$col];
                if ($dark && $run < 0) {
                    $run = $col;
                } elseif (!$dark && $run >= 0) {
                    $s .= sprintf("%.3F %.3F %.3F %.3F re\n", $x + $run * $moduleSize, $this->y($y + ($row + 1) * $moduleSize),
                        ($col - $run) * $moduleSize + 0.01, $moduleSize + 0.01);
                    $run = -1;
                }
            }
        }
        $this->stream .= $s . "f\n";
        return $this;
    }

    // ---------------------------------------------------------------------- output

    public function output(): string
    {
        $objects = [];
        $add = function (string $body) use (&$objects): int {
            $objects[] = $body;
            return count($objects);
        };
        $esc = fn (string $s): string => '(' . self::escape(self::encode($s)) . ')';

        $catalogId = $add('');           // 1, filled later
        $pagesId = $add('');             // 2
        $fontRefs = '';
        foreach ($this->usedFonts as $key => $res) {
            $id = $add('<< /Type /Font /Subtype /Type1 /BaseFont /' . self::FONTS[$key] . ' /Encoding /WinAnsiEncoding >>');
            $fontRefs .= "/{$res} {$id} 0 R ";
        }
        $gsRefs = '';
        foreach ($this->alphaStates as $alpha => $name) {
            $id = $add("<< /Type /ExtGState /ca {$alpha} /CA {$alpha} >>");
            $gsRefs .= "/{$name} {$id} 0 R ";
        }
        $shRefs = '';
        foreach ($this->shadings as $sh) {
            $f = fn (array $c) => sprintf('%.4F %.4F %.4F', $c[0] / 255, $c[1] / 255, $c[2] / 255);
            $id = $add(sprintf('<< /ShadingType 2 /ColorSpace /DeviceRGB /Coords [%.3F %.3F %.3F %.3F] /Function << /FunctionType 2 /Domain [0 1] /C0 [%s] /C1 [%s] /N 1 >> /Extend [true true] >>',
                $sh['coords'][0], $sh['coords'][1], $sh['coords'][2], $sh['coords'][3], $f($sh['from']), $f($sh['to'])));
            $shRefs .= "/{$sh['name']} {$id} 0 R ";
        }
        $content = function_exists('gzcompress') ? gzcompress($this->stream, 9) : $this->stream;
        $filter = function_exists('gzcompress') ? ' /Filter /FlateDecode' : '';
        $contentId = $add('<< /Length ' . strlen($content) . $filter . " >>\nstream\n" . $content . "\nendstream");
        $resources = "<< /ProcSet [/PDF /Text] /Font << {$fontRefs}>> /ExtGState << {$gsRefs}>> /Shading << {$shRefs}>> >>";
        $pageId = $add(sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>',
            $pagesId, $this->w, $this->h, $resources, $contentId));
        $objects[$pagesId - 1] = "<< /Type /Pages /Kids [{$pageId} 0 R] /Count 1 >>";
        $objects[$catalogId - 1] = "<< /Type /Catalog /Pages {$pagesId} 0 R /ViewerPreferences << /DisplayDocTitle true >> >>";
        $infoId = $add('<< /Title ' . $esc($this->info['Title'] ?? 'CYBER EXAM') . ' /Author (CYBER EXAM) /Creator (CYBER EXAM) /Producer (CYBER EXAM)'
            . ' /Subject ' . $esc($this->info['Subject'] ?? '') . ' /Keywords ' . $esc($this->info['Keywords'] ?? '')
            . ' /CreationDate (D:' . gmdate('YmdHis') . "Z) >>");

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $idHash = md5($pdf);
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root {$catalogId} 0 R /Info {$infoId} 0 R /ID [<{$idHash}> <{$idHash}>] >>\nstartxref\n{$xref}\n%%EOF\n";
        return $pdf;
    }
}
