<template>
    <div class="sdk-page">
        <div class="page-header">
            <h2>SDK 开发工具包</h2>
            <p class="text-muted">多语言 SDK 集成 — Python / Go / Java / PHP / Node.js</p>
        </div>

        <!-- SDK 版本概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4" v-for="(lang, key) in languages" :key="key">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="lang-card" :class="'lang-' + key">
                        <div class="lang-icon">{{ langIcon(key) }}</div>
                        <div class="lang-name">{{ lang.name }}</div>
                        <div class="lang-version">v{{ lang.version }}</div>
                        <el-tag :type="statusTag(lang.status)" size="small">{{ statusLabel(lang.status) }}</el-tag>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 功能矩阵 -->
        <el-card shadow="never" class="mb-4">
            <template #header><span>功能矩阵</span></template>
            <el-table :data="matrix" stripe>
                <el-table-column prop="feature" label="功能" width="180" />
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
                    <span>示例代码</span>
                    <div class="card-actions">
                        <el-select v-model="exampleLang" size="small" style="width:140px">
                            <el-option v-for="(lang, key) in languages" :key="key" :label="lang.name" :value="key" />
                        </el-select>
                        <el-select v-model="exampleAction" size="small" style="width:140px" class="ml-2">
                            <el-option label="激活 License" value="activate" />
                            <el-option label="验证 License" value="validate" />
                            <el-option label="解除激活" value="deactivate" />
                            <el-option label="离线验证" value="offline_verify" />
                            <el-option label="检查 Feature" value="check_feature" />
                        </el-select>
                        <el-button size="small" class="ml-2" @click="copyCode">{{ copied ? '已复制' : '复制' }}</el-button>
                    </div>
                </div>
            </template>
            <div class="code-block">
                <pre><code>{{ exampleCode || '选择语言和操作查看示例代码' }}</code></pre>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { getSdkVersions, getSdkExample } from '@/api/sdk';

const languages = ref({});
const matrix = ref([]);
const langKeys = ref([]);
const exampleLang = ref('python');
const exampleAction = ref('activate');
const exampleCode = ref('');
const copied = ref(false);

const langIcon = (key) => ({ php: '🐘', node: '🟢', python: '🐍', go: '🔷', java: '☕' }[key] || '📦');
const langLabel = (key) => ({ php: 'PHP', node: 'Node.js', python: 'Python', go: 'Go', java: 'Java' }[key] || key);
const statusTag = (s) => ({ stable: 'success', beta: 'warning', deprecated: 'danger' }[s] || 'info');
const statusLabel = (s) => ({ stable: '稳定', beta: 'Beta', deprecated: '已废弃' }[s] || s);

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
.lang-icon { font-size: 32px; margin-bottom: 8px; }
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
