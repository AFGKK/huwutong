<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import request from '@/api/client'

const loading = ref(false)
const tiers = ref([])
const rules = ref([])
const tierHistory = ref([])
const historyPagination = ref({ total: 0, current_page: 1, per_page: 20 })
const activeTab = ref('tiers')
const overview = ref(null)
const evaluateResult = ref(null)

// 等级定义编辑
const tierDialog = ref(false)
const editingTier = ref({ id: null, level: '', label: '', default_rate: null, benefits: [], color: '', icon: '' })

// 规则编辑
const ruleDialog = ref(false)
const editingRule = ref({ id: null, from_level: '', to_level: '', min_days: 0, min_subscriptions: 0, min_total_amount: null, min_referrals: 0, min_monthly_amount: null, promotion_period: 'auto' })

// 晋升评估
const evaluateDialog = ref(false)
const evaluateAgentId = ref('')
const evaluateLoading = ref(false)

async function loadTiers() {
    loading.value = true
    try {
        const res = await request.get('/agent-tiers')
        tiers.value = res.data.data || []
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

async function loadRules() {
    try {
        const res = await request.get('/agent-tiers/rules')
        rules.value = res.data.data || []
    } catch (e) {}
}

async function loadHistory(page = 1) {
    try {
        const res = await request.get('/agent-tiers/history', { params: { page, per_page: 20 } })
        tierHistory.value = res.data.data?.data || res.data.data || []
        historyPagination.value = {
            total: res.data.data?.total || 0,
            current_page: res.data.data?.current_page || page,
            per_page: res.data.data?.per_page || 20,
        }
    } catch (e) {}
}

async function loadOverview() {
    try {
        const res = await request.get('/agent-tiers/overview')
        overview.value = res.data.data
    } catch (e) {}
}

function editTier(tier) {
    editingTier.value = { ...tier }
    tierDialog.value = true
}

async function saveTier() {
    try {
        await request.put(`/agent-tiers/${editingTier.value.id}`, editingTier.value)
        ElMessage.success('已保存')
        tierDialog.value = false
        loadTiers()
    } catch (e) {
        ElMessage.error('保存失败')
    }
}

function editRule(rule) {
    editingRule.value = { ...rule }
    ruleDialog.value = true
}

async function saveRule() {
    try {
        await request.put(`/agent-tiers/rules/${editingRule.value.id}`, editingRule.value)
        ElMessage.success('规则已保存')
        ruleDialog.value = false
        loadRules()
    } catch (e) {
        ElMessage.error('保存失败')
    }
}

async function initTiers() {
    try {
        await ElMessageBox.confirm('将初始化默认等级定义和晋升规则，确定继续？', '确认')
        await request.post('/agent-tiers/init')
        ElMessage.success('初始化成功')
        loadTiers()
        loadRules()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('初始化失败')
    }
}

async function evaluateAgent() {
    const id = evaluateAgentId.value
    if (!id) { ElMessage.warning('请输入合作伙伴ID'); return }
    evaluateLoading.value = true
    try {
        const res = await request.get(`/agent-tiers/agents/${id}/evaluate`)
        evaluateResult.value = res.data.data
    } catch (e) {
        ElMessage.error('评估失败')
    } finally {
        evaluateLoading.value = false
    }
}

async function promoteAgent(agentId) {
    try {
        await request.post(`/agent-tiers/agents/${agentId}/promote`)
        ElMessage.success('已晋升')
        if (evaluateResult.value) await evaluateAgent()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '晋升失败')
    }
}

async function demoteAgent(agentId) {
    try {
        await request.post(`/agent-tiers/agents/${agentId}/demote`)
        ElMessage.success('已降级')
        if (evaluateResult.value) await evaluateAgent()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '降级失败')
    }
}

async function autoPromote() {
    try {
        await ElMessageBox.confirm('将自动评估所有符合条件的合作伙伴并自动晋升，确定继续？', '自动晋升')
        await request.post('/agent-tiers/auto-promote')
        ElMessage.success('自动晋升完成')
        loadOverview()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('自动晋升失败')
    }
}

function levelLabel(l) {
    const map = { regular: '普通', silver: '银牌', gold: '金牌', platinum: '铂金' }
    return map[l] || l
}

onMounted(() => {
    loadTiers()
    loadRules()
    loadHistory()
    loadOverview()
})
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>商业管理</el-breadcrumb-item>
            <el-breadcrumb-item>等级晋升管理</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 概述 -->
        <el-row :gutter="16" class="mb-5" v-if="overview">
            <el-col :span="6" v-for="(info, level) in overview" :key="level">
                <el-card shadow="never" class="stat-card">
                    <div class="text-sm text-gray-500">{{ levelLabel(level) }}</div>
                    <div class="text-2xl font-bold mt-1">{{ info.count || 0 }}</div>
                    <div class="text-xs text-gray-400 mt-1">占比{{ info.percentage || 0 }}%</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <div class="mb-4">
                <el-button @click="initTiers">初始化默认等级</el-button>
                <el-button type="success" @click="autoPromote">自动晋升</el-button>
                <el-button @click="evaluateDialog = true" type="primary">评估晋升</el-button>
            </div>

            <el-tabs v-model="activeTab">
                <!-- 等级定义 -->
                <el-tab-pane label="等级定义" name="tiers">
                    <el-table :data="tiers" v-loading="loading" stripe>
                        <el-table-column prop="level" label="标识" width="120" />
                        <el-table-column prop="label" label="名称" width="150" />
                        <el-table-column prop="default_rate" label="默认佣金率" width="120">
                            <template #default="{ row }">{{ row.default_rate }}%</template>
                        </el-table-column>
                        <el-table-column prop="benefits" label="权益说明" min-width="200">
                            <template #default="{ row }">
                                <span class="text-gray-500 text-sm">{{ Array.isArray(row.benefits) ? row.benefits.join(', ') : row.benefits }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="color" label="颜色" width="80">
                            <template #default="{ row }">
                                <div class="w-5 h-5 rounded" :style="{ backgroundColor: row.color || '#909399' }"></div>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="80">
                            <template #default="{ row }">
                                <el-button size="small" @click="editTier(row)">编辑</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 晋升规则 -->
                <el-tab-pane label="晋升规则" name="rules">
                    <el-table :data="rules" stripe>
                        <el-table-column label="从" width="100">
                            <template #default="{ row }">{{ levelLabel(row.from_level) }}</template>
                        </el-table-column>
                        <el-table-column label="到" width="100">
                            <template #default="{ row }">{{ levelLabel(row.to_level) }}</template>
                        </el-table-column>
                        <el-table-column prop="min_days" label="最少天数" width="90" align="center" />
                        <el-table-column prop="min_subscriptions" label="最少订阅" width="90" align="center" />
                        <el-table-column prop="min_total_amount" label="最低总金额" width="120" align="right">
                            <template #default="{ row }">{{ row.min_total_amount ? '¥' + Number(row.min_total_amount).toLocaleString() : '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="min_referrals" label="最少推荐" width="90" align="center" />
                        <el-table-column prop="min_monthly_amount" label="月最低金额" width="120" align="right">
                            <template #default="{ row }">{{ row.min_monthly_amount ? '¥' + Number(row.min_monthly_amount).toLocaleString() : '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="promotion_period" label="方式" width="80">
                            <template #default="{ row }">{{ row.promotion_period === 'auto' ? '自动' : '手动' }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="80">
                            <template #default="{ row }">
                                <el-button size="small" @click="editRule(row)">编辑</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 晋升历史 -->
                <el-tab-pane label="晋升历史" name="history">
                    <el-table :data="tierHistory" stripe>
                        <el-table-column prop="agent_id" label="合作伙伴 ID" width="100" />
                        <el-table-column label="旧等级" width="100">
                            <template #default="{ row }">{{ levelLabel(row.from_level) }}</template>
                        </el-table-column>
                        <el-table-column label="新等级" width="100">
                            <template #default="{ row }">{{ levelLabel(row.to_level) }}</template>
                        </el-table-column>
                        <el-table-column label="方式" width="80">
                            <template #default="{ row }">{{ row.promotion_period === 'auto' ? '自动' : '手动' }}</template>
                        </el-table-column>
                        <el-table-column prop="reason" label="原因" min-width="200" />
                        <el-table-column label="时间" width="160">
                            <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString('zh-CN') : '-' }}</template>
                        </el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3">
                        <el-pagination v-model:current-page="historyPagination.current_page"
                            :page-size="historyPagination.per_page" :total="historyPagination.total"
                            layout="prev, pager, next, total" @current-change="loadHistory" />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 编辑等级对话框 -->
        <el-dialog v-model="tierDialog" title="编辑等级" width="500px">
            <el-form :model="editingTier" label-width="100px">
                <el-form-item label="标识"><el-input v-model="editingTier.level" disabled /></el-form-item>
                <el-form-item label="名称"><el-input v-model="editingTier.label" /></el-form-item>
                <el-form-item label="默认佣金率(%)"><el-input-number v-model="editingTier.default_rate" :min="0" :max="100" :precision="2" /></el-form-item>
                <el-form-item label="权益">
                    <el-input v-model="editingTier.benefits" type="textarea" :rows="3" placeholder="逗号分隔的权益说明" />
                </el-form-item>
                <el-form-item label="颜色"><el-input v-model="editingTier.color" placeholder="#409eff" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="tierDialog = false">取消</el-button>
                <el-button type="primary" @click="saveTier">保存</el-button>
            </template>
        </el-dialog>

        <!-- 编辑规则对话框 -->
        <el-dialog v-model="ruleDialog" title="编辑晋升规则" width="550px">
            <el-form :model="editingRule" label-width="120px">
                <el-form-item label="从等级"><el-input v-model="editingRule.from_level" disabled /></el-form-item>
                <el-form-item label="到等级"><el-input v-model="editingRule.to_level" disabled /></el-form-item>
                <el-form-item label="最少天数"><el-input-number v-model="editingRule.min_days" :min="0" /></el-form-item>
                <el-form-item label="最少订阅数"><el-input-number v-model="editingRule.min_subscriptions" :min="0" /></el-form-item>
                <el-form-item label="最低总金额"><el-input-number v-model="editingRule.min_total_amount" :min="0" :precision="2" /></el-form-item>
                <el-form-item label="最少推荐人数"><el-input-number v-model="editingRule.min_referrals" :min="0" /></el-form-item>
                <el-form-item label="月最低金额"><el-input-number v-model="editingRule.min_monthly_amount" :min="0" :precision="2" /></el-form-item>
                <el-form-item label="晋升方式">
                    <el-radio-group v-model="editingRule.promotion_period">
                        <el-radio value="auto">自动</el-radio>
                        <el-radio value="manual">手动</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="ruleDialog = false">取消</el-button>
                <el-button type="primary" @click="saveRule">保存</el-button>
            </template>
        </el-dialog>

        <!-- 评估晋升对话框 -->
        <el-dialog v-model="evaluateDialog" title="评估晋升" width="500px">
            <div class="mb-4 flex gap-2">
                <el-input v-model="evaluateAgentId" placeholder="输入合作伙伴 ID" />
                <el-button type="primary" :loading="evaluateLoading" @click="evaluateAgent">评估</el-button>
            </div>
            <div v-if="evaluateResult">
                <el-descriptions :column="1" border>
                    <el-descriptions-item label="当前等级">{{ levelLabel(evaluateResult.current_level) }}</el-descriptions-item>
                    <el-descriptions-item label="目标等级">{{ levelLabel(evaluateResult.target_level) }}</el-descriptions-item>
                    <el-descriptions-item label="是否可晋升">
                        <el-tag :type="evaluateResult.eligible ? 'success' : 'danger'">
                            {{ evaluateResult.eligible ? '是' : '否' }}
                        </el-tag>
                    </el-descriptions-item>
                </el-descriptions>
                <div class="mt-3" v-if="evaluateResult.conditions">
                    <div v-for="(met, cond) in evaluateResult.conditions" :key="cond" class="flex items-center gap-2 mb-1">
                        <el-tag :type="met ? 'success' : 'danger'" size="small">{{ met ? '✓' : '✗' }}</el-tag>
                        <span class="text-sm">{{ cond }}</span>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <el-button v-if="evaluateResult.eligible" type="primary" @click="promoteAgent(evaluateAgentId)">执行晋升</el-button>
                    <el-button type="danger" @click="demoteAgent(evaluateAgentId)">手动降级</el-button>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-card { border-radius: 8px; }
</style>
