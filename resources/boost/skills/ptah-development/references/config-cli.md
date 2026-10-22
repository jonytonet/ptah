# ptah:config — less common options — reference

Loaded on demand, not with the skill. Read this when the task configures a
join, screen permissions or a custom bulk action.

## --join

Format: `type:table:first=second[:distinct][:select=col,col as alias]` (`JoinParser`) — `type` is `left` or `inner`. Stored as `{type, table, first, second, distinct, select: [{column, alias}]}`. Any other `key=value` (e.g. `where=`) is **refused**: a JOIN has no WHERE of its own.

**Example:** `left:suppliers:products.supplier_id=suppliers.id:select=suppliers.name as supplier_name`

## --permission

Format: `key=value`, repeatable. `create`, `edit`, `delete`, `restore`, `export`, `import`, `history`, `attachments` take a **Gate name** (string, checked with `can()`); to turn an action off use `showCreateButton=false`, `showEditButton=false`, `showDeleteButton=false` or `showTrashButton=false` — never a boolean in `create|edit|delete`. `permissionIdentifier=<key>` is the ACL key of the Permissions module (see [Permissions.md](../../../../../docs/Permissions.md)) — it gates nothing while `PTAH_MODULE_PERMISSIONS` is off.

**Example:** `--permission="edit=products.update" --permission="showDeleteButton=false"`

## Custom bulk actions

`bulkActions` entries (`{label, action, method}`) with `"method": "Class@key"`: the class MUST implement `Ptah\Contracts\BulkActionHandler` (`handleBulkAction(array $ids, string $model, string $key): void`); the part after `@` is only a key handed to it, never a method name ptah calls. Without the interface the action does not run (`ptah:config:doctor` reports it).
