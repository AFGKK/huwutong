<template>
    <div class="licenses-page">
        <!-- 统计仪表盘 -->
        <el-row :gutter="16" class="mb-4 stat-cards-row">
            <el-col :xs="12" :md="6">
                <el-card shadow="hover" class="stat-card" @click="filters.status = ''; fetchData(1)">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">{{ t('licenses_page.stat_all') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :md="6">
                <el-card shadow="hover" class="stat-card stat-active" @click="filters.status = 'active'; fetchData(1)">
                    <div class="stat-value">{{ stats.active }}</div>
                    <div class="stat-label">{{ t('licenses_page.stat_active') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :md="6">
                <el-card shadow="hover" class="stat-card stat-warning" @click="filters.status = 'active'; fetchData(1)">
                    <div class="stat-value">{{ stats.expiring_soon }}</div>
                    <div class="stat-label">{{ t('licenses_page.stat_expiring_soon') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :md="6">
                <el-card shadow="hover" class="stat-card stat-danger" @click="filters.status = 'expired'; fetchData(1)">
                    <div class="stat-value">{{ stats.expired }}</div>
                    <div class="stat-label">{{ t('licenses_page.stat_expired') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <div class="page-header">
            <h2>{{ t('licenses_page.title') }}</h2>
            <div class="header-actions">
                <el-dropdown @command="handleBulkCmd" trigger="click">
                    <el-button size="small"><el-icon><Download /></el-icon> {{ t('actions.export') }}</el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="export-csv">{{ t('licenses_page.export_csv') }}</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
                <el-button size="small" @click="showImport = true"><el-icon><Upload /></el-icon> {{ t('actions.import') }}</el-button>
                <el-button size="small" @click="showBatchCreate = true"><el-icon><DocumentAdd /></el-icon> {{ t('licenses_page.batch') }}</el-button>
                <el-button type="primary" size="small" @click="openCreate">
                    <el-icon><Plus /></el-icon> {{ t('actions.create') }}
                </el-button>
            </div>
        </div>

        <!-- 快速筛选状态标签 -->
        <div class="quick-filters mb-4">
            <el-tag
                :type="!filters.status ? 'primary' : 'info'"
                :effect="!filters.status ? 'dark' : 'plain'"
                class="clickable-tag"
                @click="filters.status = ''; fetchData(1)"
            >
                {{ t('licenses_page.all') }}
            </el-tag>
            <el-tag
                v-for="s in quickFilterOptions"
                :key="s.value"
                :type="filters.status === s.value ? s.type : 'info'"
                :effect="filters.status === s.value ? 'dark' : 'plain'"
                class="clickable-tag"
                @click="filters.status = s.value; fetchData(1)"
            >
                {{ s.label }}
            </el-tag>
        </div>

        <!-- 搜索/筛选栏 -->
        <el-card class="mb-4">
            <el-form :model="filters" inline label-width="90px" @keyup.enter="fetchData">
                <el-row :gutter="16">
                    <el-col :span="6">
                        <el-form-item :label="t('licenses_page.license_key')">
                            <el-input v-model="filters.license_key" :placeholder="t('licenses_page.search_key_ph')" clearable />
                        </el-form-item>
                    </el-col>
                    <el-col :span="4">
                        <el-form-item :label="t('licenses_page.status')">
                            <el-select v-model="filters.status" :placeholder="t('licenses_page.all')" clearable style="width: 120px">
                                <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="4">
                        <el-form-item :label="t('licenses_page.type')">
                            <el-select v-model="filters.type" :placeholder="t('licenses_page.all')" clearable style="width: 120px">
                                <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="4">
                        <el-form-item :label="t('licenses_page.product')">
                            <el-select v-model="filters.product_id" :placeholder="t('licenses_page.all')" clearable filterable style="width: 140px">
                                <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item :label="t('licenses_page.customer')">
                            <el-select v-model="filters.customer_id" :placeholder="t('licenses_page.all')" clearable filterable style="width: 160px">
                                <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item :label="t('licenses_page.created_at')">
                            <el-date-picker
                                v-model="filters.date_range"
                                type="datetimerange"
                                :range-separator="t('licenses_page.date_range_sep')"
                                :start-placeholder="t('licenses_page.date_start')"
                                :end-placeholder="t('licenses_page.date_end')"
                                value-format="YYYY-MM-DD HH:mm:ss"
                                style="width: 260px"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item label=" ">
                            <el-button type="primary" @click="fetchData">{{ t('actions.search') }}</el-button>
                            <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <div class="filter-bar-footer">
                <SavedSearchBar
                    page="licenses"
                    :current-filters="filters"
                    @apply="applySavedFilters"
                />
            </div>
        </el-card>

        <!-- 批量操作栏 -->
        <div class="batch-bar" v-if="selectedIds.length > 0">
            <span class="selected-info">{{ t('licenses_page.selected_count', { n: selectedIds.length }) }}</span>

            <el-dropdown trigger="click" @command="handleBatchActionCmd">
                <el-button size="small">
                    {{ t('licenses_page.status_change') }} <el-icon><ArrowDown /></el-icon>
                </el-button>
                <template #dropdown>
                    <el-dropdown-menu>
                        <el-dropdown-item command="activate">{{ t('licenses_page.batch_activate') }}</el-dropdown-item>
                        <el-dropdown-item command="deactivate">{{ t('licenses_page.batch_deactivate') }}</el-dropdown-item>
                        <el-dropdown-item command="suspend">{{ t('licenses_page.batch_suspend') }}</el-dropdown-item>
                        <el-dropdown-item command="restore">{{ t('licenses_page.batch_restore') }}</el-dropdown-item>
                        <el-dropdown-item command="freeze">{{ t('licenses_page.batch_freeze') }}</el-dropdown-item>
                        <el-dropdown-item command="revoke" divided>{{ t('licenses_page.batch_revoke') }}</el-dropdown-item>
                        <el-dropdown-item command="blacklist">{{ t('licenses_page.batch_blacklist') }}</el-dropdown-item>
                        <el-dropdown-item command="refund">{{ t('licenses_page.batch_refund') }}</el-dropdown-item>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>

            <el-button size="small" @click="openBatchEditDialog('renew')">{{ t('licenses_page.batch_renew') }}</el-button>
            <el-button size="small" @click="openBatchEditDialog('update_seats')">{{ t('licenses_page.batch_update_seats') }}</el-button>
            <el-button size="small" @click="openBatchEditDialog('update_metadata')">{{ t('licenses_page.batch_update_metadata') }}</el-button>
            <el-button size="small" @click="openBatchEditDialog('add_tags')">{{ t('licenses_page.batch_add_tags') }}</el-button>
            <el-button size="small" @click="openBatchEditDialog('transfer')" v-if="isSuperAdmin">{{ t('licenses_page.batch_transfer') }}</el-button>
            <el-button size="small" type="danger" plain @click="confirmBatchDelete">{{ t('licenses_page.batch_delete') }}</el-button>
            <el-button text size="small" @click="selectedIds = []">{{ t('licenses_page.deselect') }}</el-button>
        </div>

        <!-- 表格 -->
        <el-card>
            <div class="table-scroll-wrap">
            <el-table
                :data="licenses"
                v-loading="loading"
                stripe
                style="width: 100%"
                @selection-change="handleSelectionChange"
                @sort-change="handleSortChange"
                :default-sort="{ prop: 'created_at', order: 'descending' }"
            >
                <el-table-column type="selection" width="40" />
                <el-table-column prop="license_key" :label="t('licenses_page.license_key')" min-width="200" sortable="custom">
                    <template #default="{ row }">
                        <div class="key-cell">
                            <el-link type="primary" :underline="'never'" @click="$router.push(`/licenses/${row.id}`)">
                                <code class="key-text">{{ row.license_key }}</code>
                            </el-link>
                            <el-button
                                text
                                size="small"
                                class="copy-btn"
                                @click.stop="copyLicenseKey(row.license_key)"
                                :title="t('licenses_page.copy_key')"
                            >
                                <el-icon><CopyDocument /></el-icon>
                            </el-button>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="product?.name" :label="t('licenses_page.col_product')" width="120" :formatter="(r) => r.product?.name || '-'" />
                <el-table-column prop="customer?.name" :label="t('licenses_page.col_customer')" width="120" :formatter="(r) => r.customer?.name || '-'" />
                <el-table-column prop="type" :label="t('licenses_page.col_type')" width="90">
                    <template #default="{ row }">
                        <el-tag v-if="row.type === 'trial'" type="warning" size="small">{{ t('licenses_page.type_trial') }}</el-tag>
                        <el-tag v-else-if="row.type === 'enterprise'" type="success" size="small">{{ t('licenses_page.type_enterprise') }}</el-tag>
                        <el-tag v-else-if="row.type === 'development'" size="small">{{ t('licenses_page.type_development') }}</el-tag>
                        <span v-else>{{ t('licenses_page.type_standard') }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" :label="t('licenses_page.col_status')" width="100" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="max_devices" :label="t('licenses_page.col_max_devices')" width="90" align="center" />
                <el-table-column prop="expires_at" :label="t('licenses_page.col_expires_at')" width="170" sortable="custom">
                    <template #default="{ row }">
                        <el-tooltip :content="expiryTooltip(row)" placement="top" :disabled="!row.expires_at">
                            <span :class="expiryClass(row)">{{ row.expires_at || t('licenses_page.permanent') }}</span>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('licenses_page.col_created_at')" width="170" sortable="custom" />
                <el-table-column :label="t('licenses_page.col_actions')" width="310" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" text type="primary" @click="openEdit(row)">{{ t('actions.edit') }}</el-button>
                        <el-dropdown trigger="click" @command="(cmd) => handleAction(cmd, row)">
                            <el-button size="small">
                                {{ t('licenses_page.status_actions') }} <el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="detail">
                                        <el-icon><View /></el-icon>{{ t('licenses_page.view_detail') }}
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'active'" command="suspend" divided>
                                        <el-icon><VideoPause /></el-icon>{{ t('licenses_page.suspend') }}
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'active'" command="freeze">
                                        <el-icon><ColdDrink /></el-icon>{{ t('licenses_page.freeze') }}
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'suspended' || row.status === 'frozen'" command="restore">
                                        <el-icon><Refresh /></el-icon>{{ t('licenses_page.restore') }}
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status !== 'revoked' && row.status !== 'blacklisted'" command="revoke" divided>
                                        <el-icon><Remove /></el-icon>{{ t('licenses_page.revoke') }}
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status !== 'blacklisted'" command="blacklist">
                                        <el-icon><WarningFilled /></el-icon>{{ t('licenses_page.blacklist') }}
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status !== 'refunded'" command="refund" divided>
                                        <el-icon><Money /></el-icon>{{ t('licenses_page.refund') }}
                                    </el-dropdown-item>
                                    <el-dropdown-item command="seat-pool" divided>
                                        <el-icon><Grid /></el-icon>{{ t('licenses_page.seat_pool') }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <el-button size="small" text type="danger" @click="handleAction('destroy', row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            </div><!-- table-scroll-wrap -->

            <div class="pagination-wrap" v-if="meta">
                <el-pagination
                    v-model:current-page="meta.current_page"
                    :page-size="meta.per_page"
                    :total="meta.total"
                    :page-sizes="[10, 20, 50, 100]"
                    layout="total, sizes, prev, pager, next, jumper"
                    @current-change="fetchData"
                    @size-change="fetchData(1)"
                />
            </div>
        </el-card>

        <!-- 创建 License 对话框 -->
        <el-dialog v-model="showCreate" :title="t('licenses_page.create_title')" width="560px">
            <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="100px">
                <el-form-item :label="t('licenses_page.select_template')" v-if="licenseTemplates.length > 0">
                    <el-select
                        v-model="selectedTemplateId"
                        :placeholder="t('licenses_page.template_ph')"
                        filterable
                        clearable
                        style="width: 100%"
                        @change="applyTemplate"
                    >
                        <el-option
                            v-for="tpl in licenseTemplates"
                            :key="tpl.id"
                            :label="tpl.name"
                            :value="tpl.id"
                        >
                            <span>{{ tpl.name }}</span>
                            <span class="template-option-desc">{{ tpl.description || typeLabel(tpl.type) }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-divider v-if="licenseTemplates.length > 0" />
                <el-form-item :label="t('licenses_page.product')" prop="product_id">
                    <el-select v-model="createForm.product_id" :placeholder="t('licenses_page.select_product')" filterable style="width: 100%">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.customer')" prop="customer_id">
                    <el-select v-model="createForm.customer_id" :placeholder="t('licenses_page.select_customer')" filterable style="width: 100%">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.type')" prop="type">
                    <el-select v-model="createForm.type" style="width: 100%">
                        <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.expires_at')" prop="expires_at">
                    <el-date-picker
                        v-model="createForm.expires_at"
                        type="datetime"
                        :placeholder="t('licenses_page.expires_ph')"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item :label="t('licenses_page.max_devices')">
                    <el-input-number v-model="createForm.max_devices" :min="1" :max="9999" />
                </el-form-item>
                <el-form-item :label="t('licenses_page.seats')">
                    <el-input-number v-model="createForm.seats" :min="1" :max="99999" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="creating" @click="confirmCreate">{{ t('licenses_page.confirm_create') }}</el-button>
            </template>
        </el-dialog>

        <!-- 编辑 License 对话框 -->
        <el-dialog v-model="showEdit" :title="t('licenses_page.edit_title')" width="560px">
            <el-form ref="editFormRef" :model="editForm" label-width="100px">
                <el-form-item :label="t('licenses_page.product')">
                    <el-select v-model="editForm.product_id" :placeholder="t('licenses_page.select_product')" filterable style="width: 100%">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.customer')">
                    <el-select v-model="editForm.customer_id" :placeholder="t('licenses_page.select_customer')" filterable style="width: 100%">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.type')">
                    <el-select v-model="editForm.type" style="width: 100%">
                        <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.expires_at')">
                    <el-date-picker
                        v-model="editForm.expires_at"
                        type="datetime"
                        :placeholder="t('licenses_page.expires_ph')"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item :label="t('licenses_page.max_devices')">
                    <el-input-number v-model="editForm.max_devices" :min="1" :max="9999" />
                </el-form-item>
                <el-form-item :label="t('licenses_page.seats')">
                    <el-input-number v-model="editForm.seats" :min="1" :max="99999" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEdit = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="updating" @click="confirmEdit">{{ t('licenses_page.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 批量创建对话框 -->
        <el-dialog v-model="showBatchCreate" :title="t('licenses_page.batch_create_title')" width="560px">
            <el-form label-width="100px">
                <el-form-item :label="t('licenses_page.product')" prop="product_id">
                    <el-select v-model="batchForm.product_id" :placeholder="t('licenses_page.select_product')" filterable style="width: 100%">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.customer')" prop="customer_id">
                    <el-select v-model="batchForm.customer_id" :placeholder="t('licenses_page.select_customer')" filterable style="width: 100%">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.count')">
                    <el-input-number v-model="batchForm.count" :min="2" :max="1000" />
                </el-form-item>
                <el-form-item :label="t('licenses_page.type')">
                    <el-select v-model="batchForm.type" style="width: 100%">
                        <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('licenses_page.max_devices')">
                    <el-input-number v-model="batchForm.max_devices" :min="1" :max="99" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBatchCreate = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="batchCreating" @click="confirmBatchCreate">
                    {{ t('licenses_page.batch_create_btn', { n: batchForm.count }) }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 导入 License 对话框 -->
        <el-dialog v-model="showImport" :title="t('licenses_page.import_title')" width="520px">
            <el-form label-width="100px">
                <el-form-item :label="t('licenses_page.csv_file')">
                    <el-upload
                        ref="importUploadRef"
                        :auto-upload="false"
                        :show-file-list="true"
                        :limit="1"
                        :on-change="handleImportFileChange"
                        accept=".csv,.txt"
                    >
                        <el-button type="primary" plain>
                            <el-icon><Upload /></el-icon> {{ t('licenses_page.select_file') }}
                        </el-button>
                        <template #tip>
                            <div class="el-upload__tip">
                                <p>{{ t('licenses_page.import_tip_format') }}</p>
                                <p>{{ t('licenses_page.import_tip_required') }}</p>
                                <p>{{ t('licenses_page.import_tip_optional') }}</p>
                                <el-button text size="small" type="primary" @click="downloadTemplate">
                                    {{ t('licenses_page.download_template') }}
                                </el-button>
                            </div>
                        </template>
                    </el-upload>
                </el-form-item>
                <el-form-item v-if="importResult">
                    <el-alert
                        :title="t('licenses_page.import_done', { success: importResult.success, failed: importResult.failed })"
                        :type="importResult.failed > 0 ? 'warning' : 'success'"
                        show-icon
                    />
                    <div v-if="importResult.errors?.length" class="import-errors">
                        <p v-for="(err, i) in importResult.errors" :key="i" class="import-error-item">{{ err }}</p>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="resetImport">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="importing" :disabled="!importFile" @click="confirmImport">
                    {{ t('licenses_page.start_import') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 批量操作对话框 -->
        <el-dialog v-model="showBatchDialog" :title="batchDialogTitle" width="480px">
            <template v-if="batchActionType">
                <p class="mb-4">{{ t('licenses_page.batch_selected', { n: selectedIds.length }) }}</p>

                <!-- 续期 -->
                <el-form v-if="batchActionType === 'renew'" label-width="100px">
                    <el-form-item :label="t('licenses_page.renew_days')">
                        <el-input-number v-model="batchActionPayload.days" :min="1" :max="3650" :step="30" style="width: 200px" />
                    </el-form-item>
                    <el-form-item :label="t('licenses_page.send_notify')">
                        <el-switch v-model="batchActionPayload.notify" />
                    </el-form-item>
                </el-form>

                <!-- 改席位 -->
                <el-form v-if="batchActionType === 'update_seats'" label-width="100px">
                    <el-form-item :label="t('licenses_page.new_seats')">
                        <el-input-number v-model="batchActionPayload.seats" :min="1" :max="999999" style="width: 200px" />
                    </el-form-item>
                </el-form>

                <!-- 更新元数据 -->
                <el-form v-if="batchActionType === 'update_metadata'" label-width="100px">
                    <el-form-item :label="t('licenses_page.metadata_json')">
                        <el-input
                            v-model="batchActionPayload.metadata_json"
                            type="textarea"
                            :rows="6"
                            placeholder='{"key": "value"}'
                            style="width: 100%; font-family: monospace;"
                        />
                    </el-form-item>
                </el-form>

                <!-- 添加标签 -->
                <el-form v-if="batchActionType === 'add_tags'" label-width="100px">
                    <el-form-item :label="t('licenses_page.tag_names')">
                        <el-select v-model="batchActionPayload.tags" multiple filterable allow-create default-first-option :placeholder="t('licenses_page.tag_ph')" style="width: 100%">
                            <el-option v-for="tag in allTags" :key="tag" :label="tag" :value="tag" />
                        </el-select>
                    </el-form-item>
                </el-form>

                <!-- 转移租户 -->
                <el-form v-if="batchActionType === 'transfer'" label-width="100px">
                    <el-form-item :label="t('licenses_page.target_tenant_id')">
                        <el-input-number v-model="batchActionPayload.tenant_id" :min="1" style="width: 200px" />
                    </el-form-item>
                    <el-alert type="warning" :closable="false" :title="t('licenses_page.transfer_warn')" show-icon />
                </el-form>
            </template>

            <template #footer>
                <el-button @click="showBatchDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="batchSubmitting" @click="confirmBatchAction">{{ t('licenses_page.confirm_execute') }}</el-button>
            </template>
        </el-dialog>

        <!-- 批量操作结果对话框 -->
        <el-dialog v-model="showBatchResult" :title="t('licenses_page.batch_result_title')" width="500px">
            <template v-if="batchResult">
                <el-alert
                    :title="batchResult.message"
                    :type="batchResult.failed > 0 ? 'warning' : 'success'"
                    show-icon
                    :closable="false"
                />
                <el-table :data="batchResult.details || []" size="small" stripe class="mt-4" max-height="300">
                    <el-table-column prop="license_key" :label="t('licenses_page.license_key')" width="220" />
                    <el-table-column :label="t('licenses_page.result')" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                                {{ row.success ? t('licenses_page.success') : t('licenses_page.failed') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="error" :label="t('licenses_page.error_info')" min-width="150">
                        <template #default="{ row }">{{ row.error || '-' }}</template>
                    </el-table-column>
                </el-table>
            </template>
            <template #footer>
                <el-button @click="showBatchResult = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useAuthStore } from '@/stores/auth';
import licenseApi from '@/api/license';
import productApi from '@/api/product';
import customerApi from '@/api/customer';
import SavedSearchBar from '@/components/SavedSearchBar.vue';
import {
    Plus, Download, Upload, DocumentAdd, ArrowDown,
    View, VideoPause, ColdDrink, Refresh, Remove,
    WarningFilled, Money, CopyDocument, Grid,
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const authStore = useAuthStore();
const isSuperAdmin = computed(() => authStore.user?.roles?.includes('super-admin') || false);

// ─── 可用标签列表（供批量添加标签使用） ───
const allTags = ref(['active', 'expired', 'vip', 'important', 'pending_renewal']);

// ─── 状态 ───
const loading = ref(false);
const creating = ref(false);
const updating = ref(false);
const batchCreating = ref(false);
const licenses = ref([]);
const meta = ref(null);
const products = ref([]);
const customers = ref([]);
const selectedIds = ref([]);
const showCreate = ref(false);
const showEdit = ref(false);
const showBatchCreate = ref(false);
const showImport = ref(false);
const importing = ref(false);
const importFile = ref(null);
const importUploadRef = ref(null);
const importResult = ref(null);
const createFormRef = ref(null);
const editFormRef = ref(null);
const sortField = ref('');
const sortOrder = ref('');
const stats = reactive({
    total: 0,
    active: 0,
    expired: 0,
    expiring_soon: 0,
});

const TYPE_VALUES = ['standard', 'trial', 'enterprise', 'development'];

const typeOptions = computed(() =>
    TYPE_VALUES.map((value) => ({
        value,
        label: t(`licenses_page.type_${value}`),
    })),
);

function typeLabel(type) {
    return t(`licenses_page.type_${type}`) || type;
}

const statusOptions = computed(() => [
    { value: 'pending', label: t('licenses_page.st_pending') },
    { value: 'active', label: t('licenses_page.st_active') },
    { value: 'suspended', label: t('licenses_page.st_suspended') },
    { value: 'frozen', label: t('licenses_page.st_frozen') },
    { value: 'expired', label: t('licenses_page.st_expired') },
    { value: 'revoked', label: t('licenses_page.st_revoked') },
    { value: 'refunded', label: t('licenses_page.st_refunded') },
    { value: 'blacklisted', label: t('licenses_page.st_blacklisted') },
]);

const filters = reactive({
    license_key: '',
    status: '',
    type: '',
    product_id: null,
    customer_id: null,
    date_range: null,
});

const createForm = reactive({
    product_id: null,
    customer_id: null,
    type: 'standard',
    expires_at: null,
    max_devices: 1,
    seats: 1,
});

// ─── License 模板 ───
const licenseTemplates = ref([]);
const selectedTemplateId = ref(null);

async function fetchLicenseTemplates() {
    try {
        const res = await import('@/api/licenseTemplate').then(m => m.default.list({ active_only: true, per_page: 999 }));
        licenseTemplates.value = res.data?.data || [];
    } catch {
        licenseTemplates.value = [];
    }
}

function applyTemplate(templateId) {
    if (!templateId) return;
    const tpl = licenseTemplates.value.find(t => t.id === templateId);
    if (!tpl) return;
    createForm.product_id = tpl.product_id;
    createForm.type = tpl.type;
    createForm.seats = tpl.seats;
    createForm.max_devices = tpl.max_devices;
    if (tpl.expiry_days !== null && tpl.expiry_days !== undefined) {
        const d = new Date();
        d.setDate(d.getDate() + tpl.expiry_days);
        createForm.expires_at = d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0') + ' 23:59:59';
    } else {
        createForm.expires_at = null;
    }
}

const createRules = computed(() => ({
    product_id: [{ required: true, message: t('licenses_page.product_required') }],
    type: [{ required: true, message: t('licenses_page.type_required') }],
}));

const editForm = reactive({
    id: null,
    product_id: null,
    customer_id: null,
    type: 'standard',
    expires_at: null,
    max_devices: 1,
    seats: 1,
});

const batchForm = reactive({
    product_id: null,
    customer_id: null,
    count: 10,
    type: 'standard',
    max_devices: 3,
});

// ─── 数据加载 ───
async function fetchStats() {
    try {
        const { data: res } = await licenseApi.stats();
        Object.assign(stats, res.data || {});
    } catch {
        // ignore
    }
}

async function fetchData(page) {
    loading.value = true;
    try {
        const params = {
            page: page || meta.value?.current_page || 1,
            per_page: meta.value?.per_page || 20,
        };
        if (filters.license_key) params.license_key = filters.license_key;
        if (filters.status) params['filter[status]'] = filters.status;
        if (filters.type) params['filter[type]'] = filters.type;
        if (filters.product_id) params['filter[product_id]'] = filters.product_id;
        if (filters.customer_id) params['filter[customer_id]'] = filters.customer_id;
        if (filters.date_range?.[0]) params['filter[created_from]'] = filters.date_range[0];
        if (filters.date_range?.[1]) params['filter[created_to]'] = filters.date_range[1];
        if (sortField.value) params.sort = `${sortOrder.value === 'descending' ? '-' : ''}${sortField.value}`;

        const { data: res } = await licenseApi.list(params);
        licenses.value = res.data || [];
        meta.value = res.meta;
    } catch {
        // ignore
    } finally {
        loading.value = false;
    }
}

async function loadOptions() {
    try {
        const [pRes, cRes] = await Promise.all([
            productApi.list({ per_page: 999 }),
            customerApi.list({ per_page: 999 }),
        ]);
        products.value = pRes.data?.data || [];
        customers.value = cRes.data?.data || [];
    } catch {
        // ignore
    }
}

function resetFilters() {
    Object.assign(filters, {
        license_key: '', status: '', type: '',
        product_id: null, customer_id: null, date_range: null,
    });
    fetchData(1);
}

// 应用保存的搜索
function applySavedFilters(savedFilters) {
    for (const [key, value] of Object.entries(savedFilters)) {
        if (key in filters) {
            filters[key] = value;
        }
    }
    fetchData(1);
}

function handleSelectionChange(rows) {
    selectedIds.value = rows.map(r => r.id);
}

function handleSortChange({ prop, order }) {
    sortField.value = prop || '';
    sortOrder.value = order || '';
    fetchData(1);
}

// ─── 创建 ───
function openCreate() {
    Object.assign(createForm, {
        product_id: null, customer_id: null, type: 'standard',
        expires_at: null, max_devices: 1, seats: 1,
    });
    showCreate.value = true;
}

async function confirmCreate() {
    const valid = await createFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    creating.value = true;
    try {
        const payload = { ...createForm };
        if (!payload.expires_at) delete payload.expires_at;
        await licenseApi.create(payload);
        ElMessage.success(t('licenses_page.create_ok'));
        showCreate.value = false;
        fetchData(1);
        fetchStats();
    } catch {
        ElMessage.error(t('licenses_page.create_fail'));
    } finally {
        creating.value = false;
    }
}

// ─── 编辑 ───
function openEdit(row) {
    Object.assign(editForm, {
        id: row.id,
        product_id: row.product_id,
        customer_id: row.customer_id,
        type: row.type,
        expires_at: row.expires_at,
        max_devices: row.max_devices || 1,
        seats: row.seats || 1,
    });
    showEdit.value = true;
}

async function confirmEdit() {
    updating.value = true;
    try {
        const payload = {};
        if (editForm.product_id) payload.product_id = editForm.product_id;
        if (editForm.customer_id) payload.customer_id = editForm.customer_id;
        if (editForm.type) payload.type = editForm.type;
        payload.expires_at = editForm.expires_at || null;
        payload.max_devices = editForm.max_devices;
        payload.seats = editForm.seats;

        await licenseApi.update(editForm.id, payload);
        ElMessage.success(t('licenses_page.update_ok'));
        showEdit.value = false;
        fetchData();
    } catch {
        ElMessage.error(t('licenses_page.update_fail'));
    } finally {
        updating.value = false;
    }
}

// ─── 批量创建 ───
async function confirmBatchCreate() {
    if (!batchForm.product_id) return ElMessage.warning(t('licenses_page.product_required'));

    batchCreating.value = true;
    try {
        const { data: res } = await licenseApi.batchStore({
            product_id: batchForm.product_id,
            customer_id: batchForm.customer_id || undefined,
            count: batchForm.count,
            type: batchForm.type,
            max_devices: batchForm.max_devices,
        });
        const count = res.data?.length || 0;
        ElMessage.success(t('licenses_page.batch_create_ok', { n: count }));
        showBatchCreate.value = false;
        fetchData(1);
        fetchStats();
    } catch {
        ElMessage.error(t('licenses_page.batch_create_fail'));
    } finally {
        batchCreating.value = false;
    }
}

// ─── 导入 ───
function handleImportFileChange(file) {
    importFile.value = file.raw;
    importResult.value = null;
}

function resetImport() {
    showImport.value = false;
    importFile.value = null;
    importResult.value = null;
    if (importUploadRef.value) {
        importUploadRef.value.clearFiles();
    }
}

async function confirmImport() {
    if (!importFile.value) return;

    importing.value = true;
    importResult.value = null;
    try {
        const formData = new FormData();
        formData.append('file', importFile.value);
        const { data: res } = await licenseApi.import(formData);
        importResult.value = res.data || res;
        if (importResult.value.failed === 0) {
            ElMessage.success(t('licenses_page.import_ok', { n: importResult.value.success }));
            fetchData(1);
            fetchStats();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('licenses_page.import_fail'));
    } finally {
        importing.value = false;
    }
}

function downloadTemplate() {
    // Build a simple CSV template
    const headers = [
        t('licenses_page.col_product'),
        t('licenses_page.col_type'),
        t('licenses_page.col_customer'),
        t('licenses_page.seats'),
        t('licenses_page.col_max_devices'),
        t('licenses_page.col_expires_at'),
        t('licenses_page.csv_metadata'),
    ];
    const example = [
        t('licenses_page.csv_product_ex'),
        'standard',
        t('licenses_page.csv_customer_ex'),
        '1',
        '1',
        '2026-12-31 23:59:59',
        '{"key":"value"}',
    ];
    const BOM = '\uFEFF';
    const csv = BOM + headers.join(',') + '\n' + example.join(',') + '\n';
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'license-import-template.csv';
    document.body.appendChild(a);
    a.click();
    URL.revokeObjectURL(url);
    a.remove();
}

// ─── 操作 ───
function actionLabel(cmd) {
    const key = `licenses_page.act_${cmd}`;
    const translated = t(key);
    return translated !== key ? translated : cmd;
}

async function handleAction(cmd, row) {
    if (cmd === 'detail') {
        router.push(`/licenses/${row.id}`);
        return;
    }

    if (cmd === 'seat-pool') {
        router.push(`/licenses/${row.id}/seat-pool`);
        return;
    }

    if (cmd === 'destroy') {
        try {
            await ElMessageBox.confirm(
                t('licenses_page.delete_confirm', { key: row.license_key }),
                t('licenses_page.delete_title'),
                { confirmButtonText: t('licenses_page.confirm_delete'), cancelButtonText: t('actions.cancel'), type: 'warning' },
            );
            await licenseApi.destroy(row.id);
            ElMessage.success(t('licenses_page.deleted_ok'));
            fetchData();
            fetchStats();
        } catch {
            // cancelled or error
        }
        return;
    }

    const label = actionLabel(cmd);
    try {
        await ElMessageBox.confirm(
            t('licenses_page.action_confirm', { action: label, key: row.license_key }),
            t('licenses_page.confirm_action_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        );
        const apiMap = {
            suspend: licenseApi.suspend,
            freeze: licenseApi.freeze,
            restore: licenseApi.restore,
            revoke: licenseApi.revoke,
            blacklist: licenseApi.blacklist,
            refund: licenseApi.refund,
        };
        await apiMap[cmd](row.id);
        ElMessage.success(t('licenses_page.action_ok', { action: label }));
        fetchData();
        fetchStats();
    } catch {
        // cancelled or error
    }
}

function handleBatchActionCmd(cmd) {
    batchAction(cmd);
}

async function batchAction(action) {
    if (!selectedIds.value.length) return;

    const label = actionLabel(action === 'delete' ? 'destroy' : action);
    const ids = [...selectedIds.value];

    try {
        await ElMessageBox.confirm(
            t('licenses_page.batch_action_confirm', { action: label, n: ids.length }),
            t('licenses_page.batch_action_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        );

        const res = await licenseApi.batchOperation({
            license_ids: ids,
            action: action,
        });
        const data = res.data?.data || {};

        ElMessage.success(t('licenses_page.batch_action_done', {
            action: label,
            processed: data.processed || 0,
            failed: data.failed || 0,
        }));
        selectedIds.value = [];
        fetchData();
        fetchStats();
    } catch (err) {
        if (err.code !== 'CANCEL') {
            ElMessage.error(err.response?.data?.message || t('licenses_page.batch_action_fail'));
        }
    }
}

// ─── 新增批量操作对话框 ───
const showBatchDialog = ref(false);
const showBatchResult = ref(false);
const batchActionType = ref('');
const batchSubmitting = ref(false);
const batchResult = ref(null);
const batchActionPayload = reactive({
    days: 365,
    notify: true,
    seats: 1,
    metadata_json: '',
    tags: [],
    tenant_id: null,
});

const batchDialogTitle = computed(() => {
    const titles = {
        renew: t('licenses_page.batch_renew'),
        update_seats: t('licenses_page.batch_update_seats'),
        update_metadata: t('licenses_page.batch_update_metadata'),
        add_tags: t('licenses_page.batch_add_tags'),
        transfer: t('licenses_page.batch_transfer'),
    };
    return titles[batchActionType.value] || t('licenses_page.batch_dialog_default');
});

function openBatchEditDialog(type) {
    batchActionType.value = type;
    batchActionPayload.days = 365;
    batchActionPayload.notify = true;
    batchActionPayload.seats = 1;
    batchActionPayload.metadata_json = '';
    batchActionPayload.tags = [];
    batchActionPayload.tenant_id = null;
    showBatchDialog.value = true;
}

async function confirmBatchAction() {
    batchSubmitting.value = true;
    try {
        const ids = [...selectedIds.value];
        const payload = { license_ids: ids, action: batchActionType.value };

        if (batchActionType.value === 'renew') {
            payload.payload = { days: batchActionPayload.days, notify: batchActionPayload.notify };
        } else if (batchActionType.value === 'update_seats') {
            payload.payload = { seats: batchActionPayload.seats };
        } else if (batchActionType.value === 'update_metadata') {
            let metadata = {};
            try {
                metadata = JSON.parse(batchActionPayload.metadata_json || '{}');
            } catch {
                ElMessage.warning(t('licenses_page.metadata_invalid'));
                batchSubmitting.value = false;
                return;
            }
            payload.payload = { metadata };
        } else if (batchActionType.value === 'add_tags') {
            payload.payload = { tags: batchActionPayload.tags };
        } else if (batchActionType.value === 'transfer') {
            payload.payload = { tenant_id: batchActionPayload.tenant_id };
        }

        const res = await licenseApi.batchOperation(payload);
        batchResult.value = {
            message: res.data?.message || t('licenses_page.operation_done'),
            processed: res.data?.data?.processed || 0,
            failed: res.data?.data?.failed || 0,
            details: res.data?.data?.details || [],
        };

        showBatchDialog.value = false;
        showBatchResult.value = true;
        selectedIds.value = [];
        fetchData();
        fetchStats();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('licenses_page.batch_action_fail'));
    } finally {
        batchSubmitting.value = false;
    }
}

async function confirmBatchDelete() {
    const ids = [...selectedIds.value];
    try {
        await ElMessageBox.confirm(
            t('licenses_page.batch_delete_confirm', { n: ids.length }),
            t('licenses_page.batch_delete_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        );

        const res = await licenseApi.batchOperation({ license_ids: ids, action: 'delete' });
        const data = res.data?.data || {};
        ElMessage.success(t('licenses_page.batch_delete_done', {
            processed: data.processed || 0,
            failed: data.failed || 0,
        }));
        selectedIds.value = [];
        fetchData();
        fetchStats();
    } catch (err) {
        if (err.code !== 'CANCEL') {
            ElMessage.error(err.response?.data?.message || t('licenses_page.batch_delete_fail'));
        }
    }
}

function handleBulkCmd(cmd) {
    if (cmd === 'export-csv') {
        // Build current filter params
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([k, v]) => {
            if (v !== '' && v !== null) {
                if (k === 'date_range' && v) {
                    params.set('filter[created_from]', v[0]);
                    params.set('filter[created_to]', v[1]);
                } else if (k === 'status' || k === 'type') {
                    params.set(`filter[${k}]`, v);
                } else {
                    params.set(k, v);
                }
            }
        });
        const url = `/api/licenses/export?${params.toString()}`;
        const token = localStorage.getItem('auth_token');
        const link = document.createElement('a');
        link.href = url;
        if (token) {
            // Use fetch to include auth header, then download
            fetch(url, { headers: { Authorization: `Bearer ${token}` } })
                .then(res => {
                    if (!res.ok) throw new Error('export failed');
                    return res.blob();
                })
                .then(blob => {
                    const blobUrl = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.download = `licenses-${new Date().toISOString().slice(0, 19).replace(/[:-]/g, '')}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    URL.revokeObjectURL(blobUrl);
                    a.remove();
                    ElMessage.success(t('licenses_page.export_ok'));
                })
                .catch(() => ElMessage.error(t('licenses_page.export_fail')));
        }
    }
}

// ─── 展示辅助 ───
const STATUS_TYPES = {
    pending: 'info',
    active: 'success',
    suspended: 'warning',
    frozen: 'warning',
    expired: 'info',
    revoked: 'danger',
    refunded: 'danger',
    blacklisted: 'danger',
};

const quickFilterOptions = computed(() => [
    { value: 'active', label: t('licenses_page.st_active_now'), type: 'success' },
    { value: 'expired', label: t('licenses_page.st_expired'), type: 'info' },
    { value: 'suspended', label: t('licenses_page.st_suspended'), type: 'warning' },
    { value: 'frozen', label: t('licenses_page.st_frozen'), type: 'warning' },
    { value: 'revoked', label: t('licenses_page.st_revoked'), type: 'danger' },
]);

function statusType(status) {
    return STATUS_TYPES[status] || 'info';
}
function statusLabel(status) {
    const key = `licenses_page.st_${status}`;
    const translated = t(key);
    return translated !== key ? translated : status;
}
function expiryClass(row) {
    if (!row.expires_at) return '';
    const now = Date.now();
    const expiry = new Date(row.expires_at).getTime();
    if (expiry < now) return 'expired-text';
    const days = (expiry - now) / 86400000;
    return days <= 7 ? 'expiring-soon-text' : '';
}
function expiryTooltip(row) {
    if (!row.expires_at) return t('licenses_page.expiry_permanent');
    const now = Date.now();
    const expiry = new Date(row.expires_at).getTime();
    const diffMs = expiry - now;
    const days = Math.ceil(diffMs / 86400000);
    if (diffMs < 0) return t('licenses_page.expiry_past', { n: Math.abs(days) });
    if (days <= 7) return t('licenses_page.expiry_soon', { n: days });
    if (days <= 30) return t('licenses_page.expiry_soon', { n: days });
    return t('licenses_page.expiry_until', { date: row.expires_at });
}
function copyLicenseKey(key) {
    const copiedMsg = t('licenses_page.key_copied');
    navigator.clipboard.writeText(key).then(() => {
        ElMessage.success({ message: copiedMsg, duration: 1500 });
    }).catch(() => {
        // fallback
        const ta = document.createElement('textarea');
        ta.value = key;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        ElMessage.success({ message: copiedMsg, duration: 1500 });
    });
}

onMounted(() => {
    loadOptions();
    fetchStats();
    fetchData(1);
    fetchLicenseTemplates();
});
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }

/* 统计卡片 */
.stat-card {
    cursor: pointer;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #303133;
}
.stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
}
.stat-active .stat-value { color: #67c23a; }
.stat-warning .stat-value { color: #e6a23c; }
.stat-danger .stat-value { color: #f56c6c; }

.batch-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: #f1f5f9;
    border-radius: 4px;
    margin-bottom: 16px;
}
.selected-info { font-size: 14px; color: #0f172a; font-weight: 500; }

.pagination-wrap {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}

.key-text {
    font-size: 12px;
    letter-spacing: 0.5px;
}

.expired-text {
    color: #f56c6c;
    text-decoration: line-through;
}
.expiring-soon-text {
    color: #e6a23c;
    font-weight: 500;
}
/* ─── 快速筛选标签 ─── */
.quick-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.clickable-tag {
    cursor: pointer;
    user-select: none;
    transition: all 0.2s;
}
.clickable-tag:hover {
    transform: translateY(-1px);
}
/* ─── License Key 复制按钮 ─── */
.key-cell {
    display: flex;
    align-items: center;
    gap: 4px;
}
.key-cell .copy-btn {
    opacity: 0;
    transition: opacity 0.2s;
    margin-left: 2px;
}
.key-cell:hover .copy-btn {
    opacity: 1;
}
.filter-bar-footer {
    display: flex;
    justify-content: flex-end;
    padding-top: 8px;
    border-top: 1px solid var(--el-border-color-extra-light);
    margin-top: 8px;
}
.template-option-desc {
    float: right;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    margin-left: 16px;
}
.import-errors {
    max-height: 200px;
    overflow-y: auto;
    margin-top: 8px;
}
.import-error-item {
    font-size: 12px;
    color: #f56c6c;
    margin: 2px 0;
}
.mt-4 { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }

/* ─── 移动端响应式 ─── */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    .header-actions {
        flex-wrap: wrap;
        width: 100%;
    }
    .header-actions .el-button {
        flex: 1;
        min-width: 0;
    }
    .stat-cards-row .el-col {
        margin-bottom: 12px;
    }
    .stat-value {
        font-size: 22px;
    }
    .batch-bar {
        flex-wrap: wrap;
        gap: 6px;
    }
    .batch-bar .el-button {
        font-size: 12px;
        padding: 4px 8px;
    }
}
</style>