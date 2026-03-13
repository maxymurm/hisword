<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VerseImageController extends Controller
{
    /**
     * POST /verse-image/generate
     *
     * Generate a verse image with the specified template and options.
     * Returns base64 PNG data.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verse_text' => 'required|string|max:1000',
            'reference' => 'required|string|max:100',
            'template' => 'required|string|in:minimal,nature,gradient,dark,watercolor',
            'aspect_ratio' => 'required|string|in:1:1,9:16,16:9',
            'font_size' => 'nullable|integer|min:16|max:72',
            'font_family' => 'nullable|string|in:serif,sans,mono',
            'text_color' => 'nullable|string|max:7',
            'watermark' => 'nullable|string|max:50',
            'retina' => 'nullable|boolean',
        ]);

        $template = $validated['template'];
        $aspectRatio = $validated['aspect_ratio'];
        $fontSize = $validated['font_size'] ?? 32;
        $fontFamily = $validated['font_family'] ?? 'serif';
        $textColor = $validated['text_color'] ?? null;
        $watermark = $validated['watermark'] ?? null;
        $retina = $validated['retina'] ?? true;

        // Calculate dimensions
        [$width, $height] = $this->getDimensions($aspectRatio, $retina);

        // Create image
        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            return response()->json(['error' => 'Image creation failed'], 500);
        }

        // Enable alpha blending
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Apply template background
        $this->applyTemplate($image, $template, $width, $height);

        // Determine text color
        $textRgb = $this->hexToRgb($textColor ?? $this->getTemplateTextColor($template));
        $color = imagecolorallocate($image, $textRgb[0], $textRgb[1], $textRgb[2]);

        // Font path (use built-in GD font as fallback)
        $fontPath = $this->getFontPath($fontFamily);

        // Scale font size for retina
        $scaledFontSize = $retina ? $fontSize * 2 : $fontSize;

        // Render verse text with word wrapping
        $this->renderText(
            $image,
            $validated['verse_text'],
            $scaledFontSize,
            $color,
            $fontPath,
            $width,
            $height,
            0.15 // top margin ratio
        );

        // Render reference
        $refFontSize = (int) ($scaledFontSize * 0.6);
        $refColor = imagecolorallocatealpha(
            $image,
            $textRgb[0],
            $textRgb[1],
            $textRgb[2],
            30 // slight transparency
        );

        $this->renderReference(
            $image,
            $validated['reference'],
            $refFontSize,
            $refColor,
            $fontPath,
            $width,
            $height
        );

        // Watermark
        if ($watermark) {
            $this->renderWatermark($image, $watermark, $width, $height, $textRgb);
        }

        // Output as PNG
        ob_start();
        imagepng($image, null, 6);
        $data = ob_get_clean();
        imagedestroy($image);

        return response()->json([
            'image' => 'data:image/png;base64,' . base64_encode($data),
            'width' => $width,
            'height' => $height,
            'format' => 'png',
        ]);
    }

    /**
     * GET /verse-image/templates
     *
     * Return available template metadata.
     */
    public function templates(): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'id' => 'minimal',
                    'name' => 'Minimal',
                    'description' => 'Clean white background',
                    'preview_colors' => ['#ffffff', '#1a1a2e'],
                ],
                [
                    'id' => 'nature',
                    'name' => 'Nature',
                    'description' => 'Soft green gradient',
                    'preview_colors' => ['#2d5016', '#4a7c59'],
                ],
                [
                    'id' => 'gradient',
                    'name' => 'Gradient',
                    'description' => 'Indigo to purple gradient',
                    'preview_colors' => ['#4f46e5', '#7c3aed'],
                ],
                [
                    'id' => 'dark',
                    'name' => 'Dark',
                    'description' => 'Dark background with light text',
                    'preview_colors' => ['#0f172a', '#1e293b'],
                ],
                [
                    'id' => 'watercolor',
                    'name' => 'Watercolor',
                    'description' => 'Warm tones',
                    'preview_colors' => ['#fef3c7', '#f59e0b'],
                ],
            ],
        ]);
    }

    // ── Private helpers ─────────────────────────────────────────

    private function getDimensions(string $ratio, bool $retina): array
    {
        $multiplier = $retina ? 2 : 1;

        return match ($ratio) {
            '1:1' => [1080 * $multiplier / ($retina ? 2 : 1), 1080 * $multiplier / ($retina ? 2 : 1)],
            '9:16' => [1080 * $multiplier / ($retina ? 2 : 1), 1920 * $multiplier / ($retina ? 2 : 1)],
            '16:9' => [1920 * $multiplier / ($retina ? 2 : 1), 1080 * $multiplier / ($retina ? 2 : 1)],
            default => [1080, 1080],
        };
    }

    private function applyTemplate($image, string $template, int $width, int $height): void
    {
        switch ($template) {
            case 'minimal':
                $bg = imagecolorallocate($image, 255, 255, 255);
                imagefill($image, 0, 0, $bg);
                break;

            case 'nature':
                for ($y = 0; $y < $height; $y++) {
                    $ratio = $y / $height;
                    $r = (int) (45 + ($ratio * 30));
                    $g = (int) (80 + ($ratio * 44));
                    $b = (int) (22 + ($ratio * 67));
                    $col = imagecolorallocate($image, $r, $g, $b);
                    imageline($image, 0, $y, $width, $y, $col);
                }
                break;

            case 'gradient':
                for ($y = 0; $y < $height; $y++) {
                    $ratio = $y / $height;
                    $r = (int) (79 + ($ratio * 45));
                    $g = (int) (70 - ($ratio * 12));
                    $b = (int) (229 + ($ratio * 8));
                    $col = imagecolorallocate($image, min(255, $r), min(255, max(0, $g)), min(255, $b));
                    imageline($image, 0, $y, $width, $y, $col);
                }
                break;

            case 'dark':
                for ($y = 0; $y < $height; $y++) {
                    $ratio = $y / $height;
                    $r = (int) (15 + ($ratio * 15));
                    $g = (int) (23 + ($ratio * 18));
                    $b = (int) (42 + ($ratio * 17));
                    $col = imagecolorallocate($image, $r, $g, $b);
                    imageline($image, 0, $y, $width, $y, $col);
                }
                break;

            case 'watercolor':
                for ($y = 0; $y < $height; $y++) {
                    $ratio = $y / $height;
                    $r = (int) (254 - ($ratio * 9));
                    $g = (int) (243 - ($ratio * 85));
                    $b = (int) (199 - ($ratio * 188));
                    $col = imagecolorallocate($image, min(255, $r), min(255, max(0, $g)), min(255, max(0, $b)));
                    imageline($image, 0, $y, $width, $y, $col);
                }
                break;
        }
    }

    private function getTemplateTextColor(string $template): string
    {
        return match ($template) {
            'minimal' => '#1a1a2e',
            'nature' => '#ffffff',
            'gradient' => '#ffffff',
            'dark' => '#e2e8f0',
            'watercolor' => '#78350f',
            default => '#1a1a2e',
        };
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function getFontPath(string $family): ?string
    {
        // Use system fonts or bundled fonts
        $fontDir = resource_path('fonts');

        $fontMap = [
            'serif' => 'DejaVuSerif.ttf',
            'sans' => 'DejaVuSans.ttf',
            'mono' => 'DejaVuSansMono.ttf',
        ];

        $fontFile = $fontDir . '/' . ($fontMap[$family] ?? 'DejaVuSans.ttf');

        return file_exists($fontFile) ? $fontFile : null;
    }

    private function renderText($image, string $text, int $fontSize, $color, ?string $fontPath, int $width, int $height, float $topMargin): void
    {
        $padding = (int) ($width * 0.1);
        $maxWidth = $width - ($padding * 2);
        $startY = (int) ($height * $topMargin);

        if ($fontPath) {
            $words = explode(' ', $text);
            $lines = [];
            $currentLine = '';

            foreach ($words as $word) {
                $testLine = $currentLine ? "$currentLine $word" : $word;
                $bbox = imagettfbbox($fontSize, 0, $fontPath, $testLine);
                $lineWidth = abs($bbox[2] - $bbox[0]);

                if ($lineWidth > $maxWidth && $currentLine) {
                    $lines[] = $currentLine;
                    $currentLine = $word;
                } else {
                    $currentLine = $testLine;
                }
            }
            if ($currentLine) {
                $lines[] = $currentLine;
            }

            $lineHeight = (int) ($fontSize * 1.6);
            $totalTextHeight = count($lines) * $lineHeight;

            // Center vertically
            $y = max($startY, (int) (($height - $totalTextHeight) / 2));

            foreach ($lines as $line) {
                $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
                $lineWidth = abs($bbox[2] - $bbox[0]);
                $x = (int) (($width - $lineWidth) / 2);
                imagettftext($image, $fontSize, 0, $x, $y + $fontSize, $color, $fontPath, $line);
                $y += $lineHeight;
            }
        } else {
            // GD built-in font fallback
            $font = 5; // largest built-in font
            $charWidth = imagefontwidth($font);
            $charsPerLine = (int) ($maxWidth / $charWidth);
            $wrapped = wordwrap($text, $charsPerLine, "\n", true);
            $lines = explode("\n", $wrapped);

            $lineHeight = imagefontheight($font) + 4;
            $y = (int) (($height - count($lines) * $lineHeight) / 2);

            foreach ($lines as $line) {
                $lineWidth = strlen($line) * $charWidth;
                $x = (int) (($width - $lineWidth) / 2);
                imagestring($image, $font, $x, $y, $line, $color);
                $y += $lineHeight;
            }
        }
    }

    private function renderReference($image, string $reference, int $fontSize, $color, ?string $fontPath, int $width, int $height): void
    {
        $y = (int) ($height * 0.82);

        if ($fontPath) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, "— $reference");
            $lineWidth = abs($bbox[2] - $bbox[0]);
            $x = (int) (($width - $lineWidth) / 2);
            imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, "— $reference");
        } else {
            $font = 4;
            $charWidth = imagefontwidth($font);
            $text = "-- $reference";
            $lineWidth = strlen($text) * $charWidth;
            $x = (int) (($width - $lineWidth) / 2);
            imagestring($image, $font, $x, $y, $text, $color);
        }
    }

    private function renderWatermark($image, string $watermark, int $width, int $height, array $textRgb): void
    {
        $color = imagecolorallocatealpha($image, $textRgb[0], $textRgb[1], $textRgb[2], 80);
        $font = 2;
        $charWidth = imagefontwidth($font);
        $wmWidth = strlen($watermark) * $charWidth;
        $x = $width - $wmWidth - 10;
        $y = $height - imagefontheight($font) - 10;
        imagestring($image, $font, $x, $y, $watermark, $color);
    }
}
