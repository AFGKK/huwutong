<template>
  <div class="feature-store-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><DataBoard /></el-icon>
        {{ t('feature_store_page.title') }}
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('feature_store_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_groups }}</div>
          <div class="stat-label">{{ t('feature_store_page.stats.feature_groups') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_features }}</div>
          <div class="stat-label">{{ t('feature_store_page.stats.total_features') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.online_features }}</div>
          <div class="stat-label">{{ t('feature_store_page.stats.online_features') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.offline_features }}</div>
          <div class="stat-label">{{ t('feature_store_page.stats.offline_features') }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="consistencyClass">{{ stats.check_summary?.passed || 0 }}/{{ stats.check_summary?.total || 0 }}</div>
          <div class="stat-label">{{ t('feature_store_page.stats.consistency_passed') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 特征组管理 -->
        <el-tab-pane :label="t('feature_store_page.tabs.groups')" name="groups">
          <div class="tab-toolbar">
            <el-select v-model="groupFilter.entity_type" :placeholder="t('feature_store_page.filters.entity_type')" clearable style="width:140px;margin-right:8px" @change="loadGroups">
              <el-option v-for="opt in entityTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-select v-model="groupFilter.status" :placeholder="t('feature_store_page.filters.status')" clearable style="width:120px;margin-right:8px" @change="loadGroups">
              <el-option v-for="opt in groupStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-input v-model="groupFilter.search" :placeholder="t('feature_store_page.filters.search_groups_ph')" clearable style="width:200px" @clear="loadGroups" @keyup.enter="loadGroups" />
            <el-button type="primary" style="margin-left:auto" @click="showCreateGroup">
              <el-icon><Plus /></el-icon> {{ t('feature_store_page.buttons.create_group') }}
            </el-button>
          </div>
          <el-table :data="groups" stripe v-loading="groupsLoading" @row-click="showGroupDetail">
            <el-table-column :label="t('feature_store_page.columns.name')" min-width="160">
              <template #default="{ row }">
                <span style="font-weight:600">{{ row.name }}</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.key')" prop="group_key" width="140" />
            <el-table-column :label="t('feature_store_page.columns.entity_type')" width="100">
              <template #default="{ row }"><el-tag>{{ row.entity_type }}</el-tag></template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.source')" prop="source_type" width="100">
              <template #default="{ row }">{{ row.source_type || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.feature_count')" prop="features_count" width="80" align="center" />
            <el-table-column :label="t('feature_store_page.columns.status')" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.created_at')" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.actions')" width="100" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click.stop="showGroupDetail(row)">{{ t('feature_store_page.buttons.detail') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="groupPagination.total > groupPagination.per_page">
            <el-pagination
              v-model:current-page="groupPagination.current_page"
              :page-size="groupPagination.per_page"
              :total="groupPagination.total"
              layout="prev, pager, next"
              @current-change="loadGroups"
            />
          </div>
        </el-tab-pane>

        <!-- 特征定义 -->
        <el-tab-pane :label="t('feature_store_page.tabs.features')" name="features">
          <div class="tab-toolbar">
            <el-select v-model="featureFilter.group_id" :placeholder="t('feature_store_page.filters.select_group_ph')" clearable filterable style="width:200px;margin-right:8px" @change="loadFeatures">
              <el-option v-for="g in groups" :key="g.id" :label="g.name" :value="g.id" />
            </el-select>
            <el-select v-model="featureFilter.value_type" :placeholder="t('feature_store_page.filters.value_type')" clearable style="width:120px" @change="loadFeatures">
              <el-option v-for="opt in valueTypeFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-button type="primary" style="margin-left:auto" @click="showCreateFeature">
              <el-icon><Plus /></el-icon> {{ t('feature_store_page.buttons.create_feature') }}
            </el-button>
            <el-button @click="showBatchCreateFeatures">
              <el-icon><Upload /></el-icon> {{ t('feature_store_page.buttons.batch_create') }}
            </el-button>
          </div>
          <el-table :data="features" stripe v-loading="featuresLoading">
            <el-table-column :label="t('feature_store_page.columns.feature_name')" prop="name" min-width="150" />
            <el-table-column :label="t('feature_store_page.columns.feature_key')" prop="feature_key" width="140" />
            <el-table-column :label="t('feature_store_page.columns.value_type')" width="80">
              <template #default="{ row }"><el-tag size="small">{{ row.value_type }}</el-tag></template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.online')" width="60" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.is_online" type="success" size="small" effect="dark">{{ t('feature_store_page.yes') }}</el-tag>
                <span v-else class="text-muted">{{ t('feature_store_page.no') }}</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.offline')" width="60" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.is_offline" type="primary" size="small" effect="dark">{{ t('feature_store_page.yes') }}</el-tag>
                <span v-else class="text-muted">{{ t('feature_store_page.no') }}</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.default_value')" prop="default_value" width="100" />
            <el-table-column :label="t('feature_store_page.columns.version')" prop="version" width="60" align="center" />
            <el-table-column :label="t('feature_store_page.columns.actions')" width="200" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="showSetValue(row)">{{ t('feature_store_page.buttons.set_value') }}</el-button>
                <el-button size="small" type="warning" @click="showCheckConsistency(row)">{{ t('feature_store_page.buttons.consistency') }}</el-button>
                <el-button size="small" type="info" @click="showSyncOffline(row)">{{ t('feature_store_page.buttons.sync') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 一致性检查 -->
        <el-tab-pane :label="t('feature_store_page.tabs.consistency')" name="consistency">
          <div class="tab-toolbar">
            <el-select v-model="consistencyFilter.status" :placeholder="t('feature_store_page.filters.status')" clearable style="width:130px;margin-right:8px" @change="loadConsistencyHistory">
              <el-option v-for="opt in checkStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-button type="primary" @click="handleBatchCheckConsistency" :loading="batchChecking">
              <el-icon><DataAnalysis /></el-icon> {{ t('feature_store_page.buttons.batch_check') }}
            </el-button>
            <el-button type="success" @click="handleSyncAllOffline" :loading="syncingAll">
              <el-icon><Download /></el-icon> {{ t('feature_store_page.buttons.sync_all_offline') }}
            </el-button>
          </div>
          <el-table :data="consistencyRecords" stripe v-loading="consistencyLoading">
            <el-table-column :label="t('feature_store_page.columns.feature')" min-width="140">
              <template #default="{ row }">{{ row.definition?.feature_key || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.group')" width="140">
              <template #default="{ row }">{{ row.definition?.group?.name || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.sample_count')" prop="total_samples" width="80" align="center" />
            <el-table-column :label="t('feature_store_page.columns.matched_count')" prop="matched_count" width="80" align="center" />
            <el-table-column :label="t('feature_store_page.columns.mismatched_count')" prop="mismatched_count" width="80" align="center" />
            <el-table-column :label="t('feature_store_page.columns.match_rate')" width="100">
              <template #default="{ row }">{{ row.match_percent }}%</template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.drift_rate')" width="100">
              <template #default="{ row }">
                <span :style="{ color: row.drift_percent > 5 ? '#F56C6C' : '#67C23A' }">{{ row.drift_percent }}%</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.status')" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'passed' ? 'success' : row.status === 'warning' ? 'warning' : 'danger'" size="small">
                  {{ row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('feature_store_page.columns.checked_at')" width="160">
              <template #default="{ row }">{{ formatTime(row.checked_at) }}</template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="consistencyPagination.total > consistencyPagination.per_page">
            <el-pagination
              v-model:current-page="consistencyPagination.current_page"
              :page-size="consistencyPagination.per_page"
              :total="consistencyPagination.total"
              layout="prev, pager, next"
              @current-change="loadConsistencyHistory"
            />
          </div>
        </el-tab-pane>

        <!-- 特征向量查询 -->
        <el-tab-pane :label="t('feature_store_page.tabs.vector_query')" name="vector-query">
          <el-card shadow="hover">
            <el-form :model="vectorForm" label-width="100px" inline>
              <el-form-item :label="t('feature_store_page.filters.entity_type')">
                <el-select v-model="vectorForm.entity_type" style="width:160px">
                  <el-option v-for="opt in entityTypeSelectOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
              </el-form-item>
              <el-form-item :label="t('feature_store_page.vector.entity_id')">
                <el-input v-model="vectorForm.entity_id" :placeholder="t('feature_store_page.vector.entity_id_ph')" style="width:200px" />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="handleQueryVector" :loading="vectorLoading">
                  <el-icon><Search /></el-icon> {{ t('feature_store_page.buttons.query') }}
                </el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <el-card v-if="vectorResult && Object.keys(vectorResult).length" shadow="hover" style="margin-top:16px">
            <template #header><span>{{ t('feature_store_page.vector.result_title') }}</span></template>
            <el-table :data="vectorTableData" stripe>
              <el-table-column :label="t('feature_store_page.columns.vector_key')" prop="key" width="200" />
              <el-table-column :label="t('feature_store_page.columns.value')" prop="value" min-width="200" />
              <el-table-column :label="t('feature_store_page.columns.type')" prop="type" width="100" />
            </el-table>
            <div style="margin-top:12px">
              <el-input type="textarea" :model-value="JSON.stringify(vectorResult, null, 2)" :rows="8" readonly />
            </div>
          </el-card>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 创建特征组对话框 -->
    <el-dialog v-model="createGroupVisible" :title="t('feature_store_page.dialogs.create_group')" width="520px">
      <el-form ref="createGroupFormRef" :model="createGroupForm" :rules="createGroupRules" label-width="100px">
        <el-form-item :label="t('feature_store_page.form.name')" prop="name">
          <el-input v-model="createGroupForm.name" :placeholder="t('feature_store_page.form.group_name_ph')" />
        </el-form-item>
        <el-form-item :label="t('feature_store_page.form.key')" prop="group_key">
          <el-input v-model="createGroupForm.group_key" :placeholder="t('feature_store_page.form.auto_generate_ph')" />
        </el-form-item>
        <el-form-item :label="t('feature_store_page.form.entity_type')" prop="entity_type">
          <el-select v-model="createGroupForm.entity_type" style="width:100%">
            <el-option v-for="opt in entityTypeSelectOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('feature_store_page.form.source_type')" prop="source_type">
          <el-select v-model="createGroupForm.source_type" style="width:100%" clearable>
            <el-option v-for="opt in sourceTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('feature_store_page.form.description')" prop="description">
          <el-input v-model="createGroupForm.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createGroupVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreateGroup" :loading="submitting">{{ t('feature_store_page.buttons.create_submit') }}</el-button>
      </template>
    </el-dialog>

    <!-- 创建特征对话框 -->
    <el-dialog v-model="createFeatureVisible" :title="t('feature_store_page.dialogs.create_feature')" width="520px">
      <el-form ref="createFeatureFormRef" :model="createFeatureForm" :rules="createFeatureRules" label-width="100px">
        <el-form-item :label="t('feature_store_page.form.parent_group')" prop="group_id">
          <el-select v-model="createFeatureForm.group_id" style="width:100%" filterable>
            <el-option v-for="g in groups" :key="g.id" :label="g.name" :value="g.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('feature_store_page.form.name')" prop="name">
          <el-input v-model="createFeatureForm.name" :placeholder="t('feature_store_page.form.feature_name_ph')" />
        </el-form-item>
        <el-form-item :label="t('feature_store_page.form.key')" prop="feature_key">
          <el-input v-model="createFeatureForm.feature_key" :placeholder="t('feature_store_page.form.feature_key_ph')" />
        </el-form-item>
        <el-form-item :label="t('feature_store_page.columns.value_type')" prop="value_type">
          <el-select v-model="createFeatureForm.value_type" style="width:100%">
            <el-option v-for="vt in valueTypes" :key="vt" :label="vt" :value="vt" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('feature_store_page.columns.online')" prop="is_online">
          <el-switch v-model="createFeatureForm.is_online" />
        </el-form-item>
        <el-form-item :label="t('feature_store_page.columns.offline')" prop="is_offline">
          <el-switch v-model="createFeatureForm.is_offline" />
        </el-form-item>
        <el-form-item :label="t('feature_store_page.columns.default_value')" prop="default_value">
          <el-input v-model="createFeatureForm.default_value" />
        </el-form-item>
        <el-form-item :label="t('feature_store_page.form.description')" prop="description">
          <el-input v-model="createFeatureForm.description" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createFeatureVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreateFeature" :loading="submitting">{{ t('feature_store_page.buttons.create_submit') }}</el-button>
      </template>
    </el-dialog>

    <!-- 批量创建特征对话框 -->
    <el-dialog v-model="batchCreateVisible" :title="t('feature_store_page.dialogs.batch_create')" width="600px">
      <el-form :model="batchCreateForm" label-width="100px">
        <el-form-item :label="t('feature_store_page.form.parent_group')" prop="group_id">
          <el-select v-model="batchCreateForm.group_id" style="width:100%" filterable>
            <el-option v-for="g in groups" :key="g.id" :label="g.name" :value="g.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('feature_store_page.form.feature_list')">
          <el-input
            v-model="batchCreateForm.jsonInput"
            type="textarea"
            :rows="10"
            :placeholder="t('feature_store_page.form.batch_json_ph')"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="batchCreateVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleBatchCreateFeatures" :loading="submitting">{{ t('feature_store_page.buttons.batch_create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 设置特征值对话框 -->
    <el-dialog v-model="setValueVisible" :title="t('feature_store_page.dialogs.set_value')" width="480px">
      <el-form :model="setValueForm" label-width="100px">
        <el-form-item :label="t('feature_store_page.form.feature_label')">
          <strong>{{ setValueFeature?.feature_key }}</strong>
        </el-form-item>
        <el-form-item :label="t('feature_store_page.vector.entity_id')" prop="entity_id">
          <el-input v-model="setValueForm.entity_id" :placeholder="t('feature_store_page.vector.entity_id_ph')" />
        </el-form-item>
        <el-form-item :label="t('feature_store_page.columns.value')" prop="value">
          <el-input v-model="setValueForm.value" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item :label="t('feature_store_page.form.ttl_seconds')">
          <el-input-number v-model="setValueForm.ttl" :min="60" :max="86400" :step="60" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="setValueVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleSetValue" :loading="submitting">{{ t('feature_store_page.buttons.set') }}</el-button>
      </template>
    </el-dialog>

    <!-- 一致性检查对话框 -->
    <el-dialog v-model="checkConsistencyVisible" :title="t('feature_store_page.dialogs.consistency')" width="480px">
      <el-form :model="checkConsistencyForm" label-width="100px">
        <el-form-item :label="t('feature_store_page.form.feature_label')">
          <strong>{{ checkConsistencyFeature?.feature_key }}</strong>
        </el-form-item>
        <el-form-item :label="t('feature_store_page.form.sample_count')">
          <el-input-number v-model="checkConsistencyForm.sample_size" :min="10" :max="5000" :step="100" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="checkConsistencyVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="warning" @click="handleCheckConsistency" :loading="submitting">{{ t('feature_store_page.buttons.run_check') }}</el-button>
      </template>
    </el-dialog>

    <!-- 离线同步对话框 -->
    <el-dialog v-model="syncOfflineVisible" :title="t('feature_store_page.dialogs.sync_offline')" width="400px">
      <p>{{ t('feature_store_page.form.sync_offline_confirm', { key: syncOfflineFeature?.feature_key }) }}</p>
      <template #footer>
        <el-button @click="syncOfflineVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="success" @click="handleSyncOffline" :loading="submitting">{{ t('feature_store_page.buttons.sync') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { DataBoard, Refresh, Plus, Upload, DataAnalysis, Download, Search } from '@element-plus/icons-vue';
import featureStoreApi from '@/api/featureStore';

const { t, locale } = useI18n();

const loading = ref(false);
const submitting = ref(false);
const batchChecking = ref(false);
const syncingAll = ref(false);
const vectorLoading = ref(false);
const activeTab = ref('groups');
const valueTypes = ['int', 'float', 'double', 'string', 'boolean', 'json', 'vector'];

const entityTypeSelectOptions = computed(() => [
  { value: 'customer', label: t('feature_store_page.entity_types.customer') },
  { value: 'license', label: t('feature_store_page.entity_types.license') },
  { value: 'product', label: t('feature_store_page.entity_types.product') },
  { value: 'device', label: t('feature_store_page.entity_types.device') },
]);

const entityTypeOptions = computed(() => [
  { value: '', label: t('feature_store_page.filters.all') },
  ...entityTypeSelectOptions.value,
]);

const groupStatusOptions = computed(() => [
  { value: '', label: t('feature_store_page.filters.all') },
  { value: 'active', label: t('feature_store_page.group_status.active') },
  { value: 'inactive', label: t('feature_store_page.group_status.inactive') },
  { value: 'deprecated', label: t('feature_store_page.group_status.deprecated') },
]);

const checkStatusOptions = computed(() => [
  { value: '', label: t('feature_store_page.filters.all') },
  { value: 'passed', label: t('feature_store_page.check_status.passed') },
  { value: 'warning', label: t('feature_store_page.check_status.warning') },
  { value: 'failed', label: t('feature_store_page.check_status.failed') },
]);

const valueTypeFilterOptions = computed(() => [
  { value: '', label: t('feature_store_page.filters.all') },
  ...valueTypes.map((vt) => ({ value: vt, label: vt })),
]);

const sourceTypeOptions = computed(() => [
  { value: 'manual', label: t('feature_store_page.source_types.manual') },
  { value: 'sql_query', label: t('feature_store_page.source_types.sql_query') },
  { value: 'api_endpoint', label: t('feature_store_page.source_types.api_endpoint') },
  { value: 'kafka_topic', label: t('feature_store_page.source_types.kafka_topic') },
  { value: 'file_upload', label: t('feature_store_page.source_types.file_upload') },
  { value: 'model_output', label: t('feature_store_page.source_types.model_output') },
]);

// 仪表盘
const stats = ref({
  total_groups: 0, total_features: 0, online_features: 0, offline_features: 0,
  check_summary: {}, recent_groups: [],
});

const consistencyClass = computed(() => {
  const s = stats.value.check_summary;
  if (!s || !s.total) return '';
  return s.failed > 0 ? 'stat-danger' : s.warning > 0 ? 'stat-warning' : 'stat-success';
});

// 特征组
const groups = ref([]);
const groupsLoading = ref(false);
const groupFilter = reactive({ entity_type: '', status: '', search: '' });
const groupPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 特征定义
const features = ref([]);
const featuresLoading = ref(false);
const featureFilter = reactive({ group_id: '', value_type: '' });

// 一致性
const consistencyRecords = ref([]);
const consistencyLoading = ref(false);
const consistencyFilter = reactive({ status: '' });
const consistencyPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 特征向量
const vectorForm = reactive({ entity_type: 'customer', entity_id: '' });
const vectorResult = ref(null);
const vectorTableData = computed(() => {
  if (!vectorResult.value) return [];
  return Object.entries(vectorResult.value).map(([key, value]) => ({
    key, value: typeof value === 'object' ? JSON.stringify(value) : String(value),
    type: Array.isArray(value) ? 'vector' : typeof value,
  }));
});

// 创建特征组
const createGroupVisible = ref(false);
const createGroupFormRef = ref(null);
const createGroupForm = reactive({ name: '', group_key: '', entity_type: 'customer', source_type: '', description: '' });
const createGroupRules = computed(() => ({
  name: [{ required: true, message: t('feature_store_page.rules.name_required') }],
  entity_type: [{ required: true, message: t('feature_store_page.rules.entity_type_required') }],
}));

// 创建特征
const createFeatureVisible = ref(false);
const createFeatureFormRef = ref(null);
const createFeatureForm = reactive({
  group_id: '', name: '', feature_key: '', value_type: 'float',
  is_online: true, is_offline: true, default_value: '', description: '',
});
const createFeatureRules = computed(() => ({
  group_id: [{ required: true, message: t('feature_store_page.rules.group_required') }],
  name: [{ required: true, message: t('feature_store_page.rules.feature_name_required') }],
  feature_key: [{ required: true, message: t('feature_store_page.rules.feature_key_required') }],
  value_type: [{ required: true, message: t('feature_store_page.rules.value_type_required') }],
}));

// 批量创建
const batchCreateVisible = ref(false);
const batchCreateForm = reactive({ group_id: '', jsonInput: '' });

// 设置值
const setValueVisible = ref(false);
const setValueFeature = ref(null);
const setValueForm = reactive({ entity_id: '', value: '', ttl: 3600 });

// 一致性检查
const checkConsistencyVisible = ref(false);
const checkConsistencyFeature = ref(null);
const checkConsistencyForm = reactive({ sample_size: 1000 });

// 离线同步
const syncOfflineVisible = ref(false);
const syncOfflineFeature = ref(null);

onMounted(() => {
  refreshAll();
});

async function refreshAll() {
  loading.value = true;
  try {
    const res = await featureStoreApi.dashboard();
    stats.value = res.data;
  } finally {
    loading.value = false;
  }
  loadGroups();
  loadConsistencyHistory();
}

// ═══════ 特征组 ═══════

async function loadGroups() {
  groupsLoading.value = true;
  try {
    const res = await featureStoreApi.listGroups({ ...groupFilter, page: groupPagination.current_page });
    groups.value = res.data.data || [];
    Object.assign(groupPagination, res.data);
  } finally {
    groupsLoading.value = false;
  }
}

function showCreateGroup() {
  createGroupForm.name = '';
  createGroupForm.group_key = '';
  createGroupForm.entity_type = 'customer';
  createGroupForm.source_type = '';
  createGroupForm.description = '';
  createGroupVisible.value = true;
}

async function handleCreateGroup() {
  const valid = await createGroupFormRef.value.validate().catch(() => false);
  if (!valid) return;
  submitting.value = true;
  try {
    await featureStoreApi.createGroup(createGroupForm);
    ElMessage.success(t('feature_store_page.messages.group_created'));
    createGroupVisible.value = false;
    loadGroups();
    refreshAll();
  } finally {
    submitting.value = false;
  }
}

function showGroupDetail(row) {
  featureFilter.group_id = row.id;
  activeTab.value = 'features';
  loadFeatures();
}

// ═══════ 特征定义 ═══════

async function loadFeatures() {
  if (!featureFilter.group_id) {
    features.value = [];
    return;
  }
  featuresLoading.value = true;
  try {
    const res = await featureStoreApi.listFeatures(featureFilter.group_id, featureFilter);
    features.value = res.data || [];
  } finally {
    featuresLoading.value = false;
  }
}

function showCreateFeature() {
  createFeatureForm.group_id = featureFilter.group_id || (groups.value[0]?.id || '');
  createFeatureForm.name = '';
  createFeatureForm.feature_key = '';
  createFeatureForm.value_type = 'float';
  createFeatureForm.is_online = true;
  createFeatureForm.is_offline = true;
  createFeatureForm.default_value = '';
  createFeatureForm.description = '';
  createFeatureVisible.value = true;
}

async function handleCreateFeature() {
  const valid = await createFeatureFormRef.value.validate().catch(() => false);
  if (!valid) return;
  submitting.value = true;
  try {
    await featureStoreApi.createFeature(createFeatureForm.group_id, createFeatureForm);
    ElMessage.success(t('feature_store_page.messages.feature_created'));
    createFeatureVisible.value = false;
    loadFeatures();
  } finally {
    submitting.value = false;
  }
}

function showBatchCreateFeatures() {
  batchCreateForm.group_id = featureFilter.group_id || (groups.value[0]?.id || '');
  batchCreateForm.jsonInput = '';
  batchCreateVisible.value = true;
}

async function handleBatchCreateFeatures() {
  let features;
  try {
    features = JSON.parse(batchCreateForm.jsonInput);
    if (!Array.isArray(features)) throw new Error(t('feature_store_page.messages.must_be_array'));
  } catch (e) {
    ElMessage.error(t('feature_store_page.messages.json_format_error', { msg: e.message }));
    return;
  }
  if (!batchCreateForm.group_id) {
    ElMessage.warning(t('feature_store_page.messages.select_group'));
    return;
  }
  submitting.value = true;
  try {
    await featureStoreApi.batchCreateFeatures(batchCreateForm.group_id, features);
    ElMessage.success(t('feature_store_page.messages.batch_created', { count: features.length }));
    batchCreateVisible.value = false;
    loadFeatures();
  } finally {
    submitting.value = false;
  }
}

// ═══════ 设置特征值 ═══════

function showSetValue(feature) {
  setValueFeature.value = feature;
  setValueForm.entity_id = '';
  setValueForm.value = '';
  setValueForm.ttl = 3600;
  setValueVisible.value = true;
}

async function handleSetValue() {
  if (!setValueForm.entity_id) {
    ElMessage.warning(t('feature_store_page.messages.entity_id_required'));
    return;
  }
  submitting.value = true;
  try {
    await featureStoreApi.setValue(setValueFeature.value.id, setValueForm);
    ElMessage.success(t('feature_store_page.messages.value_set'));
    setValueVisible.value = false;
  } finally {
    submitting.value = false;
  }
}

// ═══════ 一致性检查 ═══════

function showCheckConsistency(feature) {
  checkConsistencyFeature.value = feature;
  checkConsistencyForm.sample_size = 1000;
  checkConsistencyVisible.value = true;
}

async function handleCheckConsistency() {
  submitting.value = true;
  try {
    const res = await featureStoreApi.checkConsistency(checkConsistencyFeature.value.id, checkConsistencyForm.sample_size);
    ElMessage.success(res.message || t('feature_store_page.messages.consistency_done'));
    checkConsistencyVisible.value = false;
    loadConsistencyHistory();
  } finally {
    submitting.value = false;
  }
}

async function handleBatchCheckConsistency() {
  batchChecking.value = true;
  try {
    const res = await featureStoreApi.batchCheckConsistency();
    ElMessage.success(res.message || t('feature_store_page.messages.batch_check_done'));
    loadConsistencyHistory();
  } finally {
    batchChecking.value = false;
  }
}

async function loadConsistencyHistory() {
  consistencyLoading.value = true;
  try {
    const res = await featureStoreApi.consistencyHistory({ ...consistencyFilter, page: consistencyPagination.current_page });
    consistencyRecords.value = res.data.data || [];
    Object.assign(consistencyPagination, res.data);
  } finally {
    consistencyLoading.value = false;
  }
}

// ═══════ 离线同步 ═══════

function showSyncOffline(feature) {
  syncOfflineFeature.value = feature;
  syncOfflineVisible.value = true;
}

async function handleSyncOffline() {
  submitting.value = true;
  try {
    const res = await featureStoreApi.syncOffline(syncOfflineFeature.value.id);
    ElMessage.success(res.message || t('feature_store_page.messages.sync_done'));
    syncOfflineVisible.value = false;
  } finally {
    submitting.value = false;
  }
}

async function handleSyncAllOffline() {
  syncingAll.value = true;
  try {
    const res = await featureStoreApi.syncAllOffline();
    ElMessage.success(res.message || t('feature_store_page.messages.sync_all_done'));
  } finally {
    syncingAll.value = false;
  }
}

// ═══════ 特征向量查询 ═══════

async function handleQueryVector() {
  if (!vectorForm.entity_id) {
    ElMessage.warning(t('feature_store_page.messages.entity_id_required'));
    return;
  }
  vectorLoading.value = true;
  try {
    const res = await featureStoreApi.getFeatureVector(vectorForm);
    vectorResult.value = res.data;
  } finally {
    vectorLoading.value = false;
  }
}

// ═══════ 工具函数 ═══════

function formatTime(time) {
  if (!time) return '—';
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return new Date(time).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.feature-store-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; }
.stat-danger { color: #F56C6C; }
.stat-warning { color: #E6A23C; }
.stat-primary { color: #0f172a; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.text-muted { color: #C0C4CC; }
</style>
