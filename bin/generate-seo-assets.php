#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * One-off generator for SEO preview images and favicons from public/logo.png.
 * Run: php bin/generate-seo-assets.php
 */

$root = dirname(__DIR__);
$logoPath = $root.'/public/logo.png';
$outputDir = $root.'/public/images/seo';

if (!is_file($logoPath)) {
    fwrite(STDERR, "Missing {$logoPath}\n");
    exit(1);
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Cannot create {$outputDir}\n");
    exit(1);
}

$logo = imagecreatefrompng($logoPath);
if ($logo === false) {
    fwrite(STDERR, "Cannot read logo PNG\n");
    exit(1);
}

imagesavealpha($logo, true);

$fontRegular = '/System/Library/Fonts/Supplemental/Arial.ttf';
$fontBold = '/System/Library/Fonts/Supplemental/Arial Bold.ttf';
if (!is_file($fontRegular)) {
    $fontRegular = '/Library/Fonts/Arial.ttf';
    $fontBold = '/Library/Fonts/Arial Bold.ttf';
}

function hex(string $hex): array
{
    $hex = ltrim($hex, '#');

    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function color(GdImage $image, string $hex, int $alpha = 0): int
{
    [$r, $g, $b] = hex($hex);

    return imagecolorallocatealpha($image, $r, $g, $b, $alpha);
}

function copyLogoCircle(GdImage $canvas, GdImage $logo, int $cx, int $cy, int $diameter): void
{
    $size = imagesx($logo);
    $scaled = imagecreatetruecolor($diameter, $diameter);
    imagesavealpha($scaled, true);
    $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
    imagefill($scaled, 0, 0, $transparent);
    imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $diameter, $diameter, $size, $size);

    $mask = imagecreatetruecolor($diameter, $diameter);
    imagesavealpha($mask, true);
    imagefill($mask, 0, 0, imagecolorallocatealpha($mask, 0, 0, 0, 127));
    $white = imagecolorallocate($mask, 255, 255, 255);
    imagefilledellipse($mask, (int) ($diameter / 2), (int) ($diameter / 2), $diameter, $diameter, $white);

    for ($y = 0; $y < $diameter; ++$y) {
        for ($x = 0; $x < $diameter; ++$x) {
            $maskAlpha = (imagecolorat($mask, $x, $y) >> 24) & 0x7F;
            if ($maskAlpha === 127) {
                imagesetpixel($scaled, $x, $y, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
            }
        }
    }

    imagedestroy($mask);
    imagecopy($canvas, $scaled, $cx - (int) ($diameter / 2), $cy - (int) ($diameter / 2), 0, 0, $diameter, $diameter);
}

function savePng(GdImage $image, string $path): void
{
    if (!imagepng($image, $path, 6)) {
        throw new RuntimeException("Cannot write {$path}");
    }
}

function resizeSquare(GdImage $logo, int $size): GdImage
{
    $canvas = imagecreatetruecolor($size, $size);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);
    $src = imagesx($logo);
    imagecopyresampled($canvas, $logo, 0, 0, 0, 0, $size, $size, $src, $src);

    return $canvas;
}

// OG preview: 1200×630
$og = imagecreatetruecolor(1200, 630);
$bg = color($og, '#f7f5f0');
imagefilledrectangle($og, 0, 0, 1200, 630, $bg);
imagefilledrectangle($og, 0, 0, 1200, 8, color($og, '#3d6b4f'));

copyLogoCircle($og, $logo, 220, 315, 300);

$titleColor = color($og, '#1a2420');
$mutedColor = color($og, '#66756c');
$accentColor = color($og, '#3d6b4f');

imagettftext($og, 72, 0, 420, 260, $titleColor, $fontBold, 'Ganesha');
imagettftext($og, 34, 0, 422, 320, $mutedColor, $fontRegular, 'вегетарианское питание');
imagettftext($og, 26, 0, 422, 380, $accentColor, $fontRegular, 'Екатеринбург · самовывоз «Хануман»');

savePng($og, $outputDir.'/og-image.png');

// Apple touch icon: 180×180
$touch = imagecreatetruecolor(180, 180);
$touchBg = color($touch, '#ffffff');
imagefilledrectangle($touch, 0, 0, 180, 180, $touchBg);
copyLogoCircle($touch, $logo, 90, 90, 160);
savePng($touch, $outputDir.'/apple-touch-icon.png');

foreach ([32, 16] as $size) {
    $icon = resizeSquare($logo, $size);
    savePng($icon, $outputDir.'/favicon-'.$size.'x'.$size.'.png');
}

echo "Generated SEO assets in public/images/seo/\n";
