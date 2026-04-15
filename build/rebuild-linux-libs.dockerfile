# Rebuilds src/lib/libtcl8.6.so, src/lib/libtk8.6.so and their init-script
# directories (src/lib/tcl8.6/, src/lib/tk8.6/) on Ubuntu 24.04 (glibc 2.39).
#
# The previously bundled binaries were compiled on an older system (glibc <= 2.29).
# In glibc 2.34, libpthread.so.0 and libdl.so.2 were merged into libc.so.6; the
# leftover stubs alter the GLOB_DAT symbol-search order so that `environ` resolves
# to a stale pointer inside TclSetupEnv -> SIGSEGV (see GitHub issue / crash report).
# Rebuilding on Ubuntu 24.04 produces a library that links correctly on all distros
# running glibc >= 2.34 (Ubuntu 22.04+, Fedora 35+, RHEL 9+, etc.).
#
# IMPORTANT: the .so binary and the tcl8.6/tk8.6 init-script directories must be
# built from the same source tarball — Tcl_Init enforces an exact version match.
# This Dockerfile exports both so they always stay in sync.
#
# Usage — run from the repository root:
#
#   docker build -f build/rebuild-linux-libs.dockerfile --output src/lib .
#
# All output files land directly in src/lib/ via BuildKit --output.

ARG TCL_VERSION=8.6.16

# ── stage 1: build ──────────────────────────────────────────────────────────
FROM ubuntu:24.04 AS builder

ARG TCL_VERSION

RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential \
        wget \
        ca-certificates \
        libx11-dev \
        libxft-dev \
        libxss-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /build

RUN wget -q "https://downloads.sourceforge.net/project/tcl/Tcl/${TCL_VERSION}/tcl${TCL_VERSION}-src.tar.gz" \
    && tar -xzf "tcl${TCL_VERSION}-src.tar.gz"

RUN cd "tcl${TCL_VERSION}/unix" \
    && ./configure \
        --enable-shared \
        --enable-threads \
        --disable-symbols \
        --prefix=/out \
    && make -j"$(nproc)" \
    && make install

RUN wget -q "https://downloads.sourceforge.net/project/tcl/Tcl/${TCL_VERSION}/tk${TCL_VERSION}-src.tar.gz" \
    && tar -xzf "tk${TCL_VERSION}-src.tar.gz"

RUN cd "tk${TCL_VERSION}/unix" \
    && ./configure \
        --enable-shared \
        --enable-threads \
        --disable-symbols \
        --with-tcl="/build/tcl${TCL_VERSION}/unix" \
        --prefix=/out \
    && make -j"$(nproc)" \
    && make install

# ── stage 2: export .so binaries + matching init-script directories ──────────
FROM scratch AS export

COPY --from=builder /out/lib/libtcl8.6.so /libtcl8.6.so
COPY --from=builder /out/lib/libtk8.6.so  /libtk8.6.so
COPY --from=builder /out/lib/tcl8.6/      /tcl8.6/
COPY --from=builder /out/lib/tk8.6/       /tk8.6/
