#!/bin/sh
# Baut zlib-ng als WebAssembly-Modul für den Kurswahl-Planer.
# Die Schule erzeugt ihre PDFs mit DevExpress unter .NET 9; dessen Deflate ist zlib-ng (Level 6).
# Nur mit derselben Bibliothek entstehen byte-gleiche komprimierte Datenströme.
# Aufruf: tools/zlib-ng-wasm/build.sh  → schreibt tools/zlib-ng-wasm/zng.wasm
# Benötigt: git, cmake, clang (mit wasm32-Ziel) und wasm-ld.
set -e
HERE=$(cd "$(dirname "$0")" && pwd)
WORK=${WORK:-$(mktemp -d)}
VERSION=2.2.5
[ -d "$WORK/zlib-ng" ] || git clone -q --depth 1 --branch "$VERSION" https://github.com/zlib-ng/zlib-ng.git "$WORK/zlib-ng"
mkdir -p "$WORK/cfg"
(cd "$WORK/cfg" && cmake "$WORK/zlib-ng" -DZLIB_COMPAT=ON -DWITH_OPTIM=OFF -DWITH_GZFILEOP=OFF \
  -DWITH_NATIVE_INSTRUCTIONS=OFF -DZLIB_ENABLE_TESTS=OFF -DZLIBNG_ENABLE_TESTS=OFF -DWITH_GTEST=OFF \
  -DWITH_RUNTIME_CPU_DETECTION=OFF >/dev/null)
Z="$WORK/zlib-ng"
clang --target=wasm32 -O2 -ffreestanding -nostdlib -I"$HERE/include" -I"$WORK/cfg" -I"$Z" \
  -DDISABLE_RUNTIME_CPU_DETECTION -DZLIB_COMPAT -DHAVE_ALIGNED_ALLOC -DHAVE_BUILTIN_CTZ -DHAVE_BUILTIN_CTZLL -DNDEBUG -std=c11 \
  -Wl,--no-entry -Wl,--export-memory -Wl,--strip-all -o "$HERE/zng.wasm" \
  "$Z/adler32.c" "$Z/crc32.c" "$Z/crc32_braid_comb.c" "$Z/deflate.c" "$Z/deflate_fast.c" "$Z/deflate_huff.c" \
  "$Z/deflate_medium.c" "$Z/deflate_quick.c" "$Z/deflate_rle.c" "$Z/deflate_slow.c" "$Z/deflate_stored.c" \
  "$Z/functable.c" "$Z/inflate.c" "$Z/inftrees.c" "$Z/insert_string.c" "$Z/insert_string_roll.c" "$Z/trees.c" \
  "$Z/zutil.c" "$Z"/arch/generic/*.c "$HERE/libc.c" "$HERE/api.c"
echo "geschrieben: $HERE/zng.wasm ($(wc -c <"$HERE/zng.wasm") Bytes)"
