<template>
    <div class="domain-whitelist-container">
        <el-page-header :content="'域名白名单验证'" @back="$router.push('/admin/dashboard')" />

        <!-- 搜索 License -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="License ID">
                    <el-input-number v-model="searchForm.license_id" :min="1" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData" :loading="loading">查询</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <template v-if="loaded">
            <!-- 统计卡片 -->
            <el-row :gutter="20" class="stat-cards">
                <el-col :span="4">
                    <el-card shadow="hover">
                        <div class="stat-label">域名总数</div>
                        <div class="stat-value">{{ stats.active }}/{{ stats.max_domains }}</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover">
                        <div class="stat-label">通配符</div>
                        <div class="stat-value">{{ stats.wildcard }}</div>
                    </el-card>
                </el-col>
                <el-col :span="4">
                    <el-card shadow="hover">
                        <div class="stat-label">待审批</div>
                        <div class="stat-value text-warning">{{ stats.pending }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="hover">
                        <div class="stat-label">近7天通过</div>
                        <div class="stat-value text-success">{{ stats.recent_passed }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="hover">
                        <div class="stat-label">近7天拦截</div>
                        <div class="stat-value text-danger">{{ stats.recent_blocked }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <el-tabs v-model="activeTab" class="main-tabs">
                <!-- 白名单管理 -->
                <el-tab-pane label="白名单管理" name="list">
                    <div class="section-header">
                        <h3>域名白名单 (License #{{ searchForm.license_id }})</h3>
                        <div>
                            <el-button size="small" @click="showBatchDialog = true">批量添加</el-button>
                            <el-button size="small" type="primary" @click="showAddDialog = true">添加域名</el-button>
                        </div>
                    </div>

                    <el-table :data="domains" v-loading="loading" stripe>
                        <el-table-column prop="domain" label="域名" min-width="250">
                            <template #default="{ row }">
                                <code>{{ row.domain }}</code>
                                <el-tag v-if="row.is_wildcard" size="small" type="warning" class="tag-spacer">通配符</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="scope" label="校验范围" width="130">
                            <template #default="{ row }">
                                <el-tag :type="scopeTagType(row.scope)" size="small">{{ scopeLabel(row.scope) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="status" label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusTagType(row.status)" size="small">
                                    {{ statusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="notes" label="备注" min-width="150" show-overflow-tooltip />
                        <el-table-column prop="created_at" label="添加时间" width="170" />
                        <el-table-column label="操作" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" type="danger" plain @click="handleRemove(row)">
                                    删除
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 验证日志 -->
                <el-tab-pane label="验证日志" name="logs">
                    <el-table :data="logs" v-loading="loadingLogs" stripe>
                        <el-table-column prop="domain" label="请求域名" min-width="220" />
                        <el-table-column prop="result" label="结果" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.result === 'passed' ? 'success' : 'danger'" size="small">
                                    {{ row.result === 'passed' ? '通过' : '拦截' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="ip_address" label="IP" width="140" />
                        <el-table-column prop="reason" label="原因" min-width="250" show-overflow-tooltip />
                        <el-table-column prop="created_at" label="时间" width="170" />
                    </el-table>
                </el-tab-pane>

                <!-- 域名验证检查 -->
                <el-tab-pane label="域名验证" name="verify">
                    <el-card>
                        <template #header>模拟域名验证</template>
                        <el-form :model="verifyForm" label-width="120px">
                            <el-form-item label="License ID">
                                <el-input-number v-model="verifyForm.license_id" :min="1" />
                            </el-form-item>
                            <el-form-item label="域名">
                                <el-input v-model="verifyForm.domain" placeholder="example.com" />
                            </el-form-item>
                            <el-form-item label="校验范围">
                                <el-select v-model="verifyForm.scope">
                                    <el-option label="验证" value="validation" />
                                    <el-option label="激活" value="activation" />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="handleVerify" :loading="verifying">验证</el-button>
                            </el-form-item>
                        </el-form>
                        <el-alert
                            v-if="verifyResult !== null"
                            :type="verifyResult.passed ? 'success' : 'error'"
                            show-icon
                        >
                            <template #title>
                                {{ verifyResult.passed ? '✅ 验证通过' : '❌ 验证失败' }}
                            </template>
                            {{ verifyResult.reason }}
                            <template v-if="verifyResult.matched">
                                <br>匹配域名: <code>{{ verifyResult.matched }}</code>
                            </template>
                        </el-alert>
                    </el-card>
                </el-tab-pane>
            </el-tabs>
        </template>

        <!-- 添加域名 Dialog -->
        <el-dialog v-model="showAddDialog" title="添加白名单域名" width="500px">
            <el-form :model="addForm" label-width="120px" :rules="addRules" ref="addFormRef">
                <el-form-item label="域名" prop="domain">
                    <el-input v-model="addForm.domain" placeholder="example.com 或 *.example.com" />
                    <div class="form-tip">支持通配符格式: *.example.com 匹配所有子域名</div>
                </el-form-item>
                <el-form-item label="校验范围" prop="scope">
                    <el-select v-model="addForm.scope">
                        <el-option label="激活+验证 (both)" value="both" />
                        <el-option label="仅激活" value="activation" />
                        <el-option label="仅验证" value="validation" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="addForm.notes" type="textarea" :rows="2" maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddDialog = false">取消</el-button>
                <el-button type="primary" @click="handleAdd" :loading="adding">添加</el-button>
            </template>
        </el-dialog>

        <!-- 批量添加 Dialog -->
        <el-dialog v-model="showBatchDialog" title="批量添加域名" width="550px">
            <el-form label-width="120px">
                <el-form-item label="域名列表">
                    <el-input
                        v-model="batchInput"
                        type="textarea"
                        :rows="6"
                        placeholder="每行一个域名，如:&#10;example.com&#10;api.example.com&#10;*.example.com"
                    />
                    <div class="form-tip">每行一个域名，支持通配符 (*.)</div>
                </el-form-item>
                <el-form-item label="校验范围">
                    <el-select v-model="batchScope">
                        <el-option label="激活+验证 (both)" value="both" />
                        <el-option label="仅激活" value="activation" />
                        <el-option label="仅验证" value="validation" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBatchDialog = false">取消</el-button>
                <el-button type="primary" @click="handleBatchAdd" :loading="batchAdding">批量添加</el-button>
            </template>
            <!-- 批量结果 -->
            <el-table v-if="batchResults.length > 0" :data="batchResults" stripe size="small" max-height="300">
                <el-table-column prop="domain" label="域名" />
                <el-table-column prop="success" label="结果" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                            {{ row.success ? '成功' : '失败' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <span v-if="row.status">{{ row.status === 'active' ? '已生效' : '待审批' }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="error" label="错误" min-width="200" show-overflow-tooltip />
            </el-table>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getDomainWhitelist, addDomain, batchAddDomains, removeDomain,
    verifyDomain, getDomainLogs, getDomainStats,
} from '@/api/domainWhitelist'

const searchForm = ref({ license_id: 1 })
const loading = ref(false)
const loaded = ref(false)
const domains = ref([])
const logs = ref([])
const loadingLogs = ref(false)
const stats = ref({ total: 0, active: 0, pending: 0, wildcard: 0, recent_passed: 0, recent_blocked: 0, max_domains: 20 })
const activeTab = ref('list')

// 添加
const showAddDialog = ref(false)
const addForm = ref({ domain: '', scope: 'both', notes: '' })
const addRules = {
    domain: [{ required: true, message: '请输入域名' }],
    scope: [{ required: true, message: '请选择校验范围' }],
}
const addFormRef = ref(null)
const adding = ref(false)

// 批量
const showBatchDialog = ref(false)
const batchInput = ref('')
const batchScope = ref('both')
const batchAdding = ref(false)
const batchResults = ref([])

// 验证
const verifyForm = ref({ license_id: 1, domain: '', scope: 'validation' })
const verifyResult = ref(null)
const verifying = ref(false)

watch(activeTab, (tab) => {
    if (tab === 'logs') fetchLogs()
})

function loadData() {
    loading.value = true
    loaded.value = false
    Promise.all([
        fetchDomains(),
        fetchStats(),
    ]).then(() => {
        loaded.value = true
    }).finally(() => {
        loading.value = false
    })
}

async function fetchDomains() {
    try {
        const res = await getDomainWhitelist(searchForm.value.license_id)
        domains.value = res.data?.domains || []
    } catch { domains.value = [] }
}

async function fetchStats() {
    try {
        const res = await getDomainStats(searchForm.value.license_id)
        if (res.data) stats.value = res.data
    } catch { /* ignore */ }
}

async function fetchLogs() {
    loadingLogs.value = true
    try {
        const res = await getDomainLogs(searchForm.value.license_id)
        logs.value = res.data?.logs || []
    } catch { logs.value = [] }
    loadingLogs.value = false
}

function scopeLabel(s) {
    return { activation: '仅激活', validation: '仅验证', both: '激活+验证' }[s] || s
}

function scopeTagType(s) {
    return { activation: 'info', validation: 'primary', both: 'success' }[s] || 'info'
}

function statusLabel(s) {
    return { active: '已生效', pending: '待审批', pending_remove: '待删除', rejected: '已拒绝', inactive: '已停用', removed: '已移除' }[s] || s
}

function statusTagType(s) {
    return { active: 'success', pending: 'warning', pending_remove: 'warning', rejected: 'danger', inactive: 'info', removed: 'info' }[s] || 'info'
}

async function handleAdd() {
    const valid = await addFormRef.value.validate().catch(() => false)
    if (!valid) return
    adding.value = true
    try {
        const res = await addDomain(searchForm.value.license_id, addForm.value)
        ElMessage.success(res.message || '域名已添加')
        showAddDialog.value = false
        addForm.value = { domain: '', scope: 'both', notes: '' }
        fetchDomains()
        fetchStats()
    } catch (e) {
        ElMessage.error(e.message || '添加失败')
    }
    adding.value = false
}

async function handleBatchAdd() {
    const lines = batchInput.value.split('\n').map(l => l.trim()).filter(Boolean)
    if (lines.length === 0) {
        ElMessage.warning('请至少输入一个域名')
        return
    }
    batchAdding.value = true
    try {
        const res = await batchAddDomains(searchForm.value.license_id, {
            domains: lines,
            scope: batchScope.value,
        })
        batchResults.value = res.data?.results || []
        ElMessage.success(`批量添加完成，${batchResults.value.filter(r => r.success).length} 成功`)
        fetchDomains()
        fetchStats()
    } catch (e) {
        ElMessage.error(e.message || '批量添加失败')
    }
    batchAdding.value = false
}

async function handleRemove(row) {
    try {
        await ElMessageBox.confirm(`确定删除域名 ${row.domain}？`, '确认')
        await removeDomain(searchForm.value.license_id, row.id)
        ElMessage.success('已删除')
        fetchDomains()
        fetchStats()
    } catch { /* ignore */ }
}

async function handleVerify() {
    verifying.value = true
    verifyResult.value = null
    try {
        const res = await verifyDomain(verifyForm.value)
        verifyResult.value = res.data || { passed: true }
    } catch (e) {
        verifyResult.value = { passed: false, reason: e.message || '验证失败' }
    }
    verifying.value = false
}
</script>

<style scoped>
.domain-whitelist-container {
    padding: 20px;
}

.search-card {
    margin-top: 20px;
    margin-bottom: 16px;
}

.stat-cards {
    margin-bottom: 16px;
}

.stat-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 6px;
    text-align: center;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    text-align: center;
}

.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.section-header h3 {
    margin: 0;
}

.tag-spacer {
    margin-left: 6px;
}

.form-tip {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

.main-tabs {
    margin-top: 8px;
}
</style>
