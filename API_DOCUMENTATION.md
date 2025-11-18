# JuaKali Lend - API Documentation

## Overview

The JuaKali Lend API provides 50+ RESTful endpoints for complete platform management. All endpoints return JSON responses and use HTTP status codes for error handling.

## Authentication

All API requests (except login/register) require a JWT token in the Authorization header:

\`\`\`
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
\`\`\`

## Response Format

### Success Response (200 OK)
\`\`\`json
{
  "success": true,
  "data": { /* response data */ },
  "message": "Operation successful"
}
\`\`\`

### Error Response (4xx/5xx)
\`\`\`json
{
  "success": false,
  "error": "Error code",
  "message": "Detailed error message",
  "code": 400
}
\`\`\`

## HTTP Status Codes

- `200` - OK (Success)
- `201` - Created (Resource created)
- `204` - No Content
- `400` - Bad Request (Validation error)
- `401` - Unauthorized (Auth required)
- `403` - Forbidden (Insufficient permissions)
- `404` - Not Found
- `429` - Too Many Requests (Rate limited)
- `500` - Internal Server Error
- `503` - Service Unavailable

## Rate Limiting

- Login attempts: 5 per 15 minutes per IP
- API calls: 100 per minute per user
- Large uploads: 1 per 5 seconds per user

## Common Error Codes

- `INVALID_CREDENTIALS` - Login failed
- `INVALID_EMAIL` - Email format error
- `WEAK_PASSWORD` - Password doesn't meet requirements
- `EMAIL_EXISTS` - Email already registered
- `UNAUTHORIZED` - Missing or invalid token
- `PERMISSION_DENIED` - Insufficient role permissions
- `RESOURCE_NOT_FOUND` - Record not found
- `VALIDATION_ERROR` - Input validation failed
- `DATABASE_ERROR` - Database operation failed
- `PAYMENT_ERROR` - Payment processing failed

## Complete API Reference

[Full API documentation with all 50+ endpoints - see API_REFERENCE.md]

## Pagination

List endpoints support pagination:
- Query: `?page=1&limit=10`
- Response includes: `total`, `pages`, `current_page`, `per_page`

## Filtering

Most list endpoints support filters:
- Status filter: `?status=active`
- Date range: `?start_date=2025-01-01&end_date=2025-01-31`
- Search: `?search=keyword`
- Sort: `?sort=created_at&order=desc`

## Webhooks

Configure webhooks in admin settings for:
- Payment notifications
- Order updates
- Loan status changes
- KYC verification results
- Dispute resolutions
