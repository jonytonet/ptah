# Sistema de Validação e Mensagens de Erro — Ptah

## 📋 Visão Geral

O Ptah implementa um sistema robusto de validação com **mensagens de erro detalhadas** que incluem:
- ✅ Contexto completo (campo, valor atual vs esperado, linha do JSON)
- ✅ Formatação visual diferenciada (CLI com box drawing, Flash HTML, JSON RFC 7807)
- ✅ Hierarquia de exceções customizadas para diferentes cenários
- ✅ Internacionalização (PT-BR e EN)

---

## 🏗️ Arquitetura

### Hierarquia de Exceções

```
PtahException (base abstrata)
├── ConfigValidationException    — Erros de configuração do CRUD
├── CommandValidationException    — Erros em comandos CLI
├── BusinessRuleException         — Violações de regras de negócio
└── GenerationException           — Erros no scaffolding/forge
```

### Traits Disponíveis

| Trait | Propósito |
|-------|-----------|
| `HasJsonContext` | Adiciona métodos para contexto JSON (path, linha, valor atual/esperado) |
| `FormatsError` | Formata exceções para CLI, Flash, JSON |

### Validators

| Classe | Responsabilidade |
|--------|------------------|
| `ConfigSchemaValidator` | Valida configuração completa do BaseCrud |
| `CommandInputValidator` | Valida inputs de comandos Artisan |
| `JsonSchemaBuilder` | Gera JSON Schema para documentação |

### Formatters

| Classe | Output |
|--------|--------|
| `CliErrorFormatter` | Terminal com box drawing (╔═╗║╚╝) |
| `FlashMessageFormatter` | HTML estruturado (Tailwind/Bootstrap) |
| `JsonErrorFormatter` | RFC 7807 Problem Details |

---

## 💻 Exemplos de Uso

### 1. Validação Automática no CrudConfigService

```php
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Exceptions\ConfigValidationException;

$configService = app(CrudConfigService::class);

try {
    $configService->save('Product', [
        'cols' => [
            [
                'colsNomeFisico' => 'price',
                'colsTipo' => 'invalid_type', // ❌ Tipo inválido
            ],
        ],
    ]);
} catch (ConfigValidationException $e) {
    // Exibir no terminal
    echo $e->formatAsCliOutput();
    
    // Ou obter contexto programaticamente
    $context = $e->getContext();
    echo "Campo: " . $e->getField();
    echo "Valor atual: " . $e->getActualValue();
}
```

**Output no terminal:**
```
╔══════════════════════════════════════════════════════════════════╗
║ ❌ Config Validation Exception                                   ║
╠══════════════════════════════════════════════════════════════════╣
║ Campo:           price                                            ║
║ Valor atual:     invalid_type                                     ║
║ Valor esperado:  text, badge, boolean, date, datetime, money...  ║
║ Seção:           cols                                             ║
║ Path JSON:       $.cols[0].colsTipo                               ║
║ Model:           Product                                          ║
╚══════════════════════════════════════════════════════════════════╝
```

---

### 2. Validação em Commands

```php
use Ptah\Services\Validation\CommandInputValidator;
use Ptah\Services\Validation\Formatters\CliErrorFormatter;
use Ptah\Exceptions\CommandValidationException;

class ConfigCommand extends Command
{
    protected CommandInputValidator $validator;
    protected CliErrorFormatter $formatter;
    
    public function handle()
    {
        try {
            $columnOpt = $this->option('column');
            $parsed = $this->validator->validateColumnOption($columnOpt);
            
            // Continue com a lógica...
            
        } catch (CommandValidationException $e) {
            $this->error($this->formatter->format($e));
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}
```

**Exemplo de erro:**
```bash
php artisan ptah:config Product --column="price:invalid_type"
```

**Output:**
```
╔══════════════════════════════════════════════════════════════════╗
║ ❌ Command Validation Exception                                  ║
╠══════════════════════════════════════════════════════════════════╣
║ Opção:           column                                           ║
║ Valor atual:     price:invalid_type                               ║
║ Tipo esperado:   text|badge|boolean|date|datetime|money|numeric   ║
║ Sugestão:        Use format: field:type[:modifier=value...]       ║
╚══════════════════════════════════════════════════════════════════╝
```

---

### 3. Flash Messages no Livewire

```php
use Ptah\Services\Validation\Formatters\FlashMessageFormatter;
use Ptah\Exceptions\ConfigValidationException;

class BaseCrudComponent extends Component
{
    protected FlashMessageFormatter $flashFormatter;
    
    public function save()
    {
        try {
            // Validação e salvamento
            $this->crudConfigService->save($this->model, $this->formData);
            
        } catch (ConfigValidationException $e) {
            // Tailwind format
            session()->flash('error_html', $this->flashFormatter->formatTailwind($e));
            
            // Ou Bootstrap format
            session()->flash('error_html', $this->flashFormatter->format($e));
            
            return;
        }
        
        session()->flash('success', 'Configuração salva com sucesso!');
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
            <div class="mt-2 text-sm text-red-700">
                <ul class="list-disc pl-5 space-y-1">
                    <li><strong>Campo:</strong> price</li>
                    <li><strong>Valor atual:</strong> invalid_type</li>
                    <li><strong>Valor esperado:</strong> text, badge, boolean...</li>
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
  "detail": "Invalid column type \"invalid_type\" for field \"price\"",
  "status": 422,
  "context": {
    "field": "price",
    "actual_value": "invalid_type",
    "expected_value": ["text", "badge", "boolean", "date", "datetime", "money", "numeric"],
    "section": "cols",
    "json_path": "$.cols[0].colsTipo",
    "model": "Product"
  },
  "trace_id": "9d7e4f1a-3c2b-4a5e-8f9d-1c2b3a4e5f6a"
}
```

---

### 5. Criando Exceções Customizadas

```php
use Ptah\Exceptions\ConfigValidationException;

// Método 1: Static factory methods
throw ConfigValidationException::invalidColumnType(
    field: 'price',
    actualValue: 'invalid_type',
    validTypes: ['text', 'badge', 'boolean'],
    section: 'cols'
)->withModel('Product')
  ->withJsonPath('$.cols[0].colsTipo')
  ->withLineNumber(42);

// Método 2: Usando context builder
throw ConfigValidationException::withContext(
    'Invalid column configuration',
    [
        'field' => 'price',
        'actual_value' => 'invalid_type',
        'expected_value' => ['text', 'badge'],
        'model' => 'Product',
    ]
);

// Método 3: Fluent builder
throw (new ConfigValidationException('Invalid type'))
    ->withField('price')
    ->withActualValue('invalid_type')
    ->withExpectedType('string')
    ->withSection('cols')
    ->withSuggestion('Use one of: text, badge, boolean, date');
```

---

### 6. Logging de Erros

```php
use Illuminate\Support\Facades\Log;
use Ptah\Exceptions\ConfigValidationException;
use Ptah\Services\Validation\Formatters\JsonErrorFormatter;

try {
    // Operação que pode falhar
    $this->crudConfigService->save($model, $config);
    
} catch (ConfigValidationException $e) {
    $formatter = app(JsonErrorFormatter::class);
    
    // Log estruturado (para parsing em monitoring tools)
    Log::error('CRUD configuration validation failed', 
        $formatter->formatForLogging($e)
    );
    
    // Re-throw ou handle conforme necessário
    throw $e;
}
```

**Log output:**
```json
{
  "type": "Ptah\\Exceptions\\ConfigValidationException",
  "message": "Invalid column type \"invalid_type\" for field \"price\"",
  "code": 0,
  "context": {
    "field": "price",
    "actual_value": "invalid_type",
    "expected_value": ["text", "badge", "boolean"],
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

## 🔧 Validação Manual

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
        'cacheEnabled' => true,
    ],
];

try {
    $validator->validate($config, 'Product');
    echo "✅ Configuração válida!";
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
    $parsed = $validator->validateColumnOption('name:text:label=Nome:sortable=true');
    // ['field' => 'name', 'type' => 'text', 'modifiers' => []]
    
} catch (CommandValidationException $e) {
    echo $e->formatAsCliOutput();
}

// Validar formato de ação
try {
    $parsed = $validator->validateActionOption('edit:wire:editRecord:icon=bx-edit:color=primary');
    // ['name' => 'edit', 'type' => 'wire', 'value' => 'editRecord', ...]
    
} catch (CommandValidationException $e) {
    echo $e->formatAsCliOutput();
}
```

---

## 🌍 Internacionalização

### Usando Traduções

```php
// config/app.php
'locale' => 'pt_BR', // ou 'en'

// As mensagens de erro serão automaticamente exibidas no idioma configurado
```

### Adicionando Novos Idiomas

1. Crie o arquivo de tradução:
```bash
cp ptah/lang/en/validation-errors.php ptah/lang/es/validation-errors.php
```

2. Traduza as mensagens:
```php
// ptah/lang/es/validation-errors.php
return [
    'invalid_column_type' => 'Tipo de columna inválido ":type" para el campo ":field"',
    // ...
];
```

---

## 📝 JSON Schema Generation

```php
use Ptah\Services\Validation\JsonSchemaBuilder;

$builder = new JsonSchemaBuilder();

// Gerar schema completo
$schema = $builder->buildCrudConfigSchema();

// Exportar como JSON
$json = $builder->exportAsJson();
file_put_contents('crud-config.schema.json', $json);

// Ou salvar direto
$builder->saveToFile('ptah/resources/schemas/crud-config.schema.json');
```

**Schema gerado (exemplo parcial):**
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
            "enum": ["text", "badge", "boolean", "date", "datetime", "money", "numeric"],
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

### 1. Sempre Capture Exceções Específicas

```php
// ❌ Evite catch genérico
try {
    $this->configService->save($model, $config);
} catch (\Exception $e) {
    // Perde contexto específico
}

// ✅ Capture exceções específicas
try {
    $this->configService->save($model, $config);
} catch (ConfigValidationException $e) {
    // Tratamento específico com contexto completo
    Log::warning('Config validation failed', $e->getContext());
    session()->flash('error', $e->formatAsFlashMessage());
} catch (BusinessRuleException $e) {
    // Outro tratamento
}
```

### 2. Use Formatters Apropriados

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

### 3. Adicione Contexto Progressivamente

```php
try {
    // Operação
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

- **Validação em ~2-5ms** para configurações típicas
- **Cache de schemas** compilados (Opcache)
- **Lazy validation**: só valida o que mudou em `updateSection()`
- **Sem overhead** quando não há erros

---

## 📚 Recursos Adicionais

- [RFC 7807 - Problem Details](https://tools.ietf.org/html/rfc7807)
- [JSON Schema Draft 07](http://json-schema.org/draft-07/schema)
- [Ptah Documentation](https://ptah.dev/docs)

---

**Implementado em:** 5 de março de 2026  
**Versão Ptah:** 2.5.0+
