#!/bin/bash

# Load password from the .env file
source .env

# LDAP server details
LDAP_SERVER="ldap://10.200.2.113"
BIND_USER="cn=admin,dc=ict-bz,dc=ch"
BASE_DN="dc=ict-bz,dc=ch"

# Check if LDAP_ADMIN_PASSWORD is set
if [ -z "$LDAP_ADMIN_PASSWORD" ]; then
    echo "LDAP_ADMIN_PASSWORD not found in .env file"
    exit 1
fi

# Define the LDIF files to create the organizational units
LDIF_FILE_1=$(mktemp)
LDIF_FILE_2=$(mktemp)

cat <<EOF > "$LDIF_FILE_1"
dn: ou=instruktoren,$BASE_DN
objectClass: organizationalUnit
ou: instruktoren
EOF

cat <<EOF > "$LDIF_FILE_2"
dn: ou=lernende,$BASE_DN
objectClass: organizationalUnit
ou: lernende
EOF

# Add the organizational units to the LDAP server
ldapadd -x -H "$LDAP_SERVER" -D "$BIND_USER" -w "$LDAP_ADMIN_PASSWORD" -f "$LDIF_FILE_1"
ldapadd -x -H "$LDAP_SERVER" -D "$BIND_USER" -w "$LDAP_ADMIN_PASSWORD" -f "$LDIF_FILE_2"

# Clean up temporary LDIF files
rm -f "$LDIF_FILE_1" "$LDIF_FILE_2"

echo "Organizational units 'instruktoren' and 'lernende' created successfully."
