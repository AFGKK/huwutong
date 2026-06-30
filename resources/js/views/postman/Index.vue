<template>
    <div class="postman-page">
        <div class="page-header">
            <div>
                <h2>Postman Collection</h2>
                <p class="text-muted">官方维护 Postman Collection · 所有 API · 预填示例参数 · 多环境变量 · 一键导入</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
            </div>
        </div>

        <!-- 统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">总 API 端点</div><div class="metric-value">{{ stats.total_endpoints }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">包含的端点</div><div class="metric-value">{{ stats.filtered_endpoints }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">环境配置</div><div class="metric-value">{{ stats.environments }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">示例请求</div><div class="metric-value">{{ stats.examples }}</div></el-card></el-col>
        </el-row>

        <el-row :gutter="16" class="mb-4">
            <!-- 下载卡片 -->
            <el-col :xs="24" :md="8">
                <el-card shadow="hover" class="action-card">
                    <el-icon :size="40" color="#409eff"><Download /></el-icon>
                    <h3>下载 Collection</h3>
                    <p class="text-muted">获取完整的 Postman Collection JSON，包含所有 API 端点定义。</p>
                    <el-button type="primary" @click="downloadCollection" :loading="dlLoading">📥 下载 Collection</el-button>
                </el-card>
            </el-col>

            <!-- Run in Postman -->
            <el-col :xs="24" :md="8">
                <el-card shadow="hover" class="action-card">
                    <el-icon :size="40" color="#ff6c37"><Connection /></el-icon>
                    <h3>Run in Postman</h3>
                    <p class="text-muted">一键导入到 Postman 桌面应用，立即开始测试 API。</p>
                    <el-button type="warning" @click="runInPostman" :loading="ripLoading">🚀 Run in Postman</el-button>
                </el-card>
            </el-col>

            <!-- 环境配置 -->
            <el-col :xs="24" :md="8">
                <el-card shadow="hover" class="action-card">
                    <el-icon :size="40" color="#67c23a"><Setting /></el-icon>
                    <h3>环境配置</h3>
                    <p class="text-muted">下载预配置环境变量（开发/预发布/生产）。</p>
                    <div class="env-buttons">
                        <el-button v-for="env in environments" :key="env.key" size="small" @click="downloadEnv(env.key)" :loading="envLoading === env.key">
                            {{ env.name }}
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 使用说明 -->
        <el-card shadow="hover">
            <template #header><span><el-icon><Reading /></el-icon> 使用说明</span></template>
            <el-steps :active="3" align-center simple>
                <el-step title="下载 Collection" description="点击上方按钮下载 JSON 文件" />
                <el-step title="导入 Postman" description="File → Import → 选择下载的文件" />
                <el-step title="选择环境" description="下载并选择对应的环境配置" />
                <el-step title="开始测试" description="填入你的 API Key 开始调用" />
            </el-steps>
            <el-alert title="提示：请先在 API 密钥管理页面生成你的 API Key，然后在 Postman 环境变量中填入 {{api_key}}" type="info" show-icon :closable="false" style="margin-top:16px" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Download, Connection, Setting, Reading } from '@element-plus/icons-vue';
import postmanApi from '@/api/postman';

const loading = ref(false);
const dlLoading = ref(false);
const ripLoading = ref(false);
const envLoading = ref('');
const stats = reactive({ total_endpoints: 0, filtered_endpoints: 0, environments: 0, examples: 0, include_groups: [] });
const environments = ref([]);

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try {
        const [s, e] = await Promise.all([postmanApi.stats(), postmanApi.environments()]);
        Object.assign(stats, s.data?.data || {});
        environments.value = e.data?.data || [];
    } finally { loading.value = false; }
}

async function downloadCollection() {
    dlLoading.value = true;
    try {
        const res = await postmanApi.downloadCollection();
        const json = JSON.stringify(res.data, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = 'huwutong-api.postman_collection.json'; a.click();
        URL.revokeObjectURL(url);
        ElMessage.success('Collection 已下载');
    } catch { ElMessage.error('下载失败'); }
    finally { dlLoading.value = false; }
}

async function runInPostman() {
    ripLoading.value = true;
    try {
        const res = await postmanApi.runInPostman();
        const data = res.data?.data;
        if (!data) { ElMessage.error('获取数据失败'); return; }
        // 创建并下载一个包含 collection + environment 的导入包
        const json = JSON.stringify(data, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = 'huwutong-postman-import.json'; a.click();
        URL.revokeObjectURL(url);
        ElMessage.success('导入包已下载，请在 Postman 中 Import');
    } catch { ElMessage.error('获取 Run in Postman 数据失败'); }
    finally { ripLoading.value = false; }
}

async function downloadEnv(key) {
    envLoading.value = key;
    try {
        const res = await postmanApi.downloadEnvironment(key);
        const json = JSON.stringify(res.data, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = `huwutong-${key}.postman_environment.json`; a.click();
        URL.revokeObjectURL(url);
        ElMessage.success(`环境「${key}」已下载`);
    } catch { ElMessage.error('下载失败'); }
    finally { envLoading.value = ''; }
}
</script>

<style scoped>
.postman-page { padding: 16px; max-width: 1000px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.text-muted { color: #909399; }

.metric-card { padding: 12px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 22px; font-weight: 700; }

.action-card { text-align: center; padding: 24px 16px; height: 100%; }
.action-card h3 { margin: 12px 0 8px; font-size: 18px; }
.action-card p { margin: 0 0 16px; font-size: 13px; }

.env-buttons { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
</style>
