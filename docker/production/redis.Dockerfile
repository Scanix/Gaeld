FROM docker.io/library/redis@sha256:7f7d30b7fdd09467bac58198a1f166dd69789d348d4fe859cccb20e0cf287fbe

# Retain the Redis 7.4.12 binary and install the Alpine OpenSSL and zlib security updates.
ARG TARGETARCH
RUN set -eu; \
    case "$TARGETARCH" in \
        amd64) arch=x86_64; ssl=a458e169610c9c1bb15d53b10e6c229a8e5ea16447f80cbcf20ea6ce7e97399f; crypto=a71631c50c4df10c00d6f6c7ead0066e33ac1a90afb65dc4c9de794a0de8ad79; zlib=a271d6491037f7037010ac98b40f7d2b81c733f425b20294ee771b6ed7571038 ;; \
        arm64) arch=aarch64; ssl=ba37d9c24cb83560f5fc52645e2dc594877ea772699a4b6d6fe3578d61ea82e4; crypto=ce909c9445d3164c4ccc7bd30aa1b1ebacd24bea006755b073164f463a76700e; zlib=279972bab4f817182d9617984880bc29471f1d3016f92265be10d71e6f95ca74 ;; \
        *) exit 1 ;; \
    esac; \
    cd /tmp; \
    for file in libssl3-3.5.9-r1.apk libcrypto3-3.5.9-r1.apk zlib-1.4-r0.apk; do \
        wget -q "https://dl-cdn.alpinelinux.org/alpine/v3.22/main/$arch/$file"; \
    done; \
    printf '%s  %s\n' "$ssl" libssl3-3.5.9-r1.apk "$crypto" libcrypto3-3.5.9-r1.apk "$zlib" zlib-1.4-r0.apk | sha256sum -c -; \
    apk verify libssl3-3.5.9-r1.apk libcrypto3-3.5.9-r1.apk zlib-1.4-r0.apk; \
    apk add --no-cache --no-network ./libssl3-3.5.9-r1.apk ./libcrypto3-3.5.9-r1.apk ./zlib-1.4-r0.apk; \
    rm libssl3-3.5.9-r1.apk libcrypto3-3.5.9-r1.apk zlib-1.4-r0.apk
