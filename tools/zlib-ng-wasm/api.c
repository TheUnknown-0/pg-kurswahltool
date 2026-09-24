#include <stdlib.h>
#include <string.h>
#include "zlib.h"

/* Speicher für Ein- und Ausgabe; heap_reset() gibt alles auf einmal frei */
__attribute__((export_name("zalloc"))) void *zalloc_export(unsigned long n){ return malloc(n); }

/* Roh-Deflate ohne zlib-Rahmen, Parameter wie .NET CompressionLevel.Optimal (Level 6, 32K-Fenster, memLevel 8) */
__attribute__((export_name("deflate_raw"))) long deflate_raw(unsigned char *in, unsigned long n, unsigned char *out, unsigned long cap, int level){
  z_stream s; memset(&s, 0, sizeof s);
  if(deflateInit2(&s, level, Z_DEFLATED, -15, 8, Z_DEFAULT_STRATEGY) != Z_OK) return -1;
  s.next_in = in; s.avail_in = n; s.next_out = out; s.avail_out = cap;
  int r = deflate(&s, Z_FINISH); long t = s.total_out; deflateEnd(&s);
  return r == Z_STREAM_END ? t : -2;
}

__attribute__((export_name("inflate_raw"))) long inflate_raw(unsigned char *in, unsigned long n, unsigned char *out, unsigned long cap){
  z_stream s; memset(&s, 0, sizeof s);
  if(inflateInit2(&s, -15) != Z_OK) return -1;
  s.next_in = in; s.avail_in = n; s.next_out = out; s.avail_out = cap;
  int r = inflate(&s, Z_FINISH); long t = s.total_out; inflateEnd(&s);
  return r == Z_STREAM_END ? t : -2;
}
