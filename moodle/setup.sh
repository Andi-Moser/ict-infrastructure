#!/bin/bash

useradd -u 1001 mariadb-bitnami
mkdir -p data/mariadb
chown -R mariadb-bitnami:mariadb-bitnami data/mariadb