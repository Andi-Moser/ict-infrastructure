#!/bin/bash

useradd -u 1001 ldap-bitnami
mkdir -p data/ldap
chown -R ldap-bitnami:ldap-bitnami data/ldap