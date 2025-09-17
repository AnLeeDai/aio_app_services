# Coding Standards Guide - AIO App Services

## Overview
Tài liệu này định nghĩa các quy tắc coding standards cho dự án AIO App Services để đảm bảo code consistency và dễ bảo trì.

## PHP Standards (PSR-4)

### 1. File Structure
```
app/
├── Http/
│   └── Controllers/
│       └── {FeatureName}Controller.php
├── Models/
│   └── {ModelName}.php
└── ...
```

### 2. Class Naming
- **Controllers**: `{FeatureName}Controller` (e.g., `ServerHealthCheckController`)
- **Models**: Singular form, PascalCase (e.g., `BirthDate`, `NameGenerator`)
- **Migrations**: Laravel convention (e.g., `create_birth_dates_table`)

### 3. Code Format
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ExampleController extends Controller
{
    /**
     * Method description
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function methodName(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'param' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Business logic
        $data = $this->processData($request);

        // Response
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
```

### 4. Model Standards
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExampleModel extends Model
{
    use HasFactory;

    protected $table = 'example_table';

    protected $fillable = [
        'field1',
        'field2',
    ];

    protected $casts = [
        'date_field' => 'date',
    ];

    public const UPDATED_AT = null; // If no updated_at column
}
```

## API Route Standards

### 1. Route Grouping
```php
// Health check
Route::get('/health-check', [ServerHealthCheckController::class, 'index']);

// Generator routes
Route::prefix('generate')->group(function () {
    Route::post('/names', [NameGeneratorController::class, 'generateName']);
    Route::post('/passwords', [PasswordGeneratorController::class, 'generatePassword']);
    // ...
});
```

### 2. Response Format
```json
{
    "status": "success|error",
    "message": "Optional message",
    "data": "Response data",
    "timestamp": "ISO string (for health checks)"
}
```

## Frontend Standards

### 1. JavaScript (ES6+)
- Use ES6 imports/exports
- Consistent indentation (2 spaces)
- Semicolons required

### 2. CSS (Tailwind)
- Use Tailwind classes
- Custom CSS in app.css if needed
- Follow Tailwind naming conventions

## File Naming Conventions

### 1. Controllers
- `{Feature}Controller.php`
- PascalCase
- Descriptive names

### 2. Models
- Singular noun
- PascalCase
- Match database table (singular)

### 3. Routes
- kebab-case for URLs
- RESTful conventions

## Database Standards

### 1. Migration Files
- Use Laravel naming convention
- Include proper up/down methods
- Add indexes for foreign keys

### 2. Table Names
- Snake_case
- Plural for tables
- Descriptive names

## Validation Standards

### 1. Request Validation
```php
$validator = Validator::make($request->all(), [
    'field_name' => 'required|type|constraints',
]);

if ($validator->fails()) {
    return response()->json([
        'status' => 'error',
        'message' => $validator->errors()->first(),
    ], 422);
}
```

## Error Handling

### 1. Standard Error Response
```php
return response()->json([
    'status' => 'error',
    'message' => 'Error description',
], 422); // Appropriate HTTP status code
```

## Tools for Consistency

### 1. PHP CS Fixer (Recommended)
```bash
composer require --dev friendsofphp/php-cs-fixer
```

### 2. Laravel Pint (Already included)
```bash
./vendor/bin/pint
```

### 3. IDE Configuration
- Use PSR-4 autoloading
- Set indentation to 4 spaces for PHP
- Set indentation to 2 spaces for JS/CSS

## Documentation Standards

### 1. Method Documentation
```php
/**
 * Brief method description
 *
 * @param Type $param Description
 * @return Type Description
 * @throws Exception Description
 */
```

### 2. Class Documentation
```php
/**
 * Class description
 *
 * @package App\Http\Controllers
 * @author Team Name
 */
```

## Testing Standards

### 1. Test File Naming
- `{Feature}Test.php`
- Place in appropriate test directory

### 2. Test Method Naming
```php
public function test_method_does_something_expected()
{
    // Test implementation
}
```

## Git Standards

### 1. Commit Messages
```
feat: add new feature
fix: fix bug description
docs: update documentation
style: format code
refactor: refactor code
test: add tests
```

### 2. Branch Naming
- `feat/feature-name`
- `fix/bug-description`
- `docs/documentation-update`

## Checklist for New Features

- [ ] Follow naming conventions
- [ ] Include proper validation
- [ ] Use consistent response format
- [ ] Add appropriate documentation
- [ ] Follow route grouping standards
- [ ] Use proper error handling
- [ ] Include tests if applicable

---

**Note**: This document should be updated as the project evolves. All team members should follow these standards to maintain code consistency.