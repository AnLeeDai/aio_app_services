# Email eBay Generator API Documentation

## Endpoint
```
POST /api/generate/emails/ebay
```

## Description
Generate eBay email addresses with passwords and associated names.

## Request Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| email_num | integer | Yes | - | Number of emails to generate (1-100) |

## Request Example
```json
{
    "email_num": 2
}
```

## Response Format

### Success Response
```json
{
    "status": "success",
    "data": [
        {
            "name": "Ravi Oliveira",
            "email": "ravisantosdr09sy@outlook.com|DyyPB12WTApe",
            "full_name": "Ravi Oliveira"
        },
        {
            "name": "Carlos Silva",
            "email": "carlossilva23ab@outlook.com|MxpQR45WTnpe", 
            "full_name": "Carlos Silva"
        }
    ]
}
```

### Error Response
```json
{
    "status": "error",
    "message": "Validation error message"
}
```

## Output Format Explanation

### Email Format
Each email follows the pattern: `{email}|{password}`
- **Email**: Generated from name + random suffix + domain
- **Password**: 12-character random password with mixed case and numbers

### Name Generation
- Names are randomly selected from a pool of common Portuguese/Spanish names
- Username is created by combining first name + last name + random suffix
- Suffixes can include patterns like "dr09sy", "23ab", etc.

### Example Output Breakdown
For `ravisantosdr09sy@outlook.com|DyyPB12WTApe`:
- **Name**: Ravi Santos (randomly generated)
- **Username**: ravisantos + dr09sy (random suffix)
- **Domain**: outlook.com (as requested)
- **Password**: DyyPB12WTApe (12 random characters)

## Usage Examples

### cURL
```bash
curl -X POST "http://your-domain.com/api/generate/emails/ebay" \
  -H "Content-Type: application/json" \
  -d '{"email_num": 3}'
```

### JavaScript/Fetch
```javascript
fetch('/api/generate/emails/ebay', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        email_num: 5
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

### PHP
```php
$data = [
    'email_num' => 2
];

$response = file_get_contents('/api/generate/emails/ebay', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($data)
    ]
]));

$result = json_decode($response, true);
```

## Validation Rules

- `email_num`: Must be between 1 and 100
- Request must be JSON format
- Content-Type must be application/json
- Domain is fixed as "outlook.com"

## Error Codes

| HTTP Code | Description |
|-----------|-------------|
| 200 | Success |
| 422 | Validation Error |
| 500 | Server Error |

## Notes

- Default domain is "outlook.com" if not specified
- Names are randomly generated from a predefined list
- Passwords are 12 characters long with mixed case letters and numbers
- Each request generates unique emails and passwords
- Username format: {firstname}{lastname}{randomsuffix}