<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Pdf\PdfDocument;

/**
 * Renders the official CYBER EXAM certificate (A4 landscape, fully vector).
 * The design is original to CYBER EXAM: navy frame, electric-blue/cyan accents,
 * graphic signature, official seal and verification QR code.
 */
final class CertificatePdf
{
    private const NAVY = [8, 13, 28];
    private const INK = [15, 23, 42];
    private const BLUE = [37, 99, 235];
    private const ELECTRIC = [47, 107, 255];
    private const CYAN = [34, 211, 238];
    private const GRAY = [100, 116, 139];
    private const LIGHT = [226, 232, 240];
    private const PAPER = [251, 252, 254];
    private const WHITE = [255, 255, 255];

    public function render(array $c): string
    {
        $pdf = new PdfDocument();
        $W = $pdf->width();
        $H = $pdf->height();
        $cx = $W / 2;
        $pdf->setInfo('CYBER EXAM Certificate ' . $c['certificate_code'], $c['certification_name'] . ' — Certificate of Achievement', 'CYBER EXAM, certificate, ' . $c['certificate_code']);

        // Frame
        $pdf->fill(self::NAVY)->rect(0, 0, $W, $H);
        $pdf->gradientRect(0, 0, $W, 5, self::ELECTRIC, self::CYAN);
        $pdf->gradientRect(0, $H - 5, $W, 5, self::CYAN, self::ELECTRIC);
        $pdf->fill(self::PAPER)->roundedRect(22, 22, $W - 44, $H - 44, 10);
        $pdf->stroke(self::LIGHT)->lineWidth(0.8)->roundedRect(34, 34, $W - 68, $H - 68, 6, 'S');

        // Geometric corner accents
        $pdf->save()->alpha(0.95);
        $pdf->fill(self::ELECTRIC)->polygon([[22, 22 + 10], [22 + 10, 22], [150, 22], [22, 150]]);
        $pdf->fill(self::CYAN)->polygon([[22, 160], [160, 22], [172, 22], [22, 172]]);
        $pdf->fill(self::ELECTRIC)->polygon([[$W - 22, $H - 32], [$W - 32, $H - 22], [$W - 120, $H - 22], [$W - 22, $H - 120]]);
        $pdf->fill(self::CYAN)->polygon([[$W - 22, $H - 130], [$W - 130, $H - 22], [$W - 142, $H - 22], [$W - 22, $H - 142]]);
        $pdf->restore();
        // Faint background grid of dots for texture
        $pdf->save()->alpha(0.07)->fill(self::BLUE);
        for ($gx = 60; $gx < $W - 50; $gx += 22) {
            for ($gy = 60; $gy < 128; $gy += 22) /* dot rows */ {
                $pdf->circle($gx + (intdiv($gy, 22) % 2) * 11, $gy, 0.9);
            }
        }
        $pdf->restore();

        // Brand
        $this->logo($pdf, $cx - 78, 58, 26);
        $pdf->text($cx - 44, 77, 'CYBER EXAM', 'helvetica-bold', 17, self::INK, 'L', 3.2);

        // Title
        $pdf->text($cx, 132, 'CERTIFICATE OF ACHIEVEMENT', 'helvetica-bold', 27, self::INK, 'C', 2.6);
        $pdf->gradientRect($cx - 90, 146, 180, 2.2, self::ELECTRIC, self::CYAN);
        $pdf->save()->fill(self::CYAN)->polygon([[$cx, 142.5], [$cx + 5, 147.1], [$cx, 151.7], [$cx - 5, 147.1]])->restore();

        $pdf->text($cx, 182, 'This certificate is proudly presented to', 'times-italic', 15, self::GRAY, 'C');
        $pdf->fitText($cx, 232, $c['candidate_name'], 'times-bolditalic', 42, $W - 240, self::NAVY, 'C', 20);
        $pdf->stroke(self::LIGHT)->lineWidth(1)->line($cx - 210, 246, $cx + 210, 246);

        $pdf->text($cx, 274, 'for successfully completing the', 'times-italic', 14, self::GRAY, 'C');
        $pdf->fitText($cx, 304, strtoupper($c['certification_name']) . ' CERTIFICATION EXAM', 'helvetica-bold', 19, $W - 220, self::BLUE, 'C', 11, 1.6);

        // Details strip
        $issued = (new \DateTimeImmutable($c['issued_at'], new \DateTimeZone('UTC')))->format('j F Y');
        $status = $c['status'] === 'revoked' ? 'REVOKED' : 'VERIFIED';
        $cols = [
            ['SCORE', rtrim(rtrim(number_format((float) $c['score_percent'], 2, '.', ''), '0'), '.') . '%'],
            ['DATE', $issued],
            ['CERTIFICATE ID', $c['certificate_code']],
            ['STATUS', $status],
        ];
        $stripX = 110;
        $stripW = $W - 220;
        $pdf->fill([241, 245, 251])->roundedRect($stripX, 326, $stripW, 50, 8);
        $colW = $stripW / 4;
        foreach ($cols as $i => [$label, $value]) {
            $x = $stripX + $colW * $i + $colW / 2;
            if ($i > 0) {
                $pdf->stroke(self::LIGHT)->lineWidth(0.8)->line($stripX + $colW * $i, 336, $stripX + $colW * $i, 366);
            }
            $pdf->text($x, 344, $label, 'helvetica', 7.5, self::GRAY, 'C', 1.6);
            $color = $label === 'STATUS' ? ($status === 'REVOKED' ? [220, 38, 38] : [5, 150, 105]) : self::INK;
            $pdf->fitText($x, 364, $value, $label === 'CERTIFICATE ID' ? 'courier-bold' : 'helvetica-bold', 12.5, $colW - 14, $color, 'C', 7);
        }

        // Signature (left)
        $sx = 150;
        $pdf->text($sx, 452, 'Cyber Exam', 'times-bolditalic', 34, self::NAVY, 'C');
        $pdf->save()->stroke(self::ELECTRIC)->lineWidth(1.4);
        $pdf->path([['M', $sx - 88, 462], ['C', $sx - 40, 470, $sx + 10, 452, $sx + 48, 458], ['C', $sx + 70, 461, $sx + 84, 456, $sx + 94, 449]], 'S');
        $pdf->restore();
        $pdf->stroke(self::GRAY)->lineWidth(0.7)->line($sx - 95, 476, $sx + 95, 476);
        $pdf->text($sx, 491, 'CYBER EXAM', 'helvetica-bold', 9, self::INK, 'C', 2);
        $pdf->text($sx, 503, 'Authorized Signature', 'helvetica', 7.5, self::GRAY, 'C', 0.6);

        // Seal (centre)
        $this->seal($pdf, $cx, 458, 58, (int) substr($c['issued_at'], 0, 4));

        // QR code (right)
        $verifyUrl = CertificateService::verifyUrl($c['certificate_code']);
        $qr = new QrCode($verifyUrl);
        $qrSize = 84;
        $module = $qrSize / $qr->size;
        $qx = $W - 168 - $qrSize / 2;
        $qy = 404;
        $pdf->fill(self::WHITE)->stroke(self::LIGHT)->lineWidth(0.8)->roundedRect($qx - 7, $qy - 7, $qrSize + 14, $qrSize + 14, 6, 'FS');
        $pdf->matrix($qr->matrix(), $qx, $qy, $module, self::NAVY);
        $pdf->text($W - 168, $qy + $qrSize + 20, 'SCAN TO VERIFY', 'helvetica-bold', 7.5, self::INK, 'C', 1.6);

        // Footer
        $verifyPage = (string) preg_replace('#^https?://#', '', url('verify.php'));
        $pdf->text($cx, $H - 44, 'Verify the authenticity of this certificate at ' . $verifyPage . ' using ID ' . $c['certificate_code'], 'helvetica', 7.5, self::GRAY, 'C', 0.2);

        if ($c['status'] === 'revoked') {
            $this->revokedStamp($pdf, $W, $H);
        }
        return $pdf->output();
    }

    /** CYBER EXAM brand mark: gradient tile with an open ring and a check stroke. */
    private function logo(PdfDocument $pdf, float $x, float $y, float $s): void
    {
        $pdf->gradientRect($x, $y, $s, $s, self::ELECTRIC, self::CYAN, true, $s * 0.28);
        $c = $s / 2;
        $pdf->save()->stroke(self::WHITE)->lineWidth($s * 0.09);
        // open "C" ring
        $r = $s * 0.28;
        $segments = [['M', $x + $c + $r * cos(deg2rad(-40)), $y + $c + $r * sin(deg2rad(-40))]];
        $steps = 8;
        $start = -40;
        $end = -320;
        for ($i = 1; $i <= $steps; $i++) {
            $a0 = deg2rad($start + ($end - $start) * ($i - 1) / $steps);
            $a1 = deg2rad($start + ($end - $start) * $i / $steps);
            $k = 4 / 3 * tan(($a1 - $a0) / 4);
            $segments[] = ['C',
                $x + $c + $r * (cos($a0) - $k * sin($a0)), $y + $c + $r * (sin($a0) + $k * cos($a0)),
                $x + $c + $r * (cos($a1) + $k * sin($a1)), $y + $c + $r * (sin($a1) - $k * cos($a1)),
                $x + $c + $r * cos($a1), $y + $c + $r * sin($a1)];
        }
        $pdf->path($segments, 'S');
        $pdf->restore();
        $pdf->fill(self::WHITE)->circle($x + $c + $r * 0.95, $y + $c, $s * 0.07);
    }

    private function seal(PdfDocument $pdf, float $cx, float $cy, float $r, int $year): void
    {
        // Rosette edge
        $points = [];
        $teeth = 44;
        for ($i = 0; $i < $teeth * 2; $i++) {
            $a = M_PI * $i / $teeth;
            $rr = $i % 2 === 0 ? $r : $r - 4.5;
            $points[] = [$cx + $rr * cos($a), $cy + $rr * sin($a)];
        }
        $pdf->fill(self::ELECTRIC)->polygon($points);
        $pdf->fill(self::NAVY)->circle($cx, $cy, $r - 7);
        $pdf->stroke(self::CYAN)->lineWidth(0.9)->circle($cx, $cy, $r - 10, 'S');
        $pdf->stroke([60, 90, 160])->lineWidth(0.6)->circle($cx, $cy, $r - 25, 'S');

        $pdf->textOnArc($cx, $cy, $r - 21.5, 'CYBER EXAM  •  OFFICIAL SEAL', 'helvetica-bold', 7.4, self::WHITE, 90, true, 0.9);
        $pdf->textOnArc($cx, $cy, $r - 14.5, 'VERIFIED CERTIFICATION', 'helvetica-bold', 6.6, self::CYAN, 270, false, 0.9);

        // Centre emblem
        $pdf->gradientRect($cx - 15, $cy - 19, 30, 30, self::ELECTRIC, self::CYAN, true, 8);
        $pdf->save()->stroke(self::WHITE)->lineWidth(2.6);
        $pdf->path([['M', $cx - 7.5, $cy - 4], ['L', $cx - 2, $cy + 1.8], ['L', $cx + 8, $cy - 9.5]], 'S');
        $pdf->restore();
        $pdf->text($cx, $cy + 24, (string) $year, 'helvetica-bold', 8, self::WHITE, 'C', 1.5);
        foreach ([-1, 1] as $side) {
            $sx = $cx + $side * 23;
            $pdf->fill(self::CYAN)->polygon([[$sx, $cy + 18], [$sx + 2.2, $cy + 20.8], [$sx, $cy + 23.6], [$sx - 2.2, $cy + 20.8]]);
        }
    }

    private function revokedStamp(PdfDocument $pdf, float $W, float $H): void
    {
        $pdf->save()->alpha(0.18)->fill([220, 38, 38]);
        $pdf->rect(22, $H / 2 - 40, $W - 44, 80);
        $pdf->restore();
        $pdf->text($W / 2, $H / 2 + 16, 'REVOKED', 'helvetica-bold', 54, [185, 28, 28], 'C', 12);
    }
}
