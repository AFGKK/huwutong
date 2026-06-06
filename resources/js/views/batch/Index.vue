<template>
    <div class="batch-manager">
        <el-page-header :content="'批量操作工具 ' + activeTabText" @back="router.push('/')" />

        <el-tabs v-model="activeTab" class="mt-4">
            <!-- ═══ 执行批量操作 ═══ -->
            <el-tab-pane label="执行批量操作" name="execute">
                <el-card class="mb-4">
                    <el-form :model="form" label-width="140">
                        <el-form-item label="操作类型" required>
                            <el-select v-model="form.type" filterable style="width: 280px" @change="onTypeChange">
                                <el-option-group v-for="group in groupedTypes" :key="group.label" :label="group.label">
                                    <el-option v-for="t in group.types" :key="t.type" :label="t.label" :value="t.type" />
                                </el-option-group>
                            </el-select>
                        </el-form-item>

                        <el-form-item label="目标模型" required>
                            <el-select v-model="form.target_model" filterable style="width: 280px">
                                <el-option label="License" value="licenses" />
                                <el-option label="订阅" value="subscriptions" />
                                <el-option label="客户" value="customers" />
                                <el-option label="账单" value="invoices" />
                                <el-option label="工单" value="tickets" />
                            </el-select>
                        </el-form-item>

                        <el-form-item label="选择方式">
                            <el-radio-group v-model="selectionMode">
                                <el-radio label="ids">按 ID 列表</el-radio>
                                <el-radio label="filters">按筛选条件</el-radio>
                            </el-radio-group>
                        </el-form-item>

                        <el-form-item v-if="selectionMode === 'ids'" label="目标 ID">
                            <el-input v-model="idsInput" type="textarea" :rows="3" placeholder="输入 ID，以逗号或换行分隔" style="width: 400px" />
                        </el-form-item>

                        <template v-if="selectionMode === 'filters'">
                            <el-form-item label="状态">
                                <el-input v-model="form.filters.status" placeholder="例如: active" style="width: 200px" />
                            </el-form-item>
                            <el-form-item label="产品 ID">
                                <el-input v-model="form.filters.product_id" placeholder="可留空" style="width: 200px" />
                            </el-form-item>
                        </template>

                        <el-form-item label="操作参数">
                            <div class="flex flex-col gap-2">
                                <div v-if="form.type === 'batch_renew'">
                                    <span class="text-sm mr-2">续期天数:</span>
                                    <el-input-number v-model="form.params.days" :min="1" :max="3650" :step="30" />
                                </div>
                                <div v-if="form.type === 'batch_change_status'">
                                    <span class="text-sm mr-2">目标状态:</span>
                                    <el-input v-model="form.params.status" placeholder="例如: suspended" style="width: 160px" />
                                </div>
                                <div v-if="form.type === 'batch_change_plan'">
                                    <span class="text-sm mr-2">新计划:</span>
                                    <el-input v-model="form.params.plan" placeholder="计划 slug" style="width: 160px" />
                                </div>
                            </div>
                        </el-form-item>

                        <el-form-item>
                            <el-button type="primary" @click="handlePreview" :loading="previewLoading">预览影响范围</el-button>
                            <el-button type="success" @click="handleExecute" :loading="executing" :disabled="!previewResult">执行操作</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <!-- 预览结果 -->
                <el-card v-if="previewResult" title="预览结果" class="mb-4">
                    <el-alert
                        :title="`共影响 ${previewResult.total_count} 条记录`"
                        :type="previewResult.total_count > 0 ? 'warning' : 'info'"
                        show-icon
                        class="mb-3"
                    />

                    <el-table :data="previewResult.sample" stripe border v-if="previewResult.sample?.length">
                        <el-table-column prop="id" label="ID" width="80" />
                        <el-table-column prop="license_key" label="License Key" min-width="240" v-if="form.target_model === 'licenses'" />
                        <el-table-column prop="name" label="名称" min-width="180" v-if="form.target_model === 'customers'" />
                        <el-table-column prop="status" label="状态" width="100" />
                        <el-table-column prop="plan" label="计划" width="120" v-if="form.target_model === 'subscriptions'" />
                        <el-table-column label="到期" width="180" v-if="['licenses', 'subscriptions'].includes(form.target_model)">
                            <template #default="{ row }">{{ row.expires_at ? new Date(row.expires_at).toLocaleDateString() : '-' }}</template>
                        </el-table-column>
                    </el-table>
                    <p v-if="previewResult.has_more" class="text-sm text-gray-400 mt-2">... 还有更多记录未显示</p>
                </el-card>
            </el-tab-pane>

            <!-- ═══ 操作历史 ═══ -->
            <el-tab-pane label="操作历史" name="history">
                <el-table :data="jobs" v-loading="loading" stripe border>
                    <el-table-column label="ID" width="70" prop="id" />
                    <el-table-column label="操作类型" width="120">
                        <template #default="{ row }">
                            <el-tag size="small">{{ typeLabel(row.type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="目标" width="100" prop="target_model" />
                    <el-table-column label="进度" width="180">
                        <template #default="{ row }">
                            <div class="flex items-center gap-2">
                                <el-progress :percentage="row.total_count > 0 ? Math.round(row.success_count / row.total_count * 100) : 0" :width="40" type="circle" :stroke-width="6" />
                                <span class="text-sm">{{ row.success_count }}/{{ row.total_count }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)">{{ statusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="执行人" width="120" prop="user?.name" />
                    <el-table-column label="执行时间" width="170">
                        <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="160" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" @click="viewDetail(row.id)">详情</el-button>
                            <el-button size="small" type="warning" :disabled="!row.is_reversible" @click="handleUndo(row.id)">撤销</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="flex justify-center mt-4">
                    <el-pagination
                        v-model:current-page="page"
                        :page-size="perPage"
                        :total="total"
                        layout="prev, pager, next"
                        @current-change="fetchJobs"
                    />
                </div>
            </el-tab-pane>
        </el-tabs>

        <!-- ═══ 执行确认对话框 ═══ -->
        <el-dialog v-model="confirmVisible" title="确认执行批量操作" width="500px">
            <el-alert :title="confirmMessage" type="warning" show-icon class="mb-3" />
            <p class="text-sm text-gray-600">操作不可轻易撤销，请确认已预览影响范围。</p>
            <template #footer>
                <el-button @click="confirmVisible = false">取消</el-button>
                <el-button type="primary" @click="doExecute" :loading="executing">确认执行</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import batchApi from '@/api/batch';

export default defineComponent({
    name: 'BatchIndex',
    setup() {
        const router = useRouter();
        const activeTab = ref('execute');
        const activeTabText = ref('- 执行操作');
        const loading = ref(false);
        const previewLoading = ref(false);
        const executing = ref(false);
        const operationTypes = ref([]);
        const previewResult = ref(null);

        const selectionMode = ref('filters');
        const idsInput = ref('');

        const form = ref({
            type: '',
            target_model: 'licenses',
            filters: { status: 'active' },
            params: { days: 365 },
        });

        const confirmVisible = ref(false);
        const confirmMessage = ref('');

        // History
        const jobs = ref([]);
        const page = ref(1);
        const perPage = ref(20);
        const total = ref(0);

        const groupedTypes = computed(() => {
            const groups = {};
            (operationTypes.value || []).forEach(t => {
                const label = t.targets?.join('/') || '通用';
                if (!groups[label]) groups[label] = { label: `目标: ${label}`, types: [] };
                groups[label].types.push(t);
            });
            return Object.values(groups);
        });

        function onTypeChange() {
            const found = (operationTypes.value || []).find(t => t.type === form.value.type);
            if (found && found.targets?.length === 1) {
                form.value.target_model = found.targets[0];
            }
        }

        function typeLabel(type) {
            const labels = {
                batch_activate: '批量激活',
                batch_renew: '批量续期',
                batch_export: '批量导出',
                batch_suspend: '批量挂起',
                batch_revoke: '批量吊销',
                batch_delete: '批量删除',
                batch_change_plan: '变更计划',
                batch_change_status: '变更状态',
            };
            return labels[type] || type;
        }

        function statusLabel(status) {
            return { pending: '待处理', processing: '处理中', completed: '已完成', failed: '失败', cancelled: '已取消' }[status] || status;
        }

        function statusTagType(status) {
            return { pending: 'info', processing: 'warning', completed: 'success', failed: 'danger', cancelled: 'info' }[status] || 'info';
        }

        async function fetchOperationTypes() {
            try {
                const res = await batchApi.getOperationTypes();
                operationTypes.value = res.data.data || [];
            } catch (e) {
                console.error('Failed to fetch types', e);
            }
        }

        async function handlePreview() {
            previewLoading.value = true;
            previewResult.value = null;
            try {
                const payload = {
                    type: form.value.type,
                    target_model: form.value.target_model,
                    params: form.value.params,
                };

                if (selectionMode.value === 'ids') {
                    payload.ids = idsInput.value.split(/[,;\n\r]+/).map(s => parseInt(s.trim())).filter(n => !isNaN(n));
                } else {
                    payload.filters = Object.fromEntries(
                        Object.entries(form.value.filters).filter(([_, v]) => v !== '' && v !== null)
                    );
                }

                const res = await batchApi.preview(payload);
                previewResult.value = res.data.data;
            } catch (e) {
                ElMessage.error('预览失败');
            } finally {
                previewLoading.value = false;
            }
        }

        async function handleExecute() {
            if (!previewResult.value || previewResult.value.total_count === 0) {
                ElMessage.warning('请先预览');
                return;
            }
            confirmMessage.value = `将执行 ${typeLabel(form.value.type)}，影响 ${previewResult.value.total_count} 条记录。`;
            confirmVisible.value = true;
        }

        async function doExecute() {
            executing.value = true;
            try {
                const payload = {
                    type: form.value.type,
                    target_model: form.value.target_model,
                    params: form.value.params,
                };

                if (selectionMode.value === 'ids') {
                    payload.ids = idsInput.value.split(/[,;\n\r]+/).map(s => parseInt(s.trim())).filter(n => !isNaN(n));
                } else {
                    payload.filters = Object.fromEntries(
                        Object.entries(form.value.filters).filter(([_, v]) => v !== '' && v !== null)
                    );
                }

                const res = await batchApi.execute(payload);
                ElMessage.success(res.data.message || '批量操作完成');
                confirmVisible.value = false;
                previewResult.value = null;
                await fetchJobs();
            } catch (e) {
                const msg = e.response?.data?.errors
                    ? Object.values(e.response.data.errors).flat().join('; ')
                    : '批量操作失败';
                ElMessage.error(msg);
            } finally {
                executing.value = false;
            }
        }

        async function fetchJobs() {
            loading.value = true;
            try {
                const res = await batchApi.getJobs({ page: page.value, per_page: perPage.value });
                jobs.value = res.data.data || [];
                total.value = res.data.total || 0;
            } catch (e) {
                console.error('Failed to fetch jobs', e);
            } finally {
                loading.value = false;
            }
        }

        async function viewDetail(id) {
            try {
                const res = await batchApi.getJob(id);
                const job = res.data.data;
                ElMessageBox.alert(
                    `<div>
                        <p>类型: ${typeLabel(job.type)}</p>
                        <p>目标: ${job.target_model}</p>
                        <p>状态: ${statusLabel(job.status)}</p>
                        <p>成功: ${job.success_count} / 失败: ${job.fail_count}</p>
                        <p>错误摘要: ${job.error_summary || '无'}</p>
                        ${job.items ? `<p>子项: ${job.items.map(i => `#${i.targetable_id} [${i.status}]${i.error_message ? ': ' + i.error_message : ''}`).join('<br>')}</p>` : ''}
                    </div>`,
                    { title: '操作详情', dangerouslyUseHTMLString: true, width: '600px' }
                );
            } catch (e) {
                ElMessage.error('获取详情失败');
            }
        }

        async function handleUndo(id) {
            try {
                await ElMessageBox.confirm('确定撤销该操作？撤销将恢复快照记录的所有字段。', '确认', { type: 'warning' });
                const res = await batchApi.undo(id);
                ElMessage.success(res.data.message || '撤销完成');
                await fetchJobs();
            } catch (e) {
                if (e !== 'cancel') ElMessage.error('撤销失败');
            }
        }

        onMounted(() => {
            fetchOperationTypes();
            fetchJobs();
        });

        return {
            router, activeTab, activeTabText, loading, previewLoading, executing,
            form, selectionMode, idsInput, operationTypes, groupedTypes,
            previewResult, confirmVisible, confirmMessage,
            jobs, page, perPage, total,
            onTypeChange, typeLabel, statusLabel, statusTagType,
            handlePreview, handleExecute, doExecute,
            fetchJobs, viewDetail, handleUndo,
        };
    },
});
</script>

<style scoped>
.batch-manager {
    padding: 20px;
}
</style>
