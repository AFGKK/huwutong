<template>
    <div class="security-page">
        <div class="page-header">
            <div>
                <h2>内容安全审核</h2>
                <p class="text-muted">自动扫描应用描述和用户评价中的违规内容</p>
            </div>
            <div class="header-actions">
                <el-button @click="scanAllApps" :loading="scanningApps"><el-icon><Search /></el-icon> 扫描应用</el-button>
                <el-button @click="scanAllReviews" :loading="scanningReviews"><el-icon><Search /></el-icon> 扫描评价</el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6"><el-card shadow="never"><div class="stat-value">{{ stats.total_apps || 0 }}</div><div class="stat-label">已检查应用</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-value danger">{{ stats.flagged_apps || 0 }}</div><div class="stat-label">违规应用</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-value success">{{ stats.clean_apps || 0 }}</div><div class="stat-label">合规应用</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-value warning">{{ stats.pending_reviews || 0 }}</div><div class="stat-label">待审评价</div></el-card></el-col>
        </el-row>

        <!-- 扫描结果 Tabs -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <el-tab-pane label="应用扫描结果" name="apps">
                    <div class="toolbar">
                        <span class="text-muted" v-if="appResults.length">{{ appResults.length }} 个应用含违规内容</span>
                        <span class="text-muted" v-else>暂无违规应用，点击「扫描应用」检查</span>
                    </div>
                    <el-table :data="appResults" v-loading="scanningApps" stripe>
                        <el-table-column label="应用" min-width="200">
                            <template #default="{ row }">
                                <div class="app-cell">
                                    <span class="app-name">{{ row.app_name }}</span>
                                    <span class="text-muted small">{{ row.slug }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="违规数" width="80" prop="total" align="center">
                            <template #default="{ row }"><el-tag :type="row.total > 2 ? 'danger' : 'warning'" size="small">{{ row.total }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="严重程度" width="100">
                            <template #default="{ row }">
                                <el-tag :type="severityTag(row.max_severity)" size="small">{{ severityLabel(row.max_severity) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="详情" min-width="300">
                            <template #default="{ row }">
                                <div v-for="v in (row.violations || [])" :key="v.field" class="violation-item">
                                    <el-tag size="small" type="danger" style="margin-right:4px">{{ v.field_label }}</el-tag>
                                    <span class="text-muted small">{{ (v.words || []).slice(0,3).join(', ') }}{{ v.words?.length > 3 ? '...' : '' }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="$router.push('/app-marketplace/' + row.app_id)">查看应用</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!appResults.length && !scanningApps" description="暂无违规内容" :image-size="50" />
                </el-tab-pane>

                <el-tab-pane label="评价扫描结果" name="reviews">
                    <div class="toolbar">
                        <span class="text-muted" v-if="reviewResults.length">{{ reviewResults.length }} 条评价含违规内容</span>
                        <span class="text-muted" v-else>暂无违规评价，点击「扫描评价」检查</span>
                    </div>
                    <el-table :data="reviewResults" v-loading="scanningReviews" stripe>
                        <el-table-column label="应用" min-width="160" prop="app_name" />
                        <el-table-column label="用户" width="120" prop="user_name" />
                        <el-table-column label="评分" width="80">
                            <template #default="{ row }"><el-rate :model-value="row.rating" disabled size="small" /></template>
                        </el-table-column>
                        <el-table-column label="违规数" width="80" prop="total" align="center">
                            <template #default="{ row }"><el-tag :type="row.total > 2 ? 'danger' : 'warning'" size="small">{{ row.total }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="严重程度" width="100">
                            <template #default="{ row }">
                                <el-tag :type="severityTag(row.max_severity)" size="small">{{ severityLabel(row.max_severity) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="详情" min-width="250">
                            <template #default="{ row }">
                                <div v-for="v in (row.violations || [])" :key="v.field" class="violation-item">
                                    <span class="text-muted small">{{ (v.words || []).slice(0,3).join(', ') }}</span>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!reviewResults.length && !scanningReviews" description="暂无违规内容" :image-size="50" />
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import api from '@/api/marketplaceSecurity';

const activeTab = ref('apps');
const stats = ref({});
const appResults = ref([]);
const reviewResults = ref([]);
const scanningApps = ref(false);
const scanningReviews = ref(false);

async function loadStats() {
    try { const { data: r } = await api.stats(); if (r.success) stats.value = r.data; } catch {}
}

async function scanAllApps() {
    scanningApps.value = true;
    try {
        const { data: r } = await api.scanAllApps();
        if (r.success) {
            appResults.value = r.data?.results || [];
            ElMessage.success(`扫描完成，发现 ${r.data?.total_flagged || 0} 个违规应用`);
            loadStats();
        }
    } catch { ElMessage.error('扫描失败'); }
    finally { scanningApps.value = false; }
}

async function scanAllReviews() {
    scanningReviews.value = true;
    try {
        const { data: r } = await api.scanAllReviews();
        if (r.success) {
            reviewResults.value = r.data?.results || [];
            ElMessage.success(`扫描完成，发现 ${r.data?.total_flagged || 0} 条违规评价`);
        }
    } catch { ElMessage.error('扫描失败'); }
    finally { scanningReviews.value = false; }
}

function severityTag(s) { return { 1: 'info', 2: 'warning', 3: 'danger' }[s] || 'info'; }
function severityLabel(s) { return { 1: '低', 2: '中', 3: '高' }[s] || '未知'; }

onMounted(loadStats);
</script>

<style scoped>
.security-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.text-muted { color: var(--el-text-color-secondary); font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.toolbar { display: flex; gap: 8px; margin-bottom: 16px; }
.stat-value { font-size: 24px; font-weight: 600; color: #303133; }
.stat-value.danger { color: #f56c6c; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.app-cell { display: flex; flex-direction: column; }
.app-name { font-weight: 500; }
.small { font-size: 12px; }
.violation-item { margin: 2px 0; }
</style>
