<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import enhancedApi from '@/api/affiliateEnhanced'
import affiliateApi from '@/api/affiliate'

const activeTab = ref('campaigns')
const loading = ref(false)

// ─── 看板数据 ───
const dashboardData = ref(null)

// ─── 推广链接 ───
const linkForm = ref({ agent_id: null, campaign_id: null, product_id: null })
const generatedLink = ref(null)
const generatingLink = ref(false)
const agentLinks = ref([])
const linkAgentId = ref('')
const linkLoading = ref(false)

// ─── 推广门户 ───
const portalAgentId = ref('')
const portalData = ref(null)
const portalLoading = ref(false)

// ─── 收益账户结算 ───
const settleForm = ref({ agent_id: null, amount: null, source: 'manual', notes: '' })
const settling = ref(false)
const settleResult = ref(null)

// ─── 商品级推广 ───
const productLinkForm = ref({ agent_id: null, product_id: null, campaign_id: null })
const productLinkResult = ref(null)
const productLinkLoading = ref(false)
const productStatsData = ref(null)
const storeDashData = ref(null)

// ─── 转化归因 ───
const attributeForm = ref({ referral_code: '', converted_user_id: null, commission_amount: 0 })
const attributing = ref(false)
const attrResult = ref(null)

async function loadDashboard() {
    try {
        const res = await affiliateApi.dashboard()
        dashboardData.value = res.data.data
    } catch (e) {}
}

async function generateLink() {
    generatingLink.value = true
    try {
        const res = await enhancedApi.generateLink(linkForm.value)
        generatedLink.value = res.data.data
        ElMessage.success('推广链接已生成')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '生成失败')
    } finally {
        generatingLink.value = false
    }
}

async function loadAgentLinks() {
    if (!linkAgentId.value) { ElMessage.warning('请输入代理商ID'); return }
    linkLoading.value = true
    try {
        const res = await enhancedApi.agentLinks(linkAgentId.value)
        agentLinks.value = res.data.data || []
    } catch (e) {
        ElMessage.error('加载链接失败')
    } finally {
        linkLoading.value = false
    }
}

async function loadPortal() {
    if (!portalAgentId.value) { ElMessage.warning('请输入代理商ID'); return }
    portalLoading.value = true
    try {
        const res = await enhancedApi.agentPortal(portalAgentId.value)
        portalData.value = res.data.data
    } catch (e) {
        ElMessage.error('加载门户数据失败')
    } finally {
        portalLoading.value = false
    }
}

async function settleCommission() {
    settling.value = true
    try {
        const res = await enhancedApi.settleCommission(settleForm.value)
        settleResult.value = res.data.data
        ElMessage.success('佣金已结算到收益账户')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '结算失败')
    } finally {
        settling.value = false
    }
}

async function generateProductLink() {
    productLinkLoading.value = true
    try {
        const res = await enhancedApi.generateProductLink(productLinkForm.value)
        productLinkResult.value = res.data.data
        ElMessage.success('商品推广链接已生成')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '生成失败')
    } finally {
        productLinkLoading.value = false
    }
}

async function loadProductStats() {
    try {
        const res = await enhancedApi.storeDashboard()
        storeDashData.value = res.data.data
    } catch (e) {}
}

async function attributeConversion() {
    attributing.value = true
    try {
        const res = await enhancedApi.attributeWithSettlement(attributeForm.value)
        attrResult.value = res.data.data
        ElMessage.success(res.data.data.attributed ? '转化已归因并结算' : '未找到匹配点击')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '归因失败')
    } finally {
        attributing.value = false
    }
}

function formatMoney(v) {
    return v != null ? '¥' + Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) : '¥0.00'
}
function fmtDate(d) {
    return d ? new Date(d).toLocaleString('zh-CN') : '-'
}
function copyText(text) {
    navigator.clipboard?.writeText(text).then(() => ElMessage.success('已复制'))
}

onMounted(() => {
    loadDashboard()
})
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>商业管理</el-breadcrumb-item>
            <el-breadcrumb-item>联盟推广 M3-05</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 看板统计 -->
        <el-row :gutter="16" class="mb-4" v-if="dashboardData?.overview">
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">累计点击</div>
                        <div class="stat-value">{{ dashboardData.overview.total_clicks }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">转化次数</div>
                        <div class="stat-value" style="color:#67C23A">{{ dashboardData.overview.total_conversions }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">转化率</div>
                        <div class="stat-value" style="color:#409EFF">{{ dashboardData.overview.conversion_rate }}%</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">累计佣金</div>
                        <div class="stat-value">{{ formatMoney(dashboardData.overview.total_commission) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">活跃活动</div>
                        <div class="stat-value" style="color:#E6A23C">{{ dashboardData.overview.active_campaigns }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">月佣金</div>
                        <div class="stat-value">{{ formatMoney(dashboardData.monthly_trend?.[dashboardData.monthly_trend.length - 1]?.commission || 0) }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <!-- Tab 1: 推广链接 -->
                <el-tab-pane label="🔗 推广链接" name="links">
                    <el-row :gutter="24">
                        <el-col :span="10">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">生成推广链接</span></template>
                                <el-form :model="linkForm" label-width="110px">
                                    <el-form-item label="代理商ID" required>
                                        <el-input-number v-model="linkForm.agent_id" :min="1" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item label="活动ID">
                                        <el-input-number v-model="linkForm.campaign_id" :min="0" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item label="商品ID">
                                        <el-input-number v-model="linkForm.product_id" :min="0" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" :loading="generatingLink" @click="generateLink">生成链接</el-button>
                                    </el-form-item>
                                </el-form>
                                <div v-if="generatedLink" class="p-3 bg-green-50 rounded">
                                    <div class="mb-2"><strong>推广码：</strong><code>{{ generatedLink.referral_code }}</code></div>
                                    <div class="mb-2"><strong>短链：</strong><code class="text-sm break-all">{{ generatedLink.short_url }}</code></div>
                                    <div class="mb-2"><strong>完整URL：</strong><code class="text-xs break-all">{{ generatedLink.full_url }}</code></div>
                                    <el-button size="small" @click="copyText(generatedLink.short_url)">复制短链</el-button>
                                    <el-button size="small" @click="copyText(generatedLink.full_url)">复制完整链接</el-button>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="14">
                            <el-card shadow="never">
                                <template #header>
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold">代理推广链接列表</span>
                                        <div class="flex gap-2">
                                            <el-input v-model="linkAgentId" placeholder="代理商ID" style="width:140px" />
                                            <el-button @click="loadAgentLinks">查询</el-button>
                                        </div>
                                    </div>
                                </template>
                                <el-table :data="agentLinks" v-loading="linkLoading" size="small" stripe>
                                    <el-table-column prop="invite_code" label="推广码" width="120" />
                                    <el-table-column label="转化" width="70" align="center">
                                        <template #default="{ row }">
                                            <el-tag :type="row.converted ? 'success' : 'info'" size="small">{{ row.converted ? '是' : '否' }}</el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="clicks" label="点击" width="60" />
                                    <el-table-column label="商品" width="70">
                                        <template #default="{ row }">{{ row.product_id || '-' }}</template>
                                    </el-table-column>
                                    <el-table-column label="创建时间" width="160">
                                        <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                                    </el-table-column>
                                    <el-table-column label="操作" width="60">
                                        <template #default="{ row }">
                                            <el-button size="small" @click="copyText(row.landing_url)">复制</el-button>
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <el-empty v-if="!agentLinks.length && linkLoading === false" description="输入代理商ID查询" />
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>

                <!-- Tab 2: 推广门户 -->
                <el-tab-pane label="🏪 推广门户" name="portal">
                    <div class="flex gap-2 mb-4">
                        <el-input v-model="portalAgentId" placeholder="输入代理商ID查看门户" style="width:260px" />
                        <el-button type="primary" :loading="portalLoading" @click="loadPortal">查看门户</el-button>
                    </div>
                    <div v-if="portalData" v-loading="portalLoading">
                        <el-row :gutter="16" class="mb-4">
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-item">
                                        <div class="stat-label">累计点击</div>
                                        <div class="stat-value">{{ portalData.summary?.clicks_total ?? 0 }}</div>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-item">
                                        <div class="stat-label">转化次数</div>
                                        <div class="stat-value" style="color:#67C23A">{{ portalData.summary?.conversions_total ?? 0 }}</div>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-item">
                                        <div class="stat-label">转化率</div>
                                        <div class="stat-value" style="color:#409EFF">{{ portalData.summary?.conversion_rate ?? 0 }}%</div>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-item">
                                        <div class="stat-label">下级代理</div>
                                        <div class="stat-value">{{ portalData.summary?.downline_count ?? 0 }}</div>
                                    </div>
                                </el-card>
                            </el-col>
                        </el-row>

                        <!-- 收益账户 -->
                        <el-card shadow="never" class="mb-4" v-if="portalData.earnings_account">
                            <template #header><span>💰 收益账户</span></template>
                            <el-descriptions :column="4" border size="small">
                                <el-descriptions-item label="待结算(冻结)">{{ formatMoney(portalData.earnings_account.pending_balance) }}</el-descriptions-item>
                                <el-descriptions-item label="可用余额">{{ formatMoney(portalData.earnings_account.available_balance) }}</el-descriptions-item>
                                <el-descriptions-item label="已提现总额">{{ formatMoney(portalData.earnings_account.total_withdrawn) }}</el-descriptions-item>
                                <el-descriptions-item label="待结算佣金">{{ formatMoney(portalData.pending_commission) }}</el-descriptions-item>
                            </el-descriptions>
                            <!-- 提现入口 -->
                            <div class="mt-3">
                                <el-button type="primary" @click="$router.push('/admin/withdrawals')">
                                    💸 前往提现 ({{ formatMoney(portalData.withdrawal?.available_balance) }}) 
                                </el-button>
                                <span class="text-xs text-gray-400 ml-2">最低提现额: {{ formatMoney(portalData.withdrawal?.min_withdrawal) }}</span>
                            </div>
                        </el-card>

                        <!-- 最近转化 -->
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>📋 最近转化 (最近10笔)</span></template>
                            <el-table :data="portalData.recent_conversions ?? []" size="small" stripe>
                                <el-table-column label="客户" prop="user_name" />
                                <el-table-column label="佣金" width="130">
                                    <template #default="{ row }">{{ formatMoney(row.commission) }}</template>
                                </el-table-column>
                                <el-table-column label="时间" width="160">
                                    <template #default="{ row }">{{ fmtDate(row.converted_at) }}</template>
                                </el-table-column>
                            </el-table>
                            <el-empty v-if="!portalData.recent_conversions?.length" description="暂无转化" />
                        </el-card>

                        <!-- 推广链接 -->
                        <el-card shadow="never">
                            <template #header><span>🔗 推广链接 ({{ portalData.referral_links?.length ?? 0 }} 个)</span></template>
                            <el-table :data="portalData.referral_links ?? []" size="small" stripe>
                                <el-table-column prop="invite_code" label="推广码" width="120" />
                                <el-table-column label="已转化" width="80" align="center">
                                    <template #default="{ row }">
                                        <el-tag :type="row.converted ? 'success' : 'info'" size="small">{{ row.converted ? '是' : '否' }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="clicks" label="点击数" width="70" />
                                <el-table-column label="落地页" min-width="200">
                                    <template #default="{ row }">
                                        <span class="text-xs break-all">{{ row.landing_url }}</span>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </div>
                    <el-empty v-else-if="!portalLoading" description="输入代理商ID查看推广门户" />
                </el-tab-pane>

                <!-- Tab 3: 收益账户结算 -->
                <el-tab-pane label="💰 收益结算" name="settlement">
                    <el-row :gutter="24">
                        <el-col :span="10">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">结算佣金到收益账户</span></template>
                                <el-form :model="settleForm" label-width="120px">
                                    <el-form-item label="代理商ID" required>
                                        <el-input-number v-model="settleForm.agent_id" :min="1" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item label="金额(元)" required>
                                        <el-input-number v-model="settleForm.amount" :min="0.01" :precision="2" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item label="来源">
                                        <el-select v-model="settleForm.source" style="width:100%">
                                            <el-option label="联盟推广" value="affiliate_commission" />
                                            <el-option label="电商分销" value="store_commission" />
                                            <el-option label="手动结算" value="manual" />
                                        </el-select>
                                    </el-form-item>
                                    <el-form-item label="备注">
                                        <el-input v-model="settleForm.notes" type="textarea" :rows="2" />
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" :loading="settling" @click="settleCommission">结算到收益账户</el-button>
                                    </el-form-item>
                                </el-form>
                                <div v-if="settleResult" class="p-3 bg-green-50 rounded mt-2">
                                    ✅ 结算成功！记录ID: {{ settleResult.id }}, 金额: {{ formatMoney(settleResult.commission_amount) }}
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="14">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">转化归因 + 自动结算</span></template>
                                <el-form :model="attributeForm" label-width="120px">
                                    <el-form-item label="推广码" required>
                                        <el-input v-model="attributeForm.referral_code" placeholder="如 AFABC1231" />
                                    </el-form-item>
                                    <el-form-item label="转化用户ID" required>
                                        <el-input-number v-model="attributeForm.converted_user_id" :min="1" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item label="佣金金额">
                                        <el-input-number v-model="attributeForm.commission_amount" :min="0" :precision="2" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" :loading="attributing" @click="attributeConversion">归因并结算</el-button>
                                    </el-form-item>
                                </el-form>
                                <div v-if="attrResult" :class="['p-3 rounded mt-2', attrResult.attributed ? 'bg-green-50' : 'bg-yellow-50']">
                                    {{ attrResult.attributed ? '✅ 转化已归因，佣金已进入收益账户待结算期' : '⚠️ 未找到匹配点击记录' }}
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>

                <!-- Tab 4: 电商分销 (M2-149) -->
                <el-tab-pane label="🛒 电商分销" name="store">
                    <el-row :gutter="24">
                        <el-col :span="10">
                            <el-card shadow="never" class="mb-4">
                                <template #header><span class="font-semibold">生成商品推广链接</span></template>
                                <el-form :model="productLinkForm" label-width="110px">
                                    <el-form-item label="代理商ID" required>
                                        <el-input-number v-model="productLinkForm.agent_id" :min="1" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item label="商品ID" required>
                                        <el-input-number v-model="productLinkForm.product_id" :min="1" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item label="活动ID">
                                        <el-input-number v-model="productLinkForm.campaign_id" :min="0" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" :loading="productLinkLoading" @click="generateProductLink">生成商品链接</el-button>
                                    </el-form-item>
                                </el-form>
                                <div v-if="productLinkResult" class="p-3 bg-green-50 rounded">
                                    <div><strong>推广码：</strong><code>{{ productLinkResult.referral_code }}</code></div>
                                    <div><strong>链接：</strong><code class="text-xs break-all">{{ productLinkResult.full_url }}</code></div>
                                    <el-button size="small" class="mt-2" @click="copyText(productLinkResult.full_url)">复制链接</el-button>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="14">
                            <el-card shadow="never">
                                <template #header>
                                    <span class="font-semibold">📊 电商分销看板</span>
                                    <el-button size="small" class="ml-2" @click="loadProductStats">刷新</el-button>
                                </template>
                                <div v-if="storeDashData">
                                    <h4 class="mb-2">商品推广统计</h4>
                                    <el-table :data="storeDashData.product_stats ?? []" size="small" stripe>
                                        <el-table-column prop="name" label="素材名称" min-width="150" />
                                        <el-table-column prop="clicks" label="点击" width="70" />
                                        <el-table-column prop="conversions" label="转化" width="70" />
                                        <el-table-column prop="conversion_rate" label="转化率" width="80">
                                            <template #default="{ row }">{{ row.conversion_rate }}%</template>
                                        </el-table-column>
                                        <el-table-column label="状态" width="70">
                                            <template #default="{ row }">
                                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '启用' : '禁用' }}</el-tag>
                                            </template>
                                        </el-table-column>
                                    </el-table>

                                    <h4 class="mt-4 mb-2">Top 推广代理</h4>
                                    <el-table :data="storeDashData.top_agents ?? []" size="small" stripe>
                                        <el-table-column prop="agent_id" label="代理商ID" width="100" />
                                        <el-table-column prop="conversions" label="转化数" width="80" />
                                        <el-table-column label="佣金" width="130">
                                            <template #default="{ row }">{{ formatMoney(row.commission) }}</template>
                                        </el-table-column>
                                    </el-table>
                                </div>
                                <el-empty v-else-if="!storeDashData" description="暂无数据，点击刷新加载" />
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<style scoped>
.stat-item { text-align: center; padding: 6px 0; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }
.text-xs { font-size: 12px; }
.break-all { word-break: break-all; }
</style>
