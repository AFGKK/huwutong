<template>
    <div class="points-management">
        <div class="page-header">
            <div>
                <h2>{{ t(`${P}.title`) }}</h2>
                <p class="text-muted">{{ t(`${P}.subtitle`) }}</p>
            </div>
            <div class="page-header-actions">
                <el-button type="warning" @click="showGrantDialog = true">
                    <el-icon><Plus /></el-icon> {{ t(`${P}.grant`) }}
                </el-button>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value">{{ stats.total_users || 0 }}</div>
                <div class="stat-label">{{ t(`${P}.stats.users`) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#e6a23c">{{ formatNum(stats.total_balance) }}</div>
                <div class="stat-label">{{ t(`${P}.stats.balance`) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#67c23a">+{{ formatNum(stats.total_earned) }}</div>
                <div class="stat-label">{{ t(`${P}.stats.earned`) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#f56c6c">-{{ formatNum(stats.total_spent) }}</div>
                <div class="stat-label">{{ t(`${P}.stats.spent`) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#0f172a">{{ formatNum(stats.today_earned) }}</div>
                <div class="stat-label">{{ t(`${P}.stats.today_earned`) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#e6a23c">{{ formatNum(stats.today_spent) }}</div>
                <div class="stat-label">{{ t(`${P}.stats.today_spent`) }}</div>
            </div>
        </div>

        <el-tabs v-model="activeTab" style="margin-top:16px">
            <el-tab-pane :label="t(`${P}.tabs.users`)" name="users">
                <div style="display:flex;gap:8px;margin-bottom:12px">
                    <el-input v-model="searchKeyword" :placeholder="t(`${P}.search_ph`)" size="small" clearable style="width:300px" @clear="loadUsers" @keyup.enter="loadUsers">
                        <template #prefix><el-icon><Search /></el-icon></template>
                    </el-input>
                    <el-button size="small" @click="loadUsers">{{ t('actions.search') }}</el-button>
                </div>
                <el-table :data="users" v-loading="loadingUsers" stripe style="width:100%">
                    <el-table-column :label="t(`${P}.cols.user`)" min-width="180">
                        <template #default="{ row }">
                            <div style="display:flex;align-items:center;gap:8px">
                                <el-avatar :size="28" :src="row.user?.avatar">{{ row.user?.name?.charAt(0) || '?' }}</el-avatar>
                                <div>
                                    <div style="font-weight:500;font-size:13px">{{ row.user?.name || t(`${P}.unknown`) }}</div>
                                    <div style="font-size:11px;color:#909399">{{ row.user?.email || '' }}</div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="balance" :label="t(`${P}.cols.balance`)" width="120" align="center">
                        <template #default="{ row }"><strong>{{ formatNum(row.balance) }}</strong></template>
                    </el-table-column>
                    <el-table-column prop="total_earned" :label="t(`${P}.cols.total_earned`)" width="120" align="center">
                        <template #default="{ row }"><span style="color:#67c23a">+{{ formatNum(row.total_earned) }}</span></template>
                    </el-table-column>
                    <el-table-column prop="total_spent" :label="t(`${P}.cols.total_spent`)" width="120" align="center">
                        <template #default="{ row }"><span style="color:#f56c6c">-{{ formatNum(row.total_spent) }}</span></template>
                    </el-table-column>
                    <el-table-column :label="t(`${P}.cols.actions`)" width="160" align="center">
                        <template #default="{ row }">
                            <el-button size="small" text type="primary" @click="viewUserTxns(row)">{{ t(`${P}.txns`) }}</el-button>
                            <el-button size="small" text type="warning" @click="openGrantForUser(row)">{{ t(`${P}.grant_short`) }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div style="display:flex;justify-content:center;margin-top:12px">
                    <el-pagination v-model:current-page="userPage" :page-size="20" :total="userTotal" layout="prev,pager,next" small @current-change="loadUsers" />
                </div>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P}.tabs.transactions`)" name="transactions">
                <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center">
                    <el-select v-model="txFilter.type" :placeholder="t(`${P}.cols.type`)" size="small" style="width:100px" clearable @change="loadTxns">
                        <el-option :label="t(`${P}.all`)" value="" />
                        <el-option :label="t(`${P}.types.earn`)" value="earn" />
                        <el-option :label="t(`${P}.types.spend`)" value="spend" />
                        <el-option :label="t(`${P}.types.refund`)" value="refund" />
                        <el-option :label="t(`${P}.types.bonus`)" value="bonus" />
                    </el-select>
                    <el-input v-model="txFilter.user_id" :placeholder="t(`${P}.user_id`)" size="small" style="width:100px" clearable @clear="loadTxns" @keyup.enter="loadTxns" />
                    <el-date-picker v-model="txFilter.from" type="date" :placeholder="t(`${P}.from`)" size="small" style="width:130px" value-format="YYYY-MM-DD" @change="loadTxns" />
                    <el-date-picker v-model="txFilter.to" type="date" :placeholder="t(`${P}.to`)" size="small" style="width:130px" value-format="YYYY-MM-DD" @change="loadTxns" />
                    <el-button size="small" @click="loadTxns">{{ t('actions.search') }}</el-button>
                </div>
                <el-table :data="transactions" v-loading="loadingTxns" stripe style="width:100%">
                    <el-table-column :label="t(`${P}.cols.user`)" min-width="140">
                        <template #default="{ row }">
                            <span style="font-size:13px">{{ row.user?.name || 'ID:' + row.user_id }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="type" :label="t(`${P}.cols.type`)" width="80" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.type === 'earn' ? 'success' : row.type === 'spend' ? 'danger' : 'warning'" size="small">
                                {{ typeLabel(row.type) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="amount" :label="t(`${P}.cols.amount`)" width="100" align="center">
                        <template #default="{ row }">
                            <span :style="{ color: row.type === 'earn' ? '#67c23a' : '#f56c6c', fontWeight:600 }">
                                {{ row.type === 'earn' ? '+' : '-' }}{{ formatNum(row.amount) }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="description" :label="t(`${P}.cols.description`)" min-width="200" />
                    <el-table-column prop="balance_before" :label="t(`${P}.cols.before`)" width="80" align="center">
                        <template #default="{ row }">{{ formatNum(row.balance_before) }}</template>
                    </el-table-column>
                    <el-table-column prop="balance_after" :label="t(`${P}.cols.after`)" width="80" align="center">
                        <template #default="{ row }">{{ formatNum(row.balance_after) }}</template>
                    </el-table-column>
                    <el-table-column prop="created_at" :label="t(`${P}.cols.time`)" width="150" align="center">
                        <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                    </el-table-column>
                </el-table>
                <div style="display:flex;justify-content:center;margin-top:12px">
                    <el-pagination v-model:current-page="txPage" :page-size="30" :total="txTotal" layout="prev,pager,next" small @current-change="loadTxns" />
                </div>
            </el-tab-pane>
        </el-tabs>

        <el-dialog v-model="showGrantDialog" :title="t(`${P}.grant`)" width="420px" :close-on-click-modal="false">
            <el-form :model="grantForm" label-width="80px">
                <el-form-item :label="t(`${P}.cols.user`)">
                    <el-select v-model="grantForm.user_id" filterable remote :remote-method="searchUsers" :loading="searchingUsers" :placeholder="t(`${P}.search_user`)" style="width:100%">
                        <el-option v-for="u in userSearchResults" :key="u.id" :label="u.name + ' (' + u.email + ')'" :value="u.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.amount`)">
                    <el-input-number v-model="grantForm.amount" :min="1" :max="999999" style="width:100%" />
                </el-form-item>
                <el-form-item :label="t(`${P}.note`)">
                    <el-input v-model="grantForm.description" type="textarea" :rows="2" maxlength="500" :placeholder="t(`${P}.note_ph`)" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showGrantDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="warning" :loading="granting" @click="submitGrant">{{ t(`${P}.confirm_grant`) }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Search, Plus } from '@element-plus/icons-vue'
import pointsApi from '@/api/points'

const { t, locale } = useI18n()
const P = 'points_page'
const dateLocale = computed(() => (locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'))

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

function typeLabel(type) {
    const key = `${P}.types.${type}`
    const translated = t(key)
    return translated === key ? type : translated
}

async function loadStats() {
    try {
        const r = await pointsApi.getAdminStats()
        stats.value = r.data?.data || {}
    } catch { /* ignore */ }
}

async function loadUsers() {
    loadingUsers.value = true
    try {
        const r = await pointsApi.getAdminUsers({
            keyword: searchKeyword.value, page: userPage.value, per_page: 20
        })
        const d = r.data?.data || {}
        users.value = Array.isArray(d) ? d : (d.data || [])
        userTotal.value = r.data?.meta?.total || d.total || 0
    } catch { ElMessage.error(t('messages.load_failed')) }
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
        const r = await pointsApi.getAdminTransactions(params)
        const d = r.data?.data || {}
        transactions.value = Array.isArray(d) ? d : (d.data || [])
        txTotal.value = r.data?.meta?.total || d.total || 0
    } catch { ElMessage.error(t('messages.load_failed')) }
    finally { loadingTxns.value = false }
}

async function searchUsers(query) {
    if (!query) { userSearchResults.value = []; return }
    searchingUsers.value = true
    try {
        const r = await pointsApi.getAdminUsers({ keyword: query, per_page: 10 })
        userSearchResults.value = Array.isArray(r.data?.data) ? r.data.data : (r.data?.data?.data || [])
    } catch { /* ignore */ }
    finally { searchingUsers.value = false }
}

async function submitGrant() {
    if (!grantForm.user_id) { ElMessage.warning(t(`${P}.messages.select_user`)); return }
    if (!grantForm.amount || grantForm.amount < 1) { ElMessage.warning(t(`${P}.messages.invalid_amount`)); return }
    granting.value = true
    try {
        const r = await pointsApi.grantPoints({
            user_id: grantForm.user_id,
            amount: grantForm.amount,
            description: grantForm.description || t(`${P}.default_desc`),
        })
        ElMessage.success(t(`${P}.messages.granted`, { n: grantForm.amount, balance: r.data?.data?.balance }))
        showGrantDialog.value = false
        grantForm.user_id = null
        grantForm.amount = 10
        grantForm.description = ''
        loadUsers()
        loadStats()
    } catch (e) { ElMessage.error(e.response?.data?.message || t(`${P}.messages.grant_failed`)) }
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
    return Number(n).toLocaleString(dateLocale.value, { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function formatTime(time) {
    if (!time) return ''
    return new Date(time).toLocaleString(dateLocale.value, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

watch(activeTab, (val) => {
    if (val === 'transactions') loadTxns()
})

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
