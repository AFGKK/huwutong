<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>电商运营</el-breadcrumb-item>
            <el-breadcrumb-item>商品搜索管理</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-gray-800">{{ stats.total_searches ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">总搜索次数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-primary">{{ stats.today_searches ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">今日搜索</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-warning">{{ stats.unique_terms ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">独立搜索词</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-success">{{ stats.zero_result_rate ?? '0%' }}</div>
                    <div class="text-sm text-gray-500 mt-1">无结果率</div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20">
            <!-- 热门搜索词 -->
            <el-col :span="12">
                <el-card class="mb-4">
                    <template #header><span class="font-semibold">🔥 热门搜索词</span></template>
                    <div v-if="hotTerms.length">
                        <div v-for="(term, i) in hotTerms" :key="i"
                            class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                                    :class="rankClass(i)">{{ i + 1 }}</span>
                                <span>{{ term.term }}</span>
                            </div>
                            <span class="text-sm text-gray-500">{{ term.count }} 次</span>
                        </div>
                    </div>
                    <el-empty v-else description="暂无搜索数据" :image-size="60" />
                </el-card>

                <!-- 无结果搜索词 -->
                <el-card>
                    <template #header><span class="font-semibold">⚠️ 无结果搜索词</span></template>
                    <div v-if="zeroResultTerms.length">
                        <div v-for="(term, i) in zeroResultTerms" :key="i"
                            class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                            <span>{{ term.term }}</span>
                            <el-tag type="danger" size="small">{{ term.count }} 次</el-tag>
                        </div>
                    </div>
                    <el-empty v-else description="暂无无结果搜索" :image-size="60" />
                </el-card>
            </el-col>

            <!-- 搜索配置 -->
            <el-col :span="12">
                <el-card class="mb-4">
                    <template #header><span class="font-semibold">⚙️ 搜索配置</span></template>
                    <el-form label-position="left" label-width="140px">
                        <el-form-item label="搜索引擎">
                            <el-tag :type="config.engine === 'meilisearch' ? 'success' : 'info'">
                                {{ config.engine === 'meilisearch' ? 'Meilisearch' : '数据库 (MySQL)' }}
                            </el-tag>
                        </el-form-item>
                        <el-form-item label="每页条数">
                            <span>{{ config.search?.per_page }}</span>
                        </el-form-item>
                        <el-form-item label="排序选项">
                            <div class="flex flex-wrap gap-1">
                                <el-tag v-for="(opt, key) in config.sort_options" :key="key" size="small">
                                    {{ opt.label }}
                                </el-tag>
                            </div>
                        </el-form-item>
                        <el-form-item label="筛选器">
                            <div class="flex flex-wrap gap-1">
                                <el-tag v-for="(f, key) in config.filters" :key="key"
                                    :type="f.enabled ? 'success' : 'danger'" size="small">
                                    {{ f.label }}
                                </el-tag>
                            </div>
                        </el-form-item>
                        <el-form-item label="热门搜索词">
                            <span>前 {{ config.logging?.hot_terms_limit ?? 20 }} 个，缓存 {{ (config.logging?.hot_terms_cache_ttl ?? 3600) / 60 }} 分钟</span>
                        </el-form-item>
                    </el-form>
                </el-card>

                <!-- 搜索日志 -->
                <el-card>
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">📋 最近搜索日志</span>
                            <el-button size="small" @click="fetchLogs">刷新</el-button>
                        </div>
                    </template>
                    <el-table :data="logs" v-loading="logLoading" stripe max-height="300" size="small">
                        <el-table-column prop="term" label="搜索词" min-width="120" />
                        <el-table-column prop="result_count" label="结果数" width="70" align="center">
                            <template #default="{ row }">
                                <el-tag v-if="row.result_count === 0" type="danger" size="small">0</el-tag>
                                <span v-else>{{ row.result_count }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="时间" width="150" />
                    </el-table>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import client from '../../api/client';

const stats = ref({});
const hotTerms = ref([]);
const zeroResultTerms = ref([]);
const config = ref({});
const logs = ref([]);
const logLoading = ref(false);

function rankClass(i) {
    if (i === 0) return 'bg-red-500 text-white';
    if (i === 1) return 'bg-orange-500 text-white';
    if (i === 2) return 'bg-yellow-500 text-white';
    return 'bg-gray-100 text-gray-600';
}

async function fetchStats() {
    try {
        const res = await client.get('/admin/product-search/stats');
        stats.value = res.data;
    } catch { /* ignore */ }
}

async function fetchHotTerms() {
    try {
        const res = await client.get('/admin/product-search/hot-terms');
        hotTerms.value = res.data.data || [];
    } catch { hotTerms.value = []; }
}

async function fetchZeroResultTerms() {
    try {
        const res = await client.get('/admin/product-search/zero-result-terms');
        zeroResultTerms.value = res.data.data || [];
    } catch { zeroResultTerms.value = []; }
}

async function fetchConfig() {
    try {
        const res = await client.get('/admin/product-search/config');
        config.value = res.data;
    } catch { config.value = {}; }
}

async function fetchLogs() {
    logLoading.value = true;
    try {
        const res = await client.get('/admin/product-search/logs', { params: { per_page: 20 } });
        logs.value = res.data.data || [];
    } catch { logs.value = []; }
    finally { logLoading.value = false; }
}

onMounted(() => {
    fetchStats();
    fetchHotTerms();
    fetchZeroResultTerms();
    fetchConfig();
    fetchLogs();
});
</script>

<style scoped>
.text-primary { color: #409eff; }
.text-warning { color: #e6a23c; }
.text-success { color: #67c23a; }
</style>
