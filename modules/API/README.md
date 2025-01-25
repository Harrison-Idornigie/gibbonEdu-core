# Gibbon API Module

This module provides a RESTful API for accessing Gibbon data.

## Base URL

All API endpoints are prefixed with `/api/v1/`.

## Authentication

The API supports two authentication methods:

1. OAuth2 (recommended)
2. API Key (legacy)

### OAuth2 Authentication

To use OAuth2, you need to:

1. Create a client in the `gibbonOAuthClient` table
2. Use the client credentials to obtain an access token
3. Include the access token in the `Authorization` header

Example:
```bash
# Get access token
curl -X POST "http://your-gibbon-url/api/v1/oauth2/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials" \
  -d "client_id=YOUR_CLIENT_ID" \
  -d "client_secret=YOUR_CLIENT_SECRET" \
  -d "scope=students:read"

# Use access token
curl "http://your-gibbon-url/api/v1/students" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### API Key Authentication (Legacy)

Include your API key in the `X-API-Key` header:

```bash
curl "http://your-gibbon-url/api/v1/students" \
  -H "X-API-Key: YOUR_API_KEY"
```

## Endpoints

### Students

#### List Students

Get a list of students in the current school year.

**Endpoint:** `/api/v1/students`  
**Method:** GET  
**Scope Required:** `students:read`

##### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| q | string | Search query for name or email |
| yearGroup | string | Filter by year group ID |
| formGroup | string | Filter by form group ID |
| sort | string | Field to sort by (default: surname) |
| order | string | Sort order: ASC or DESC (default: ASC) |
| page | integer | Page number (default: 1) |
| pageSize | integer | Results per page (default: 25, max: 50) |

##### Example Response

```json
{
  "data": [
    {
      "id": "0000002746",
      "name": {
        "title": "",
        "surname": "Abbott",
        "preferredName": "Reese"
      },
      "email": null,
      "yearGroup": {
        "id": "001",
        "name": "Y07"
      },
      "formGroup": {
        "id": "00143",
        "name": "07.1"
      },
      "image": ""
    }
  ],
  "meta": {
    "total": 391,
    "page": 1,
    "pageSize": 25
  }
}
```

#### Get Single Student

Get details for a specific student.

**Endpoint:** `/api/v1/students/{id}`  
**Method:** GET  
**Scope Required:** `students:read`

##### Example Response

```json
{
  "data": {
    "id": "0000002746",
    "name": {
      "title": "",
      "surname": "Abbott",
      "preferredName": "Reese"
    },
    "email": null,
    "yearGroup": {
      "id": "001",
      "name": "Y07"
    },
    "formGroup": {
      "id": "00143",
      "name": "07.1"
    },
    "image": ""
  }
}
```

## Error Responses

The API uses standard HTTP status codes and returns errors in JSON format:

```json
{
  "error": "error_code",
  "message": "Human readable error message"
}
```

Common error codes:
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 500: Internal Server Error

## Rate Limiting

Currently, there are no rate limits implemented. However, we recommend:
1. Using pagination to limit the size of responses
2. Caching responses when possible
3. Limiting requests to a reasonable frequency

## Support

For issues or feature requests, please:
1. Check the [Gibbon Support Forum](https://ask.gibbonedu.org)
2. Submit an issue on [GitHub](https://github.com/GibbonEdu/core)
