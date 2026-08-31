<template>
    <div class="sdk-page">
        <div class="page-header">
            <h2>{{ t('sdk_page.title') }}</h2>
            <p class="text-muted">{{ t('sdk_page.subtitle') }}</p>
        </div>

        <!-- SDK 版本概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4" v-for="(lang, key) in languages" :key="key">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="lang-card" :class="'lang-' + key">
                        <div class="lang-badge">{{ langBadge(key) }}</div>
                        <div class="lang-name">{{ lang.name }}</div>
                        <div class="lang-version">v{{ lang.version }}</div>
                        <el-tag :type="statusTag(lang.status)" size="small">{{ statusLabel(lang.status) }}</el-tag>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 功能矩阵 -->
        <el-card shadow="never" class="mb-4">
            <template #header><span>{{ t('sdk_page.matrix_title') }}</span></template>
            <el-table :data="localizedMatrix" stripe>
                <el-table-column prop="feature" :label="t('sdk_page.col_feature')" width="180" />
                <el-table-column v-for="lang in langKeys" :key="lang" :label="langLabel(lang)" width="120" align="center">
                    <template #default="{ row }">
                        <el-icon v-if="row[lang]" color="#67c23a"><CircleCheck /></el-icon>
                        <el-icon v-else color="#c0c4cc"><Close /></el-icon>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 示例代码 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ t('sdk_page.example_title') }}</span>
                    <div class="card-actions">
                        <el-select v-model="exampleLang" size="small" style="width:140px">
                            <el-option v-for="(lang, key) in languages" :key="key" :label="lang.name" :value="key" />
                        </el-select>
                        <el-select v-model="exampleAction" size="small" style="width:140px" class="ml-2">
                            <el-option v-for="opt in exampleActionOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                        <el-button size="small" class="ml-2" @click="copyCode">{{ copyButtonLabel }}</el-button>
                    </div>
                </div>
            </template>
            <div class="code-block">
                <pre><code>{{ exampleCode || t('sdk_page.code_placeholder') }}</code></pre>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { getSdkVersions, getSdkExample } from '@/api/sdk';

const { t } = useI18n();

const languages = ref({});
const matrix = ref([]);
const langKeys = ref([]);
const exampleLang = ref('python');
const exampleAction = ref('activate');
const exampleCode = ref('');
const copied = ref(false);

const exampleActionKeys = ['activate', 'validate', 'deactivate', 'offline_verify', 'check_feature'];

const exampleActionOptions = computed(() =>
    exampleActionKeys.map((value) => ({
        value,
        label: t(`sdk_page.actions.${value}`),
    }))
);

const localizedMatrix = computed(() =>
    matrix.value.map((row) => ({
        ...row,
        feature: featureLabel(row.feature),
    }))
);

const copyButtonLabel = computed(() =>
    copied.value ? t('sdk_page.messages.copied') : t('actions.copy')
);

const langBadge = (key) => ({ php: 'PHP', node: 'JS', python: 'PY', go: 'GO', java: 'JV' }[key] || key.toUpperCase().slice(0, 2));
const langLabel = (key) => t(`sdk_page.langs.${key}`) !== `sdk_page.langs.${key}` ? t(`sdk_page.langs.${key}`) : key;
const statusTag = (s) => ({ stable: 'success', beta: 'warning', deprecated: 'danger' }[s] || 'info');
const statusLabel = (s) => t(`sdk_page.status.${s}`) !== `sdk_page.status.${s}` ? t(`sdk_page.status.${s}`) : s;
const featureLabel = (feature) => t(`sdk_page.features.${feature}`) !== `sdk_page.features.${feature}` ? t(`sdk_page.features.${feature}`) : feature;

const loadData = async () => {
    try {
        const res = await getSdkVersions();
        if (res.data.success) {
            languages.value = res.data.data.languages || {};
            matrix.value = res.data.data.matrix || [];
            langKeys.value = Object.keys(languages.value);
        }
    } catch (e) { /* ignore */ }
};

const loadExample = async () => {
    try {
        const res = await getSdkExample(exampleLang.value, exampleAction.value);
        if (res.data.success) {
            exampleCode.value = res.data.data.code;
        }
    } catch (e) { /* ignore */ }
};

const copyCode = async () => {
    if (!exampleCode.value) return;
    try {
        await navigator.clipboard.writeText(exampleCode.value);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch { /* ignore */ }
};

watch([exampleLang, exampleAction], () => loadExample());

onMounted(() => {
    loadData();
});
</script>

<style scoped>
.page-header { margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0 0; }
.lang-card { text-align: center; cursor: default; }
.lang-badge { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; margin: 0 auto 8px; border-radius: 8px; background: #f0f2f5; color: #606266; font-size: 12px; font-weight: 700; letter-spacing: 0.02em; }
.lang-name { font-weight: 600; font-size: 14px; }
.lang-version { font-size: 12px; color: #909399; margin: 2px 0; }
.card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.card-actions { display: flex; align-items: center; }
.ml-2 { margin-left: 8px; }
.code-block { background: #1e1e1e; border-radius: 4px; padding: 16px; overflow-x: auto; }
.code-block pre { margin: 0; }
.code-block code { color: #d4d4d4; font-size: 13px; font-family: 'Consolas', 'Monaco', monospace; line-height: 1.6; white-space: pre; }
.mb-4 { margin-bottom: 16px; }
</style>
