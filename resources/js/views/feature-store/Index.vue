<template>
  <div class="feature-store-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><DataBoard /></el-icon>
        AI 特征工程平台
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_groups }}</div>
          <div class="stat-label">特征组</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_features }}</div>
          <div class="stat-label">特征总数</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.online_features }}</div>
          <div class="stat-label">在线特征</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.offline_features }}</div>
          <div class="stat-label">离线特征</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="consistencyClass">{{ stats.check_summary?.passed || 0 }}/{{ stats.check_summary?.total || 0 }}</div>
          <div class="stat-label">一致性通过</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 特征组管理 -->
        <el-tab-pane label="特征组管理" name="groups">
          <div class="tab-toolbar">
            <el-select v-model="groupFilter.entity_type" placeholder="实体类型" clearable style="width:140px;margin-right:8px" @change="loadGroups">
              <el-option label="全部" value="" />
              <el-option label="客户" value="customer" />
              <el-option label="License" value="license" />
              <el-option label="产品" value="product" />
              <el-option label="设备" value="device" />
            </el-select>
            <el-select v-model="groupFilter.status" placeholder="状态" clearable style="width:120px;margin-right:8px" @change="loadGroups">
              <el-option label="全部" value="" />
              <el-option label="活跃" value="active" />
              <el-option label="停用" value="inactive" />
              <el-option label="已弃用" value="deprecated" />
            </el-select>
            <el-input v-model="groupFilter.search" placeholder="搜索特征组..." clearable style="width:200px" @clear="loadGroups" @keyup.enter="loadGroups" />
            <el-button type="primary" style="margin-left:auto" @click="showCreateGroup">
              <el-icon><Plus /></el-icon> 新建特征组
            </el-button>
          </div>
          <el-table :data="groups" stripe v-loading="groupsLoading" @row-click="showGroupDetail">
            <el-table-column label="名称" min-width="160">
              <template #default="{ row }">
                <span style="font-weight:600">{{ row.name }}</span>
              </template>
            </el-table-column>
            <el-table-column label="标识" prop="group_key" width="140" />
            <el-table-column label="实体类型" width="100">
              <template #default="{ row }"><el-tag>{{ row.entity_type }}</el-tag></template>
            </el-table-column>
            <el-table-column label="来源" prop="source_type" width="100">
              <template #default="{ row }">{{ row.source_type || '—' }}</template>
            </el-table-column>
            <el-table-column label="特征数" prop="features_count" width="80" align="center" />
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="创建时间" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="100" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click.stop="showGroupDetail(row)">详情</el-button>
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
        <el-tab-pane label="特征定义" name="features">
          <div class="tab-toolbar">
            <el-select v-model="featureFilter.group_id" placeholder="选择特征组" clearable filterable style="width:200px;margin-right:8px" @change="loadFeatures">
              <el-option v-for="g in groups" :key="g.id" :label="g.name" :value="g.id" />
            </el-select>
            <el-select v-model="featureFilter.value_type" placeholder="值类型" clearable style="width:120px" @change="loadFeatures">
              <el-option label="全部" value="" />
              <el-option label="int" value="int" />
              <el-option label="float" value="float" />
              <el-option label="double" value="double" />
              <el-option label="string" value="string" />
              <el-option label="boolean" value="boolean" />
              <el-option label="json" value="json" />
              <el-option label="vector" value="vector" />
            </el-select>
            <el-button type="primary" style="margin-left:auto" @click="showCreateFeature">
              <el-icon><Plus /></el-icon> 新建特征
            </el-button>
            <el-button @click="showBatchCreateFeatures">
              <el-icon><Upload /></el-icon> 批量创建
            </el-button>
          </div>
          <el-table :data="features" stripe v-loading="featuresLoading">
            <el-table-column label="特征名称" prop="name" min-width="150" />
            <el-table-column label="特征标识" prop="feature_key" width="140" />
            <el-table-column label="值类型" width="80">
              <template #default="{ row }"><el-tag size="small">{{ row.value_type }}</el-tag></template>
            </el-table-column>
            <el-table-column label="在线" width="60" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.is_online" type="success" size="small" effect="dark">是</el-tag>
                <span v-else class="text-muted">否</span>
              </template>
            </el-table-column>
            <el-table-column label="离线" width="60" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.is_offline" type="primary" size="small" effect="dark">是</el-tag>
                <span v-else class="text-muted">否</span>
              </template>
            </el-table-column>
            <el-table-column label="默认值" prop="default_value" width="100" />
            <el-table-column label="版本" prop="version" width="60" align="center" />
            <el-table-column label="操作" width="200" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="showSetValue(row)">设值</el-button>
                <el-button size="small" type="warning" @click="showCheckConsistency(row)">一致性</el-button>
                <el-button size="small" type="info" @click="showSyncOffline(row)">同步</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 一致性检查 -->
        <el-tab-pane label="一致性检查" name="consistency">
          <div class="tab-toolbar">
            <el-select v-model="consistencyFilter.status" placeholder="状态" clearable style="width:130px;margin-right:8px" @change="loadConsistencyHistory">
              <el-option label="全部" value="" />
              <el-option label="通过" value="passed" />
              <el-option label="警告" value="warning" />
              <el-option label="失败" value="failed" />
            </el-select>
            <el-button type="primary" @click="handleBatchCheckConsistency" :loading="batchChecking">
              <el-icon><DataAnalysis /></el-icon> 批量检查
            </el-button>
            <el-button type="success" @click="handleSyncAllOffline" :loading="syncingAll">
              <el-icon><Download /></el-icon> 全部同步离线
            </el-button>
          </div>
          <el-table :data="consistencyRecords" stripe v-loading="consistencyLoading">
            <el-table-column label="特征" min-width="140">
              <template #default="{ row }">{{ row.definition?.feature_key || '—' }}</template>
            </el-table-column>
            <el-table-column label="特征组" width="140">
              <template #default="{ row }">{{ row.definition?.group?.name || '—' }}</template>
            </el-table-column>
            <el-table-column label="样本数" prop="total_samples" width="80" align="center" />
            <el-table-column label="匹配数" prop="matched_count" width="80" align="center" />
            <el-table-column label="不匹配数" prop="mismatched_count" width="80" align="center" />
            <el-table-column label="匹配率" width="100">
              <template #default="{ row }">{{ row.match_percent }}%</template>
            </el-table-column>
            <el-table-column label="漂移率" width="100">
              <template #default="{ row }">
                <span :style="{ color: row.drift_percent > 5 ? '#F56C6C' : '#67C23A' }">{{ row.drift_percent }}%</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'passed' ? 'success' : row.status === 'warning' ? 'warning' : 'danger'" size="small">
                  {{ row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="检查时间" width="160">
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
        <el-tab-pane label="特征向量查询" name="vector-query">
          <el-card shadow="hover">
            <el-form :model="vectorForm" label-width="100px" inline>
              <el-form-item label="实体类型">
                <el-select v-model="vectorForm.entity_type" style="width:160px">
                  <el-option label="客户" value="customer" />
                  <el-option label="License" value="license" />
                  <el-option label="产品" value="product" />
                  <el-option label="设备" value="device" />
                </el-select>
              </el-form-item>
              <el-form-item label="实体ID">
                <el-input v-model="vectorForm.entity_id" placeholder="输入实体ID" style="width:200px" />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="handleQueryVector" :loading="vectorLoading">
                  <el-icon><Search /></el-icon> 查询
                </el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <el-card v-if="vectorResult && Object.keys(vectorResult).length" shadow="hover" style="margin-top:16px">
            <template #header><span>特征向量结果</span></template>
            <el-table :data="vectorTableData" stripe>
              <el-table-column label="特征键" prop="key" width="200" />
              <el-table-column label="值" prop="value" min-width="200" />
              <el-table-column label="类型" prop="type" width="100" />
            </el-table>
            <div style="margin-top:12px">
              <el-input type="textarea" :model-value="JSON.stringify(vectorResult, null, 2)" :rows="8" readonly />
            </div>
          </el-card>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 创建特征组对话框 -->
    <el-dialog v-model="createGroupVisible" title="新建特征组" width="520px">
      <el-form ref="createGroupFormRef" :model="createGroupForm" :rules="createGroupRules" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="createGroupForm.name" placeholder="特征组名称" />
        </el-form-item>
        <el-form-item label="标识" prop="group_key">
          <el-input v-model="createGroupForm.group_key" placeholder="留空自动生成" />
        </el-form-item>
        <el-form-item label="实体类型" prop="entity_type">
          <el-select v-model="createGroupForm.entity_type" style="width:100%">
            <el-option label="客户" value="customer" />
            <el-option label="License" value="license" />
            <el-option label="产品" value="product" />
            <el-option label="设备" value="device" />
          </el-select>
        </el-form-item>
        <el-form-item label="来源类型" prop="source_type">
          <el-select v-model="createGroupForm.source_type" style="width:100%" clearable>
            <el-option label="手动录入" value="manual" />
            <el-option label="SQL查询" value="sql_query" />
            <el-option label="API端点" value="api_endpoint" />
            <el-option label="Kafka主题" value="kafka_topic" />
            <el-option label="文件上传" value="file_upload" />
            <el-option label="模型输出" value="model_output" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="createGroupForm.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createGroupVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreateGroup" :loading="submitting">创建</el-button>
      </template>
    </el-dialog>

    <!-- 创建特征对话框 -->
    <el-dialog v-model="createFeatureVisible" title="新建特征" width="520px">
      <el-form ref="createFeatureFormRef" :model="createFeatureForm" :rules="createFeatureRules" label-width="100px">
        <el-form-item label="所属组" prop="group_id">
          <el-select v-model="createFeatureForm.group_id" style="width:100%" filterable>
            <el-option v-for="g in groups" :key="g.id" :label="g.name" :value="g.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="名称" prop="name">
          <el-input v-model="createFeatureForm.name" placeholder="特征名称" />
        </el-form-item>
        <el-form-item label="标识" prop="feature_key">
          <el-input v-model="createFeatureForm.feature_key" placeholder="特征标识" />
        </el-form-item>
        <el-form-item label="值类型" prop="value_type">
          <el-select v-model="createFeatureForm.value_type" style="width:100%">
            <el-option v-for="t in valueTypes" :key="t" :label="t" :value="t" />
          </el-select>
        </el-form-item>
        <el-form-item label="在线" prop="is_online">
          <el-switch v-model="createFeatureForm.is_online" />
        </el-form-item>
        <el-form-item label="离线" prop="is_offline">
          <el-switch v-model="createFeatureForm.is_offline" />
        </el-form-item>
        <el-form-item label="默认值" prop="default_value">
          <el-input v-model="createFeatureForm.default_value" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="createFeatureForm.description" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createFeatureVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreateFeature" :loading="submitting">创建</el-button>
      </template>
    </el-dialog>

    <!-- 批量创建特征对话框 -->
    <el-dialog v-model="batchCreateVisible" title="批量创建特征" width="600px">
      <el-form :model="batchCreateForm" label-width="100px">
        <el-form-item label="所属组" prop="group_id">
          <el-select v-model="batchCreateForm.group_id" style="width:100%" filterable>
            <el-option v-for="g in groups" :key="g.id" :label="g.name" :value="g.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="特征列表">
          <el-input
            v-model="batchCreateForm.jsonInput"
            type="textarea"
            :rows="10"
            placeholder='[
  {"name":"特征1","feature_key":"feature_1","value_type":"float","description":"说明"},
  {"name":"特征2","feature_key":"feature_2","value_type":"int"}
]'
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="batchCreateVisible = false">取消</el-button>
        <el-button type="primary" @click="handleBatchCreateFeatures" :loading="submitting">批量创建</el-button>
      </template>
    </el-dialog>

    <!-- 设置特征值对话框 -->
    <el-dialog v-model="setValueVisible" title="设置特征值" width="480px">
      <el-form :model="setValueForm" label-width="100px">
        <el-form-item label="特征">
          <strong>{{ setValueFeature?.feature_key }}</strong>
        </el-form-item>
        <el-form-item label="实体ID" prop="entity_id">
          <el-input v-model="setValueForm.entity_id" placeholder="输入实体ID" />
        </el-form-item>
        <el-form-item label="值" prop="value">
          <el-input v-model="setValueForm.value" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="TTL(秒)">
          <el-input-number v-model="setValueForm.ttl" :min="60" :max="86400" :step="60" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="setValueVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSetValue" :loading="submitting">设置</el-button>
      </template>
    </el-dialog>

    <!-- 一致性检查对话框 -->
    <el-dialog v-model="checkConsistencyVisible" title="一致性检查" width="480px">
      <el-form :model="checkConsistencyForm" label-width="100px">
        <el-form-item label="特征">
          <strong>{{ checkConsistencyFeature?.feature_key }}</strong>
        </el-form-item>
        <el-form-item label="样本数">
          <el-input-number v-model="checkConsistencyForm.sample_size" :min="10" :max="5000" :step="100" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="checkConsistencyVisible = false">取消</el-button>
        <el-button type="warning" @click="handleCheckConsistency" :loading="submitting">执行检查</el-button>
      </template>
    </el-dialog>

    <!-- 离线同步对话框 -->
    <el-dialog v-model="syncOfflineVisible" title="同步到离线存储" width="400px">
      <p>确定将特征 <strong>{{ syncOfflineFeature?.feature_key }}</strong> 的在线数据同步到离线存储？</p>
      <template #footer>
        <el-button @click="syncOfflineVisible = false">取消</el-button>
        <el-button type="success" @click="handleSyncOffline" :loading="submitting">同步</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage } from 'element-plus';
import { DataBoard, Refresh, Plus, Upload, DataAnalysis, Download, Search } from '@element-plus/icons-vue';
import featureStoreApi from '@/api/featureStore';

const loading = ref(false);
const submitting = ref(false);
const batchChecking = ref(false);
const syncingAll = ref(false);
const vectorLoading = ref(false);
const activeTab = ref('groups');
const valueTypes = ['int', 'float', 'double', 'string', 'boolean', 'json', 'vector'];

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
const createGroupRules = {
  name: [{ required: true, message: '请输入名称' }],
  entity_type: [{ required: true, message: '请选择实体类型' }],
};

// 创建特征
const createFeatureVisible = ref(false);
const createFeatureFormRef = ref(null);
const createFeatureForm = reactive({
  group_id: '', name: '', feature_key: '', value_type: 'float',
  is_online: true, is_offline: true, default_value: '', description: '',
});
const createFeatureRules = {
  group_id: [{ required: true, message: '请选择特征组' }],
  name: [{ required: true, message: '请输入特征名称' }],
  feature_key: [{ required: true, message: '请输入特征标识' }],
  value_type: [{ required: true, message: '请选择值类型' }],
};

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
    ElMessage.success('特征组创建成功');
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
    ElMessage.success('特征创建成功');
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
    if (!Array.isArray(features)) throw new Error('必须是数组');
  } catch (e) {
    ElMessage.error('JSON 格式错误: ' + e.message);
    return;
  }
  if (!batchCreateForm.group_id) {
    ElMessage.warning('请选择特征组');
    return;
  }
  submitting.value = true;
  try {
    await featureStoreApi.batchCreateFeatures(batchCreateForm.group_id, features);
    ElMessage.success(`成功创建 ${features.length} 个特征`);
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
    ElMessage.warning('请输入实体ID');
    return;
  }
  submitting.value = true;
  try {
    await featureStoreApi.setValue(setValueFeature.value.id, setValueForm);
    ElMessage.success('特征值已设置');
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
    ElMessage.success(res.message || '一致性检查完成');
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
    ElMessage.success(res.message || '批量检查完成');
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
    ElMessage.success(res.message || '同步完成');
    syncOfflineVisible.value = false;
  } finally {
    submitting.value = false;
  }
}

async function handleSyncAllOffline() {
  syncingAll.value = true;
  try {
    const res = await featureStoreApi.syncAllOffline();
    ElMessage.success(res.message || '全部同步完成');
  } finally {
    syncingAll.value = false;
  }
}

// ═══════ 特征向量查询 ═══════

async function handleQueryVector() {
  if (!vectorForm.entity_id) {
    ElMessage.warning('请输入实体ID');
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

function formatTime(t) {
  if (!t) return '—';
  return new Date(t).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
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
.stat-primary { color: #409EFF; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.text-muted { color: #C0C4CC; }
</style>
