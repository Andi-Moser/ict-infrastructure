FROM php:8.3-cli

RUN apt-get update && apt-get install -y libsqlite3-dev && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_sqlite

WORKDIR /app

COPY public/    ./public/
COPY api/       ./api/
COPY router.php ./router.php

# Ensure the data directory exists so SQLite can write even without a volume mount
RUN mkdir -p /app/data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r "file_get_contents('http://localhost:8080/api') !== false or exit(1);"

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "router.php"]
