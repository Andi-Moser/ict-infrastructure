#!/bin/bash

if [ "$#" -ne 5 ]; then
    echo "Usage: $0 <email> <loginname> <firstname> <lastname> <role>"
    exit 1
fi

EMAIL="$1"
LOGINNAME="$2"
FIRSTNAME="$3"
LASTNAME="$4"
ROLE="$5"

# Validate role
if [[ "$ROLE" != "lernende" && "$ROLE" != "instruktoren" ]]; then
    echo "Error: Invalid role '$ROLE' for user $LOGINNAME. Skipping..."
    exit 1
fi

source .env

# Define LDAP base DN
BASE_DN="dc=ict-bz,dc=ch"
USER_DN="cn=$LOGINNAME,ou=$ROLE,$BASE_DN"

# Define LDAP server IP
LDAP_SERVER="ldap://10.200.2.113"

# Get the next available uidNumber
NEXT_UID=$(ldapsearch -x -LLL -H "$LDAP_SERVER" -D "cn=admin,$BASE_DN" -w $LDAP_ADMIN_PASSWORD \
    -b "ou=$ROLE,$BASE_DN" "(uidNumber=*)" uidNumber | awk '{print $2}' | sort -n | tail -1)
NEXT_UID=$((NEXT_UID+1))

# Use the same gidNumber as the primary user group
GID_NUMBER=1010
if [[ "$ROLE" == "instruktoren" ]]; then
    GID_NUMBER=1020
fi

# Set a fixed password
ENCRYPTED_PASSWORD=$(slappasswd -s "$DEFAULT_USER_PASSWORD")

# Generate LDIF file
tempfile=$(mktemp)
cat <<EOF > "$tempfile"
dn: $USER_DN
objectClass: inetOrgPerson
objectClass: posixAccount
objectClass: top
cn: $LOGINNAME
sn: $LASTNAME
givenName: $FIRSTNAME
uid: $LOGINNAME
mail: $EMAIL
uidNumber: $NEXT_UID
gidNumber: $GID_NUMBER
homeDirectory: /home/$LOGINNAME
loginShell: /bin/bash
userPassword: $ENCRYPTED_PASSWORD
preferredLanguage: de_ch
EOF

# Add user to LDAP
ldapadd -x -H "$LDAP_SERVER" -D "cn=admin,$BASE_DN" -w $LDAP_ADMIN_PASSWORD -f "$tempfile"

# Cleanup
test -f "$tempfile" && rm "$tempfile"

echo "LDAP user $LOGINNAME added successfully with uidNumber $NEXT_UID and default password set."
