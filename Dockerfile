ARG ARGWS_FRANKENPHP_IMAGE=dunglas/frankenphp:1-php8.3-bookworm

# Keep installer implementation out of the runtime image. Only the existing
# database schema and compatible password/parser helpers are staged for CLI bootstrap.
FROM ${ARGWS_FRANKENPHP_IMAGE} AS crm-source
WORKDIR /source
COPY . /source
RUN mkdir -p /out/app /out/provisioner \
    && cp -a /source/. /out/app/ \
    && rm -rf /out/app/install /out/app/deploy /out/app/tools/argws-crm-deployer /out/app/docker \
    && cp /source/install/database.sql /source/install/sqlparser.php /source/install/phpass.php /out/provisioner/ \
    && cp /source/docker/provision.php /out/provisioner/provision.php

FROM ${ARGWS_FRANKENPHP_IMAGE}

ARG ARGWS_VERSION=3.4.2
LABEL org.opencontainers.image.title="ARGWS CRM" \
      org.opencontainers.image.version="${ARGWS_VERSION}" \
      org.opencontainers.image.vendor="ARGWS" \
      org.opencontainers.image.description="ARGWS CRM com FrankenPHP"

WORKDIR /app

RUN install-php-extensions mysqli pdo_mysql curl mbstring imap gd zip intl bcmath soap exif opcache

COPY --from=crm-source --chown=www-data:www-data /out/app/ /app/
COPY --from=crm-source --chown=root:root /out/provisioner/ /opt/argws-crm-provisioner/
COPY --chown=root:root docker/Caddyfile /etc/caddy/Caddyfile
COPY --chown=root:root docker/Caddyfile.unprovisioned /etc/caddy/Caddyfile.unprovisioned
COPY --chown=root:root docker/entrypoint.sh /usr/local/bin/argws-entrypoint

RUN mkdir -p /app/uploads /app/temp /app/application/cache /app/application/logs /data /config /var/lib/argws-crm/config \
    && chown -R www-data:www-data /app /data /config /var/lib/argws-crm \
    && chmod 0555 /opt/argws-crm-provisioner/provision.php \
    && chmod 0444 /opt/argws-crm-provisioner/database.sql /opt/argws-crm-provisioner/sqlparser.php /opt/argws-crm-provisioner/phpass.php \
    && chmod 0755 /usr/local/bin/argws-entrypoint \
    && find application modules -type f -name '*.php' \
       -not -path '*/vendor/*' -not -path '*/third_party/*' -print0 \
       | xargs -0 -r -n1 -P8 sh -c 'output="$(php -l "$1" 2>&1)" || { echo "$output" >&2; echo "Falha na validação PHP: $1" >&2; exit 255; }' argws-lint \
    && php -l /opt/argws-crm-provisioner/provision.php \
    && php -l /opt/argws-crm-provisioner/sqlparser.php \
    && php -l /opt/argws-crm-provisioner/phpass.php

USER www-data
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/argws-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
