<template>
  <div class="graphql-page">
    <el-row :gutter="20">
      <!-- Schema 侧边栏 -->
      <el-col :span="6">
        <el-card shadow="never" class="h-full">
          <template #header>
            <div class="flex items-center gap-2">
              <el-icon><Monitor /></el-icon>
              <span class="font-semibold">{{ t('graphql_page.schema_browser') }}</span>
            </div>
          </template>

          <el-tree
            :data="schemaTree"
            :default-expanded-keys="['License', 'Customer', 'User', 'Product']"
            node-key="id"
            @node-click="selectSchemaNode"
          >
            <template #default="{ node, data }">
              <span v-if="data.type === 'type'" class="font-medium text-sm">
                <el-tag size="small" effect="plain" class="mr-1">{{ data.graphqlType }}</el-tag>
                {{ data.name }}
              </span>
              <span v-else-if="data.type === 'field'" class="text-sm">
                <span class="text-blue-500">{{ data.name }}</span>
                <span v-if="data.fieldType" class="text-gray-400 ml-1">: {{ data.fieldType }}</span>
              </span>
              <span v-else-if="data.type === 'relation'" class="text-sm">
                <span class="text-green-600">{{ data.name }}</span>
                <span class="text-gray-400 ml-1">: {{ data.fieldType }}</span>
              </span>
              <span v-else-if="data.type === 'filter'" class="text-sm text-orange-500">
                {{ data.name }}
              </span>
              <span v-else class="text-sm">{{ data.name }}</span>
            </template>
          </el-tree>
        </el-card>
      </el-col>

      <!-- 主编辑区 -->
      <el-col :span="18">
        <el-card shadow="never" class="mb-4">
          <template #header>
            <div class="flex justify-between items-center">
              <div class="flex items-center gap-2">
                <el-icon><Edit /></el-icon>
                <span class="font-semibold">{{ t('graphql_page.query_editor') }}</span>
              </div>
              <div class="flex gap-2">
                <el-button size="small" @click="clearQuery">{{ t('graphql_page.clear') }}</el-button>
                <el-button size="small" @click="addQueryTab">{{ t('graphql_page.add_query') }}</el-button>
                <el-button size="small" @click="loadSample">{{ t('graphql_page.sample') }}</el-button>
                <el-button type="primary" size="small" @click="executeQuery" :loading="executing">
                  {{ t('graphql_page.execute') }}
                </el-button>
              </div>
            </div>
          </template>

          <el-tabs v-model="activeEditor" type="card" closable @tab-remove="removeQueryTab">
            <el-tab-pane
              v-for="(tab, idx) in queryTabs"
              :key="idx"
              :label="queryTabLabel(idx)"
              :name="String(idx)"
              :closable="queryTabs.length > 1"
            >
              <el-input
                v-model="tab.query"
                type="textarea"
                :rows="12"
                class="font-mono"
                :placeholder="t('graphql_page.query_placeholder')"
              />
            </el-tab-pane>
          </el-tabs>
        </el-card>

        <!-- 结果区 -->
        <el-card shadow="never">
          <template #header>
            <div class="flex items-center gap-2">
              <el-icon><DataAnalysis /></el-icon>
              <span class="font-semibold">{{ t('graphql_page.results') }}</span>
              <el-tag v-if="executionTime" size="small" effect="plain">{{ executionTime }}ms</el-tag>
              <el-tag v-if="resultSize" size="small" type="success">{{ t('graphql_page.record_count', { n: resultSize }) }}</el-tag>
            </div>
          </template>

          <div v-if="error" class="error-area mb-4">
            <el-alert :title="error" type="error" :closable="false" show-icon />
          </div>

          <div v-if="formattedResult" class="result-area">
            <pre class="json-output font-mono text-sm">{{ formattedResult }}</pre>
          </div>

          <el-empty v-if="!formattedResult && !error" :description="t('graphql_page.empty_hint')" />
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Monitor, Edit, DataAnalysis } from '@element-plus/icons-vue';
import graphqlApi from '@/api/graphql';

const { t, locale } = useI18n();

const SCHEMA_GROUPS = computed(() => ({
  fields: t('graphql_page.schema_fields'),
  relations: t('graphql_page.schema_relations'),
  filters: t('graphql_page.schema_filters'),
}));

function queryTabLabel(idx) {
  return t('graphql_page.query_tab', { n: idx + 1 });
}

// ─── 查询标签 ───
const queryTabs = ref([{ query: '' }]);
const activeEditor = ref('0');
const executing = ref(false);
const executionTime = ref(null);
const resultSize = ref(null);
const error = ref(null);
const formattedResult = ref(null);

function addQueryTab() {
  queryTabs.value.push({ query: '' });
  activeEditor.value = String(queryTabs.value.length - 1);
}

function removeQueryTab(name) {
  const idx = parseInt(name);
  queryTabs.value.splice(idx, 1);
  if (activeEditor.value === name) {
    activeEditor.value = '0';
  }
}

function clearQuery() {
  const tab = queryTabs.value[parseInt(activeEditor.value)];
  if (tab) tab.query = '';
  formattedResult.value = null;
  error.value = null;
  executionTime.value = null;
  resultSize.value = null;
}

// ─── Schema ───
const schemaData = ref({});
const schemaTree = ref([]);

async function loadSchema() {
  try {
    const { data } = await graphqlApi.schema();
    schemaData.value = data?.data || {};
    buildSchemaTree();
  } catch (e) {
    ElMessage.error(t('messages.load_failed'));
  }
}

function buildSchemaTree() {
  const tree = [];
  for (const [typeName, def] of Object.entries(schemaData.value)) {
    const typeNode = {
      id: typeName,
      label: typeName,
      name: typeName,
      type: 'type',
      graphqlType: typeName,
      children: [],
    };

    // Fields
    if (def.fields?.length) {
      typeNode.children.push({
        id: `${typeName}-fields`,
        label: SCHEMA_GROUPS.value.fields,
        name: SCHEMA_GROUPS.value.fields,
        type: 'group',
        children: def.fields.map(f => ({
          id: `${typeName}-field-${f}`,
          label: f,
          name: f,
          type: 'field',
          fieldType: 'String',
        })),
      });
    }

    // Relations
    if (def.relations && Object.keys(def.relations).length) {
      typeNode.children.push({
        id: `${typeName}-relations`,
        label: SCHEMA_GROUPS.value.relations,
        name: SCHEMA_GROUPS.value.relations,
        type: 'group',
        children: Object.entries(def.relations).map(([name, type]) => ({
          id: `${typeName}-rel-${name}`,
          label: `${name} : ${type}`,
          name,
          type: 'relation',
          fieldType: type,
        })),
      });
    }

    // Filters
    if (def.filters?.length) {
      typeNode.children.push({
        id: `${typeName}-filters`,
        label: SCHEMA_GROUPS.value.filters,
        name: SCHEMA_GROUPS.value.filters,
        type: 'group',
        children: def.filters.map(f => ({
          id: `${typeName}-filter-${f}`,
          label: f,
          name: f,
          type: 'filter',
        })),
      });
    }

    tree.push(typeNode);
  }
  schemaTree.value = tree;
}

// 点击 schema 节点时生成示例查询
function selectSchemaNode(node) {
  if (node.type === 'type') {
    const tab = queryTabs.value[parseInt(activeEditor.value)];
    const def = schemaData.value[node.name];
    if (!def) return;

    const fields = def.fields?.slice(0, 5).map(f => `"${f}"`).join(',\n      ') || '"id"';
    const query = JSON.stringify({
      type: node.name,
      fields: [def.fields?.slice(0, 3), ...Object.keys(def.relations || {}).slice(0, 2).map(r => ({
        [r]: { fields: ['id'] }
      }))].flat().filter(Boolean),
      args: { filter: {}, sort: [{ field: 'id', direction: 'desc' }], page: 1, per_page: 10 },
    }, null, 2);

    tab.query = query;
    ElMessage.info(t('graphql_page.messages.sample_loaded', { type: node.name }));
  }
}

// ─── 执行查询 ───
async function executeQuery() {
  const tab = queryTabs.value[parseInt(activeEditor.value)];
  if (!tab?.query?.trim()) {
    ElMessage.warning(t('graphql_page.messages.enter_query'));
    return;
  }

  let payload;
  try {
    payload = JSON.parse(tab.query);
  } catch (e) {
    ElMessage.error(t('graphql_page.messages.json_parse_failed'));
    return;
  }

  executing.value = true;
  error.value = null;
  formattedResult.value = null;
  executionTime.value = null;
  resultSize.value = null;

  const start = performance.now();

  try {
    const { data } = await graphqlApi.query({ query: payload });
    const elapsed = Math.round(performance.now() - start);
    executionTime.value = elapsed;

    const result = data?.data;
    formattedResult.value = JSON.stringify(result, null, 2);

    // 计算记录数
    if (result?.data?.data) {
      resultSize.value = Array.isArray(result.data.data) ? result.data.data.length : 1;
    } else if (result?.data) {
      const vals = Object.values(result.data);
      if (Array.isArray(vals[0])) resultSize.value = vals[0].length;
    }

    if (result?.errors) {
      error.value = result.errors.map(e => e.message).join('; ');
    }
  } catch (e) {
    error.value = e.response?.data?.message || e.message || t('messages.failed');
    formattedResult.value = null;
  } finally {
    executing.value = false;
  }
}

// ─── 示例查询 ───
function loadSample() {
  const tab = queryTabs.value[parseInt(activeEditor.value)];
  tab.query = JSON.stringify({
    type: 'License',
    fields: [
      'id', 'license_key', 'type', 'status', 'seats',
      {
        product: {
          fields: ['id', 'name', 'slug']
        }
      },
      {
        customer: {
          fields: ['id', 'type', 'status']
        }
      },
    ],
    args: {
      filter: { status: 'active' },
      sort: [{ field: 'created_at', direction: 'desc' }],
      page: 1,
      per_page: 10,
    },
  }, null, 2);
  ElMessage.success(t('graphql_page.messages.sample_loaded_generic'));
}

watch(locale, () => {
  if (Object.keys(schemaData.value).length) {
    buildSchemaTree();
  }
});

onMounted(loadSchema);
</script>

<style scoped>
.graphql-page { max-width: 1400px; }
.h-full { min-height: calc(100vh - 120px); }
.font-mono { font-family: 'Cascadia Code', 'Fira Code', 'JetBrains Mono', monospace; }
.error-area { max-height: 200px; overflow: auto; }
.result-area {
  max-height: 500px;
  overflow: auto;
}
.json-output {
  background: #1e1e1e;
  color: #d4d4d4;
  padding: 16px;
  border-radius: 6px;
  overflow-x: auto;
  line-height: 1.6;
  font-size: 13px;
}
.json-output pre { margin: 0; }
</style>
