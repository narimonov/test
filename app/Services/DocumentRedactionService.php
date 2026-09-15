<?php

namespace App\Services;

use FPDF;
use RuntimeException;

/**
 * Hujjat rasmini qayta ishlaydi:
 *   1. EXIF bo'yicha to'g'ri burchakka buradi va kichraytiradi
 *   2. Belgilangan joylarni qaytarib bo'lmaydigan qilib berkitadi
 *   3. Watermark qo'yadi
 *   4. PDF qilib o'raydi
 *
 * Berkitish uchun oddiy blur ishlatilmaydi — blur qilingan matnni tiklash
 * mumkin. O'rniga katta blokli piksellashtirish yoki to'liq qora to'rtburchak
 * ishlatiladi (config/documents.php dagi redaction_mode).
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
     * @param  string  $sourcePath    asl rasm (absolute path)
     * @param  string  $targetPdfPath yaratiladigan PDF (absolute path)
     * @param  array   $redactions    [['x'=>0.1,'y'=>0.2,'w'=>0.3,'h'=>0.1], ...] 0..1
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
            throw new RuntimeException("Fayl topilmadi: {$path}");
        }

        $info = @getimagesize($path);

        if (! $info) {
            throw new RuntimeException('Fayl rasm emas yoki buzilgan.');
        }

        switch ($info[2]) {
            case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG:  $image = imagecreatefrompng($path); break;
            case IMAGETYPE_WEBP: $image = imagecreatefromwebp($path); break;
            default:
                throw new RuntimeException('Faqat JPG, PNG va WEBP qabul qilinadi.');
        }

        if (! $image) {
            throw new RuntimeException('Rasmni ochib bo\'lmadi.');
        }

        return $this->autoOrient($image, $path, $info[2]);
    }

    /** Telefonda olingan rasm EXIF burchagi bilan keladi — to'g'rilaymiz. */
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
     * Bitta sohani berkitadi. Koordinatalar 0..1 oralig'ida (rasm o'lchamiga
     * nisbatan), shuning uchun frontend qanday masshtabda ko'rsatgani muhim emas.
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

        // Sohani ajratib olamiz, juda kichik o'lchamga siqamiz va qaytarib
        // cho'zamiz — bu asl piksellarni butunlay yo'qotadi.
        $blocks = max(3, (int) floor(min($w, $h) / 12));
        $tiny = imagecreatetruecolor($blocks, max(2, (int) round($blocks * $h / $w)));

        imagecopyresampled($tiny, $image, 0, 0, $x, $y, imagesx($tiny), imagesy($tiny), $w, $h);

        $patch = imagecreatetruecolor($w, $h);
        imagecopyresized($patch, $tiny, 0, 0, 0, 0, $w, $h, imagesx($tiny), imagesy($tiny));

        // Blok chegaralarini yumshatamiz, lekin ma'lumot allaqachon yo'qolgan.
        for ($i = 0; $i < 3; $i++) {
            imagefilter($patch, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopy($image, $patch, $x, $y, 0, 0, $w, $h);

        imagedestroy($tiny);
        imagedestroy($patch);

        // Berkitilgani ko'rinib tursin.
        imagerectangle($image, $x, $y, $x + $w, $y + $h, imagecolorallocate($image, 40, 40, 40));
    }

    /**
     * Butun rasm bo'ylab takrorlanuvchi qiya watermark.
     * Rasmning o'ziga yoziladi, shuning uchun PDF'dan olib tashlab bo'lmaydi.
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
                    // TTF yo'q bo'lsa ham watermark qo'yiladi (burchaksiz).
                    imagestring($layer, 5, $x, $y, $text, $white);
                }
            }
        }

        imagecopymerge($image, $layer, 0, 0, 0, 0, $width, $height, (int) round($settings['opacity'] * 100));
        imagedestroy($layer);
    }

    protected function toPdf(string $jpegPath, string $targetPath, int $width, int $height): void
    {
        // A4 ichiga sig'diramiz, nisbatni saqlagan holda.
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
