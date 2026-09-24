#include <stddef.h>
#include <stdint.h>
extern unsigned char __heap_base;
static uintptr_t heap_top;
__attribute__((export_name("heap_reset"))) void heap_reset(void){ heap_top=0; }
void *memcpy(void *d, const void *s, size_t n){ unsigned char *a=d; const unsigned char *b=s; while(n--) *a++=*b++; return d; }
void *memmove(void *d, const void *s, size_t n){ unsigned char *a=d; const unsigned char *b=s; if(a<b){ while(n--) *a++=*b++; } else { a+=n; b+=n; while(n--) *--a=*--b; } return d; }
void *memset(void *d, int c, size_t n){ unsigned char *a=d; while(n--) *a++=(unsigned char)c; return d; }
int memcmp(const void *x, const void *y, size_t n){ const unsigned char *a=x,*b=y; for(;n;n--,a++,b++) if(*a!=*b) return *a-*b; return 0; }
size_t strlen(const char *s){ size_t n=0; while(s[n]) n++; return n; }
void *aligned_alloc(size_t al, size_t n){
  if(!heap_top) heap_top=(uintptr_t)&__heap_base;
  heap_top=(heap_top+al-1)&~(uintptr_t)(al-1);
  size_t need=heap_top+n, have=__builtin_wasm_memory_size(0)*65536;
  if(need>have && __builtin_wasm_memory_grow(0,(need-have+65535)/65536)==(size_t)-1) return 0;
  void *p=(void*)heap_top; heap_top+=n; return p; }
void *malloc(size_t n){ return aligned_alloc(16,n); }
void *calloc(size_t a, size_t b){ void *p=malloc(a*b); if(p) memset(p,0,a*b); return p; }
void free(void *p){ (void)p; }
