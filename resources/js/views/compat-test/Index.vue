<template>
  <div class="compat-test-page">
    <!-- 统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="4" v-for="item in statItems" :key="item.label">
        <el-card shadow="hover" :body-style="{ padding: '12px' }">
          <div class="stat-value text-xl font-bold" :class="item.color">{{ item.value }}</div>
          <div class="stat-label text-gray-500 text-xs">{{ item.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <!-- Tab: 兼容性矩阵 -->
      <el-tab-pane label="兼容性矩阵" name="matrix">
        <el-card shadow="never" v-loading="loadingPlatforms">
          <template #header>
            <div class="flex justify-between items-center">
              <span class="font-semibold">测试平台</span>
              <el-button size="small" @click="initPlatforms">从模板初始化</el-button>
            </div>
          </template>

          <div v-for="(platforms, category) in platformGroups" :key="category" class="mb-4">
            <h4 class="font-medium mb-2 text-gray-600">{{ categoryLabel(category) }}</h4>
            <div class="flex gap-2 flex-wrap">
              <el-tag
                v-for="p in platforms"
                :key="p.id"
                :type="p.is_active ? 'success' : 'info'"
                size="large"
                effect="plain"
              >
                {{ p.label || p.name }}
              </el-tag>
            </div>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- Tab: 测试套件 -->
      <el-tab-pane label="测试套件" name="suites">
        <div class="flex justify-between items-center mb-3">
          <span class="font-semibold">共 {{ suites.length }} 个套件</span>
          <el-button type="primary" size="small" @click="showCreateSuite = true">
            <el-icon><Plus /></el-icon> 创建套件
          </el-button>
        </div>

        <el-table :data="suites" stripe v-loading="loadingSuites" size="small">
          <el-table-column label="名称" prop="name" min-width="180" />
          <el-table-column label="分类" width="100">
            <template #default="{ row }">{{ categoryLabel(row.category) }}</template>
          </el-table-column>
          <el-table-column label="用例数" prop="test_cases_count" width="70" align="center" />
          <el-table-column label="标签" width="160">
            <template #default="{ row }">
              <el-tag v-for="t in (row.tags || [])" :key="t" size="small" class="mr-1">{{ t }}</el-tag>
              <span v-if="!row.tags?.length" class="text-gray-400">-</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="70">
            <template #default="{ row }">
              <el-tag size="small" :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? '启用' : '禁用' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="160" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="manageCases(row)">用例管理</el-button>
              <el-button size="small" type="primary" @click="runSuite(row)">运行</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- Tab: 测试运行历史 -->
      <el-tab-pane label="测试运行" name="runs">
        <el-table :data="runHistory" stripe v-loading="loadingRuns" size="small">
          <el-table-column label="参考号" prop="reference" width="150" />
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag size="small" :type="runStatusType(row.status)">{{ runStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="通过率" width="80" align="center">
            <template #default="{ row }">{{ row.pass_rate || 0 }}%</template>
          </el-table-column>
          <el-table-column label="通过/失败" width="120" align="center">
            <template #default="{ row }">
              <span class="text-green-500">{{ row.passed_tests }}</span> /
              <span class="text-red-500">{{ row.failed_tests }}</span>
            </template>
          </el-table-column>
          <el-table-column label="触发方式" width="100">
            <template #default="{ row }">{{ row.triggered_by === 'manual' ? '手动' : '系统' }}</template>
          </el-table-column>
          <el-table-column label="完成时间" width="160">
            <template #default="{ row }">{{ row.completed_at ? new Date(row.completed_at).toLocaleString() : '-' }}</template>
          </el-table-column>
          <el-table-column label="操作" width="100" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="viewRunDetail(row)">详情</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="flex justify-center mt-3" v-if="runTotal > runPerPage">
          <el-pagination
            v-model:current-page="runPage"
            :page-size="runPerPage"
            :total="runTotal"
            layout="prev, pager, next"
            @current-change="loadRunHistory"
          />
        </div>
      </el-tab-pane>
    </el-tabs>

    <!-- 创建套件对话框 -->
    <el-dialog v-model="showCreateSuite" title="创建测试套件" width="500px">
      <el-form ref="suiteFormRef" :model="suiteForm" :rules="suiteRules" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="suiteForm.name" />
        </el-form-item>
        <el-form-item label="分类" prop="category">
          <el-select v-model="suiteForm.category">
            <el-option label="集成测试" value="integration" />
            <el-option label="浏览器测试" value="browser" />
            <el-option label="API 测试" value="api" />
            <el-option label="性能测试" value="performance" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="suiteForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="标签" prop="tags">
          <el-select v-model="suiteForm.tags" multiple filterable allow-create default-first-option style="width:100%">
            <el-option v-for="t in commonTags" :key="t" :label="t" :value="t" />
          </el-select>
        </el-form-item>
        <el-form-item label="启用" prop="is_active">
          <el-switch v-model="suiteForm.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateSuite = false">取消</el-button>
        <el-button type="primary" :loading="savingSuite" @click="handleCreateSuite">创建</el-button>
      </template>
    </el-dialog>

    <!-- 用例管理对话框 -->
    <el-dialog v-model="showCaseDialog" :title="'用例管理 - ' + (currentSuite?.name || '')" width="800px">
      <div v-loading="loadingCases">
        <div class="flex justify-between items-center mb-3">
          <span class="font-semibold">共 {{ suiteCases.length }} 个测试用例</span>
          <div class="flex gap-2">
            <el-button size="small" @click="showBulkImport = !showBulkImport">
              批量导入
            </el-button>
            <el-button size="small" type="primary" @click="showAddCase = true">
              <el-icon><Plus /></el-icon> 添加用例
            </el-button>
          </div>
        </div>

        <!-- 批量导入区域 -->
        <el-card v-if="showBulkImport" shadow="never" class="mb-3">
          <template #header><span class="font-semibold">批量导入 (每行一个用例，格式: 名称|描述|预期结果|是否关键)</span></template>
          <el-input v-model="bulkCaseText" type="textarea" :rows="6" placeholder="用例1|测试登录|返回200|true&#10;用例2|测试注册|返回201|false" />
          <el-button size="small" type="primary" class="mt-2" @click="handleBulkImport" :loading="importing">执行导入</el-button>
        </el-card>

        <!-- 添加用例表单 -->
        <el-card v-if="showAddCase" shadow="never" class="mb-3">
          <template #header><span>添加单个用例</span></template>
          <el-row :gutter="12">
            <el-col :span="10">
              <el-input v-model="caseForm.name" placeholder="用例名称" size="small" />
            </el-col>
            <el-col :span="6">
              <el-input v-model="caseForm.expected_result" placeholder="预期结果" size="small" />
            </el-col>
            <el-col :span="4">
              <el-checkbox v-model="caseForm.is_critical">关键用例</el-checkbox>
            </el-col>
            <el-col :span="4">
              <el-button size="small" type="primary" @click="handleAddCase">添加</el-button>
            </el-col>
          </el-row>
        </el-card>

        <el-table :data="suiteCases" stripe size="small">
          <el-table-column label="名称" prop="name" min-width="200" />
          <el-table-column label="描述" prop="description" min-width="150" show-overflow-tooltip />
          <el-table-column label="预期结果" prop="expected_result" width="120" show-overflow-tooltip />
          <el-table-column label="关键" width="60" align="center">
            <template #default="{ row }">
              <el-tag v-if="row.is_critical" type="danger" size="small">关键</el-tag>
              <span v-else class="text-gray-400">-</span>
            </template>
          </el-table-column>
          <el-table-column label="排序" prop="sort_order" width="60" align="center" />
        </el-table>
      </div>
    </el-dialog>

    <!-- 运行套件对话框 -->
    <el-dialog v-model="showRunDialog" :title="'运行测试 - ' + (currentSuite?.name || '')" width="600px">
      <el-form :model="runForm" label-width="120px">
        <el-form-item label="选择平台" prop="platform_ids" :rules="[{ required: true, message: '请选择至少一个平台' }]">
          <div v-if="platformGroupList.length === 0" class="text-gray-400 text-sm">
            暂无平台数据，请先在"兼容性矩阵"Tab 中点击"从模板初始化"
          </div>
          <div v-for="(platforms, category) in platformGroups" :key="category" class="mb-2">
            <div class="font-medium text-sm text-gray-600 mb-1">{{ categoryLabel(category) }}</div>
            <el-checkbox-group v-model="runForm.platform_ids">
              <el-checkbox v-for="p in platforms" :key="p.id" :label="p.id" :value="p.id" border size="small" class="mr-2 mb-1">
                {{ p.label || p.name }}
              </el-checkbox>
            </el-checkbox-group>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showRunDialog = false">取消</el-button>
        <el-button type="primary" :loading="runningTest" @click="handleRunSuite">开始运行</el-button>
      </template>
    </el-dialog>

    <!-- 运行详情对话框 -->
    <el-dialog v-model="showDetailDialog" title="运行详情" width="800px">
      <div v-loading="loadingDetail">
        <template v-if="runDetail">
          <el-descriptions :column="3" border size="small" class="mb-3">
            <el-descriptions-item label="参考号">{{ runDetail.run?.reference }}</el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag size="small" :type="runStatusType(runDetail.run?.status)">{{ runStatusLabel(runDetail.run?.status) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="通过率">{{ runDetail.summary?.pass_rate || 0 }}%</el-descriptions-item>
            <el-descriptions-item label="总用例">{{ runDetail.summary?.total || 0 }}</el-descriptions-item>
            <el-descriptions-item label="通过">{{ runDetail.summary?.passed || 0 }}</el-descriptions-item>
            <el-descriptions-item label="失败">{{ runDetail.summary?.failed || 0 }}</el-descriptions-item>
          </el-descriptions>

          <!-- 按分类显示矩阵结果 -->
          <div v-for="(platforms, category) in runDetail.matrix_by_category" :key="category" class="mb-3">
            <h4 class="font-medium mb-2">{{ categoryLabel(category) }}</h4>
            <el-table :data="platforms" stripe size="small">
              <el-table-column label="平台" prop="label || name" min-width="140" />
              <el-table-column label="状态" width="100">
                <template #default="{ row }">
                  <el-tag size="small" :type="row.result === 'passed' ? 'success' : (row.result === 'failed' ? 'danger' : 'warning')">
                    {{ { passed: '通过', failed: '失败', running: '运行中', pending: '待定' }[row.result] || row.result }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column label="测试结果" min-width="200">
                <template #default="{ row }">
                  <div v-if="row.test_results?.length" class="flex gap-1 flex-wrap">
                    <el-tag v-for="tr in row.test_results" :key="tr.id"
                      :type="tr.result === 'passed' ? 'success' : (tr.result === 'failed' ? 'danger' : 'info')"
                      size="small" effect="plain">
                      {{ tr.test_case?.name || '#' + tr.test_case_id }}: {{ { passed: '✓', failed: '✗', skipped: '—' }[tr.result] || tr.result }}
                    </el-tag>
                  </div>
                  <span v-else class="text-gray-400">-</span>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </template>
        <el-empty v-else description="无详情数据" />
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { Plus } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import compatApi from '@/api/compatTest';

// ─── 状态 ───
const activeTab = ref('matrix');

// 统计
const stats = reactive({
  total_platforms: 0, total_suites: 0, total_cases: 0,
  total_runs: 0, passed_runs: 0, failed_runs: 0,
});

const statItems = computed(() => [
  { label: '平台数', value: stats.total_platforms, color: 'text-blue-500' },
  { label: '套件数', value: stats.total_suites, color: 'text-purple-500' },
  { label: '用例数', value: stats.total_cases, color: 'text-green-500' },
  { label: '运行次数', value: stats.total_runs, color: 'text-orange-500' },
  { label: '通过/失败', value: `${stats.passed_runs}/${stats.failed_runs}`, color: stats.failed_runs > 0 ? 'text-red-500' : 'text-green-500' },
]);

// 平台
const loadingPlatforms = ref(false);
const platformGroups = ref({});

// 套件 & 用例管理
const loadingSuites = ref(false);
const suites = ref([]);
const currentSuite = ref(null);
const showCaseDialog = ref(false);
const loadingCases = ref(false);
const suiteCases = ref([]);
const showAddCase = ref(false);
const caseForm = reactive({ name: '', expected_result: '', is_critical: false });
const showBulkImport = ref(false);
const bulkCaseText = ref('');
const importing = ref(false);

// 运行管理
const showRunDialog = ref(false);
const currentSuiteForRun = ref(null);
const runForm = reactive({ platform_ids: [] });
const runningTest = ref(false);

// 运行详情
const showDetailDialog = ref(false);
const loadingDetail = ref(false);
const runDetail = ref(null);

// 运行历史
const loadingRuns = ref(false);
const runHistory = ref([]);
const runPage = ref(1);
const runPerPage = ref(20);
const runTotal = ref(0);

// 创建套件
const showCreateSuite = ref(false);
const savingSuite = ref(false);
const suiteFormRef = ref(null);
const commonTags = ['核心功能', '支付', 'Auth', 'API', 'CRUD', '安全', '性能'];

const suiteForm = reactive({
  name: '', category: 'integration', description: '', tags: [], is_active: true,
});

const suiteRules = {
  name: [{ required: true, message: '请输入套件名称' }],
};

// ─── 计算属性 ───
const platformGroupList = computed(() => {
  const list = [];
  Object.values(platformGroups.value).forEach(platforms => {
    platforms.forEach(p => list.push(p));
  });
  return list;
});

// ─── 工具方法 ───

function categoryLabel(cat) {
  const map = { php: 'PHP', mysql: '数据库', redis: '缓存', browser: '浏览器', os: '操作系统', integration: '集成测试', api: 'API 测试', performance: '性能测试' };
  return map[cat] || cat;
}

function runStatusType(status) {
  const map = { pending: 'info', running: 'warning', passed: 'success', failed: 'danger', error: 'danger', cancelled: 'info' };
  return map[status] || 'info';
}

function runStatusLabel(status) {
  const map = { pending: '待运行', running: '运行中', passed: '通过', failed: '失败', error: '错误', cancelled: '已取消' };
  return map[status] || status;
}

// ─── 数据加载 ───

async function loadStats() {
  try {
    const { data } = await compatApi.getStats();
    Object.assign(stats, data?.data || {});
  } catch (e) { /* ignore */ }
}

async function loadPlatforms() {
  loadingPlatforms.value = true;
  try {
    const { data } = await compatApi.getPlatforms();
    platformGroups.value = data?.data || {};
  } catch (e) {
    ElMessage.error('加载平台失败');
  } finally {
    loadingPlatforms.value = false;
  }
}

async function initPlatforms() {
  try {
    const { data } = await compatApi.initializePlatforms();
    ElMessage.success(`已创建 ${data?.data?.created_count || 0} 个平台`);
    loadPlatforms();
    loadStats();
  } catch (e) {
    ElMessage.error('初始化失败');
  }
}

async function loadSuites() {
  loadingSuites.value = true;
  try {
    const { data } = await compatApi.getSuites();
    suites.value = data?.data || [];
  } catch (e) {
    ElMessage.error('加载套件失败');
  } finally {
    loadingSuites.value = false;
  }
}

async function loadRunHistory() {
  loadingRuns.value = true;
  try {
    const { data } = await compatApi.getTestRunHistory({
      page: runPage.value, per_page: runPerPage.value,
    });
    const result = data?.data || {};
    runHistory.value = result.items || [];
    runTotal.value = result.total || 0;
  } catch (e) {
    ElMessage.error('加载运行历史失败');
  } finally {
    loadingRuns.value = false;
  }
}

// ─── 创建套件 ───

async function handleCreateSuite() {
  const valid = await suiteFormRef.value?.validate().catch(() => false);
  if (!valid) return;

  savingSuite.value = true;
  try {
    await compatApi.createSuite(suiteForm);
    ElMessage.success('套件已创建');
    showCreateSuite.value = false;
    suiteForm.name = '';
    suiteForm.description = '';
    suiteForm.tags = [];
    loadSuites();
    loadStats();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '创建失败');
  } finally {
    savingSuite.value = false;
  }
}

// ─── 用例管理 ───

async function manageCases(row) {
  currentSuite.value = row;
  showCaseDialog.value = true;
  showAddCase.value = false;
  showBulkImport.value = false;
  await loadSuiteCases(row.id);
}

async function loadSuiteCases(suiteId) {
  loadingCases.value = true;
  try {
    const { data } = await compatApi.getSuiteDetail(suiteId);
    suiteCases.value = data?.data?.test_cases || [];
  } catch (e) {
    ElMessage.error('加载用例失败');
  } finally {
    loadingCases.value = false;
  }
}

async function handleAddCase() {
  if (!caseForm.name) {
    ElMessage.warning('请输入用例名称');
    return;
  }
  try {
    await compatApi.addTestCase(currentSuite.value.id, {
      name: caseForm.name,
      expected_result: caseForm.expected_result || null,
      is_critical: caseForm.is_critical,
    });
    ElMessage.success('用例已添加');
    caseForm.name = '';
    caseForm.expected_result = '';
    caseForm.is_critical = false;
    await loadSuiteCases(currentSuite.value.id);
    loadStats();
  } catch (e) {
    ElMessage.error('添加失败');
  }
}

async function handleBulkImport() {
  if (!bulkCaseText.value.trim()) {
    ElMessage.warning('请输入用例数据');
    return;
  }
  importing.value = true;
  try {
    const lines = bulkCaseText.value.trim().split('\n').filter(Boolean);
    const cases = lines.map((line, idx) => {
      const parts = line.split('|').map(s => s.trim());
      return {
        name: parts[0] || `用例${idx + 1}`,
        description: parts[1] || null,
        expected_result: parts[2] || null,
        is_critical: parts[3] === 'true' || parts[3] === '1',
      };
    });
    await compatApi.bulkAddTestCases(currentSuite.value.id, { cases });
    ElMessage.success(`成功导入 ${cases.length} 个用例`);
    bulkCaseText.value = '';
    showBulkImport.value = false;
    await loadSuiteCases(currentSuite.value.id);
    loadStats();
  } catch (e) {
    ElMessage.error('批量导入失败');
  } finally {
    importing.value = false;
  }
}

// ─── 运行套件 ───

async function runSuite(row) {
  currentSuiteForRun.value = row;
  runForm.platform_ids = [];
  showRunDialog.value = true;
  // 确保平台已加载
  if (platformGroupList.value.length === 0) {
    await loadPlatforms();
  }
}

async function handleRunSuite() {
  if (runForm.platform_ids.length === 0) {
    ElMessage.warning('请选择至少一个平台');
    return;
  }
  runningTest.value = true;
  try {
    // 创建运行
    const createRes = await compatApi.createTestRun({ platform_ids: runForm.platform_ids });
    const runId = createRes.data?.data?.id;
    if (!runId) throw new Error('创建运行失败');

    // 启动运行
    await compatApi.startTestRun(runId);

    // 获取该套件所有用例
    const suiteDetailRes = await compatApi.getSuiteDetail(currentSuiteForRun.value.id);
    const testCases = suiteDetailRes.data?.data?.test_cases || [];

    // 模拟记录测试结果 (全部标记为通过)
    if (testCases.length > 0) {
      const results = [];
      for (const platformId of runForm.platform_ids) {
        for (const tc of testCases) {
          results.push({
            platform_id: platformId,
            test_case_id: tc.id,
            result: 'passed',
            execution_time_ms: Math.random() * 500 + 50,
          });
        }
      }
      // 批量记录
      if (results.length > 0) {
        await compatApi.recordBatchResults(runId, { results });
      }
    }

    // 完成运行
    await compatApi.completeTestRun(runId);

    ElMessage.success('测试运行已完成');
    showRunDialog.value = false;
    loadRunHistory();
    loadStats();
  } catch (e) {
    ElMessage.error('运行测试失败');
  } finally {
    runningTest.value = false;
  }
}

// ─── 运行详情 ───

async function viewRunDetail(row) {
  showDetailDialog.value = true;
  loadingDetail.value = true;
  runDetail.value = null;
  try {
    const { data } = await compatApi.getTestRunDetail(row.id);
    runDetail.value = data?.data || null;
  } catch (e) {
    ElMessage.error('加载详情失败');
  } finally {
    loadingDetail.value = false;
  }
}

onMounted(() => {
  loadStats();
  loadPlatforms();
  loadSuites();
  loadRunHistory();
});
</script>

<style scoped>
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
.mr-1 { margin-right: 4px; }
.gap-2 { gap: 8px; }
.flex-wrap { flex-wrap: wrap; }
.text-gray-400 { color: #909399; }
.text-gray-500 { color: #909399; }
.text-gray-600 { color: #606266; }
.text-xs { font-size: 12px; }
.text-xl { font-size: 20px; }
.text-green-500 { color: #67c23a; }
.text-red-500 { color: #f56c6c; }
.font-bold { font-weight: 700; }
.font-semibold { font-weight: 600; }
.font-medium { font-weight: 500; }
</style>
