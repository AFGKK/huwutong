<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import request from '@/api/client'

const { t, locale } = useI18n()
const ns = 'agent_tiers_page'
const ch = 'channel_page'
const cp = 'channel_partners_page'

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

const levelLabels = computed(() => ({
    regular: t(`${ch}.levels.regular`),
    silver: t(`${ch}.levels.silver`),
    gold: t(`${ch}.levels.gold`),
    platinum: t(`${ch}.levels.platinum`),
}))

function levelLabel(l) {
    return levelLabels.value[l] || l
}

function promotionModeLabel(mode) {
    return mode === 'auto'
        ? t(`${ns}.promotion_mode.auto`)
        : t(`${ns}.promotion_mode.manual`)
}

function fmtDate(d) {
    return d ? new Date(d).toLocaleString(locale.value) : '-'
}

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
        ElMessage.success(t(`${ns}.messages.saved`))
        tierDialog.value = false
        loadTiers()
    } catch (e) {
        ElMessage.error(t('messages.failed'))
    }
}

function editRule(rule) {
    editingRule.value = { ...rule }
    ruleDialog.value = true
}

async function saveRule() {
    try {
        await request.put(`/agent-tiers/rules/${editingRule.value.id}`, editingRule.value)
        ElMessage.success(t(`${ns}.messages.rule_saved`))
        ruleDialog.value = false
        loadRules()
    } catch (e) {
        ElMessage.error(t('messages.failed'))
    }
}

async function initTiers() {
    try {
        await ElMessageBox.confirm(t(`${ns}.prompts.init_confirm`), t('actions.confirm'))
        await request.post('/agent-tiers/init')
        ElMessage.success(t(`${ns}.messages.init_ok`))
        loadTiers()
        loadRules()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t(`${ns}.messages.init_failed`))
    }
}

async function evaluateAgent() {
    const id = evaluateAgentId.value
    if (!id) {
        ElMessage.warning(t(`${ns}.messages.enter_partner_id`))
        return
    }
    evaluateLoading.value = true
    try {
        const res = await request.get(`/agent-tiers/agents/${id}/evaluate`)
        evaluateResult.value = res.data.data
    } catch (e) {
        ElMessage.error(t(`${ns}.messages.evaluate_failed`))
    } finally {
        evaluateLoading.value = false
    }
}

async function promoteAgent(agentId) {
    try {
        await request.post(`/agent-tiers/agents/${agentId}/promote`)
        ElMessage.success(t(`${ns}.messages.promoted`))
        if (evaluateResult.value) await evaluateAgent()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${ns}.messages.promote_failed`))
    }
}

async function demoteAgent(agentId) {
    try {
        await request.post(`/agent-tiers/agents/${agentId}/demote`)
        ElMessage.success(t(`${ns}.messages.demoted`))
        if (evaluateResult.value) await evaluateAgent()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${ns}.messages.demote_failed`))
    }
}

async function autoPromote() {
    try {
        await ElMessageBox.confirm(
            t(`${ns}.prompts.auto_promote_confirm`),
            t(`${ns}.prompts.auto_promote_title`),
        )
        await request.post('/agent-tiers/auto-promote')
        ElMessage.success(t(`${ns}.messages.auto_promote_ok`))
        loadOverview()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t(`${ns}.messages.auto_promote_failed`))
    }
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
            <el-breadcrumb-item :to="{ path: '/admin' }">{{ t('nav.home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t(`${cp}.breadcrumb.business`) }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t(`${ns}.breadcrumb.current`) }}</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 概述 -->
        <el-row :gutter="16" class="mb-5" v-if="overview">
            <el-col :span="6" v-for="(info, level) in overview" :key="level">
                <el-card shadow="never" class="stat-card">
                    <div class="text-sm text-gray-500">{{ levelLabel(level) }}</div>
                    <div class="text-2xl font-bold mt-1">{{ info.count || 0 }}</div>
                    <div class="text-xs text-gray-400 mt-1">{{ t(`${ns}.stats.percentage`, { pct: info.percentage || 0 }) }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <div class="mb-4">
                <el-button @click="initTiers">{{ t(`${ns}.buttons.init_defaults`) }}</el-button>
                <el-button type="success" @click="autoPromote">{{ t(`${ns}.buttons.auto_promote`) }}</el-button>
                <el-button @click="evaluateDialog = true" type="primary">{{ t(`${ns}.buttons.evaluate`) }}</el-button>
            </div>

            <el-tabs v-model="activeTab">
                <!-- 等级定义 -->
                <el-tab-pane :label="t(`${ns}.tabs.tiers`)" name="tiers">
                    <el-table :data="tiers" v-loading="loading" stripe>
                        <el-table-column prop="level" :label="t('commission_page.cols.slug')" width="120" />
                        <el-table-column prop="label" :label="t('commission_page.cols.name')" width="150" />
                        <el-table-column prop="default_rate" :label="t(`${ns}.cols.default_rate`)" width="120">
                            <template #default="{ row }">{{ row.default_rate }}%</template>
                        </el-table-column>
                        <el-table-column prop="benefits" :label="t(`${ns}.cols.benefits`)" min-width="200">
                            <template #default="{ row }">
                                <span class="text-gray-500 text-sm">{{ Array.isArray(row.benefits) ? row.benefits.join(', ') : row.benefits }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="color" :label="t(`${ns}.cols.color`)" width="80">
                            <template #default="{ row }">
                                <div class="w-5 h-5 rounded" :style="{ backgroundColor: row.color || '#909399' }"></div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('commission_page.cols.actions')" width="80">
                            <template #default="{ row }">
                                <el-button size="small" @click="editTier(row)">{{ t('actions.edit') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 晋升规则 -->
                <el-tab-pane :label="t(`${ns}.tabs.rules`)" name="rules">
                    <el-table :data="rules" stripe>
                        <el-table-column :label="t(`${ns}.cols.from`)" width="100">
                            <template #default="{ row }">{{ levelLabel(row.from_level) }}</template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.to`)" width="100">
                            <template #default="{ row }">{{ levelLabel(row.to_level) }}</template>
                        </el-table-column>
                        <el-table-column prop="min_days" :label="t(`${ns}.cols.min_days`)" width="90" align="center" />
                        <el-table-column prop="min_subscriptions" :label="t(`${ns}.cols.min_subscriptions`)" width="90" align="center" />
                        <el-table-column prop="min_total_amount" :label="t(`${ns}.cols.min_total_amount`)" width="120" align="right">
                            <template #default="{ row }">{{ row.min_total_amount ? '¥' + Number(row.min_total_amount).toLocaleString(locale) : '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="min_referrals" :label="t(`${ns}.cols.min_referrals`)" width="90" align="center" />
                        <el-table-column prop="min_monthly_amount" :label="t(`${ns}.cols.min_monthly_amount`)" width="120" align="right">
                            <template #default="{ row }">{{ row.min_monthly_amount ? '¥' + Number(row.min_monthly_amount).toLocaleString(locale) : '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="promotion_period" :label="t(`${ns}.cols.mode`)" width="80">
                            <template #default="{ row }">{{ promotionModeLabel(row.promotion_period) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('commission_page.cols.actions')" width="80">
                            <template #default="{ row }">
                                <el-button size="small" @click="editRule(row)">{{ t('actions.edit') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 晋升历史 -->
                <el-tab-pane :label="t(`${ns}.tabs.history`)" name="history">
                    <el-table :data="tierHistory" stripe>
                        <el-table-column prop="agent_id" :label="t(`${ns}.cols.partner_id`)" width="100" />
                        <el-table-column :label="t(`${ns}.cols.old_level`)" width="100">
                            <template #default="{ row }">{{ levelLabel(row.from_level) }}</template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.new_level`)" width="100">
                            <template #default="{ row }">{{ levelLabel(row.to_level) }}</template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.mode`)" width="80">
                            <template #default="{ row }">{{ promotionModeLabel(row.promotion_period) }}</template>
                        </el-table-column>
                        <el-table-column prop="reason" :label="t(`${ns}.cols.reason`)" min-width="200" />
                        <el-table-column :label="t(`${ns}.cols.time`)" width="160">
                            <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
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
        <el-dialog v-model="tierDialog" :title="t(`${ns}.tier_dialog.title`)" width="500px">
            <el-form :model="editingTier" label-width="100px">
                <el-form-item :label="t('commission_page.cols.slug')"><el-input v-model="editingTier.level" disabled /></el-form-item>
                <el-form-item :label="t('commission_page.cols.name')"><el-input v-model="editingTier.label" /></el-form-item>
                <el-form-item :label="t(`${ns}.tier_dialog.default_rate_pct`)"><el-input-number v-model="editingTier.default_rate" :min="0" :max="100" :precision="2" /></el-form-item>
                <el-form-item :label="t(`${ns}.tier_dialog.benefits`)">
                    <el-input v-model="editingTier.benefits" type="textarea" :rows="3" :placeholder="t(`${ns}.tier_dialog.benefits_ph`)" />
                </el-form-item>
                <el-form-item :label="t(`${ns}.cols.color`)"><el-input v-model="editingTier.color" :placeholder="t(`${ns}.tier_dialog.color_ph`)" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="tierDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="saveTier">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 编辑规则对话框 -->
        <el-dialog v-model="ruleDialog" :title="t(`${ns}.rule_dialog.title`)" width="550px">
            <el-form :model="editingRule" label-width="120px">
                <el-form-item :label="t(`${ns}.rule_dialog.from_level`)"><el-input v-model="editingRule.from_level" disabled /></el-form-item>
                <el-form-item :label="t(`${ns}.rule_dialog.to_level`)"><el-input v-model="editingRule.to_level" disabled /></el-form-item>
                <el-form-item :label="t(`${ns}.cols.min_days`)"><el-input-number v-model="editingRule.min_days" :min="0" /></el-form-item>
                <el-form-item :label="t(`${ns}.rule_dialog.min_subscriptions`)"><el-input-number v-model="editingRule.min_subscriptions" :min="0" /></el-form-item>
                <el-form-item :label="t(`${ns}.cols.min_total_amount`)"><el-input-number v-model="editingRule.min_total_amount" :min="0" :precision="2" /></el-form-item>
                <el-form-item :label="t(`${ns}.rule_dialog.min_referrals`)"><el-input-number v-model="editingRule.min_referrals" :min="0" /></el-form-item>
                <el-form-item :label="t(`${ns}.cols.min_monthly_amount`)"><el-input-number v-model="editingRule.min_monthly_amount" :min="0" :precision="2" /></el-form-item>
                <el-form-item :label="t(`${ns}.rule_dialog.promotion_mode`)">
                    <el-radio-group v-model="editingRule.promotion_period">
                        <el-radio value="auto">{{ t(`${ns}.promotion_mode.auto`) }}</el-radio>
                        <el-radio value="manual">{{ t(`${ns}.promotion_mode.manual`) }}</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="ruleDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="saveRule">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 评估晋升对话框 -->
        <el-dialog v-model="evaluateDialog" :title="t(`${ns}.evaluate_dialog.title`)" width="500px">
            <div class="mb-4 flex gap-2">
                <el-input v-model="evaluateAgentId" :placeholder="t(`${ns}.evaluate_dialog.partner_id_ph`)" />
                <el-button type="primary" :loading="evaluateLoading" @click="evaluateAgent">{{ t(`${ns}.buttons.evaluate_action`) }}</el-button>
            </div>
            <div v-if="evaluateResult">
                <el-descriptions :column="1" border>
                    <el-descriptions-item :label="t(`${ns}.evaluate_dialog.current_level`)">{{ levelLabel(evaluateResult.current_level) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${ns}.evaluate_dialog.target_level`)">{{ levelLabel(evaluateResult.target_level) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${ns}.evaluate_dialog.eligible`)">
                        <el-tag :type="evaluateResult.eligible ? 'success' : 'danger'">
                            {{ evaluateResult.eligible ? t(`${ns}.labels.yes`) : t(`${ns}.labels.no`) }}
                        </el-tag>
                    </el-descriptions-item>
                </el-descriptions>
                <div class="mt-3" v-if="evaluateResult.conditions">
                    <div v-for="(met, cond) in evaluateResult.conditions" :key="cond" class="flex items-center gap-2 mb-1">
                        <el-tag :type="met ? 'success' : 'danger'" size="small">{{ met ? t(`${ns}.labels.met`) : t(`${ns}.labels.unmet`) }}</el-tag>
                        <span class="text-sm">{{ cond }}</span>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <el-button v-if="evaluateResult.eligible" type="primary" @click="promoteAgent(evaluateAgentId)">{{ t(`${ns}.buttons.promote`) }}</el-button>
                    <el-button type="danger" @click="demoteAgent(evaluateAgentId)">{{ t(`${ns}.buttons.demote`) }}</el-button>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-card { border-radius: 8px; }
</style>
