# Email eBay Generator - Sample Outputs

## Sample API Response

When calling:
```
POST /api/generate/emails/ebay
Content-Type: application/json

{
    "email_num": 3
}
```

Expected Response:
```json
{
    "status": "success",
    "data": [
        {
            "name": "Ravi Oliveira",
            "email": "ravioliveira02dr@outlook.com|DyyPB12WTApe",
            "full_name": "Ravi Oliveira"
        },
        {
            "name": "Carlos Santos",
            "email": "carlossantos15xy@outlook.com|MxpQR45WTnpe",
            "full_name": "Carlos Santos"
        },
        {
            "name": "Maria Silva",
            "email": "mariasilva2845@outlook.com|KlpDF78WQrts",
            "full_name": "Maria Silva"
        }
    ]
}
```

## Email Format Breakdown

Each email follows the pattern: `{email}|{password}`

### Email Component
- Format: `{firstname}{lastname}{randomsuffix}@outlook.com`
- Example: `ravioliveira02dr@outlook.com`
  - firstname: `ravi` (lowercase)
  - lastname: `oliveira` (lowercase)
  - suffix: `02dr` (random pattern)
  - domain: `outlook.com` (fixed)

### Password Component
- 12 characters long
- Mix of uppercase, lowercase, and numbers
- Example: `DyyPB12WTApe`

### Name Component
- Full name as it would appear on documents
- Example: "Ravi Oliveira"
- Available in both `name` and `full_name` fields for compatibility

## Available Name Pool

### First Names
Ravi, Carlos, Maria, João, Ana, Pedro, Lucas, Sofia, Miguel, Isabella, Gabriel, Valentina, Diego, Camila, Mateo, Mariana, Santiago, Lucia, Alejandro, Elena, Fernando, Natalia, Ricardo, Adriana, Manuel, Carmen, Jorge, Patricia, Rafael, Monica, Antonio, Sandra, Francisco, Silvia

### Last Names  
Santos, Silva, Oliveira, Souza, Rodrigues, Ferreira, Alves, Pereira, Lima, Gomes, Costa, Ribeiro, Martins, Carvalho, Almeida, Lopes, Soares, Fernandes, Vieira, Barbosa, Rocha, Dias, Monteiro, Mendes, Castro, Araujo, Cardoso, Reis, Nascimento, Freitas, Correia, Moreira

## Random Suffix Patterns

The system generates various patterns for the username suffix:
- `dr{number}{letters}` - e.g., "dr09sy"
- `{number}{letters}` - e.g., "23ab" 
- `{letters}{number}` - e.g., "xy15"
- `{4digits}` - e.g., "2845"

## Password Generation Rules

- Always 12 characters
- Contains uppercase letters (A-Z)
- Contains lowercase letters (a-z) 
- Contains numbers (0-9)
- Pattern: Mixed randomly but ensures good complexity

## Usage in eBay Context

The generated emails are formatted specifically for eBay account creation:
- Email format matches typical user patterns
- Passwords meet security requirements
- Names are realistic and diverse
- Format `email|password` for easy copy/paste

## Error Handling

If validation fails:
```json
{
    "status": "error", 
    "message": "The email num field is required."
}
```

Common validation errors:
- email_num is required
- email_num must be between 1 and 100