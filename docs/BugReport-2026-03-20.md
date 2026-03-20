# Bug Report Response — ptah `jonytonet/ptah`

**Data:** 2026-03-20  
**Versão analisada:** `dev-main`  
**Referência:** `problemas-ptah.md` (PetPlace ERP)

---

## Resumo Executivo

| Bug | Título | Status anterior | Status atual |
|---|---|---|---|
| #11 | `--filter` sempre falha na validação | 🐛 Bug ativo | ✅ Corrigido |
| #12 | `--style` sempre falha na validação | 🐛 Bug ativo | ✅ Corrigido |
| #13 | `badge`/`money` usados como `colsTipo` | ⚙️ Documentação | ℹ️ Confirmado (comportamento esperado) |
| #14 | `renderer=money/number` rejeitado via CLI | 🐛 Bug ativo | ℹ️ Não reproduzível — não é bug |
| #15 | `searchdropdown` casing errado no validator | 🐛 Bug silencioso | ℹ️ Não reproduzível — não é bug |
| #16 | ColumnParser quebra valores com `:` | 🐛 Bug ativo | ✅ Corrigido |
| #17 | `--action` falha para todos os tipos | 🐛 Bug ativo | ✅ Corrigido |

---

## Detalhamento por Bug

---

### Bug #11 — `--filter` sempre falhava ✅ CORRIGIDO

**Causa raiz:** `FilterParser::parse()` gerava a chave `colsNomeFisico` no array de saída, mas `ConfigValidator::validateFilter()` esperava a chave `field`. Mismatch entre parser e validador — a validação lançava exceção antes de salvar.

**Correção aplicada em** `src/Commands/Config/Parsers/FilterParser.php`:
- Chave `'colsNomeFisico'` → `'field'` no array retornado pelo parser

**Como usar agora:**
```bash
php artisan ptah:config "App\Models\Product" \
  --filter="status:select:options=active,inactive:operator==" \
  --filter="name:text:label=Nome do Produto"
```

---

### Bug #12 — `--style` sempre falhava ✅ CORRIGIDO

**Causa raiz:** `StyleParser::parse()` gerava as chaves `colsNomeFisico`, `colsOperator`, `colsValue`, `colsCss`, mas `ConfigValidator::validateStyle()` esperava `field`, `condition`, `value`, `style`. Mesmo padrão do Bug #11.

**Correção aplicada em** `src/Commands/Config/Parsers/StyleParser.php`:
- Chaves renomeadas para `field`, `condition`, `value`, `style`

**Como usar agora:**
```bash
php artisan ptah:config "App\Models\Product" \
  --style="status:==:inactive:background:#FEE2E2;color:#991B1B;" \
  --style="stock:<=:0:background:#FEF3C7;color:#92400E;"
```

---

### Bug #13 — `badge`/`money` como `colsTipo` ℹ️ CONFIRMADO (comportamento esperado)

**Análise:** Confirmado que `badge` e `money` são **renderers**, não tipos de input. O `ConfigSchemaValidator` rejeita esses valores em `colsTipo` — comportamento correto.

**Regra:**
- `colsTipo` = tipo do campo no formulário de criação/edição: `text`, `number`, `select`, `boolean`, `image`, etc.
- `renderer=badge|money|number|...` = como o valor é exibido na listagem

```bash
# ERRADO:
--column="status:badge"

# CORRETO:
--column="status:select:renderer=badge:badges=active|green,inactive|red"
```

Não há bug no código — a documentação interna do projeto precisava ser ajustada.

---

### Bug #14 — `renderer=money/number` rejeitado via CLI ℹ️ NÃO REPRODUZÍVEL

**Análise do código (`ConfigValidator::validateColumn`):**

```php
if (!empty($config['colsRenderer']) && !in_array($config['colsRenderer'], CrudConfigEnums::RENDERERS)) {
    throw new \InvalidArgumentException(...);
}
```

`CrudConfigEnums::RENDERERS` contém todos os 19 valores: `text`, `badge`, `pill`, `boolean`, `money`, `date`, `datetime`, `link`, `image`, `truncate`, `number`, `filesize`, `duration`, `code`, `color`, `progress`, `rating`, `qrcode`.

**Conclusão:** `renderer=money` e `renderer=number` são aceitos corretamente pelo validador. O bug não existe na versão analisada.

---

### Bug #15 — `searchdropdown` casing errado ℹ️ NÃO REPRODUZÍVEL

**Análise do código (`ConfigValidator::validateColumn`):**

```php
if ($config['colsTipo'] === 'searchdropdown') {  // ← já lowercase, correto
    if (empty($config['colsSDModel']) && empty($config['colsSDService'])) {
        throw new \InvalidArgumentException('SearchDropdown requires colsSDModel or colsSDService');
    }
}
```

O validador já compara com `'searchdropdown'` (lowercase), que é o valor armazenado por `CrudConfigEnums::COLUMN_TYPES`. A validação de dependência ocorre corretamente.

**Conclusão:** Não há bug. Colunas `searchdropdown` sem `sd_model` ou `sd_service` geram erro como esperado.

---

### Bug #16 — `options=key:Label` quebrava silenciosamente ✅ CORRIGIDO

**Causa raiz:** `ColumnParser::parse()` aplicava `explode(':', $definition)` na string inteira antes de processar os tokens. Valores de opções que continham `:` (como `options=active:Active,inactive:Inactive`) eram fragmentados incorretamente após o split.

**Correção aplicada em** `src/Commands/Config/Parsers/ColumnParser.php`:
- Substituído `explode(':')` por método `tokenize()` inteligente
- Os dois primeiros tokens (`field` e `type`) são sempre posicionais
- A partir do terceiro token: modificadores standalone (`required`, `hidden`) são detectados pela ausência de `=`; tokens sem `=` após um `key=value` são tratados como continuação do valor anterior (preservando `:` interno)

**Impacto:** corrige também `badges=`, `validation=` com regras compostas, e qualquer outro valor de opção que contenha `:`

**Como usar agora:**
```bash
# Ambos funcionam:
--column="status:select:options=active:Active,inactive:Inactive"
--column="status:select:renderer=badge:badges=active|green|Ativo,inactive|gray|Inativo"
```

---

### Bug #17 — `--action` falhava para todos os tipos ✅ CORRIGIDO

**Causa raiz (dois problemas combinados):**

1. `ActionParser::parse()` fazia `array_shift()` apenas uma vez para o value, o que quebrava URLs que contêm `:` (ex: `https://example.com/products/%id%` virava apenas `https`)
2. O bug era reportado com tipos `url`/`wire`/`route`/`modal` — que **não existem** no enum. Os tipos válidos são `link`, `livewire`, `javascript`

**Correção aplicada em** `src/Commands/Config/Parsers/ActionParser.php`:
- Value é coletado consumindo tokens sequencialmente enquanto não encontrar um token no formato `key=value` — preserva `:` em URLs e valores de método

**Como usar agora:**
```bash
# Link para página externa (URL com :// preservado):
--action="view:link:https://app.example.com/products/%id%:icon=bx-show:color=info"

# Livewire method call:
--action="approve:livewire:approve(%id%):icon=bx-check:color=success"

# Para toda linha clicável (mais simples):
--set="configLinkLinha=/products/%id%"
```

> **Atenção:** Tipos `url`, `wire`, `route`, `modal` **não são válidos**. Usar sempre `link`, `livewire` ou `javascript`.

---

## Arquivos Modificados

| Arquivo | Bug(s) |
|---|---|
| `src/Commands/Config/Parsers/FilterParser.php` | #11 |
| `src/Commands/Config/Parsers/StyleParser.php` | #12 |
| `src/Commands/Config/Parsers/ColumnParser.php` | #16 |
| `src/Commands/Config/Parsers/ActionParser.php` | #17 |
| `docs/KnownLimitations.md` | Todos |
