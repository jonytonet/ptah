# Performance & high-demand architecture — reference

Loaded on demand, not with the skill. Read this when the task actually involves
throughput, caching, queues, indexes or a listing over large data — not for
ordinary CRUD work, which the BaseCrud already handles.

**One measurement worth knowing before you optimise anything here** (taken on
1.27.0, sqlite in memory so the disk is out of the picture, 5.000 rows,
20 repetitions with the warm-up discarded):

| operation | queries | time |
|---|---|---|
| plain Eloquent: `where+like+order+paginate` | 2 | 7.63 ms |
| `BaseService::getData()` AND + order + paginate | **2** | 6.70 ms |
| `getData()` searchLike | 1 | 12.05 ms |
| `getData()` search (OR across `$fillable`) | 4 | 20.01 ms |
| reading a screen's CrudConfig | 1 | 0.49 ms |

The package's main listing path emits **the same queries as hand-written
Eloquent** — the abstraction charges no toll, and being config-driven costs one
query and half a millisecond per screen. So: do not "optimise" the base layer
on suspicion. Measure first, and prefer the levers below.

---

## Performance & High Demand Architecture

> This project is designed for **high-performance, high-concurrency** workloads.
> Every code decision must consider scalability. Treat performance as a first-class requirement, not an afterthought.

### Cardinal Rules

| Rule | Detail |
|---|---|
| **Never nest foreach inside foreach** | Use `keyBy()`, `groupBy()` or a single keyed array lookup instead |
| **Never query inside a loop** | All IDs must be collected first, then fetched in one `whereIn()` — N+1 is a bug |
| **Eager-load always** | `with(['relation'])` on every query that accesses a relation |
| **Cache hot data** | Any data read more than once per request or unchanged for minutes belongs in cache |
| **Queue heavy work** | Email, PDF, export, external API calls, image processing → always a Job |
| **Index every FK and filter column** | No query without an index on filtered / joined columns |
| **Chunk large datasets** | Never `->get()` on unbounded result sets — use `->chunk()` or cursor |

---

### Cache — Mandatory Patterns

#### Tag-based cache (Redis)

```php
// ✅ Always use tags for grouped invalidation
Cache::tags(['products', 'catalog'])->remember(
    "product:{$id}",
    now()->addMinutes(30),
    fn () => $this->repo->findOrFail($id)
);

// Invalidate on write
public function update(int $id, array $data): Product
{
    $result = $this->repo->update($id, $data);
    Cache::tags(['products', 'catalog'])->flush();
    return $result;
}
```

#### Cache keys — naming convention

```php
// pattern: {entity}:{id|variant}:{context}
"product:{$id}"                  // single record
"products:active"                // list
"products:category:{$catId}"     // filtered list
"user:{$userId}:cart"            // user-scoped
```

#### What to cache (and for how long)

| Data | TTL | Tags |
|---|---|---|
| Reference/lookup tables (species, breeds, categories) | 24h | `['reference']` |
| Product catalog listing | 30 min | `['products', 'catalog']` |
| Individual product | 30 min | `['products', "product:{$id}"]` |
| User-specific data (cart, preferences) | session | `['user:{$id}']` |
| Dashboard aggregates | 5 min | `['reports']` |
| Auth / permissions | until logout | `['permissions', "user:{$id}"]` |

#### Never cache

```php
// ❌ Never cache mutable financial / stock data without explicit invalidation
// ❌ Never cache full paginated results (cache the data, not the paginator)
// ❌ Never hardcode TTL in Controller or Livewire — always in Service or Repository
```

---

### Jobs & Queues — Mandatory Patterns

#### What must be a Job

```
✅ Sending emails / SMS / WhatsApp notifications
✅ Generating PDF / Excel exports
✅ Syncing stock with external ERP/API
✅ Resizing / processing uploaded images
✅ Webhook dispatch
✅ Heavy aggregation / report generation
✅ Invalidating distributed cache across nodes
✅ Any operation > 200ms
```

#### Job structure

```php
<?php

namespace App\Jobs;

use App\Models\Order;
use App\Contracts\Services\OrderServiceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60; // seconds between retries

    public function __construct(private readonly int $orderId) {}

    public function handle(OrderServiceContract $service): void
    {
        $service->processOrder($this->orderId);
    }

    public function failed(\Throwable $e): void
    {
        \Log::error("ProcessOrderJob failed for order {$this->orderId}: {$e->getMessage()}");
    }
}

// Dispatch from Service — never from Controller or Livewire directly
ProcessOrderJob::dispatch($order->id)->onQueue('orders');
```

#### Named queues — priority tiers

```
high    → auth, payments, critical notifications
default → order processing, stock movements
low     → reports, PDF exports, bulk emails, image processing
```

#### Required in production

```bash
# Laravel Horizon (Redis-backed queue dashboard)
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

---

### Database Indexes — Mandatory Rules

#### Every migration must include indexes on

```
✅ All _id foreign key columns (auto-handled by foreignId())
✅ All columns used in WHERE clauses (status, is_active, type)
✅ All columns used in ORDER BY frequently  
✅ Composite indexes for multi-column filters
✅ Unique indexes for natural keys (sku, slug, cpf, email, code)
```

#### Migration patterns

```php
public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('sku', 50)->unique();
        $table->string('name');
        $table->decimal('price', 10, 2);
        $table->integer('stock')->default(0);
        $table->boolean('is_active')->default(true)->index();
        $table->boolean('is_featured')->default(false);
        $table->foreignId('category_id')->constrained()->cascadeOnDelete();
        $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
        $table->softDeletes();
        $table->timestamps();

        // Composite indexes for common query patterns
        $table->index(['is_active', 'is_featured']);          // catalog listing
        $table->index(['category_id', 'is_active', 'price']); // filtered catalog
        $table->index(['deleted_at', 'is_active']);           // soft-delete fast filter
    });
}
```

#### Forced index in Repository (when query plan regression is detected)

```php
// Available via BaseRepository::useIndex()
public function getActiveFeatured(): Collection
{
    return $this->useIndex('products_is_active_is_featured_index')
        ->where('is_active', true)
        ->where('is_featured', true)
        ->with(['category', 'brand'])
        ->get();
}
```

---

### N+1 — Forbidden Query Patterns

```php
// ❌ CRITICAL BUG — N+1: 1 query for products + N queries for categories
$products = Product::all();
foreach ($products as $product) {
    echo $product->category->name; // query per iteration
}

// ✅ Correct — 2 queries total
$products = Product::with('category')->get();

// ❌ CRITICAL BUG — nested foreach O(n²)
foreach ($orders as $order) {
    foreach ($items as $item) {
        if ($item->order_id === $order->id) { ... } // O(n²)
    }
}

// ✅ Correct — O(n) using keyBy
$itemsByOrder = $items->groupBy('order_id'); // one pass
foreach ($orders as $order) {
    $orderItems = $itemsByOrder->get($order->id, collect());
}

// ❌ CRITICAL BUG — query inside loop
foreach ($productIds as $id) {
    $stock = Stock::where('product_id', $id)->sum('qty'); // N queries
}

// ✅ Correct — one query with groupBy
$stocks = Stock::whereIn('product_id', $productIds)
    ->selectRaw('product_id, SUM(qty) as total')
    ->groupBy('product_id')
    ->pluck('total', 'product_id');

foreach ($productIds as $id) {
    $stock = $stocks[$id] ?? 0;
}
```

---

### Large Datasets — Chunking & Cursor

```php
// ❌ Never — loads everything into memory
$all = Order::where('status', 'pending')->get();
foreach ($all as $order) { ... }

// ✅ chunk() — processes in batches (memory safe)
Order::where('status', 'pending')
    ->chunk(200, function (Collection $batch) {
        foreach ($batch as $order) {
            ProcessOrderJob::dispatch($order->id)->onQueue('orders');
        }
    });

// ✅ lazy() — generator-based cursor for read-only operations
Order::where('status', 'pending')->lazy()->each(function (Order $order) {
    // one record in memory at a time
    ReportJob::dispatch($order->id);
});

// ✅ chunkById() — safer than chunk() for delete/update within the loop
Order::where('status', 'cancelled')->chunkById(500, fn ($batch) => ...);
```

---

### Livewire Performance Rules

```php
// ❌ Computing expensive data on every Livewire render
public function render()
{
    return view('livewire.dashboard', [
        'stats' => $this->service->getFullStats(),    // heavy query on every interaction
        'chart' => $this->service->buildChartData(),  // heavy query on every interaction
    ]);
}

// ✅ Use #[Computed] with cache TTL
use Livewire\Attributes\Computed;

#[Computed(seconds: 300)]
public function stats(): array
{
    return $this->service->getFullStats();
}

#[Computed(seconds: 300)]
public function chartData(): array
{
    return $this->service->buildChartData();
}

// ✅ Lazy-load expensive sections
#[Lazy]
public function render() { ... }

// ✅ wire:model.blur on all text inputs — avoids a server round-trip per keystroke
<x-forge-input wire:model.blur="search" />
```

---
### Recommended Tools (Optional)

| Tool | Purpose | When to use |
|---|---|---|
| **Redis** | Primary cache + queue driver | Recommended for staging/production |
| **Laravel Horizon** | Queue dashboard + monitoring | Recommended for projects with jobs |
| **Laravel Telescope** (dev only) | Query/job/cache/request inspector | Development only (`--dev`) |
| **Laravel Octane** | App server (Swoole/FrankenPHP) | High-concurrency APIs (optional) |
| **Laravel Scout** | Full-text search (Meilisearch/Algolia) | Search on large text catalogs (optional) |
| **Clockwork** | Timeline profiler (browser devtools) | Browser-side profiling in dev (optional) |

```bash
# Essential installations
composer require laravel/horizon
composer require laravel/scout
composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle
composer require --dev laravel/telescope
composer require --dev itsgoingd/clockwork
```

---

### Performance Anti-Patterns (FORBIDDEN — agent must reject these patterns)

> **Critical:** These patterns cause production outages in high-traffic scenarios.
> Agents must **refuse** to generate code containing any of these patterns and **fix** them immediately when detected in existing code.

#### 1. N+1 Query Problem

```php
// ❌ CRITICAL BUG — 1 query for orders + N queries for clients
$orders = Order::all(); // 1 query
foreach ($orders as $order) {
    echo $order->client->name; // N queries (one per iteration)
}

// ✅ FIX: eager load — 2 queries total
$orders = Order::with('client')->get();
foreach ($orders as $order) {
    echo $order->client->name; // no extra query
}
```

#### 2. Nested foreach on Collections

```php
// ❌ CRITICAL BUG — O(n²) complexity
foreach ($orders as $order) {
    foreach ($items as $item) {
        if ($item->order_id === $order->id) { // n × m iterations
            $order->items[] = $item;
        }
    }
}

// ✅ FIX: groupBy() — O(n)
$itemsByOrder = $items->groupBy('order_id');
foreach ($orders as $order) {
    $order->items = $itemsByOrder->get($order->id, collect());
}
```

#### 3. Query Inside Loop

```php
// ❌ CRITICAL BUG — N queries
foreach ($productIds as $id) {
    $stock = Stock::where('product_id', $id)->sum('qty'); // query per iteration
}

// ✅ FIX: collect IDs, single whereIn()
$stocks = Stock::whereIn('product_id', $productIds)
    ->selectRaw('product_id, SUM(qty) as total')
    ->groupBy('product_id')
    ->pluck('total', 'product_id');

foreach ($productIds as $id) {
    $stock = $stocks[$id] ?? 0;
}
```

#### 4. Unbounded ->get() Without Pagination

```php
// ❌ CRITICAL BUG — loads 100k rows into memory
$all = Order::where('status', 'pending')->get();
foreach ($all as $order) { ... }

// ✅ FIX: chunk() for batch processing
Order::where('status', 'pending')->chunk(200, function ($batch) {
    foreach ($batch as $order) {
        ProcessOrderJob::dispatch($order->id);
    }
});

// ✅ FIX: lazy() for read-only iteration
Order::where('status', 'pending')->lazy()->each(function ($order) {
    // one record in memory at a time
});
```

#### 5. Missing Eager Load

```php
// ❌ CRITICAL BUG — relations accessed without with()
$products = Product::all(); // no ->with()
foreach ($products as $p) {
    echo $p->category->name;  // N queries
    echo $p->brand->name;     // N queries
}

// ✅ FIX: eager load all accessed relations
$products = Product::with(['category', 'brand'])->get();
```

#### 6. Synchronous External Calls in Web Request

```php
// ❌ CRITICAL BUG — blocks request for 2+ seconds
public function store(Request $request)
{
    $order = Order::create($request->all());
    Mail::send(new OrderConfirmation($order));        // blocks 1s
    Http::post('https://erp.com/api/sync', $order);   // blocks 1s
    return response()->json($order);
}

// ✅ FIX: queue everything async
public function store(Request $request)
{
    $order = Order::create($request->all());
    Mail::queue(new OrderConfirmation($order));       // instant
    SyncOrderJob::dispatch($order->id);               // instant
    return response()->json($order);
}
```

#### 7. Heavy Livewire Computation on Every Render

```php
// ❌ CRITICAL BUG — runs query on every keystroke
public function render()
{
    return view('livewire.dashboard', [
        'stats' => Order::selectRaw('COUNT(*), SUM(total)')->get(), // every render
    ]);
}

// ✅ FIX: #[Computed] with cache
use Livewire\Attributes\Computed;

#[Computed(seconds: 300)]
public function stats()
{
    return Order::selectRaw('COUNT(*), SUM(total)')->get();
}
```

#### 8. Cache Inside Loop

```php
// ❌ CRITICAL BUG — N cache reads
foreach ($productIds as $id) {
    $product = Cache::get("product:{$id}"); // cache hit/miss per iteration
}

// ✅ FIX: fetch all cache keys at once (Redis mget)
$keys = array_map(fn($id) => "product:{$id}", $productIds);
$cached = Cache::many($keys);

foreach ($productIds as $id) {
    $product = $cached["product:{$id}"] ?? null;
    if (!$product) {
        $product = Product::find($id);
        Cache::put("product:{$id}", $product, 1800);
    }
}
```

#### 9. SELECT * With Unneeded Columns

```php
// ❌ BAD — transfers 10 columns when only 2 are needed
$products = Product::all();
foreach ($products as $p) {
    echo $p->id . ' - ' . $p->name;
}

// ✅ FIX: select only needed columns
$products = Product::select(['id', 'name'])->get();
```

#### 10. Missing Database Index

```php
// ❌ CRITICAL BUG — migration without index on filtered column
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('status');  // ❌ no index
    $table->timestamps();
});

// Repository:
Order::where('status', 'pending')->get(); // full table scan

// ✅ FIX: add index
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('status')->index();  // ✅ indexed
    $table->timestamps();
});
```

#### 11. Individual Job Dispatch in Loop

```php
// ❌ BAD — dispatches 1000 jobs sequentially
foreach ($orderIds as $id) {
    ProcessOrderJob::dispatch($id); // 1000 Redis writes
}

// ✅ FIX: batch dispatch
Bus::batch(
    collect($orderIds)->map(fn($id) => new ProcessOrderJob($id))
)->dispatch();
```

#### 12. Direct Delete Without Eloquent Events (HasAuditFields)

```php
// ❌ CRITICAL BUG — bypasses deleted_by audit
Product::whereIn('id', $ids)->delete(); // no deleted event fired

// ✅ FIX: delete individually to fire events
Product::whereIn('id', $ids)->each(fn($p) => $p->delete());
```

---

### Performance Anti-Pattern Checklist (agent must enforce before generating code)

```
[ ] Does any Repository method have a query inside a loop?
    → FIX: Collect IDs first, then ->whereIn() in one query

[ ] Does any method call ->get() on an unbounded result set?
    → FIX: Use ->paginate() or ->chunk() or ->lazy()

[ ] Are all relations eager-loaded with ->with([]) before accessing them?
    → FIX: Add ->with(['relation']) to the query

[ ] Is any heavy operation (email, PDF, API call) synchronous?
    → FIX: Dispatch a Job with ->onQueue()

[ ] Is frequently read data computed fresh on every request?
    → FIX: Use Cache::tags()->remember() with appropriate TTL

[ ] Are new filter columns missing an index in the migration?
    → FIX: Add $table->index() or ->index() after column definition

[ ] Does any Livewire render() run expensive queries on every interaction?
    → FIX: Use #[Computed(seconds: X)]

[ ] Are there nested foreach loops on Eloquent collections?
    → FIX: Use ->keyBy() or ->groupBy()

[ ] Is any query selecting all columns when only a few are needed?
    → FIX: Use ->select(['id', 'name', ...])

[ ] Does any bulk delete skip Eloquent events (HasAuditFields)?
    → FIX: Use ->each(fn($r) => $r->delete())
```

---
