#!/bin/sh
# Rebuilds the receipt's decorative images (public/image/receipt/*.png) from the HTML/SVG sources in this folder.
# Needs Chrome. Run from this folder:  sh build.sh
CHROME="${CHROME:-/c/Program Files/Google/Chrome/Application/chrome.exe}"
mkdir -p ../../public/image/receipt; OUT="$(cd ../../public/image/receipt && (pwd -W 2>/dev/null || pwd))"
shot() { "$CHROME" --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor=3 --default-background-color=00000000 --window-size="$2,$3" --screenshot="$OUT/$4" "file:///$(pwd -W 2>/dev/null || pwd)/$1" >/dev/null 2>&1; }
shot banner.html 853 110 banner.png
shot footer.html 853 193 footer.png
shot lockup.html 600 140 lockup.png
sed 's/ICONCLASS/white/' icons.html > _white.html; shot _white.html 512 128 icons-white.png
sed 's/ICONCLASS/green/' icons.html > _green.html; shot _green.html 512 128 icons-green.png
rm -f _white.html _green.html
php split-icons.php
