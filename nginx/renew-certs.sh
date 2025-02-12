#!/usr/bin/sh
docker compose run --rm certbot renew && docker compose down && docker compose up -d