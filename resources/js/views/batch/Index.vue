<template>
    <div class="batch-manager">
        <el-page-header :content="t('batch_page.title') + activeTabText" @back="router.push('/')" />

        <el-tabs v-model="activeTab" class="mt-4">
            <!-- 执行批量操作 -->
            <el-tab-pane :label="t('batch_page.tabs.execute')" name="execute">
                <el-card class="mb-4">
                    <el-form :model="form" label-width="140">
                        <el-form-item :label="t('batch_page.labels.operation_type')" required>
                            <el-select v-model="form.type" filterable style="width: 280px" @change="onTypeChange">
                                <el-option-group v-for="group in groupedTypes" :key="group.label" :label="group.label">
                                    <el-option v-for="t in group.types" :key="t.type" :label="t.label" :value="t.type" />
                                </el-option-group>
                            </el-select>
                        </el-form-item>

                        <el-form-item :label="t('batch_page.labels.target_model')" required>
                            <el-select v-model="form.target_model" filterable style="width: 280px">
                                <el-option
                                    v-for="(label, key) in targetModels"
                                    :key="key"
                                    :label="label"
                                    :value="key"
                                />
                            </el-select>
                        </el-form-item>

                        <el-form-item :label="t('batch_page.labels.selection_mode')">
                            <el-radio-group v-model="selectionMode">
                                <el-radio label="ids">{{ t('batch_page.labels.selection_ids') }}</el-radio>
                                <el-radio label="filters">{{ t('batch_page.labels.selection_filters') }}</el-radio>
                            </el-radio-group>
                        </el-form-item>

                        <el-form-item v-if="selectionMode === 'ids'" :label="t('batch_page.labels.target_ids')">
                            <el-input v-model="idsInput" type="textarea" :rows="3" :placeholder="t('batch_page.labels.target_ids_ph')" style="width: 400px" />
                        </el-form-item>

                        <template v-if="selectionMode === 'filters'">
                            <el-form-item :label="t('licenses_page.status')">
                                <el-input v-model="form.filters.status" :placeholder="t('batch_page.labels.status_ph')" style="width: 200px" />
                            </el-form-item>
                            <el-form-item :label="t('batch_page.labels.product_id')">
                                <el-input v-model="form.filters.product_id" :placeholder="t('batch_page.labels.product_id_ph')" style="width: 200px" />
                            </el-form-item>
                        </template>

                        <el-form-item :label="t('batch_page.labels.operation_params')">
                            <div class="flex flex-col gap-2">
                                <div v-if="form.type === 'batch_renew'">
                                    <span class="text-sm mr-2">{{ t('batch_page.labels.renew_days') }}</span>
                                    <el-input-number v-model="form.params.days" :min="1" :max="3650" :step="30" />
                                </div>
                                <div v-if="form.type === 'batch_change_status'">
                                    <span class="text-sm mr-2">{{ t('batch_page.labels.target_status') }}</span>
                                    <el-input v-model="form.params.status" :placeholder="t('batch_page.labels.target_status_ph')" style="width: 160px" />
                                </div>
                                <div v-if="form.type === 'batch_change_plan'">
                                    <span class="text-sm mr-2">{{ t('batch_page.labels.new_plan') }}</span>
                                    <el-input v-model="form.params.plan" :placeholder="t('batch_page.labels.new_plan_ph')" style="width: 160px" />
                                </div>
                            </div>
                        </el-form-item>

                        <el-form-item>
                            <el-button type="primary" @click="handlePreview" :loading="previewLoading">{{ t('batch_page.preview_btn') }}</el-button>
                            <el-button type="success" @click="handleExecute" :loading="executing" :disabled="!previewResult">{{ t('batch_page.execute_btn') }}</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <!-- 预览结果 -->
                <el-card v-if="previewResult" :header="t('batch_page.preview.title')" class="mb-4">
                    <el-alert
                        :title="t('batch_page.preview.affected_count', { count: previewResult.total_count })"
                        :type="previewResult.total_count > 0 ? 'warning' : 'info'"
                        show-icon
                        class="mb-3"
                    />

                    <el-table :data="previewResult.sample" stripe border v-if="previewResult.sample?.length">
                        <el-table-column prop="id" label="ID" width="80" />
                        <el-table-column prop="license_key" :label="t('licenses_page.license_key')" min-width="240" v-if="form.target_model === 'licenses'" />
                        <el-table-column prop="name" :label="t('batch_page.cols.name')" min-width="180" v-if="form.target_model === 'customers'" />
                        <el-table-column prop="status" :label="t('licenses_page.status')" width="100" />
                        <el-table-column prop="plan" :label="t('batch_page.cols.plan')" width="120" v-if="form.target_model === 'subscriptions'" />
                        <el-table-column :label="t('batch_page.cols.expires')" width="180" v-if="['licenses', 'subscriptions'].includes(form.target_model)">
                            <template #default="{ row }">{{ row.expires_at ? new Date(row.expires_at).toLocaleDateString() : '-' }}</template>
                        </el-table-column>
                    </el-table>
                    <p v-if="previewResult.has_more" class="text-sm text-gray-400 mt-2">{{ t('batch_page.preview.more_records') }}</p>
                </el-card>
            </el-tab-pane>

            <!-- 操作历史 -->
            <el-tab-pane :label="t('batch_page.tabs.history')" name="history">
                <el-table :data="jobs" v-loading="loading" stripe border>
                    <el-table-column label="ID" width="70" prop="id" />
                    <el-table-column :label="t('batch_page.cols.operation_type')" width="120">
                        <template #default="{ row }">
                            <el-tag size="small">{{ typeLabel(row.type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('batch_page.cols.target')" width="100" prop="target_model" />
                    <el-table-column :label="t('batch_page.cols.progress')" width="180">
                        <template #default="{ row }">
                            <div class="flex items-center gap-2">
                                <el-progress :percentage="row.total_count > 0 ? Math.round(row.success_count / row.total_count * 100) : 0" :width="40" type="circle" :stroke-width="6" />
                                <span class="text-sm">{{ row.success_count }}/{{ row.total_count }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('licenses_page.status')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)">{{ statusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('batch_page.cols.executor')" width="120" prop="user?.name" />
                    <el-table-column :label="t('batch_page.cols.executed_at')" width="170">
                        <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
                    </el-table-column>
                    <el-table-column :label="t('licenses_page.col_actions')" width="160" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" @click="viewDetail(row.id)">{{ t('batch_page.detail') }}</el-button>
                            <el-button size="small" type="warning" :disabled="!row.is_reversible" @click="handleUndo(row.id)">{{ t('batch_page.undo') }}</el-button>
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

        <!-- 执行确认对话框 -->
        <el-dialog v-model="confirmVisible" :title="t('batch_page.confirm.title')" width="500px">
            <el-alert :title="confirmMessage" type="warning" show-icon class="mb-3" />
            <p class="text-sm text-gray-600">{{ t('batch_page.confirm.hint') }}</p>
            <template #footer>
                <el-button @click="confirmVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="doExecute" :loading="executing">{{ t('batch_page.confirm.execute_btn') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import batchApi from '@/api/batch';

export default defineComponent({
    name: 'BatchIndex',
    setup() {
        const router = useRouter();
        const { t } = useI18n();

        const activeTab = ref('execute');
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

        const jobs = ref([]);
        const page = ref(1);
        const perPage = ref(20);
        const total = ref(0);

        const activeTabText = computed(() =>
            activeTab.value === 'history'
                ? t('batch_page.tab_history_suffix')
                : t('batch_page.tab_execute_suffix')
        );

        const targetModels = computed(() => ({
            licenses: t('batch_page.target_models.licenses'),
            subscriptions: t('batch_page.target_models.subscriptions'),
            customers: t('batch_page.target_models.customers'),
            invoices: t('batch_page.target_models.invoices'),
            tickets: t('batch_page.target_models.tickets'),
        }));

        const typeLabels = computed(() => ({
            batch_activate: t('licenses_page.batch_activate'),
            batch_renew: t('licenses_page.batch_renew'),
            batch_export: t('batch_page.types.batch_export'),
            batch_suspend: t('licenses_page.batch_suspend'),
            batch_revoke: t('licenses_page.batch_revoke'),
            batch_delete: t('licenses_page.batch_delete'),
            batch_change_plan: t('batch_page.types.batch_change_plan'),
            batch_change_status: t('batch_page.types.batch_change_status'),
        }));

        const statusLabels = computed(() => ({
            pending: t('batch_page.status.pending'),
            processing: t('batch_page.status.processing'),
            completed: t('data_import_page.status.completed'),
            failed: t('data_import_page.status.failed'),
            cancelled: t('data_import_page.status.cancelled'),
        }));

        const groupedTypes = computed(() => {
            const groups = {};
            (operationTypes.value || []).forEach(op => {
                const label = op.targets?.join('/') || t('batch_page.target_general');
                if (!groups[label]) {
                    groups[label] = {
                        label: t('batch_page.target_prefix', { label }),
                        types: [],
                    };
                }
                groups[label].types.push(op);
            });
            return Object.values(groups);
        });

        function onTypeChange() {
            const found = (operationTypes.value || []).find(op => op.type === form.value.type);
            if (found && found.targets?.length === 1) {
                form.value.target_model = found.targets[0];
            }
        }

        function typeLabel(type) {
            return typeLabels.value[type] || type;
        }

        function statusLabel(status) {
            return statusLabels.value[status] || status;
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
                ElMessage.error(t('batch_page.messages.preview_fail'));
            } finally {
                previewLoading.value = false;
            }
        }

        async function handleExecute() {
            if (!previewResult.value || previewResult.value.total_count === 0) {
                ElMessage.warning(t('batch_page.messages.preview_first'));
                return;
            }
            confirmMessage.value = t('batch_page.confirm.message', {
                type: typeLabel(form.value.type),
                count: previewResult.value.total_count,
            });
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
                ElMessage.success(res.data.message || t('batch_page.messages.execute_ok'));
                confirmVisible.value = false;
                previewResult.value = null;
                await fetchJobs();
            } catch (e) {
                const msg = e.response?.data?.errors
                    ? Object.values(e.response.data.errors).flat().join('; ')
                    : t('messages.failed');
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
                const itemsHtml = job.items
                    ? `<p>${t('batch_page.messages.detail_items')} ${job.items.map(i =>
                        t('batch_page.messages.detail_item_entry', {
                            id: i.targetable_id,
                            status: i.status,
                            error: i.error_message ? ': ' + i.error_message : '',
                        })
                    ).join('<br>')}</p>`
                    : '';
                ElMessageBox.alert(
                    `<div>
                        <p>${t('batch_page.messages.detail_type', { value: typeLabel(job.type) })}</p>
                        <p>${t('batch_page.messages.detail_target', { value: job.target_model })}</p>
                        <p>${t('batch_page.messages.detail_status', { value: statusLabel(job.status) })}</p>
                        <p>${t('batch_page.messages.detail_success_fail', { success: job.success_count, fail: job.fail_count })}</p>
                        <p>${t('batch_page.messages.detail_error_summary', { value: job.error_summary || t('batch_page.messages.detail_error_none') })}</p>
                        ${itemsHtml}
                    </div>`,
                    {
                        title: t('batch_page.messages.detail_title'),
                        dangerouslyUseHTMLString: true,
                        width: '600px',
                    }
                );
            } catch (e) {
                ElMessage.error(t('batch_page.messages.detail_fail'));
            }
        }

        async function handleUndo(id) {
            try {
                await ElMessageBox.confirm(
                    t('batch_page.messages.undo_confirm'),
                    t('actions.confirm'),
                    { type: 'warning' }
                );
                const res = await batchApi.undo(id);
                ElMessage.success(res.data.message || t('batch_page.messages.undo_ok'));
                await fetchJobs();
            } catch (e) {
                if (e !== 'cancel') ElMessage.error(t('batch_page.messages.undo_fail'));
            }
        }

        onMounted(() => {
            fetchOperationTypes();
            fetchJobs();
        });

        return {
            t,
            router, activeTab, activeTabText, loading, previewLoading, executing,
            form, selectionMode, idsInput, operationTypes, groupedTypes, targetModels,
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
