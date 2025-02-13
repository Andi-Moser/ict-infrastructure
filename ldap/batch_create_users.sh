#!/bin/bash

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 <csv_file>"
    exit 1
fi

CSV_FILE="$1"
SCRIPT_DIR="$(dirname "$0")"
CREATE_USER_SCRIPT="$SCRIPT_DIR/create_user.sh"

if [ ! -f "$CREATE_USER_SCRIPT" ]; then
    echo "Error: create_user.sh not found in $SCRIPT_DIR"
    exit 1
fi

if [ ! -f "$CSV_FILE" ]; then
    echo "Error: CSV file $CSV_FILE not found"
    exit 1
fi

# Read CSV and execute create_user.sh for each entry
while IFS=',' read -r EMAIL LOGINNAME FIRSTNAME LASTNAME ROLE; do
    # Skip empty lines or lines starting with a comment
    [[ -z "$EMAIL" || "$EMAIL" =~ ^#.*$ ]] && continue
    
    # Validate role
    if [[ "$ROLE" != "lernende" && "$ROLE" != "instruktoren" ]]; then
        echo "Error: Invalid role '$ROLE' for user $LOGINNAME. Skipping..."
        continue
    fi
    
    echo "$CREATE_USER_SCRIPT" "$EMAIL" "$LOGINNAME" "$FIRSTNAME" "$LASTNAME" "$ROLE"
    bash "$CREATE_USER_SCRIPT" "$EMAIL" "$LOGINNAME" "$FIRSTNAME" "$LASTNAME" "$ROLE"
done < "$CSV_FILE"

echo "Batch user creation completed."
