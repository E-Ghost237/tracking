#!/usr/bin/env bash
#
# Optional, repeatable pass over the photography in public/images.
#
# Every photograph is checked in as a JPEG (the same file is also used for
# og:image, where scrapers expect JPEG). Pages serve the smaller WebP next to it.
# Run this script after adding or replacing a photograph, then commit both files.
#
# Each photograph also produces a ladder of narrower WebP variants (480, 800 and
# 1100 px) named "<name>-<width>.webp". config/media.php lists them, and the
# media() helper turns them into a srcset with width descriptors, so a photo in a
# quarter-width card downloads a quarter-width file instead of the full one.
#
# Requirements: ImageMagick (`convert`) and cwebp are not needed — WebP support in
# ImageMagick is enough. On Debian/Ubuntu with ImageMagick 6 the binary is `convert`.
#
set -euo pipefail

cd "$(dirname "$0")/.."

QUALITY="${QUALITY:-80}"
DIR="public/images"

if ! command -v convert >/dev/null 2>&1; then
    echo "ImageMagick 'convert' was not found. Install it, or convert the images manually." >&2
    exit 1
fi

convert_one() {
    local source="$1" target="$2" width="$3"
    convert "$source" -strip -resize "${width}x>" -quality "$QUALITY" -define webp:method=6 "$target"
    printf '  %-46s %5s KB  %s\n' "$target" "$(( $(stat -c%s "$target") / 1024 ))" "$(identify -format '%wx%h' "$target")"
}

# name:base width the full-size WebP is rendered at. Keep these in step with
# config/media.php: the width there is the value used in the srcset descriptor.
PHOTOS="
hero-freight-air:1600
freight-air:1376
freight-sea:1376
freight-road:1376
freight-express:1376
warehouse-hub:1376
customs-documents:1400
global-globe-logistics:1376
"

LADDER="480 800 1100"

echo "Photography:"
while IFS=: read -r name base; do
    [ -n "$name" ] || continue
    [ -f "$DIR/$name.jpg" ] || continue

    convert_one "$DIR/$name.jpg" "$DIR/$name.webp" "$base"

    for width in $LADDER; do
        if [ "$width" -lt "$base" ]; then
            convert_one "$DIR/$name.jpg" "$DIR/$name-$width.webp" "$width"
        fi
    done
done <<< "$PHOTOS"

echo
echo "Total WebP weight (all variants):"
du -ch "$DIR"/*.webp | tail -1
