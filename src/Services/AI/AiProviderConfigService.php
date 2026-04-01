<?php

declare(strict_types=1);

namespace Ptah\Services\AI;

use Illuminate\Support\Facades\Cache;
use Ptah\Models\AiModelConfig;

/**
 * Manages AI Model/Provider configuration records.
 *
 * Results are cached in the application cache to avoid hitting the database
 * on every request when the widget checks whether a provider is available.
 */
class AiProviderConfigService
{
    private const CACHE_KEY_DEFAULT   = 'ptah:ai:default_config';
    private const CACHE_KEY_AVAILABLE = 'ptah:ai:has_provider';
    private const CACHE_TTL           = 60; // seconds

    // ─────────────────────────────────────────
    // Queries
    // ─────────────────────────────────────────

    /** Returns the default active provider config, or null if none exists. */
    public function findDefault(): ?AiModelConfig
    {
        $id = Cache::remember(self::CACHE_KEY_DEFAULT, self::CACHE_TTL, function () {
            return AiModelConfig::active()->default()->value('id');
        });

        return $id ? AiModelConfig::find($id) : null;
    }

    /** Returns true when at least one active provider config exists. */
    public function hasActiveProvider(): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY_AVAILABLE, self::CACHE_TTL, function () {
            return AiModelConfig::active()->exists();
        });
    }

    // ─────────────────────────────────────────
    // Mutations
    // ─────────────────────────────────────────

    public function create(array $data): AiModelConfig
    {
        if (!empty($data['is_default'])) {
            $this->clearDefaultFlag();
        }

        $config = AiModelConfig::create($data);
        $this->clearCache();

        return $config;
    }

    public function update(AiModelConfig $config, array $data): AiModelConfig
    {
        if (!empty($data['is_default']) && !$config->is_default) {
            $this->clearDefaultFlag();
        }

        $config->update($data);
        $this->clearCache();

        return $config->fresh();
    }

    public function delete(AiModelConfig $config): void
    {
        $config->delete();
        $this->clearCache();
    }

    /**
     * Marks the given config as the default (clears the flag from all others first).
     */
    public function setDefault(int $id): void
    {
        $this->clearDefaultFlag();

        AiModelConfig::findOrFail($id)->update(['is_default' => true]);
        $this->clearCache();
    }

    // ─────────────────────────────────────────
    // Cache helpers
    // ─────────────────────────────────────────

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_DEFAULT);
        Cache::forget(self::CACHE_KEY_AVAILABLE);
    }

    private function clearDefaultFlag(): void
    {
        AiModelConfig::where('is_default', true)->update(['is_default' => false]);
    }
}
