import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            '~': path.resolve(__dirname, 'resources'),
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
            },
        },
    },
    build: {
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // Vue 核心框架
                    if (id.includes('node_modules/vue') ||
                        id.includes('node_modules/@vue') ||
                        id.includes('node_modules/pinia') ||
                        id.includes('node_modules/vue-router')) {
                        return 'vendor-vue';
                    }
                    // Element Plus + 图标（源代码通过 import 从 element-plus 全量引入）
                    if (id.includes('node_modules/element-plus') ||
                        id.includes('node_modules/@element-plus')) {
                        return 'vendor-element';
                    }
                    // 其他第三方依赖
                    if (id.includes('node_modules')) {
                        return 'vendor-other';
                    }
                },
            },
        },
    },
});
