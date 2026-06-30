import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    // M2-133: 当设置了 CDN 域名时，资源引用使用绝对 CDN URL
    const cdnDomain = env.CLOUD_STORAGE_CDN_DOMAIN || env.VITE_CDN_DOMAIN || '';

    return {
        base: cdnDomain ? `https://${cdnDomain}/assets/` : '/build/',
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/css/public.css',
                    'resources/js/app.js',
                    'resources/js/admin.js',
                    'resources/js/widget-sdk/hwt-widget.js',
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
                        if (id.includes('node_modules')) {
                            // 拆分大 vendor 包
                            if (id.includes('element-plus') || id.includes('element-plus')) {
                                return 'vendor-element';
                            }
                            if (id.includes('echarts') || id.includes('zrender')) {
                                return 'vendor-echarts';
                            }
                            if (id.includes('vue') && id.includes('router')) {
                                return 'vendor-vue';
                            }
                            if (id.includes('@vueuse')) {
                                return 'vendor-vueuse';
                            }
                            return 'vendor';
                        }
                    },
                },
            },
        },
    };
});
