import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    const cdnDomain = env.CLOUD_STORAGE_CDN_DOMAIN || env.VITE_CDN_DOMAIN || '';

    return {
        base: cdnDomain ? `https://${cdnDomain}/assets/` : '/build/',
        server: {
            host: '127.0.0.1',
        },
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
            // Element Plus 按需导入
            AutoImport({
                resolvers: [ElementPlusResolver()],
                eslintrc: { enabled: false },
            }),
            Components({
                resolvers: [ElementPlusResolver()],
                // 不自动导入 src/components 下的组件（防止冲突）
                dirs: [],
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
            chunkSizeWarningLimit: 400,
            target: 'es2020',
            cssMinify: 'esbuild',
            minify: 'esbuild',
            rollupOptions: {
                output: {
                    manualChunks(id) {
                        if (id.includes('node_modules')) {
                            // Vue 核心生态
                            if (/[\\/]node_modules[\\/](vue|@vue)[\\/]/.test(id)) {
                                return 'vendor-vue';
                            }
                            if (/[\\/]node_modules[\\/]vue-router[\\/]/.test(id)) {
                                return 'vendor-vue';
                            }
                            if (/[\\/]node_modules[\\/]pinia[\\/]/.test(id)) {
                                return 'vendor-vue';
                            }
                            if (/[\\/]node_modules[\\/]@vueuse[\\/]/.test(id)) {
                                return 'vendor-vueuse';
                            }

                            // Element Plus — 拆分为组件和图标两个独立 chunk
                            // 注意: 语言包本地化文件单独拆分
                            if (/[\\/]node_modules[\\/]element-plus[\\/].*locale[\\/]/.test(id)) {
                                return 'vendor-element-locale';
                            }
                            if (/[\\/]node_modules[\\/]element-plus[\\/]/.test(id)) {
                                // element-plus/es/components/xxx 为组件代码
                                if (/[\\/]element-plus[\\/]es[\\/]/.test(id)) {
                                    return 'vendor-element-comp';
                                }
                                return 'vendor-element';
                            }
                            // Element Plus 图标
                            if (/[\\/]node_modules[\\/]@element-plus[\\/]icons-vue[\\/]/.test(id)) {
                                return 'vendor-element-icons';
                            }
                            // 其他 @element-plus/* 子包
                            if (/[\\/]node_modules[\\/]@element-plus[\\/]/.test(id)) {
                                return 'vendor-element';
                            }

                            // ECharts
                            if (/[\\/]node_modules[\\/](echarts|zrender)[\\/]/.test(id)) {
                                return 'vendor-echarts';
                            }

                            // 编辑器 (Tiptap / ProseMirror)
                            if (/[\\/]node_modules[\\/](tiptap|prosemirror|@tiptap)[\\/]/.test(id)) {
                                return 'vendor-editor';
                            }

                            // xlsx / excel
                            if (/[\\/]node_modules[\\/](xlsx|exceljs)[\\/]/.test(id)) {
                                return 'vendor-xlsx';
                            }

                            // 国际化 / 日期
                            if (/[\\/]node_modules[\\/](dayjs|date-fns)[\\/]/.test(id)) {
                                return 'vendor-i18n';
                            }

                            // 图表 / 可视化 (除 echarts 外)
                            if (/[\\/]node_modules[\\/](d3|three|chart\.js)[\\/]/.test(id)) {
                                return 'vendor-viz';
                            }

                            // Markdown / 代码高亮
                            if (/[\\/]node_modules[\\/](marked|highlight\.js|prismjs)[\\/]/.test(id)) {
                                return 'vendor-markdown';
                            }

                            // === 大包独立拆分（防止 vendor-p-r 等过大） ===

                            // lowlight 与 highlight.js 关联，但为避免与 vendor-markdown 合并太大，在此单独捕获
                            if (/[\\/]node_modules[\\/]lowlight[\\/]/.test(id)) {
                                return 'vendor-markdown';
                            }

                            // qrcode
                            if (/[\\/]node_modules[\\/]qrcode[\\/]/.test(id)) {
                                return 'vendor-qrcode';
                            }

                            // laravel-echo / pusher-js
                            if (/[\\/]node_modules[\\/](laravel-echo|pusher-js)[\\/]/.test(id)) {
                                return 'vendor-echo';
                            }

                            // axios
                            if (/[\\/]node_modules[\\/]axios[\\/]/.test(id)) {
                                return 'vendor-axios';
                            }

                            // vuedraggable / sortablejs
                            if (/[\\/]node_modules[\\/](vuedraggable|sortablejs)[\\/]/.test(id)) {
                                return 'vendor-dnd';
                            }

                            // vue-echarts / resize-detector
                            if (/[\\/]node_modules[\\/](vue-echarts|resize-detector)[\\/]/.test(id)) {
                                return 'vendor-vue-echarts';
                            }

                            // sass-embedded (dev, 但可能被依赖追踪)
                            if (/[\\/]node_modules[\\/]sass-embedded[\\/]/.test(id)) {
                                return 'vendor-sass';
                            }

                            // @vue/devtools-api → 与 Vue 一起
                            if (/[\\/]node_modules[\\/]@vue[\\/]devtools-api[\\/]/.test(id)) {
                                return 'vendor-vue';
                            }

                            // picocolors / postcss (devDep, 可能被某些运行时引用)
                            if (/[\\/]node_modules[\\/](picocolors|source-map-js|postcss)[\\/]/.test(id)) {
                                return 'vendor-postcss';
                            }

                            // preact / react → vendor-p-r 中可能包含
                            if (/[\\/]node_modules[\\/](preact|react)[\\/]/.test(id)) {
                                return 'vendor-react';
                            }

                            // 剩余 vendor 按首字母分组，避免单文件过大
                            const match = id.match(/[\\/]node_modules[\\/]@?([^\\/]+)/);
                            if (match) {
                                const pkgName = match[1].toLowerCase();
                                // 按字母范围分组（每个范围预期 < 150KB）
                                if (/^[a-c]/.test(pkgName)) return 'vendor-a-c';
                                if (/^[d-f]/.test(pkgName)) return 'vendor-d-f';
                                if (/^[g-i]/.test(pkgName)) return 'vendor-g-i';
                                if (/^[j-l]/.test(pkgName)) return 'vendor-j-l';
                                if (/^[m-o]/.test(pkgName)) return 'vendor-m-o';
                                if (/^[p-r]/.test(pkgName)) return 'vendor-p-r';
                                if (/^[s-u]/.test(pkgName)) return 'vendor-s-u';
                                if (/^[v-z]/.test(pkgName)) return 'vendor-v-z';
                            }
                            return 'vendor-other';
                        }
                    },
                },
            },
        },
    };
});
