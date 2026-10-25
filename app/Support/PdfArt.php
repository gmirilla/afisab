<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The artwork shared by the receipt and the certificate (public/image/receipt, rebuilt from resources/receipt-assets by
 * build.sh), as data URIs: embedding keeps PDFs working wherever the web root sits, including the cPanel layout.
 * A missing file gives '' and the templates fall back to plain text.
 */
class PdfArt
{
    private const NAMED = ['lockup', 'banner', 'banner-certificate', 'footer', 'footer-wide'];

    private const ICONS = ['user', 'coins', 'naira', 'calendar', 'target', 'document', 'wallet', 'pin', 'phone', 'mail', 'globe'];

    /** @return array<string, string> name => data URI ('' if the file is missing) */
    public static function assets(): array
    {
        $out = [];
        foreach (array_merge(self::NAMED, self::ICONS) as $name) {
            $file = public_path('image/receipt/'.(in_array($name, self::NAMED, true) ? $name : "icon-{$name}").'.png');
            $out[$name] = is_file($file) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($file)) : '';
        }

        return $out;
    }

    /** A stored (private disk) image such as a signature or stamp as a data URI, or null. */
    public static function stored(?string $path): ?string
    {
        return $path && Storage::exists($path) ? 'data:image/png;base64,'.base64_encode(Storage::get($path)) : null;
    }
}
