# Validation System and Error Messages — Ptah

## 📋 Overview

Ptah implements a robust validation system with **detailed error messages** that include:
- ✅ Contexto completo (campo, valor atual vs esperado, linha do JSON)
- ✅ Differentiated visual formatting (CLI with box drawing, Flash HTML, JSON RFC 7807)
- ✅ Custom exception hierarchy for different scenarios
- ✅ Context labels in Portuguese (fixed in the formatters); messages built in English by the exception factories

---

## 🏗️ Architecture

### Exception Hierarchy

```
PtahException (base abstrata)
├── ConfigValidationException    — CRUD configuration errors
├── CommandValidationException    — CLI command errors
├── BusinessRuleException         — Business rule violations
└── GenerationException           — Scaffolding/forge errors
```

### Available Traits

| Trait | Purpose |
|-------|-----------|
| `HasJsonContext` | Adds methods for JSON context (path, line, current/expected value) |
| `FormatsError` | Formats exceptions for CLI, Flash, JSON |

### Validators

| Classe | Responsabilidade |
|--------|------------------|
| `ConfigSchemaValidator` | Validates complete BaseCrud configuration |
| `CommandInputValidator` | Validates `--column`/`--action`/`--filter`/`--style`/`--join`/`--set` option strings. **Not called by any `ptah:*` command today** — `ptah:config` validates through `ConfigSchemaValidator` + `CliErrorFormatter` |
| `JsonSchemaBuilder` | Generates JSON Schema for documentation |

### Formatters

| Classe | Output |
|--------|--------|
| `CliErrorFormatter` | Terminal com box drawing (╔═╗║╚╝) |
| `FlashMessageFormatter` | HTML estruturado (Tailwind/Bootstrap) |
| `JsonErrorFormatter` | RFC 7807 Problem Details |

---

## 💻 Usage Examples

### 1. Automatic Validation in CrudConfigService

```php
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Exceptions\ConfigValidationException;

$configService = app(CrudConfigService::class);

try {
    $configService->save('Product', [
        'cols' => [
            [
                'colsNomeFisico' => 'price',
                'colsTipo' => 'invalid_type', // ❌ Invalid type
            ],
        ],
    ]);
} catch (ConfigValidationException $e) {
    // Display in terminal
    echo $e->formatAsCliOutput();
    
    // Or get context programmatically
    $context = $e->getContext();
    echo "Campo: " . $e->getField();
    echo "Valor atual: " . $e->getActualValue();
}
```

**Terminal output:**
```
╔════════════════════════════════════════════════════════════════════╗
║ ❌ Config Validation Exception                                    ║
╠════════════════════════════════════════════════════════════════════╣
║ Campo: price                                                       ║
║ Valor atual: invalid_type                                          ║
║ Opções válidas:                                                 ║
║ ["text","textarea","number","date","datetime","select",...]       ║
║ Seção: cols                                                      ║
║ Path JSON: $.cols[0].colsTipo                                      ║
║ Model: Product                                                     ║
╚════════════════════════════════════════════════════════════════════╝
```

The box lists the context only (the message is in `$e->getMessage()`), the
labels are fixed in Portuguese, and an array value such as `Opções válidas` is
printed as JSON on one line (it is not wrapped and can overflow the box).

---

### 2. Validation in Commands

`ptah:config` runs `ConfigSchemaValidator` before saving and prints the
exception through `CliErrorFormatter` (`ConfigCommand::saveConfiguration()`).
Error example (an invalid type, on purpose):

```bash
php artisan ptah:config "App\Models\Product" --column="price:invalid_type"
```

The output is the same box as in example 1 (`Campo: price`, `Valor atual:
invalid_type`, `Opções válidas: [...]`, `Path JSON: $.cols[0].colsTipo`), and the
command exits with failure without saving.

`CommandInputValidator` can also be used on its own to check an option string
(no command calls it today):

```php
use Ptah\Services\Validation\CommandInputValidator;
use Ptah\Services\Validation\Formatters\CliErrorFormatter;
use Ptah\Exceptions\CommandValidationException;

$validator = app(CommandInputValidator::class);

try {
    $validator->validateColumnOption('price:invalid_type');
} catch (CommandValidationException $e) {
    echo app(CliErrorFormatter::class)->format($e);
}
```

**Output:**
```
╔════════════════════════════════════════════════════════════════════╗
║ ❌ Command Validation Exception                                   ║
╠════════════════════════════════════════════════════════════════════╣
║ Option: column type                                                ║
║ Valor atual: invalid_type                                          ║
║ Opções válidas:                                                 ║
║ ["text","badge","boolean","date","datetime","money","numeric",...] ║
╚════════════════════════════════════════════════════════════════════╝
```

The type list of `validateColumnOption()` (`text`, `badge`, `boolean`, `date`,
`datetime`, `money`, `numeric`, `relation`) and the action types of
`validateActionOption()` (`wire`, `route`, `url`) are **not** the vocabulary
`ptah:config` accepts (`colsTipo`: see `CrudConfigEnums::COLUMN_TYPES`; action
types `link`, `livewire`, `javascript`).

---

### 3. Flash Messages in Livewire

```php
use Ptah\Services\Validation\Formatters\FlashMessageFormatter;
use Ptah\Exceptions\ConfigValidationException;

class BaseCrudComponent extends Component
{
    protected FlashMessageFormatter $flashFormatter;
    
    public function save()
    {
        try {
            // Validation and saving
            $this->crudConfigService->save($this->model, $this->formData);
            
        } catch (ConfigValidationException $e) {
            // Tailwind format
            session()->flash('error_html', $this->flashFormatter->formatTailwind($e));
            
            // Ou Bootstrap format
            session()->flash('error_html', $this->flashFormatter->format($e));
            
            return;
        }
        
        session()->flash('success', 'Configuration saved successfully!');
    }
}
```

**Blade template:**
```blade
@if (session()->has('error_html'))
    {!! session('error_html') !!}
@endif
```

**Output (Tailwind):**
```html
<div class="rounded-md bg-red-50 p-4">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                <!-- X icon -->
            </svg>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">Config Validation Exception</h3>
            <div class="mt-2 text-sm text-red-700">Invalid column type "invalid_type" for field "price". Valid types: ...</div>
            <div class="mt-2 text-sm text-red-700">
                <ul class="list-disc pl-5 space-y-1">
                    <li><strong>Campo:</strong> price</li>
                    <li><strong>Valor atual:</strong> invalid_type</li>
                    <li><strong>Opções válidas:</strong> text, textarea, number...</li>
                </ul>
            </div>
        </div>
    </div>
</div>
```

---

### 4. API Responses (JSON)

```php
use Ptah\Services\Validation\Formatters\JsonErrorFormatter;
use Ptah\Exceptions\ConfigValidationException;

class CrudApiController extends Controller
{
    protected JsonErrorFormatter $jsonFormatter;
    
    public function store(Request $request)
    {
        try {
            $config = $request->input('config');
            $this->crudConfigService->save($request->model, $config);
            
            return response()->json([
                'message' => 'Configuration saved successfully',
            ], 201);
            
        } catch (ConfigValidationException $e) {
            // RFC 7807 format
            return $this->jsonFormatter->toResponse($e);
            
            // Ou manualmente:
            return response()->json(
                $this->jsonFormatter->format($e),
                422
            );
        }
    }
}
```

**Response (JSON):**
```json
{
  "type": "https://ptah.dev/errors/config-validation-exception",
  "title": "Config Validation Exception",
  "detail": "Invalid column type \"invalid_type\" for field \"price\". Valid types: text, textarea, number, ...",
  "status": 422,
  "context": {
    "field": "price",
    "actual_value": "invalid_type",
    "available_options": ["text", "textarea", "number", "date", "datetime", "select", "..."],
    "section": "cols",
    "json_path": "$.cols[0].colsTipo",
    "model": "Product"
  }
}
```

`ConfigValidationException` brings its own `formatAsJsonResponse()` (from
`FormatsError`), and `JsonErrorFormatter::format()` uses it — so there is no
`trace_id`. The `trace_id` is only added for exceptions without that method.

---

### 5. Creating Custom Exceptions

```php
use Ptah\Exceptions\ConfigValidationException;

// Method 1: Static factory methods
throw ConfigValidationException::invalidColumnType(
    field: 'price',
    actualValue: 'invalid_type',
    validTypes: ['text', 'badge', 'boolean'],
    section: 'cols'
)->withModel('Product')
  ->withJsonPath('$.cols[0].colsTipo')
  ->withLineNumber(42);

// Method 2: Using context builder
throw ConfigValidationException::withContext(
    'Invalid column configuration',
    [
        'field' => 'price',
        'actual_value' => 'invalid_type',
        'expected_value' => ['text', 'badge'],
        'model' => 'Product',
    ]
);

// Method 3: Fluent builder
throw (new ConfigValidationException('Invalid type'))
    ->withField('price')
    ->withActualValue('invalid_type')
    ->withExpectedType('string')
    ->withSection('cols')
    ->withSuggestion('Use one of: text, badge, boolean, date');
```

---

### 6. Error Logging

```php
use Illuminate\Support\Facades\Log;
use Ptah\Exceptions\ConfigValidationException;
use Ptah\Services\Validation\Formatters\JsonErrorFormatter;

try {
    // Operation that may fail
    $this->crudConfigService->save($model, $config);
    
} catch (ConfigValidationException $e) {
    $formatter = app(JsonErrorFormatter::class);
    
    // Log estruturado (para parsing em monitoring tools)
    Log::error('CRUD configuration validation failed', 
        $formatter->formatForLogging($e)
    );
    
    // Re-throw or handle as needed
    throw $e;
}
```

**Log output:**
```json
{
  "type": "Ptah\\Exceptions\\ConfigValidationException",
  "message": "Invalid column type \"invalid_type\" for field \"price\". Valid types: text, textarea, number, ...",
  "code": 0,
  "context": {
    "field": "price",
    "actual_value": "invalid_type",
    "available_options": ["text", "textarea", "number", "..."],
    "section": "cols",
    "model": "Product"
  },
  "file": "/var/www/ptah/src/Services/Validation/ConfigSchemaValidator.php",
  "line": 95,
  "trace": "...",
  "timestamp": "2026-03-05T10:30:45-03:00"
}
```

---

## 🔧 Manual Validation

### ConfigSchemaValidator

```php
use Ptah\Services\Validation\ConfigSchemaValidator;

$validator = app(ConfigSchemaValidator::class);

$config = [
    'cols' => [
        [
            'colsNomeFisico' => 'name',
            'colsTipo' => 'text',
        ],
    ],
    'general' => [
        'itemsPerPage' => 15,
    ],
];

try {
    $validator->validate($config, 'Product');
    echo "✅ Configuration valid!";
} catch (ConfigValidationException $e) {
    echo "❌ Erro: " . $e->getMessage();
    print_r($e->getContext());
}
```

### CommandInputValidator

```php
use Ptah\Services\Validation\CommandInputValidator;

$validator = app(CommandInputValidator::class);

// Validar formato de coluna
try {
    $parsed = $validator->validateColumnOption('name:text:label=Nome:sortable');
    // ['field' => 'name', 'type' => 'text', 'modifiers' => ['label=Nome', 'sortable']]
    
} catch (CommandValidationException $e) {
    echo $e->formatAsCliOutput();
}

// Validate action format
try {
    $parsed = $validator->validateActionOption('edit:wire:editRecord:icon=bx-edit:color=primary');
    // ['name' => 'edit', 'type' => 'wire', 'value' => 'editRecord', 'modifiers' => ['icon=bx-edit', 'color=primary']]
    
} catch (CommandValidationException $e) {
    echo $e->formatAsCliOutput();
}
```

---

## 🌍 Internationalization

The package does **not** translate these errors:

- the exception messages are built in English by the factory methods
  (`ConfigValidationException::invalidColumnType()` and the others);
- the context labels (`Campo`, `Valor atual`, `Opções válidas`, `Seção`,
  `Path JSON`, …) are fixed in Portuguese inside the formatters;
- `config('app.locale')` changes none of it.

`lang/en/validation-errors.php` and `lang/pt_BR/validation-errors.php` ship in
the package, but no code loads them (the service provider only registers
`resources/lang` under the `ptah::` namespace, whose `ui.php` is the screens'
catalog), so adding a locale file there has no effect.

---

## 📝 JSON Schema Generation

```php
use Ptah\Services\Validation\JsonSchemaBuilder;

$builder = new JsonSchemaBuilder();

// Generate complete schema
$schema = $builder->buildCrudConfigSchema();

// Export as JSON
$json = $builder->exportAsJson();
file_put_contents('crud-config.schema.json', $json);

// Or save directly
$builder->saveToFile('ptah/resources/schemas/crud-config.schema.json');
```

**Generated schema (partial example):**
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "CRUD Configuration",
  "type": "object",
  "properties": {
    "cols": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["colsNomeFisico"],
        "properties": {
          "colsNomeFisico": {
            "type": "string",
            "description": "Physical column name in database"
          },
          "colsTipo": {
            "type": "string",
            "enum": ["text", "textarea", "number", "date", "datetime", "select", "searchdropdown", "boolean", "file", "image", "color", "time", "datetime-local", "range", "email", "url", "tel", "password"],
            "description": "Column display type"
          }
        }
      }
    }
  }
}
```

---

## 🎯 Best Practices

### 1. Always Capture Specific Exceptions

```php
// ❌ Avoid generic catch
try {
    $this->configService->save($model, $config);
} catch (\Exception $e) {
    // Loses specific context
}

// ✅ Capture specific exceptions
try {
    $this->configService->save($model, $config);
} catch (ConfigValidationException $e) {
    // Specific handling with full context
    Log::warning('Config validation failed', $e->getContext());
    session()->flash('error', $e->formatAsFlashMessage());
} catch (BusinessRuleException $e) {
    // Outro tratamento
}
```

### 2. Use Appropriate Formatters

```php
// CLI Command
catch (PtahException $e) {
    $formatter = app(CliErrorFormatter::class);
    $this->error($formatter->format($e));
}

// Livewire Component
catch (PtahException $e) {
    $formatter = app(FlashMessageFormatter::class);
    session()->flash('error_html', $formatter->formatTailwind($e));
}

// API Controller
catch (PtahException $e) {
    $formatter = app(JsonErrorFormatter::class);
    return $formatter->toResponse($e);
}
```

### 3. Add Context Progressively

```php
try {
    // Operation
} catch (\Exception $e) {
    throw ConfigValidationException::withContext(
        'Failed to save configuration',
        ['model' => $model]
    )->withField($field)
      ->withSection('cols')
      ->withSuggestion('Verify field configuration');
}
```

---

## 🚀 Performance

- **Validation in ~2-5ms** for typical configurations
- **No schema cache**: the validator is plain PHP; each `joins` entry runs a `Schema::hasTable()` query
- `updateSection()` merges the section and validates the **complete** configuration
- **No overhead** when there are no errors

---

## 📚 Additional Resources

- [RFC 7807 - Problem Details](https://tools.ietf.org/html/rfc7807)
- [JSON Schema Draft 07](http://json-schema.org/draft-07/schema)
- [Ptah Documentation](https://ptah.dev/docs)

---

**Implemented on:** March 5, 2026  
**Ptah Version:** 1.x
