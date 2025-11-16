<?php
// includes/image_helpers.php
// Simple image thumbnail and WebP generator using GD when available.

function create_image_thumbnail($srcPath, $destPath, $maxWidth = 300, $maxHeight = 300, $quality = 85) {
    if (!file_exists($srcPath)) return false;
    $info = getimagesize($srcPath);
    if (!$info) return false;
    $mime = $info['mime'];

    // SVG - copy original
    if ($mime === 'image/svg+xml') {
        return copy($srcPath, $destPath);
    }

    switch ($mime) {
        case 'image/jpeg':
            if (!function_exists('imagecreatefromjpeg')) return copy($srcPath, $destPath);
            $img = imagecreatefromjpeg($srcPath);
            break;
        case 'image/png':
            if (!function_exists('imagecreatefrompng')) return copy($srcPath, $destPath);
            $img = imagecreatefrompng($srcPath);
            break;
        case 'image/gif':
            if (!function_exists('imagecreatefromgif')) return copy($srcPath, $destPath);
            $img = imagecreatefromgif($srcPath);
            break;
        default:
            return copy($srcPath, $destPath);
    }

    if (!$img) return false;

    $width = imagesx($img);
    $height = imagesy($img);

    // Calculate new size preserving aspect ratio
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    if ($ratio >= 1) {
        // Image smaller than limits - copy original
        imagedestroy($img);
        return copy($srcPath, $destPath);
    }

    $newWidth = (int)round($width * $ratio);
    $newHeight = (int)round($height * $ratio);

    $thumb = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve PNG transparency
    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $saved = false;
    if ($mime === 'image/jpeg') {
        $saved = imagejpeg($thumb, $destPath, $quality);
    } elseif ($mime === 'image/png') {
        // quality for png is 0-9 (compression level), map quality 0-100 to 0-9
        $pngLevel = (int)round((100 - $quality) / 11);
        $saved = imagepng($thumb, $destPath, max(0, min(9, $pngLevel)));
    } elseif ($mime === 'image/gif') {
        $saved = imagegif($thumb, $destPath);
    }

    imagedestroy($img);
    imagedestroy($thumb);

    // Optionally create WebP if supported
    if ($saved && function_exists('imagewebp')) {
        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $destPath);
        @imagewebp(imagecreatefromstring(file_get_contents($destPath)), $webpPath, 80);
    }

    return $saved;
}

?>