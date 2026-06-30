<template>
    <div class="time-restriction-page">
        <div class="page-header">
            <h2><el-icon style="vertical-align:middle;margin-right:8px"><Clock /></el-icon>License 使用时段限制管理</h2>
            <p class="text-muted">管理所有 License 的使用时段限制配置，支持每周排期、特定期日、节假日的灵活设置</p>
            <div class="header-actions">
                <el-button @click="refreshAll" :loading="loading" :icon="Refresh">刷新</el-button>
            </div>
        </div>

        <!-- 概览统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-label">总配置数</div>
                    <div class="stat-value">{{ stats.total_configs }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-active">
                    <div class="stat-label">已启用</div>
                    <div class="stat-value">{{ stats.active_configs }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-warning">
                    <div class="stat-label">今日检查次数</div>
                    <div class="stat-value">{{ stats.today_checks }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-danger">
                    <div class="stat-label">今日拒绝次数</div>
                    <div class="stat-value">{{ stats.today_denials }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 标签页 -->
        <el-card shadow="hover">
            <el-tabs v-model="activeTab">
                <!-- 配置列表 -->
                <el-tab-pane label="时段限制配置" name="configs">
                    <div class="tab-toolbar">
                        <el-input v-model="search" placeholder="搜索 License ID/描述" clearable style="width:240px" @clear="loadConfigs" @keyup.enter="loadConfigs" />
                        <el-select v-model="filterActive" placeholder="状态" clearable style="width:120px;margin-left:8px" @change="loadConfigs">
                            <el-option label="全部" value="" />
                            <el-option label="已启用" value="1" />
                            <el-option label="已禁用" value="0" />
                        </el-select>
                    </div>
                    <el-table :data="configs" stripe v-loading="configsLoading">
                        <el-table-column label="ID" width="60" prop="id" />
                        <el-table-column label="关联对象" min-width="200">
                            <template #default="{ row }">
                                <div class="restrictable-info">
                                    <el-tag size="small" type="info">{{ row.restrictable_type_label }}</el-tag>
                                    <span class="ml-1">#{{ row.restrictable_id }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? '启用' : '禁用' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="时区" width="140" prop="timezone" />
                        <el-table-column label="每周时段" min-width="200">
                            <template #default="{ row }">
                                <div v-if="row.summary?.weekly_schedule?.length" class="schedule-summary">
                                    <el-tag v-for="s in row.summary.weekly_schedule" :key="s" size="small" class="mr-1">
                                        {{ s }}
                                    </el-tag>
                                </div>
                                <span v-else class="no-data">未配置</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="节假日" width="80" align="center">
                            <template #default="{ row }">
                                <el-tag v-if="row.summary?.holiday_count" type="warning" size="small">
                                    {{ row.summary.holiday_count }} 天
                                </el-tag>
                                <span v-else class="no-data">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="超时行为" width="100">
                            <template #default="{ row }">
                                <el-tag :type="actionTagType(row.out_of_hours_action)" size="small">
                                    {{ actionLabel(row.out_of_hours_action) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="备注" min-width="120" show-overflow-tooltip prop="description" />
                        <el-table-column label="操作" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button type="primary" link size="small" @click="viewLicense(row)">查看 License</el-button>
                                <el-popconfirm title="确定删除此配置？" @confirm="handleDelete(row)">
                                    <template #reference>
                                        <el-button type="danger" link size="small">删除</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 检查日志 -->
                <el-tab-pane label="检查日志" name="logs">
                    <el-table :data="logs" stripe v-loading="logsLoading" max-height="600">
                        <el-table-column label="时间" width="170">
                            <template #default="{ row }">{{ row.checked_at || row.created_at }}</template>
                        </el-table-column>
                        <el-table-column label="License ID" width="100" prop="license_id" />
                        <el-table-column label="结果" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.result === 'allowed' ? 'success' : row.result === 'denied' ? 'danger' : 'warning'" size="small">
                                    {{ row.result === 'allowed' ? '通过' : row.result === 'denied' ? '拒绝' : '宽限' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="原因" min-width="200" prop="reason" show-overflow-tooltip />
                        <el-table-column label="IP" width="140" prop="ip_address" />
                        <el-table-column label="时区" width="140" prop="timezone_used" />
                    </el-table>
                    <div v-if="logPagination.total > 0" class="pagination-footer">
                        <el-pagination
                            v-model:current-page="logPage"
                            :page-size="20"
                            :total="logPagination.total"
                            layout="total, prev, pager, next"
                            @current-change="loadLogs"
                            small
                        />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Clock, Refresh } from '@element-plus/icons-vue'
import api from '../../api/timeRestriction'

const loading = ref(false)
const activeTab = ref('configs')
const search = ref('')
const filterActive = ref('')

// ─── 统计 ───
const stats = reactive({
    total_configs: 0,
    active_configs: 0,
    today_checks: 0,
    today_denials: 0,
})

// ─── 配置列表 ───
const configs = ref([])
const configsLoading = ref(false)

async function loadConfigs() {
    configsLoading.value = true
    try {
        const res = await api.listAll({
            search: search.value || undefined,
            is_active: filterActive.value || undefined,
        })
        configs.value = res.data?.data || []
    } catch (e) {
        console.error(e)
    } finally {
        configsLoading.value = false
    }
}

async function loadStats() {
    try {
        const res = await api.getStats()
        Object.assign(stats, res.data || {})
    } catch (e) {
        console.error(e)
    }
}

// ─── 日志 ───
const logs = ref([])
const logsLoading = ref(false)
const logPage = ref(1)
const logPagination = reactive({ total: 0 })

async function loadLogs() {
    logsLoading.value = true
    try {
        const res = await api.getLogs({ page: logPage.value, per_page: 20 })
        const d = res.data?.data
        if (Array.isArray(d)) {
            logs.value = d
        } else if (d?.data) {
            logs.value = d.data
            logPagination.total = d.total || 0
        } else {
            logs.value = []
        }
    } catch (e) {
        console.error(e)
    } finally {
        logsLoading.value = false
    }
}

// ─── 操作 ───

function viewLicense(row) {
    // Navigate to License detail page in a new tab
    const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '')
    window.open(`${baseUrl}/licenses/${row.restrictable_id}`, '_blank')
}

async function handleDelete(row) {
    try {
        await api.deleteConfig(row.restrictable_id)
        ElMessage.success('配置已删除')
        loadConfigs()
        loadStats()
    } catch (e) {
        ElMessage.error('删除失败')
    }
}

function actionTagType(action) {
    return { deny: 'danger', grace: 'warning', warn: 'info' }[action] || 'info'
}

function actionLabel(action) {
    return { deny: '拒绝访问', grace: '宽限使用', warn: '仅警告' }[action] || action
}

function refreshAll() {
    loading.value = true
    Promise.all([loadStats(), loadConfigs(), loadLogs()])
        .finally(() => { loading.value = false })
}

onMounted(() => {
    refreshAll()
})
</script>

<style scoped>
.time-restriction-page {
    padding: 20px;
}
.page-header {
    margin-bottom: 20px;
}
.page-header h2 {
    margin: 0 0 4px;
    font-size: 22px;
}
.text-muted {
    color: #909399;
    font-size: 13px;
    margin: 0 0 12px;
}
.header-actions {
    display: flex;
    gap: 8px;
}
.mb-4 {
    margin-bottom: 16px;
}
.stat-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 4px;
}
.stat-value {
    font-size: 24px;
    font-weight: 700;
}
.stat-active .stat-value { color: #67c23a; }
.stat-warning .stat-value { color: #e6a23c; }
.stat-danger .stat-value { color: #f56c6c; }
.tab-toolbar {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
}
.restrictable-info {
    display: flex;
    align-items: center;
    gap: 4px;
}
.ml-1 { margin-left: 4px; }
.mr-1 { margin-right: 4px; }
.schedule-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.no-data {
    color: #c0c4cc;
}
.pagination-footer {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}
</style>
