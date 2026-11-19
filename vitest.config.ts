import react from '@vitejs/plugin-react';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react(), vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/__tests__/**/*.test.{ts,tsx}'],
        // 43 files in parallel on a busy machine (CI, a dev box): a screen test
        // that takes 1 s alone was crossing the 5 s default now and then.
        testTimeout: 20000,
    },
});
