ARG ARGWS_FRANKENPHP_IMAGE=dunglas/frankenphp:1-php8.3-bookworm

# Instalar dependências do módulo de nota fiscal na construção da imagem.
# O runtime permanece sem Composer e não realiza downloads durante a ativação.
FROM composer:2 AS einvoice-deps
WORKDIR /build/einvoice
COPY modules/einvoice/composer.json modules/einvoice/composer.lock ./
COPY modules/einvoice/src/ ./src/
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader \
    && php -r 'require "vendor/autoload.php"; exit(class_exists("Mustache_Engine") && class_exists("Argws\\CRM\\EInvoice\\EinvoiceHandler") ? 0 : 1);'


# Keep installer implementation out of the runtime image. Only the existing
# database schema/helpers and the minimal web first-run handler are staged outside the app root.
FROM ${ARGWS_FRANKENPHP_IMAGE} AS crm-source
WORKDIR /source
COPY . /source
RUN mkdir -p /out/app /out/provisioner/web \
    && cp -a /source/. /out/app/ \
    && rm -rf /out/app/install /out/app/deploy /out/app/tools/argws-crm-deployer /out/app/docker \
    && cp /source/install/database.sql /source/install/sqlparser.php /source/install/phpass.php /out/provisioner/ \
    && cp /source/docker/provision.php /out/provisioner/provision.php \
    && cp /source/docker/provisioner.php /out/provisioner/provisioner.php \
    && cp /source/docker/migration-cli.php /out/provisioner/migration-cli.php \
    && cp /source/docker/setup-web.php /out/provisioner/web/index.php

FROM ${ARGWS_FRANKENPHP_IMAGE}

ARG ARGWS_VERSION=3.6.4
LABEL org.opencontainers.image.title="ARGWS CRM" \
      org.opencontainers.image.version="${ARGWS_VERSION}" \
      org.opencontainers.image.vendor="ARGWS" \
      org.opencontainers.image.description="ARGWS CRM com FrankenPHP"

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends gosu \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions mysqli pdo_mysql curl mbstring imap gd zip intl bcmath soap exif opcache

COPY --from=crm-source --chown=www-data:www-data /out/app/ /app/
COPY --from=einvoice-deps --chown=www-data:www-data /build/einvoice/vendor/ /app/modules/einvoice/vendor/
COPY --from=crm-source --chown=root:root /out/provisioner/ /opt/argws-crm-provisioner/
COPY --chown=root:root docker/Caddyfile /etc/caddy/Caddyfile
COPY --chown=root:root docker/Caddyfile.unprovisioned /etc/caddy/Caddyfile.unprovisioned
COPY --chown=root:root docker/entrypoint.sh /usr/local/bin/argws-entrypoint

RUN mkdir -p /app/uploads /app/temp /app/application/cache /app/application/logs /data /config /var/lib/argws-crm/config \
    && chown -R www-data:www-data /app /data /config /var/lib/argws-crm \
    && chmod 0444 /opt/argws-crm-provisioner/provision.php /opt/argws-crm-provisioner/provisioner.php /opt/argws-crm-provisioner/migration-cli.php /opt/argws-crm-provisioner/web/index.php \
    && chmod 0444 /opt/argws-crm-provisioner/database.sql /opt/argws-crm-provisioner/sqlparser.php /opt/argws-crm-provisioner/phpass.php \
    && chmod 0755 /usr/local/bin/argws-entrypoint \
    && find application modules -type f -name '*.php' \
       -not -path '*/vendor/*' -not -path '*/third_party/*' -print0 \
       | xargs -0 -r -n1 -P8 sh -c 'output="$(php -l "$1" 2>&1)" || { echo "$output" >&2; echo "Falha na validação PHP: $1" >&2; exit 255; }' argws-lint \
    && php -l /opt/argws-crm-provisioner/provision.php \
    && php -l /opt/argws-crm-provisioner/provisioner.php \
    && php -l /opt/argws-crm-provisioner/migration-cli.php \
    && php -l /opt/argws-crm-provisioner/web/index.php \
    && php -l /opt/argws-crm-provisioner/sqlparser.php \
    && php -l /opt/argws-crm-provisioner/phpass.php \
    && php /app/modules/asaas/scripts/assert-webhook-security.php \
    && php -r 'require "/app/modules/einvoice/vendor/autoload.php"; exit(class_exists("Mustache_Engine") && class_exists("Argws\\CRM\\EInvoice\\EinvoiceHandler") ? 0 : 1);' \
    && php -r 'if (PHP_SAPI !== "cli") { fwrite(STDERR, "O executável php não está em modo CLI.\\n"); exit(1); }'

RUN frankenphp validate --config /etc/caddy/Caddyfile --adapter caddyfile \
    && frankenphp validate --config /etc/caddy/Caddyfile.unprovisioned --adapter caddyfile


# The entrypoint initializes bind-mounted paths as root, then drops FrankenPHP to www-data.
USER root
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/argws-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
