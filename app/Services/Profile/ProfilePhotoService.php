<?php

namespace App\Services\Profile;

use App\Models\InvestorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The investor's profile picture. Kept on the private disk and only ever served through a signed-in route. Every upload
 * is decoded and re-encoded as a 400x400 square JPEG: that fixes phone rotation, drops hidden data such as GPS
 * location, caps the size, and means nothing the user uploaded is ever stored as-is.
 */
class ProfilePhotoService
{
    public const SIZE = 400;

    /** @return string|null the new path, or null if the file could not be read as an image */
    public function store(InvestorProfile $profile, UploadedFile $file): ?string
    {
        $jpeg = $this->normalise($file);
        if ($jpeg === null) {
            return null;
        }

        $path = 'profile-photos/'.Str::random(32).'.jpg';
        Storage::put($path, $jpeg);
        $old = $profile->photo_path;
        $profile->update(['photo_path' => $path]);
        if ($old) {
            Storage::delete($old);
        }

        return $path;
    }

    public function remove(InvestorProfile $profile): void
    {
        $old = $profile->photo_path;
        $profile->update(['photo_path' => null]);
        if ($old) {
            Storage::delete($old);
        }
    }

    private function normalise(UploadedFile $file): ?string
    {
        $real = $file->getRealPath();
        $info = @getimagesize($real);
        $source = match ($info[2] ?? null) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($real),
            IMAGETYPE_PNG => @imagecreatefrompng($real),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($real) : false,
            default => false,
        };
        if (! $source) {
            return null;
        }

        if (($info[2] ?? null) === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (@exif_read_data($real)['Orientation'] ?? 1);
            $source = match ((int) $orientation) {
                3 => imagerotate($source, 180, 0),
                6 => imagerotate($source, -90, 0),
                8 => imagerotate($source, 90, 0),
                default => $source,
            };
        }

        [$w, $h] = [imagesx($source), imagesy($source)];
        $side = min($w, $h);
        $out = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // transparent PNGs flatten onto white
        imagecopyresampled($out, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($out, null, 85);

        return (string) ob_get_clean();
    }
}
