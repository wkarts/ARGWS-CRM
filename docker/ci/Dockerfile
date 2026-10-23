# Validation-only image: never use this as the application/runtime image.
ARG NODE_IMAGE=node:22-bookworm-slim
ARG PHP_IMAGE=php:8.1-cli-bookworm
FROM ${NODE_IMAGE} AS browser-dependencies
WORKDIR /opt/ci-browser
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund

FROM ${PHP_IMAGE}
LABEL org.opencontainers.image.title="ARGWS CRM validation tools" \
      org.opencontainers.image.description="Disposable CI only; no CRM source or production configuration"
COPY --from=browser-dependencies /usr/local/bin/node /usr/local/bin/node
COPY --from=browser-dependencies /opt/ci-browser /opt/ci-browser
ENV NODE_PATH=/opt/ci-browser/node_modules \
    PLAYWRIGHT_BROWSERS_PATH=/opt/ci-browsers
RUN apt-get update \
 && apt-get install -y --no-install-recommends ca-certificates git python3 poppler-utils libpng-dev libjpeg62-turbo-dev libonig-dev \
 && docker-php-ext-configure gd --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd mbstring \
 && node /opt/ci-browser/node_modules/playwright/cli.js install --with-deps chromium \
 && rm -rf /var/lib/apt/lists/* /root/.npm \
 && chmod -R a+rX /opt/ci-browser /opt/ci-browsers
COPY smoke.cjs /opt/ci-browser/smoke.cjs
COPY smoke.sh /usr/local/bin/argws-ci-smoke
RUN chmod 0755 /usr/local/bin/argws-ci-smoke
WORKDIR /workspace
CMD ["php", "--version"]
