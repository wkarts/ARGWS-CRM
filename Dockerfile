FROM dunglas/frankenphp:1-php8.3-bookworm

ARG ARGWS_VERSION=3.4.2
LABEL org.opencontainers.image.title="ARGWS CRM" \
      org.opencontainers.image.version="${ARGWS_VERSION}" \
      org.opencontainers.image.vendor="ARGWS" \
      org.opencontainers.image.description="ARGWS CRM com FrankenPHP"

WORKDIR /app

RUN install-php-extensions mysqli pdo_mysql curl mbstring imap gd zip intl bcmath soap exif opcache

COPY --chown=www-data:www-data . /app
COPY --chown=root:root docker/Caddyfile /etc/caddy/Caddyfile

RUN mkdir -p /app/uploads /app/temp /app/application/cache /app/application/logs /data /config /var/lib/argws-crm/config \
    && chown -R www-data:www-data /app /data /config /var/lib/argws-crm \
    && find application modules install -type f -name '*.php' \
       -not -path '*/vendor/*' -not -path '*/third_party/*' -print0 \
       | xargs -0 -r -n1 -P8 sh -c 'output="$(php -l "$1" 2>&1)" || { echo "$output" >&2; echo "Falha na validação PHP: $1" >&2; exit 255; }' argws-lint

COPY --chown=root:root docker/entrypoint.sh /usr/local/bin/argws-entrypoint
RUN chmod 0755 /usr/local/bin/argws-entrypoint

USER www-data
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/argws-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
