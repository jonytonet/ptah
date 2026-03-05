<?php

/**
 * Exemplo de Lifecycle Hooks usando Classes PHP
 * 
 * Este arquivo demonstra como criar hooks reutilizáveis, testáveis e
 * com autocomplete para o sistema BaseCrud da Ptah.
 * 
 * Como usar:
 * 1. Copie este arquivo para app/CrudHooks/ProductHooks.php
 * 2. Ajuste o namespace e a lógica conforme necessário
 * 3. Crie as classes referenciadas (Events, Jobs, Notifications, etc.)
 * 4. No modal de configuração do CRUD, use: @ProductHooks
 * 
 * IMPORTANTE: Este é um arquivo de EXEMPLO com classes fictícias.
 * As classes App\Events\*, App\Jobs\*, App\Notifications\*, etc. não
 * existem no projeto base e devem ser criadas conforme sua necessidade.
 * 
 * @see ptah/docs/Configuration.md (Seção 14: Lifecycle Hooks)
 */

namespace App\CrudHooks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductHooks
{
    /**
     * Hook executado ANTES de criar um novo registro.
     * 
     * Use para:
     * - Definir valores padrão
     * - Gerar UUIDs, slugs, códigos
     * - Validação customizada
     * - Transformar dados antes de salvar
     * 
     * Assinatura obrigatória:
     * @param array &$data Dados do formulário (mutável por referência)
     * @param Model|null $record Sempre null neste hook
     * @param mixed $component Componente Livewire (HasCrudForm trait)
     */
    public function beforeCreate(array &$data, ?Model $record, $component): void
    {
        // ── Exemplo 1: Definir valores padrão ──────────────────────────
        $data['status'] = $data['status'] ?? 'pending';
        $data['uuid'] = Str::uuid()->toString();
        
        // ── Exemplo 2: Gerar slug automaticamente ──────────────────────
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        
        // ── Exemplo 3: Gerar código único ──────────────────────────────
        if (empty($data['code'])) {
            $data['code'] = 'PROD-' . strtoupper(Str::random(8));
        }
        
        // ── Exemplo 4: Definir timestamps customizados ────────────────
        $data['published_at'] = $data['published_at'] ?? now();
        
        // ── Exemplo 5: Validação customizada ───────────────────────────
        if (isset($data['price']) && $data['price'] < 0) {
            throw new \InvalidArgumentException('Preço não pode ser negativo');
        }
        
        // ── Exemplo 6: Log estruturado ─────────────────────────────────
        Log::info('ProductHooks: Preparando criação de produto', [
            'name' => $data['name'] ?? 'N/A',
            'slug' => $data['slug'] ?? 'N/A',
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Hook executado APÓS criar um novo registro com sucesso.
     * 
     * Use para:
     * - Disparar eventos
     * - Criar registros relacionados
     * - Enviar notificações
     * - Atualizar cache
     * - Disparar jobs assíncronos
     * 
     * Assinatura obrigatória:
     * @param array &$data Dados que foram salvos (somente leitura)
     * @param Model $record Registro recém-criado (com ID)
     * @param mixed $component Componente Livewire
     */
    public function afterCreate(array &$data, Model $record, $component): void
    {
        // ── Exemplo 1: Disparar evento Laravel ─────────────────────────
        event(new \App\Events\ProductCreated($record));
        
        // ── Exemplo 2: Criar registros relacionados ────────────────────
        // Anexar tags padrão
        if (method_exists($record, 'tags')) {
            $record->tags()->attach([1, 2, 3]); // IDs das tags
        }
        
        // Criar histórico
        $record->history()->create([
            'action' => 'created',
            'user_id' => auth()->id(),
            'changes' => json_encode($data),
        ]);
        
        // ── Exemplo 3: Atualizar cache ─────────────────────────────────
        Cache::put('latest_product', $record->id, now()->addHours(24));
        Cache::tags(['products'])->flush();
        
        // ── Exemplo 4: Disparar job assíncrono ─────────────────────────
        \App\Jobs\ProcessNewProduct::dispatch($record);
        
        // ── Exemplo 5: Enviar notificação ──────────────────────────────
        $admins = \App\Models\User::where('role', 'admin')->get();
        \Illuminate\Support\Facades\Notification::send(
            $admins,
            new \App\Notifications\NewProductCreated($record)
        );
        
        // ── Exemplo 6: Webhook externo ─────────────────────────────────
        \Illuminate\Support\Facades\Http::post('https://api.example.com/webhook', [
            'event' => 'product.created',
            'product_id' => $record->id,
            'name' => $record->name,
        ]);
        
        // ── Exemplo 7: Log estruturado ─────────────────────────────────
        Log::info('ProductHooks: Produto criado com sucesso', [
            'id' => $record->id,
            'name' => $record->name,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Hook executado ANTES de atualizar um registro existente.
     * 
     * Use para:
     * - Detectar mudanças específicas
     * - Atualizar campos relacionados
     * - Validação condicional
     * - Registrar transições de estado
     * 
     * Assinatura obrigatória:
     * @param array &$data Novos dados do formulário (mutável)
     * @param Model $record Registro original (antes do update)
     * @param mixed $component Componente Livewire
     */
    public function beforeUpdate(array &$data, Model $record, $component): void
    {
        // ── Exemplo 1: Detectar mudanças com isDirty ──────────────────
        if ($record->isDirty('price')) {
            $data['price_updated_at'] = now();
            $data['price_updated_by'] = auth()->id();
            
            Log::warning('ProductHooks: Preço alterado', [
                'id' => $record->id,
                'old_price' => $record->getOriginal('price'),
                'new_price' => $data['price'],
            ]);
        }
        
        // ── Exemplo 2: Transição de status ────────────────────────────
        $oldStatus = $record->status;
        $newStatus = $data['status'] ?? $oldStatus;
        
        if ($oldStatus === 'draft' && $newStatus === 'published') {
            // Publicando pela primeira vez
            $data['published_at'] = now();
            $data['published_by'] = auth()->id();
            
            Log::info('ProductHooks: Produto publicado', [
                'id' => $record->id,
                'name' => $record->name,
            ]);
        } elseif ($oldStatus === 'published' && $newStatus === 'archived') {
            // Arquivando produto publicado
            $data['archived_at'] = now();
            $data['archived_by'] = auth()->id();
        }
        
        // ── Exemplo 3: Atualizar slug se nome mudou ───────────────────
        if (isset($data['name']) && $data['name'] !== $record->name) {
            $data['slug'] = Str::slug($data['name']);
        }
        
        // ── Exemplo 4: Validação condicional ───────────────────────────
        if (isset($data['discount_price']) && isset($data['price'])) {
            if ($data['discount_price'] >= $data['price']) {
                throw new \InvalidArgumentException(
                    'Preço com desconto deve ser menor que preço normal'
                );
            }
        }
        
        // ── Exemplo 5: Proteger campos críticos ───────────────────────
        // Não permitir alterar código após criação
        if ($record->exists && isset($data['code'])) {
            unset($data['code']);
            Log::warning('ProductHooks: Tentativa de alterar código bloqueada', [
                'id' => $record->id,
                'user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Hook executado APÓS atualizar um registro com sucesso.
     * 
     * Use para:
     * - Invalidar cache
     * - Recarregar relações
     * - Sincronizar dados externos
     * - Notificar mudanças
     * 
     * Assinatura obrigatória:
     * @param array &$data Dados que foram atualizados
     * @param Model $record Registro atualizado (com mudanças aplicadas)
     * @param mixed $component Componente Livewire
     */
    public function afterUpdate(array &$data, Model $record, $component): void
    {
        // ── Exemplo 1: Invalidar cache específico ─────────────────────
        Cache::forget('product_' . $record->id);
        Cache::forget('product_slug_' . $record->slug);
        Cache::tags(['products', 'catalog'])->flush();
        
        // ── Exemplo 2: Recarregar relações ────────────────────────────
        $record->load('category', 'tags', 'images', 'manufacturer');
        
        // ── Exemplo 3: Detectar mudanças e notificar ──────────────────
        if ($record->wasChanged('status')) {
            // Status mudou
            event(new \App\Events\ProductStatusChanged($record, $record->getOriginal('status')));
            
            // Notificar gerentes
            $managers = \App\Models\User::where('role', 'manager')->get();
            \Illuminate\Support\Facades\Notification::send(
                $managers,
                new \App\Notifications\ProductStatusChanged($record)
            );
        }
        
        if ($record->wasChanged('price')) {
            // Preço mudou - atualizar índice de busca
            $record->searchable();
            
            // Disparar job de recalculo de promoções
            \App\Jobs\RecalculatePromotions::dispatch($record);
        }
        
        // ── Exemplo 4: Sincronizar com sistema externo ────────────────
        if ($record->wasChanged(['name', 'price', 'status'])) {
            \Illuminate\Support\Facades\Http::put(
                "https://api.example.com/products/{$record->id}",
                [
                    'name' => $record->name,
                    'price' => $record->price,
                    'status' => $record->status,
                ]
            );
        }
        
        // ── Exemplo 5: Criar histórico de alterações ──────────────────
        $record->history()->create([
            'action' => 'updated',
            'user_id' => auth()->id(),
            'changes' => json_encode($record->getChanges()),
            'original' => json_encode($record->getOriginal()),
        ]);
        
        // ── Exemplo 6: Atualizar timestamp customizado ────────────────
        if ($record->wasChanged('content')) {
            $record->forceFill(['content_updated_at' => now()])->save();
        }
        
        // ── Exemplo 7: Log estruturado ─────────────────────────────────
        Log::info('ProductHooks: Produto atualizado', [
            'id' => $record->id,
            'changed_fields' => array_keys($record->getChanges()),
            'user_id' => auth()->id(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // MÉTODOS AUXILIARES (OPCIONAIS)
    // ══════════════════════════════════════════════════════════════════

    /**
     * Exemplo de método auxiliar privado que pode ser usado pelos hooks.
     */
    private function generateUniqueCode(string $prefix = 'PROD'): string
    {
        do {
            $code = $prefix . '-' . strtoupper(Str::random(8));
        } while (\App\Models\Product::where('code', $code)->exists());

        return $code;
    }

    /**
     * Exemplo de validação customizada reutilizável.
     */
    private function validateProductData(array $data): void
    {
        if (isset($data['price']) && $data['price'] < 0) {
            throw new \InvalidArgumentException('Preço não pode ser negativo');
        }

        if (isset($data['stock']) && $data['stock'] < 0) {
            throw new \InvalidArgumentException('Estoque não pode ser negativo');
        }

        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Nome é obrigatório');
        }
    }

    /**
     * Exemplo de método que envia notificação customizada.
     */
    private function notifyPriceChange(Model $record, float $oldPrice, float $newPrice): void
    {
        $percentChange = (($newPrice - $oldPrice) / $oldPrice) * 100;

        if (abs($percentChange) > 10) {
            // Mudança significativa (>10%), notificar time
            Log::warning('ProductHooks: Mudança significativa de preço', [
                'id' => $record->id,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'percent_change' => round($percentChange, 2) . '%',
            ]);

            // Enviar email para gerentes
            \Illuminate\Support\Facades\Mail::to('managers@example.com')
                ->send(new \App\Mail\SignificantPriceChange($record, $oldPrice, $newPrice));
        }
    }
}
