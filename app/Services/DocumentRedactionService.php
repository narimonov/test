<?php

namespace App\Services;

use FPDF;
use RuntimeException;

/**
 * Turns a photographed document into a shareable PDF:
 *   1. rotates by EXIF and scales down
 *   2. destroys the marked areas
 *   3. burns in a watermark
 *   4. wraps the result in a PDF
 *
 * Plain blur is not used — blurred text can be recovered. Instead the area is
 * collapsed to a handful of pixels and stretched back, or blacked out
 * entirely (redaction_mode in config/documents.php).
 */
class DocumentRedactionService
{
    /** @var array */
    protected $config;

    public function __construct(array $config = null)
    {
        $this->config = $config ?: config('documents');
    }

    /**
     * @param  string  $sourcePath     the original image, absolute path
     * @param  string  $targetPdfPath  where the PDF is written, absolute path
     * @param  array   $redactions     [['x'=>0.1,'y'=>0.2,'w'=>0.3,'h'=>0.1], ...] in 0..1
     */
    public function process(string $sourcePath, string $targetPdfPath, array $redactions = [], string $watermarkText = null): array
    {
        $image = $this->load($sourcePath);
        $image = $this->resize($image);

        $width = imagesx($image);
        $height = imagesy($image);

        foreach ($redactions as $box) {
            $this->redact($image, $box, $width, $height);
        }

        $this->watermark($image, $watermarkText ?: $this->config['watermark_text']);

        $jpegPath = tempnam(sys_get_temp_dir(), 'doc_') . '.jpg';
        imagejpeg($image, $jpegPath, $this->config['jpeg_quality']);
        imagedestroy($image);

        try {
            $this->toPdf($jpegPath, $targetPdfPath, $width, $height);
        } finally {
            @unlink($jpegPath);
        }

        return ['width' => $width, 'height' => $height, 'redactions' => count($redactions)];
    }

    // ------------------------------------------------------------------

    protected function load(string $path)
    {
        if (! is_file($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $info = @getimagesize($path);

        if (! $info) {
            throw new RuntimeException('That file is not an image, or it is corrupt.');
        }

        switch ($info[2]) {
            case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG:  $image = imagecreatefrompng($path); break;
            case IMAGETYPE_WEBP: $image = imagecreatefromwebp($path); break;
            default:
                throw new RuntimeException('Only JPG, PNG and WEBP are accepted.');
        }

        if (! $image) {
            throw new RuntimeException('The image could not be opened.');
        }

        return $this->autoOrient($image, $path, $info[2]);
    }

    /** Phone photos carry an EXIF orientation; straighten it. */
    protected function autoOrient($image, string $path, int $type)
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? 1;

        $angles = [3 => 180, 6 => -90, 8 => 90];

        if (isset($angles[$orientation])) {
            $rotated = imagerotate($image, $angles[$orientation], 0);

            if ($rotated) {
                imagedestroy($image);

                return $rotated;
            }
        }

        return $image;
    }

    protected function resize($image)
    {
        $max = $this->config['max_width'];
        $width = imagesx($image);

        if ($width <= $max) {
            return $image;
        }

        $height = (int) round(imagesy($image) * ($max / $width));
        $resized = imagecreatetruecolor($max, $height);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $max, $height, $width, imagesy($image));
        imagedestroy($image);

        return $resized;
    }

    /**
     * Destroys one area. Coordinates are relative to the image, so whatever
     * scale the frontend displayed at does not matter.
     */
    protected function redact($image, array $box, int $width, int $height): void
    {
        $x = (int) round(max(0, min(1, $box['x'] ?? 0)) * $width);
        $y = (int) round(max(0, min(1, $box['y'] ?? 0)) * $height);
        $w = (int) round(max(0, min(1, $box['w'] ?? 0)) * $width);
        $h = (int) round(max(0, min(1, $box['h'] ?? 0)) * $height);

        $w = min($w, $width - $x);
        $h = min($h, $height - $y);

        if ($w < 2 || $h < 2) {
            return;
        }

        if ($this->config['redaction_mode'] === 'blackout') {
            imagefilledrectangle($image, $x, $y, $x + $w, $y + $h, imagecolorallocate($image, 0, 0, 0));

            return;
        }

        // Squeeze the area down to a few pixels and stretch it back: the
        // original pixels are gone, not merely smeared.
        $blocks = max(3, (int) floor(min($w, $h) / 12));
        $tiny = imagecreatetruecolor($blocks, max(2, (int) round($blocks * $h / $w)));

        imagecopyresampled($tiny, $image, 0, 0, $x, $y, imagesx($tiny), imagesy($tiny), $w, $h);

        $patch = imagecreatetruecolor($w, $h);
        imagecopyresized($patch, $tiny, 0, 0, 0, 0, $w, $h, imagesx($tiny), imagesy($tiny));

        // Soften the block edges; the information is already gone.
        for ($i = 0; $i < 3; $i++) {
            imagefilter($patch, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopy($image, $patch, $x, $y, 0, 0, $w, $h);

        imagedestroy($tiny);
        imagedestroy($patch);

        // Make it obvious something was removed.
        imagerectangle($image, $x, $y, $x + $w, $y + $h, imagecolorallocate($image, 40, 40, 40));
    }

    /**
     * A tiled diagonal watermark, burned into the image itself so it cannot
     * be lifted out of the PDF.
     */
    protected function watermark($image, string $text): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $settings = $this->config['watermark'];

        $layer = imagecreatetruecolor($width, $height);
        imagealphablending($layer, false);
        imagefilledrectangle($layer, 0, 0, $width, $height, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        imagealphablending($layer, true);

        $white = imagecolorallocate($layer, 255, 255, 255);
        $fontSize = max(10, (int) round($width * $settings['font_size_ratio']));
        $font = $this->config['font_path'];
        $useTtf = function_exists('imagettftext') && is_file($font);

        $stepX = (int) max(120, $fontSize * (mb_strlen($text) + 6) * 0.6);
        $stepY = (int) max(80, $fontSize * 4);

        for ($y = -$stepY; $y < $height + $stepY; $y += $stepY) {
            for ($x = -$stepX; $x < $width + $stepX; $x += $stepX) {
                if ($useTtf) {
                    imagettftext($layer, $fontSize, $settings['angle'], $x, $y, $white, $font, $text);
                } else {
                    // Without a TTF we still watermark, just without rotation.
                    imagestring($layer, 5, $x, $y, $text, $white);
                }
            }
        }

        imagecopymerge($image, $layer, 0, 0, 0, 0, $width, $height, (int) round($settings['opacity'] * 100));
        imagedestroy($layer);
    }

    protected function toPdf(string $jpegPath, string $targetPath, int $width, int $height): void
    {
        // Fit inside A4, keeping the aspect ratio.
        $pageWidth = 210;
        $pageHeight = 297;
        $margin = 10;

        $usableWidth = $pageWidth - 2 * $margin;
        $usableHeight = $pageHeight - 2 * $margin;

        $scale = min($usableWidth / $width, $usableHeight / $height);
        $drawWidth = $width * $scale;
        $drawHeight = $height * $scale;

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->Image(
            $jpegPath,
            $margin + ($usableWidth - $drawWidth) / 2,
            $margin + ($usableHeight - $drawHeight) / 2,
            $drawWidth,
            $drawHeight,
            'JPG'
        );

        $directory = dirname($targetPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $pdf->Output('F', $targetPath);
    }
}
