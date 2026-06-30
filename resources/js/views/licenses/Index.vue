<template>
    <div class="licenses-page">
        <!-- 统计仪表盘 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card" @click="filters.status = ''; fetchData(1)">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">全部 License</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card stat-active" @click="filters.status = 'active'; fetchData(1)">
                    <div class="stat-value">{{ stats.active }}</div>
                    <div class="stat-label">活跃中</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card stat-warning" @click="filters.status = 'active'; fetchData(1)">
                    <div class="stat-value">{{ stats.expiring_soon }}</div>
                    <div class="stat-label">30天内到期</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card stat-danger" @click="filters.status = 'expired'; fetchData(1)">
                    <div class="stat-value">{{ stats.expired }}</div>
                    <div class="stat-label">已过期</div>
                </el-card>
            </el-col>
        </el-row>

        <div class="page-header">
            <h2>License 管理</h2>
            <div class="header-actions">
                <el-dropdown @command="handleBulkCmd" trigger="click">
                    <el-button><el-icon><Download /></el-icon> 导出</el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="export-csv">导出 CSV</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
                <el-button @click="showImport = true"><el-icon><Upload /></el-icon> 导入</el-button>
                <el-button @click="showBatchCreate = true"><el-icon><DocumentAdd /></el-icon> 批量创建</el-button>
                <el-button type="primary" @click="openCreate">
                    <el-icon><Plus /></el-icon> 创建 License
                </el-button>
            </div>
        </div>

        <!-- 搜索/筛选栏 -->
        <el-card class="mb-4">
            <el-form :model="filters" inline label-width="90px" @keyup.enter="fetchData">
                <el-row :gutter="16">
                    <el-col :span="6">
                        <el-form-item label="License Key">
                            <el-input v-model="filters.license_key" placeholder="支持模糊搜索" clearable />
                        </el-form-item>
                    </el-col>
                    <el-col :span="4">
                        <el-form-item label="状态">
                            <el-select v-model="filters.status" placeholder="全部" clearable style="width: 120px">
                                <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="4">
                        <el-form-item label="类型">
                            <el-select v-model="filters.type" placeholder="全部" clearable style="width: 120px">
                                <el-option label="标准" value="standard" />
                                <el-option label="试用" value="trial" />
                                <el-option label="企业版" value="enterprise" />
                                <el-option label="开发版" value="development" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="4">
                        <el-form-item label="产品">
                            <el-select v-model="filters.product_id" placeholder="全部" clearable filterable style="width: 140px">
                                <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item label="客户">
                            <el-select v-model="filters.customer_id" placeholder="全部" clearable filterable style="width: 160px">
                                <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item label="创建时间">
                            <el-date-picker
                                v-model="filters.date_range"
                                type="datetimerange"
                                range-separator="至"
                                start-placeholder="开始"
                                end-placeholder="结束"
                                value-format="YYYY-MM-DD HH:mm:ss"
                                style="width: 260px"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item label=" ">
                            <el-button type="primary" @click="fetchData">搜索</el-button>
                            <el-button @click="resetFilters">重置</el-button>
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
            <span class="selected-info">已选择 {{ selectedIds.length }} 项</span>

            <el-dropdown trigger="click" @command="handleBatchActionCmd">
                <el-button size="small">
                    状态变更 <el-icon><ArrowDown /></el-icon>
                </el-button>
                <template #dropdown>
                    <el-dropdown-menu>
                        <el-dropdown-item command="activate">批量激活</el-dropdown-item>
                        <el-dropdown-item command="deactivate">批量停用</el-dropdown-item>
                        <el-dropdown-item command="suspend">批量暂停</el-dropdown-item>
                        <el-dropdown-item command="restore">批量恢复</el-dropdown-item>
                        <el-dropdown-item command="freeze">批量冻结</el-dropdown-item>
                        <el-dropdown-item command="revoke" divided>批量吊销</el-dropdown-item>
                        <el-dropdown-item command="blacklist">批量加入黑名单</el-dropdown-item>
                        <el-dropdown-item command="refund">批量退款</el-dropdown-item>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>

            <el-button size="small" @click="openBatchEditDialog('renew')">批量续期</el-button>
            <el-button size="small" @click="openBatchEditDialog('update_seats')">批量改席位</el-button>
            <el-button size="small" @click="openBatchEditDialog('update_metadata')">批量更新元数据</el-button>
            <el-button size="small" @click="openBatchEditDialog('add_tags')">批量添加标签</el-button>
            <el-button size="small" @click="openBatchEditDialog('transfer')" v-if="isSuperAdmin">批量转移租户</el-button>
            <el-button size="small" type="danger" plain @click="confirmBatchDelete">批量删除</el-button>
            <el-button text size="small" @click="selectedIds = []">取消选择</el-button>
        </div>

        <!-- 表格 -->
        <el-card>
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
                <el-table-column prop="license_key" label="License Key" min-width="200" sortable="custom">
                    <template #default="{ row }">
                        <el-link type="primary" :underline="'never'" @click="$router.push(`/licenses/${row.id}`)">
                            <code class="key-text">{{ row.license_key }}</code>
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column prop="product?.name" label="产品" width="120" :formatter="(r) => r.product?.name || '-'" />
                <el-table-column prop="customer?.name" label="客户" width="120" :formatter="(r) => r.customer?.name || '-'" />
                <el-table-column prop="type" label="类型" width="90">
                    <template #default="{ row }">
                        <el-tag v-if="row.type === 'trial'" type="warning" size="small">试用</el-tag>
                        <el-tag v-else-if="row.type === 'enterprise'" type="success" size="small">企业版</el-tag>
                        <el-tag v-else-if="row.type === 'development'" size="small">开发版</el-tag>
                        <span v-else>标准</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="max_devices" label="设备限制" width="90" align="center" />
                <el-table-column prop="expires_at" label="过期时间" width="170" sortable="custom">
                    <template #default="{ row }">
                        <span :class="expiryClass(row)">{{ row.expires_at || '永久' }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170" sortable="custom" />
                <el-table-column label="操作" width="310" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" text type="primary" @click="openEdit(row)">编辑</el-button>
                        <el-dropdown trigger="click" @command="(cmd) => handleAction(cmd, row)">
                            <el-button size="small">
                                状态操作 <el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="detail">
                                        <el-icon><View /></el-icon>查看详情
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'active'" command="suspend" divided>
                                        <el-icon><VideoPause /></el-icon>暂停
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'active'" command="freeze">
                                        <el-icon><ColdDrink /></el-icon>冻结
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'suspended' || row.status === 'frozen'" command="restore">
                                        <el-icon><Refresh /></el-icon>恢复
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status !== 'revoked' && row.status !== 'blacklisted'" command="revoke" divided>
                                        <el-icon><Remove /></el-icon>吊销
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status !== 'blacklisted'" command="blacklist">
                                        <el-icon><WarningFilled /></el-icon>加入黑名单
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status !== 'refunded'" command="refund" divided>
                                        <el-icon><Money /></el-icon>退款
                                    </el-dropdown-item>
                                    <el-dropdown-item command="seat-pool" divided>
                                        <el-icon><Grid /></el-icon>席位池
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <el-button size="small" text type="danger" @click="handleAction('destroy', row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

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
        <el-dialog v-model="showCreate" title="创建 License" width="560px">
            <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="100px">
                <el-form-item label="选择模板" v-if="licenseTemplates.length > 0">
                    <el-select
                        v-model="selectedTemplateId"
                        placeholder="从模板快速填充（可选）"
                        filterable
                        clearable
                        style="width: 100%"
                        @change="applyTemplate"
                    >
                        <el-option
                            v-for="t in licenseTemplates"
                            :key="t.id"
                            :label="t.name"
                            :value="t.id"
                        >
                            <span>{{ t.name }}</span>
                            <span class="template-option-desc">{{ t.description || typeLabel(t.type) }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-divider v-if="licenseTemplates.length > 0" />
                <el-form-item label="产品" prop="product_id">
                    <el-select v-model="createForm.product_id" placeholder="选择产品" filterable style="width: 100%">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="客户" prop="customer_id">
                    <el-select v-model="createForm.customer_id" placeholder="选择客户" filterable style="width: 100%">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类型" prop="type">
                    <el-select v-model="createForm.type" style="width: 100%">
                        <el-option label="标准" value="standard" />
                        <el-option label="试用" value="trial" />
                        <el-option label="企业版" value="enterprise" />
                        <el-option label="开发版" value="development" />
                    </el-select>
                </el-form-item>
                <el-form-item label="过期时间" prop="expires_at">
                    <el-date-picker
                        v-model="createForm.expires_at"
                        type="datetime"
                        placeholder="留空为永久"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item label="设备限制">
                    <el-input-number v-model="createForm.max_devices" :min="1" :max="9999" />
                </el-form-item>
                <el-form-item label="座位数">
                    <el-input-number v-model="createForm.seats" :min="1" :max="99999" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">取消</el-button>
                <el-button type="primary" :loading="creating" @click="confirmCreate">确认创建</el-button>
            </template>
        </el-dialog>

        <!-- 编辑 License 对话框 -->
        <el-dialog v-model="showEdit" title="编辑 License" width="560px">
            <el-form ref="editFormRef" :model="editForm" label-width="100px">
                <el-form-item label="产品">
                    <el-select v-model="editForm.product_id" placeholder="选择产品" filterable style="width: 100%">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="客户">
                    <el-select v-model="editForm.customer_id" placeholder="选择客户" filterable style="width: 100%">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="editForm.type" style="width: 100%">
                        <el-option label="标准" value="standard" />
                        <el-option label="试用" value="trial" />
                        <el-option label="企业版" value="enterprise" />
                        <el-option label="开发版" value="development" />
                    </el-select>
                </el-form-item>
                <el-form-item label="过期时间">
                    <el-date-picker
                        v-model="editForm.expires_at"
                        type="datetime"
                        placeholder="留空为永久"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item label="设备限制">
                    <el-input-number v-model="editForm.max_devices" :min="1" :max="9999" />
                </el-form-item>
                <el-form-item label="座位数">
                    <el-input-number v-model="editForm.seats" :min="1" :max="99999" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEdit = false">取消</el-button>
                <el-button type="primary" :loading="updating" @click="confirmEdit">保存修改</el-button>
            </template>
        </el-dialog>

        <!-- 批量创建对话框 -->
        <el-dialog v-model="showBatchCreate" title="批量创建 License" width="560px">
            <el-form label-width="100px">
                <el-form-item label="产品" prop="product_id">
                    <el-select v-model="batchForm.product_id" placeholder="选择产品" filterable style="width: 100%">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="客户" prop="customer_id">
                    <el-select v-model="batchForm.customer_id" placeholder="选择客户" filterable style="width: 100%">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="数量">
                    <el-input-number v-model="batchForm.count" :min="2" :max="1000" />
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="batchForm.type" style="width: 100%">
                        <el-option label="标准" value="standard" />
                        <el-option label="试用" value="trial" />
                        <el-option label="企业版" value="enterprise" />
                        <el-option label="开发版" value="development" />
                    </el-select>
                </el-form-item>
                <el-form-item label="设备限制">
                    <el-input-number v-model="batchForm.max_devices" :min="1" :max="99" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBatchCreate = false">取消</el-button>
                <el-button type="primary" :loading="batchCreating" @click="confirmBatchCreate">
                    批量创建 {{ batchForm.count }} 个
                </el-button>
            </template>
        </el-dialog>

        <!-- 导入 License 对话框 -->
        <el-dialog v-model="showImport" title="导入 License" width="520px">
            <el-form label-width="100px">
                <el-form-item label="CSV 文件">
                    <el-upload
                        ref="importUploadRef"
                        :auto-upload="false"
                        :show-file-list="true"
                        :limit="1"
                        :on-change="handleImportFileChange"
                        accept=".csv,.txt"
                    >
                        <el-button type="primary" plain>
                            <el-icon><Upload /></el-icon> 选择文件
                        </el-button>
                        <template #tip>
                            <div class="el-upload__tip">
                                <p>支持 .csv / .txt 格式，最大 5MB</p>
                                <p>必填列：<strong>产品</strong>（产品名称或产品 ID）</p>
                                <p>可选列：客户、类型（trial/standard/enterprise/development）、座位数、设备限制、过期时间、元数据</p>
                                <el-button text size="small" type="primary" @click="downloadTemplate">
                                    下载 CSV 模板
                                </el-button>
                            </div>
                        </template>
                    </el-upload>
                </el-form-item>
                <el-form-item v-if="importResult">
                    <el-alert
                        :title="`导入完成：成功 ${importResult.success} 条，失败 ${importResult.failed} 条`"
                        :type="importResult.failed > 0 ? 'warning' : 'success'"
                        show-icon
                    />
                    <div v-if="importResult.errors?.length" class="import-errors">
                        <p v-for="(err, i) in importResult.errors" :key="i" class="import-error-item">{{ err }}</p>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="resetImport">取消</el-button>
                <el-button type="primary" :loading="importing" :disabled="!importFile" @click="confirmImport">
                    开始导入
                </el-button>
            </template>
        </el-dialog>

        <!-- 批量操作对话框 -->
        <el-dialog v-model="showBatchDialog" :title="batchDialogTitle" width="480px">
            <template v-if="batchActionType">
                <p class="mb-4">共选择 <strong>{{ selectedIds.length }}</strong> 个 License</p>

                <!-- 续期 -->
                <el-form v-if="batchActionType === 'renew'" label-width="100px">
                    <el-form-item label="续期天数">
                        <el-input-number v-model="batchActionPayload.days" :min="1" :max="3650" :step="30" style="width: 200px" />
                    </el-form-item>
                    <el-form-item label="发送通知">
                        <el-switch v-model="batchActionPayload.notify" />
                    </el-form-item>
                </el-form>

                <!-- 改席位 -->
                <el-form v-if="batchActionType === 'update_seats'" label-width="100px">
                    <el-form-item label="新席位数量">
                        <el-input-number v-model="batchActionPayload.seats" :min="1" :max="999999" style="width: 200px" />
                    </el-form-item>
                </el-form>

                <!-- 更新元数据 -->
                <el-form v-if="batchActionType === 'update_metadata'" label-width="100px">
                    <el-form-item label="元数据(JSON)">
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
                    <el-form-item label="标签名称">
                        <el-select v-model="batchActionPayload.tags" multiple filterable allow-create default-first-option placeholder="输入标签名后回车添加" style="width: 100%">
                            <el-option v-for="tag in allTags" :key="tag" :label="tag" :value="tag" />
                        </el-select>
                    </el-form-item>
                </el-form>

                <!-- 转移租户 -->
                <el-form v-if="batchActionType === 'transfer'" label-width="100px">
                    <el-form-item label="目标租户 ID">
                        <el-input-number v-model="batchActionPayload.tenant_id" :min="1" style="width: 200px" />
                    </el-form-item>
                    <el-alert type="warning" :closable="false" title="仅超级管理员可执行租户转移操作" show-icon />
                </el-form>
            </template>

            <template #footer>
                <el-button @click="showBatchDialog = false">取消</el-button>
                <el-button type="primary" :loading="batchSubmitting" @click="confirmBatchAction">确认执行</el-button>
            </template>
        </el-dialog>

        <!-- 批量操作结果对话框 -->
        <el-dialog v-model="showBatchResult" title="批量操作结果" width="500px">
            <template v-if="batchResult">
                <el-alert
                    :title="batchResult.message"
                    :type="batchResult.failed > 0 ? 'warning' : 'success'"
                    show-icon
                    :closable="false"
                />
                <el-table :data="batchResult.details || []" size="small" stripe class="mt-4" max-height="300">
                    <el-table-column prop="license_key" label="License Key" width="220" />
                    <el-table-column label="结果" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                                {{ row.success ? '成功' : '失败' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="error" label="错误信息" min-width="150">
                        <template #default="{ row }">{{ row.error || '-' }}</template>
                    </el-table-column>
                </el-table>
            </template>
            <template #footer>
                <el-button @click="showBatchResult = false">关闭</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useAuthStore } from '@/stores/auth';
import licenseApi from '@/api/license';
import productApi from '@/api/product';
import customerApi from '@/api/customer';
import SavedSearchBar from '@/components/SavedSearchBar.vue';
import {
    Plus, Download, Upload, DocumentAdd, ArrowDown,
    View, VideoPause, ColdDrink, Refresh, Remove,
    WarningFilled, Money,
} from '@element-plus/icons-vue';

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

const statusOptions = [
    { value: 'pending', label: '待激活' },
    { value: 'active', label: '活跃' },
    { value: 'suspended', label: '已暂停' },
    { value: 'frozen', label: '已冻结' },
    { value: 'expired', label: '已过期' },
    { value: 'revoked', label: '已吊销' },
    { value: 'refunded', label: '已退款' },
    { value: 'blacklisted', label: '黑名单' },
];

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

const createRules = {
    product_id: [{ required: true, message: '请选择产品' }],
    type: [{ required: true, message: '请选择类型' }],
};

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
        ElMessage.success('License 创建成功');
        showCreate.value = false;
        fetchData(1);
        fetchStats();
    } catch {
        ElMessage.error('创建失败');
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
        ElMessage.success('License 已更新');
        showEdit.value = false;
        fetchData();
    } catch {
        ElMessage.error('更新失败');
    } finally {
        updating.value = false;
    }
}

// ─── 批量创建 ───
async function confirmBatchCreate() {
    if (!batchForm.product_id) return ElMessage.warning('请选择产品');

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
        ElMessage.success(`成功创建 ${count} 个 License`);
        showBatchCreate.value = false;
        fetchData(1);
        fetchStats();
    } catch {
        ElMessage.error('批量创建失败');
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
            ElMessage.success(`成功导入 ${importResult.value.success} 个 License`);
            fetchData(1);
            fetchStats();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '导入失败');
    } finally {
        importing.value = false;
    }
}

function downloadTemplate() {
    // Build a simple CSV template
    const headers = ['产品', '类型', '客户', '座位数', '设备限制', '过期时间', '元数据'];
    const example = ['产品名称（必填）', 'standard', '客户名称（选填）', '1', '1', '2026-12-31 23:59:59', '{"key":"value"}'];
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
async function handleAction(cmd, row) {
    const actionLabels = {
        detail: '查看详情',
        suspend: '暂停', freeze: '冻结', restore: '恢复',
        revoke: '吊销', blacklist: '加入黑名单', refund: '退款',
        destroy: '删除',
    };

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
                `确定要删除 License "${row.license_key}" 吗？删除后可在回收站恢复。`,
                '确认删除',
                { confirmButtonText: '确认删除', cancelButtonText: '取消', type: 'warning' },
            );
            await licenseApi.destroy(row.id);
            ElMessage.success('License 已移至回收站');
            fetchData();
            fetchStats();
        } catch {
            // cancelled or error
        }
        return;
    }

    try {
        await ElMessageBox.confirm(
            `确定要${actionLabels[cmd]} License "${row.license_key}" 吗？`,
            '确认操作',
            { confirmButtonText: '确认', cancelButtonText: '取消', type: 'warning' },
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
        ElMessage.success(`${actionLabels[cmd]}成功`);
        fetchData();
        fetchStats();
    } catch {
        // cancelled or error
    }
}

async function batchAction(action) {
    if (!selectedIds.value.length) return;

    const actionLabels = { suspend: '暂停', restore: '恢复', revoke: '吊销', activate: '激活', deactivate: '停用', freeze: '冻结', blacklist: '加入黑名单', refund: '退款', delete: '删除' };
    const label = actionLabels[action] || action;
    const ids = [...selectedIds.value];

    try {
        await ElMessageBox.confirm(
            `确定要批量${label}选中的 ${ids.length} 个 License 吗？`,
            '批量操作',
            { confirmButtonText: '确认', cancelButtonText: '取消', type: 'warning' },
        );

        const res = await licenseApi.batchOperation({
            license_ids: ids,
            action: action,
        });
        const data = res.data?.data || {};

        ElMessage.success(`批量${label}完成：成功 ${data.processed || 0}，失败 ${data.failed || 0}`);
        selectedIds.value = [];
        fetchData();
        fetchStats();
    } catch (err) {
        if (err.code !== 'CANCEL') {
            ElMessage.error(err.response?.data?.message || '批量操作失败');
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
        renew: '批量续期',
        update_seats: '批量改席位',
        update_metadata: '批量更新元数据',
        add_tags: '批量添加标签',
        transfer: '批量转移租户',
    };
    return titles[batchActionType.value] || '批量操作';
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
                ElMessage.warning('元数据 JSON 格式无效');
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
            message: res.data?.message || '操作完成',
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
        ElMessage.error(err.response?.data?.message || '批量操作失败');
    } finally {
        batchSubmitting.value = false;
    }
}

async function confirmBatchDelete() {
    const ids = [...selectedIds.value];
    try {
        await ElMessageBox.confirm(
            `确定要批量删除选中的 ${ids.length} 个 License 吗？（将移入回收站）`,
            '批量删除',
            { confirmButtonText: '确认', cancelButtonText: '取消', type: 'warning' },
        );

        const res = await licenseApi.batchOperation({ license_ids: ids, action: 'delete' });
        const data = res.data?.data || {};
        ElMessage.success(`批量删除完成：成功 ${data.processed || 0}，失败 ${data.failed || 0}`);
        selectedIds.value = [];
        fetchData();
        fetchStats();
    } catch (err) {
        if (err.code !== 'CANCEL') {
            ElMessage.error(err.response?.data?.message || '批量删除失败');
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
                    if (!res.ok) throw new Error('导出失败');
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
                    ElMessage.success('导出成功');
                })
                .catch(() => ElMessage.error('导出失败'));
        }
    }
}

// ─── 展示辅助 ───
const STATUS_MAP = {
    pending: { type: 'info', label: '待激活' },
    active: { type: 'success', label: '活跃' },
    suspended: { type: 'warning', label: '已暂停' },
    frozen: { type: 'warning', label: '已冻结' },
    expired: { type: 'info', label: '已过期' },
    revoked: { type: 'danger', label: '已吊销' },
    refunded: { type: 'danger', label: '已退款' },
    blacklisted: { type: 'danger', label: '黑名单' },
};

function statusType(status) {
    return STATUS_MAP[status]?.type || 'info';
}
function statusLabel(status) {
    return STATUS_MAP[status]?.label || status;
}
function expiryClass(row) {
    if (!row.expires_at) return '';
    const now = Date.now();
    const expiry = new Date(row.expires_at).getTime();
    if (expiry < now) return 'expired-text';
    const days = (expiry - now) / 86400000;
    return days <= 7 ? 'expiring-soon-text' : '';
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
    background: #ecf5ff;
    border-radius: 4px;
    margin-bottom: 16px;
}
.selected-info { font-size: 14px; color: #409eff; font-weight: 500; }

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
</style>