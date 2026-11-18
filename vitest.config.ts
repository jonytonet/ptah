import react from '@vitejs/plugin-react';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react(), vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/__tests__/**/*.test.{ts,tsx}'],
    },
});
