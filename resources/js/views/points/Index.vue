<template>
    <div class="points-management">
        <!-- 页头 -->
        <div class="page-header">
            <div>
                <h2>🪙 积分管理</h2>
                <p class="text-muted">管理全平台用户积分余额、发放积分、查看交易流水</p>
            </div>
            <div class="page-header-actions">
                <el-button type="warning" @click="showGrantDialog = true">
                    <el-icon><Plus /></el-icon> 发放积分
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value">{{ stats.total_users || 0 }}</div>
                <div class="stat-label">持有积分用户</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#e6a23c">🪙 {{ formatNum(stats.total_balance) }}</div>
                <div class="stat-label">流通总积分</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#67c23a">+{{ formatNum(stats.total_earned) }}</div>
                <div class="stat-label">累计发放</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#f56c6c">-{{ formatNum(stats.total_spent) }}</div>
                <div class="stat-label">累计消费</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#409eff">📈 {{ formatNum(stats.today_earned) }}</div>
                <div class="stat-label">今日发放</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#e6a23c">📉 {{ formatNum(stats.today_spent) }}</div>
                <div class="stat-label">今日消费</div>
            </div>
        </div>

        <!-- Tabs: 用户列表 / 交易流水 -->
        <el-tabs v-model="activeTab" style="margin-top:16px">
            <!-- 用户列表 -->
            <el-tab-pane label="👥 用户积分" name="users">
                <div style="display:flex;gap:8px;margin-bottom:12px">
                    <el-input v-model="searchKeyword" placeholder="搜索用户名称/邮箱..." size="small" clearable style="width:300px" @clear="loadUsers" @keyup.enter="loadUsers">
                        <template #prefix><el-icon><Search /></el-icon></template>
                    </el-input>
                    <el-button size="small" @click="loadUsers">搜索</el-button>
                </div>
                <el-table :data="users" v-loading="loadingUsers" stripe style="width:100%">
                    <el-table-column label="用户" min-width="180">
                        <template #default="{ row }">
                            <div style="display:flex;align-items:center;gap:8px">
                                <el-avatar :size="28" :src="row.user?.avatar">{{ row.user?.name?.charAt(0) || '?' }}</el-avatar>
                                <div>
                                    <div style="font-weight:500;font-size:13px">{{ row.user?.name || '未知' }}</div>
                                    <div style="font-size:11px;color:#909399">{{ row.user?.email || '' }}</div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="balance" label="🪙 余额" width="120" align="center">
                        <template #default="{ row }"><strong>{{ formatNum(row.balance) }}</strong></template>
                    </el-table-column>
                    <el-table-column prop="total_earned" label="累计获得" width="120" align="center">
                        <template #default="{ row }"><span style="color:#67c23a">+{{ formatNum(row.total_earned) }}</span></template>
                    </el-table-column>
                    <el-table-column prop="total_spent" label="累计消费" width="120" align="center">
                        <template #default="{ row }"><span style="color:#f56c6c">-{{ formatNum(row.total_spent) }}</span></template>
                    </el-table-column>
                    <el-table-column label="操作" width="160" align="center">
                        <template #default="{ row }">
                            <el-button size="small" text type="primary" @click="viewUserTxns(row)">流水</el-button>
                            <el-button size="small" text type="warning" @click="openGrantForUser(row)">发积分</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div style="display:flex;justify-content:center;margin-top:12px">
                    <el-pagination v-model:current-page="userPage" :page-size="20" :total="userTotal" layout="prev,pager,next" small @current-change="loadUsers" />
                </div>
            </el-tab-pane>

            <!-- 交易流水 -->
            <el-tab-pane label="📋 交易流水" name="transactions">
                <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center">
                    <el-select v-model="txFilter.type" placeholder="类型" size="small" style="width:100px" clearable @change="loadTxns">
                        <el-option label="全部" value="" />
                        <el-option label="获得" value="earn" />
                        <el-option label="消费" value="spend" />
                        <el-option label="退款" value="refund" />
                        <el-option label="奖励" value="bonus" />
                    </el-select>
                    <el-input v-model="txFilter.user_id" placeholder="用户ID" size="small" style="width:100px" clearable @clear="loadTxns" @keyup.enter="loadTxns" />
                    <el-date-picker v-model="txFilter.from" type="date" placeholder="开始日期" size="small" style="width:130px" value-format="YYYY-MM-DD" @change="loadTxns" />
                    <el-date-picker v-model="txFilter.to" type="date" placeholder="结束日期" size="small" style="width:130px" value-format="YYYY-MM-DD" @change="loadTxns" />
                    <el-button size="small" @click="loadTxns">查询</el-button>
                </div>
                <el-table :data="transactions" v-loading="loadingTxns" stripe style="width:100%">
                    <el-table-column label="用户" min-width="140">
                        <template #default="{ row }">
                            <span style="font-size:13px">{{ row.user?.name || 'ID:' + row.user_id }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="type" label="类型" width="80" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.type === 'earn' ? 'success' : row.type === 'spend' ? 'danger' : 'warning'" size="small">
                                {{ row.type === 'earn' ? '获得' : row.type === 'spend' ? '消费' : row.type }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="amount" label="金额" width="100" align="center">
                        <template #default="{ row }">
                            <span :style="{ color: row.type === 'earn' ? '#67c23a' : '#f56c6c', fontWeight:600 }">
                                {{ row.type === 'earn' ? '+' : '-' }}{{ formatNum(row.amount) }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="description" label="说明" min-width="200" />
                    <el-table-column prop="balance_before" label="变动前" width="80" align="center">
                        <template #default="{ row }">{{ formatNum(row.balance_before) }}</template>
                    </el-table-column>
                    <el-table-column prop="balance_after" label="变动后" width="80" align="center">
                        <template #default="{ row }">{{ formatNum(row.balance_after) }}</template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="时间" width="150" align="center">
                        <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                    </el-table-column>
                </el-table>
                <div style="display:flex;justify-content:center;margin-top:12px">
                    <el-pagination v-model:current-page="txPage" :page-size="30" :total="txTotal" layout="prev,pager,next" small @current-change="loadTxns" />
                </div>
            </el-tab-pane>
        </el-tabs>

        <!-- 发放积分对话框 -->
        <el-dialog v-model="showGrantDialog" title="🎁 发放积分" width="420px" :close-on-click-modal="false">
            <el-form :model="grantForm" label-width="80px">
                <el-form-item label="用户">
                    <el-select v-model="grantForm.user_id" filterable remote :remote-method="searchUsers" :loading="searchingUsers" placeholder="搜索用户..." style="width:100%">
                        <el-option v-for="u in userSearchResults" :key="u.id" :label="u.name + ' (' + u.email + ')'" :value="u.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="积分数量">
                    <el-input-number v-model="grantForm.amount" :min="1" :max="999999" style="width:100%" />
                </el-form-item>
                <el-form-item label="备注说明">
                    <el-input v-model="grantForm.description" type="textarea" :rows="2" maxlength="500" placeholder="如：活动奖励、内容激励..." />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showGrantDialog = false">取消</el-button>
                <el-button type="warning" :loading="granting" @click="submitGrant">确认发放</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Search, Plus } from '@element-plus/icons-vue'
import apiClient from '@/api/client'

// ── 状态 ──
const activeTab = ref('users')
const stats = ref({})
const loadingUsers = ref(false)
const users = ref([])
const userPage = ref(1)
const userTotal = ref(0)
const searchKeyword = ref('')

const loadingTxns = ref(false)
const transactions = ref([])
const txPage = ref(1)
const txTotal = ref(0)
const txFilter = reactive({
    type: '',
    user_id: '',
    from: '',
    to: '',
})

const showGrantDialog = ref(false)
const grantForm = reactive({ user_id: null, amount: 10, description: '' })
const granting = ref(false)
const userSearchResults = ref([])
const searchingUsers = ref(false)

// ── 加载 ──
async function loadStats() {
    try {
        const r = await apiClient.get('/points/admin/stats')
        stats.value = r.data?.data || {}
    } catch { /* ignore */ }
}

async function loadUsers() {
    loadingUsers.value = true
    try {
        const r = await apiClient.get('/points/admin/users', {
            params: { keyword: searchKeyword.value, page: userPage.value, per_page: 20 }
        })
        const d = r.data?.data || {}
        users.value = d.data || []
        userTotal.value = d.total || 0
    } catch { ElMessage.error('加载失败') }
    finally { loadingUsers.value = false }
}

async function loadTxns() {
    loadingTxns.value = true
    try {
        const params = { page: txPage.value, per_page: 30 }
        if (txFilter.type) params.type = txFilter.type
        if (txFilter.user_id) params.user_id = txFilter.user_id
        if (txFilter.from) params.from = txFilter.from
        if (txFilter.to) params.to = txFilter.to
        const r = await apiClient.get('/points/admin/transactions', { params })
        const d = r.data?.data || {}
        transactions.value = d.data || []
        txTotal.value = d.total || 0
    } catch { ElMessage.error('加载失败') }
    finally { loadingTxns.value = false }
}

async function searchUsers(query) {
    if (!query) { userSearchResults.value = []; return }
    searchingUsers.value = true
    try {
        const r = await apiClient.get('/points/admin/users', { params: { keyword: query, per_page: 10 } })
        userSearchResults.value = r.data?.data?.data || []
    } catch { /* ignore */ }
    finally { searchingUsers.value = false }
}

async function submitGrant() {
    if (!grantForm.user_id) { ElMessage.warning('请选择用户'); return }
    if (!grantForm.amount || grantForm.amount < 1) { ElMessage.warning('请输入有效积分数量'); return }
    granting.value = true
    try {
        const r = await apiClient.post('/points/grant', {
            user_id: grantForm.user_id,
            amount: grantForm.amount,
            description: grantForm.description || '管理员发放',
        })
        ElMessage.success(`🎉 已发放 ${grantForm.amount} 积分，当前余额 ${r.data?.data?.balance}`)
        showGrantDialog.value = false
        grantForm.user_id = null
        grantForm.amount = 10
        grantForm.description = ''
        loadUsers()
        loadStats()
    } catch (e) { ElMessage.error(e.response?.data?.message || '发放失败') }
    finally { granting.value = false }
}

function viewUserTxns(row) {
    txFilter.user_id = String(row.user_id)
    txFilter.type = ''
    txFilter.from = ''
    txFilter.to = ''
    activeTab.value = 'transactions'
    txPage.value = 1
    loadTxns()
}

function openGrantForUser(row) {
    grantForm.user_id = row.user_id
    grantForm.amount = 10
    grantForm.description = ''
    userSearchResults.value = [row.user]
    showGrantDialog.value = true
}

function formatNum(n) {
    if (n === null || n === undefined) return '0'
    return Number(n).toLocaleString('zh-CN', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function formatTime(t) {
    if (!t) return ''
    return new Date(t).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => {
    loadStats()
    loadUsers()
})
</script>

<style scoped>
.points-management {
    padding: 20px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.page-header .text-muted { font-size: 13px; color: #909399; margin: 0; }
.page-header-actions { display: flex; gap: 8px; }
.stats-row {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    margin-bottom: 8px;
}
.stat-card {
    background: #fff;
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 14px 12px;
    text-align: center;
}
.stat-value { font-size: 22px; font-weight: 700; color: #303133; }
.stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
@media (max-width: 900px) {
    .stats-row { grid-template-columns: repeat(3, 1fr); }
}
</style>
