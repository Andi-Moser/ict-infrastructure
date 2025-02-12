#!/usr/bin/sh

if [ -z "$1" ]; then
  echo "Error: No domain provided"
else
    docker compose run --rm  certbot certonly --webroot --webroot-path /var/www/certbot/ -d $1
fi
