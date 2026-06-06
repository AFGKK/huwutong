import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/tests/setup.js'],
        include: ['resources/js/tests/**/*.test.{ts,js}'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'lcov', 'html'],
            include: ['resources/js/**/*.{vue,js}'],
            exclude: [
                'resources/js/tests/**',
                'resources/js/api/**',
                'resources/js/router/**',
            ],
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
});
