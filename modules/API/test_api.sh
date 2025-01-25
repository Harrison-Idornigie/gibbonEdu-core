#!/bin/bash

# Configuration
API_URL="http://localhost"  # Change this to your Gibbon URL
CLIENT_ID="test_client"
CLIENT_SECRET="test_secret"
SCOPE="students:read"

# Get access token
echo "Getting access token..."
TOKEN_RESPONSE=$(curl -s -X POST "${API_URL}/core/modules/API/oauth2.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials" \
  -d "client_id=${CLIENT_ID}" \
  -d "client_secret=${CLIENT_SECRET}" \
  -d "scope=${SCOPE}")

ACCESS_TOKEN=$(echo $TOKEN_RESPONSE | grep -o '"access_token":"[^"]*' | cut -d'"' -f4)

if [ -z "$ACCESS_TOKEN" ]; then
    echo "Failed to get access token. Response:"
    echo $TOKEN_RESPONSE
    exit 1
fi

echo "Got access token: ${ACCESS_TOKEN:0:20}..."

# Test students endpoint
echo -e "\nTesting students endpoint..."
curl -s "${API_URL}/core/modules/API/api_endpoints.php?endpoint=students" \
  -H "Authorization: Bearer ${ACCESS_TOKEN}" | json_pp

echo -e "\nDone!"
