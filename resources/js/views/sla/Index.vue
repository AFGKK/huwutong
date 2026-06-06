<template>
    <div class="sla-manager">
        <el-page-header :content="'客户分级 SLA ' + activeTabText" @back="router.push('/')" />

        <el-tabs v-model="activeTab" class="mt-4">
            <!-- ═══ SLA 等级管理 ═══ -->
            <el-tab-pane label="SLA 等级" name="tiers">
                <div class="flex justify-between mb-4">
                    <div class="flex gap-2">
                        <el-button type="primary" @click="openTierDialog()">创建等级</el-button>
                        <el-button @click="handleInitialize">初始化默认等级</el-button>
                    </div>
                </div>

                <el-table :data="tiers" v-loading="loading" stripe border>
                    <el-table-column prop="name" label="等级名称" width="120" />
                    <el-table-column prop="slug" label="标识" width="100" />
                    <el-table-column label="优先级" width="80">
                        <template #default="{ row }">
                            <el-tag type="info">{{ row.priority }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="默认" width="70">
                        <template #default="{ row }">
                            <el-tag v-if="row.is_default" type="success" size="small">默认</el-tag>
                        </template>
                    </el-table-column>

                    <!-- API 限制 -->
                    <el-table-column label="API 限流" min-width="180">
                        <template #default="{ row }">
                            <div class="text-sm">
                                <span>{{ row.api_rate_limit }}/min</span>
                                <span class="text-gray-400 ml-1">突发 {{ row.api_burst_limit }}</span>
                                <span class="text-gray-400 ml-1">并发 {{ row.api_concurrent_limit }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="验证" min-width="120">
                        <template #default="{ row }">
                            <div class="text-sm">
                                <span>{{ row.verify_rate_limit }}/min</span>
                                <span class="text-gray-400 ml-1">超时 {{ row.verify_timeout_seconds }}s</span>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- 客服 SLA -->
                    <el-table-column label="客服 SLA" min-width="160">
                        <template #default="{ row }">
                            <div class="text-sm">
                                <div>响应 {{ row.sla_response_hours }}h</div>
                                <div>解决 {{ row.sla_resolution_hours }}h</div>
                                <div class="flex gap-1 mt-1">
                                    <el-tag v-if="row.support_24_7" size="small" type="success">7x24</el-tag>
                                    <el-tag v-if="row.support_phone" size="small">电话</el-tag>
                                    <el-tag v-if="row.support_dedicated_manager" size="small" type="warning">专属经理</el-tag>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="设备限制" min-width="120">
                        <template #default="{ row }">
                            <div class="text-sm">
                                <div>License: {{ row.max_active_licenses || '不限' }}</div>
                                <div>设备/License: {{ row.max_devices_per_license || '不限' }}</div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="客户数" width="80" prop="assigned_customers" />

                    <el-table-column label="操作" width="120" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" @click="openTierDialog(row)">编辑</el-button>
                            <el-button size="small" type="danger" :disabled="row.is_default" @click="handleDeleteTier(row.id)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- ═══ 客户分配 ═══ -->
            <el-tab-pane label="客户分配" name="assign">
                <el-card class="mb-4">
                    <el-form :model="assignForm" label-width="120">
                        <el-form-item label="客户 ID" required>
                            <el-input v-model="assignForm.customer_id" placeholder="输入客户 ID" style="width: 200px" />
                        </el-form-item>
                        <el-form-item label="SLA 等级" required>
                            <el-select v-model="assignForm.sla_tier_id" filterable style="width: 200px">
                                <el-option v-for="t in tiers" :key="t.id" :label="t.name" :value="t.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="到期时间">
                            <el-date-picker v-model="assignForm.expires_at" type="datetime" placeholder="永不过期" style="width: 200px" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleAssign">分配</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <el-card title="查询客户当前 SLA">
                    <el-form :model="queryForm" label-width="120">
                        <el-form-item label="客户 ID">
                            <el-input v-model="queryForm.customer_id" placeholder="输入客户 ID" style="width: 200px" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleQueryCustomer">查询</el-button>
                        </el-form-item>
                    </el-form>

                    <div v-if="customerTier" class="p-4 bg-gray-50 rounded">
                        <el-descriptions :column="2" border>
                            <el-descriptions-item label="SLA等级">{{ customerTier.tier?.name }}</el-descriptions-item>
                            <el-descriptions-item label="标识">{{ customerTier.tier?.slug }}</el-descriptions-item>
                            <el-descriptions-item label="是否自定义">
                                <el-tag :type="customerTier.is_custom ? 'warning' : 'info'">
                                    {{ customerTier.is_custom ? '自定义' : '默认推断' }}
                                </el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="到期时间">{{ customerTier.assignment?.expires_at ? new Date(customerTier.assignment.expires_at).toLocaleString() : '永不过期' }}</el-descriptions-item>
                            <el-descriptions-item label="API 限流">{{ customerTier.tier?.api_rate_limit }}/min</el-descriptions-item>
                            <el-descriptions-item label="验证限流">{{ customerTier.tier?.verify_rate_limit }}/min</el-descriptions-item>
                            <el-descriptions-item label="响应 SLA">{{ customerTier.tier?.sla_response_hours }}h</el-descriptions-item>
                            <el-descriptions-item label="解决 SLA">{{ customerTier.tier?.sla_resolution_hours }}h</el-descriptions-item>
                        </el-descriptions>
                        <el-button v-if="customerTier.is_custom" type="danger" size="small" class="mt-3" @click="handleResetTier(customerTier.tier?.customer_id)">恢复默认</el-button>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- ═══ 审计日志 ═══ -->
            <el-tab-pane label="审计日志" name="audit">
                <el-table :data="auditLogs" v-loading="loading" stripe border>
                    <el-table-column label="客户" min-width="160">
                        <template #default="{ row }">{{ row.customer?.user?.name || `ID:${row.customer_id}` }}</template>
                    </el-table-column>
                    <el-table-column label="事件" width="160">
                        <template #default="{ row }">
                            <el-tag :type="eventTagType(row.event_type)" size="small">{{ eventLabel(row.event_type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="SLA 等级" width="120" prop="sla_tier?.name" />
                    <el-table-column label="描述" min-width="250" prop="description" />
                    <el-table-column label="时间" width="170">
                        <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>
        </el-tabs>

        <!-- SLA 等级对话框 -->
        <el-dialog v-model="tierDialogVisible" :title="editingTier ? '编辑 SLA 等级' : '创建 SLA 等级'" width="700px">
            <el-form :model="tierForm" label-width="140">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="标识" required>
                            <el-input v-model="tierForm.slug" placeholder="例如: enterprise" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="名称" required>
                            <el-input v-model="tierForm.name" placeholder="例如: 企业版" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="描述">
                    <el-input v-model="tierForm.description" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="优先级">
                            <el-input-number v-model="tierForm.priority" :min="0" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="默认等级">
                            <el-switch v-model="tierForm.is_default" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-divider>API 与验证限制</el-divider>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="API 限流/min">
                            <el-input-number v-model="tierForm.api_rate_limit" :min="1" :max="10000" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="突发请求数">
                            <el-input-number v-model="tierForm.api_burst_limit" :min="1" :max="20000" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="并发数">
                            <el-input-number v-model="tierForm.api_concurrent_limit" :min="1" :max="500" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="验证限流/min">
                            <el-input-number v-model="tierForm.verify_rate_limit" :min="1" :max="10000" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="验证超时(s)">
                            <el-input-number v-model="tierForm.verify_timeout_seconds" :min="1" :max="60" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-divider>设备限制</el-divider>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="活跃 License (0=不限)">
                            <el-input-number v-model="tierForm.max_active_licenses" :min="0" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="设备数/License">
                            <el-input-number v-model="tierForm.max_devices_per_license" :min="0" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-divider>客服 SLA</el-divider>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="响应时间(h)">
                            <el-input-number v-model="tierForm.sla_response_hours" :min="1" :max="168" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="解决时间(h)">
                            <el-input-number v-model="tierForm.sla_resolution_hours" :min="1" :max="720" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="6"><el-checkbox v-model="tierForm.support_priority_queue">优先排队</el-checkbox></el-col>
                    <el-col :span="6"><el-checkbox v-model="tierForm.support_dedicated_manager">专属经理</el-checkbox></el-col>
                    <el-col :span="6"><el-checkbox v-model="tierForm.support_phone">电话支持</el-checkbox></el-col>
                    <el-col :span="6"><el-checkbox v-model="tierForm.support_24_7">7x24 支持</el-checkbox></el-col>
                </el-row>

                <el-divider>安全合规</el-divider>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="审计保留(天)">
                            <el-input-number v-model="tierForm.audit_retention_days" :min="30" :max="3650" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="强制 MFA">
                            <el-switch v-model="tierForm.require_mfa" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="IP 白名单">
                            <el-input v-model="tierForm.allowed_ip_ranges" placeholder="CIDR,逗号分隔" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="tierDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleUpsertTier">{{ editingTier ? '更新' : '创建' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { defineComponent, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import slaApi from '@/api/sla';

export default defineComponent({
    name: 'SlaTierIndex',
    setup() {
        const router = useRouter();
        const activeTab = ref('tiers');
        const activeTabText = ref('');
        const loading = ref(false);
        const tiers = ref([]);
        const auditLogs = ref([]);

        // Tier dialog
        const tierDialogVisible = ref(false);
        const editingTier = ref(null);
        const tierForm = ref(getDefaultTierForm());

        function getDefaultTierForm() {
            return {
                slug: '', name: '', description: '', priority: 10, is_default: false,
                api_rate_limit: 60, api_burst_limit: 100, api_concurrent_limit: 10,
                verify_rate_limit: 120, verify_timeout_seconds: 5,
                max_active_licenses: 0, max_devices_per_license: 0,
                sla_response_hours: 24, sla_resolution_hours: 72,
                support_priority_queue: false, support_dedicated_manager: false,
                support_phone: false, support_24_7: false,
                audit_retention_days: 365, require_mfa: false, allowed_ip_ranges: '',
            };
        }

        // Assign
        const assignForm = ref({ customer_id: '', sla_tier_id: '', expires_at: null });
        const queryForm = ref({ customer_id: '' });
        const customerTier = ref(null);

        async function fetchTiers() {
            loading.value = true;
            try {
                const res = await slaApi.getTiers();
                tiers.value = res.data.data || [];
            } catch (e) {
                console.error('Failed to fetch tiers', e);
            } finally {
                loading.value = false;
            }
        }

        async function fetchAuditLogs() {
            try {
                const res = await slaApi.getAuditLog();
                auditLogs.value = res.data.data || [];
            } catch (e) {
                console.error('Failed to fetch audit logs', e);
            }
        }

        async function handleInitialize() {
            try {
                await ElMessageBox.confirm('将初始化4个默认 SLA 等级（企业版/专业版/标准版/免费版），是否继续？', '确认', { type: 'info' });
                const res = await slaApi.initialize();
                ElMessage.success(res.data.message || '初始化完成');
                await fetchTiers();
            } catch (e) {
                if (e !== 'cancel') ElMessage.error('初始化失败');
            }
        }

        function openTierDialog(tier = null) {
            editingTier.value = tier;
            if (tier) {
                tierForm.value = { ...getDefaultTierForm(), ...tier };
                tierForm.value.allowed_ip_ranges = tier.allowed_ip_ranges || '';
            } else {
                tierForm.value = getDefaultTierForm();
            }
            tierDialogVisible.value = true;
        }

        async function handleUpsertTier() {
            try {
                const data = { ...tierForm.value };
                if (editingTier.value) {
                    await slaApi.upsertTier(data, editingTier.value.id);
                    ElMessage.success('SLA 等级已更新');
                } else {
                    await slaApi.upsertTier(data);
                    ElMessage.success('SLA 等级已创建');
                }
                tierDialogVisible.value = false;
                await fetchTiers();
            } catch (e) {
                const msg = e.response?.data?.errors
                    ? Object.values(e.response.data.errors).flat().join('; ')
                    : '操作失败';
                ElMessage.error(msg);
            }
        }

        async function handleDeleteTier(id) {
            try {
                await ElMessageBox.confirm('确定删除该 SLA 等级？', '确认', { type: 'warning' });
                await slaApi.deleteTier(id);
                ElMessage.success('已删除');
                await fetchTiers();
            } catch (e) {
                if (e !== 'cancel') ElMessage.error('删除失败');
            }
        }

        async function handleAssign() {
            try {
                const res = await slaApi.assignTier(assignForm.value);
                ElMessage.success(res.data.message || '分配成功');
                assignForm.value = { customer_id: '', sla_tier_id: '', expires_at: null };
            } catch (e) {
                ElMessage.error('分配失败');
            }
        }

        async function handleQueryCustomer() {
            if (!queryForm.value.customer_id) {
                ElMessage.warning('请输入客户 ID');
                return;
            }
            try {
                const res = await slaApi.getCustomerTier(queryForm.value.customer_id);
                customerTier.value = res.data.data;
            } catch (e) {
                customerTier.value = null;
                ElMessage.error('查询失败');
            }
        }

        async function handleResetTier(customerId) {
            try {
                await slaApi.resetTier(customerId);
                ElMessage.success('已恢复默认 SLA 等级');
                customerTier.value = null;
            } catch (e) {
                ElMessage.error('恢复失败');
            }
        }

        function eventLabel(type) {
            return {
                tier_assigned: '分配等级',
                tier_changed: '变更等级',
                tier_expired: '等级过期',
                limit_exceeded: '超出限流',
                sla_breached: 'SLA 违规',
            }[type] || type;
        }

        function eventTagType(type) {
            return {
                tier_assigned: 'success',
                tier_changed: 'warning',
                tier_expired: 'info',
                limit_exceeded: 'danger',
                sla_breached: 'danger',
            }[type] || 'info';
        }

        onMounted(() => {
            fetchTiers();
            fetchAuditLogs();
        });

        return {
            router, activeTab, activeTabText, loading, tiers, auditLogs,
            tierDialogVisible, editingTier, tierForm,
            assignForm, queryForm, customerTier,
            fetchTiers, fetchAuditLogs,
            handleInitialize, openTierDialog, handleUpsertTier, handleDeleteTier,
            handleAssign, handleQueryCustomer, handleResetTier,
            eventLabel, eventTagType,
        };
    },
});
</script>

<style scoped>
.sla-manager {
    padding: 20px;
}
</style>
