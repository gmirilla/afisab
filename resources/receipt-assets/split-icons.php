<?php
// Cuts the icon sheets (icons-white.png, icons-green.png) into one PNG per icon. Run by build.sh.
$dir = __DIR__.'/../../public/image/receipt';
$names = ['white' => ['user', 'coins', 'naira', 'calendar', 'target', 'document', 'wallet'], 'green' => [7 => 'pin', 8 => 'phone', 9 => 'mail', 10 => 'globe']];
foreach ($names as $sheet => $list) {
    $src = imagecreatefrompng("$dir/icons-$sheet.png");
    foreach ($list as $i => $name) {
        $col = $i % 8; $row = intdiv($i, 8);
        $out = imagecreatetruecolor(192, 192);
        imagealphablending($out, false); imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopy($out, $src, 0, 0, $col * 192, $row * 192, 192, 192);
        imagepng($out, "$dir/icon-$name.png");
    }
}
foreach (['icons-white', 'icons-green'] as $f) { @unlink("$dir/$f.png"); }
