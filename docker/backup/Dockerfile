FROM alpine:3.22

RUN apk add --no-cache \
        curl \
        jq \
        minio-client \
        postgresql17-client \
        redis \
        restic \
        util-linux

COPY docker/backup/*.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/*.sh

ENTRYPOINT ["/usr/local/bin/backup-loop.sh"]
