#pragma once
#include <stddef.h>
void *malloc(size_t n); void free(void *p); void *calloc(size_t a, size_t b);
void *aligned_alloc(size_t al, size_t n);
static inline long long llabs(long long x){ return x<0?-x:x; }
