<template>
  <div class="rag-admin">
    <div class="page-header">
      <h2>{{ t('rag_page.title') }}</h2>
      <div class="header-actions">
        <el-button type="primary" :loading="rebuilding" @click="handleRebuildIndex">
          <el-icon><Refresh /></el-icon> {{ t('rag_page.rebuild_index') }}
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col v-for="stat in statsList" :key="stat.key" :span="6">
        <el-card shadow="never">
          <div class="stat-card">
            <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
            <div class="stat-label">{{ stat.label }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 索引状态 -->
    <el-card class="mb-4" shadow="never">
      <template #header>
        <span>{{ t('rag_page.overview.title') }}</span>
      </template>
      <el-row :gutter="24">
        <el-col :span="8">
          <div class="index-stat">
            <div class="index-label">{{ t('rag_page.overview.last_indexed_at') }}</div>
            <div class="index-value">{{ lastIndexedLabel }}</div>
          </div>
        </el-col>
        <el-col :span="8">
          <div class="index-stat">
            <div class="index-label">{{ t('rag_page.overview.index_status') }}</div>
            <el-tag :type="stats.index_status === 'ready' ? 'success' : 'warning'" size="small">
              {{ indexStatusLabel(stats.index_status) }}
            </el-tag>
          </div>
        </el-col>
        <el-col :span="8">
          <div class="index-stat">
            <div class="index-label">{{ t('rag_page.overview.embedding_model') }}</div>
            <div class="index-value">{{ embeddingModelLabel }}</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- 已索引文章列表 -->
    <el-card shadow="never">
      <template #header>
        <div class="card-header">
          <span>{{ t('rag_page.documents.title') }}</span>
          <el-button size="small" @click="fetchDocuments">{{ t('rag_page.refresh') }}</el-button>
        </div>
      </template>
      <el-table :data="documents" v-loading="loading" stripe>
        <el-table-column prop="id" :label="t('rag_page.cols.id')" width="60" />
        <el-table-column :label="t('rag_page.cols.title')" min-width="200">
          <template #default="{ row }">
            <el-link type="primary" :underline="'never'" @click="$router.push(`/knowledge-base`)">
              {{ articleTitle(row) }}
            </el-link>
          </template>
        </el-table-column>
        <el-table-column prop="chunk_count" :label="t('rag_page.cols.chunk_count')" width="80" align="center" />
        <el-table-column prop="char_count" :label="t('rag_page.cols.char_count')" width="90" align="center">
          <template #default="{ row }">{{ (row.char_count || 0).toLocaleString() }}</template>
        </el-table-column>
        <el-table-column prop="status" :label="t('rag_page.cols.status')" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'indexed' ? 'success' : 'warning'" size="small">
              {{ docStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="indexed_at" :label="t('rag_page.cols.indexed_at')" width="170" />
        <el-table-column :label="t('rag_page.cols.actions')" width="100" fixed="right">
          <template #default="{ row }">
            <el-button size="small" text type="primary" @click="handleReindex(row)">
              {{ t('rag_page.reindex') }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!loading && documents.length === 0" :description="t('rag_page.documents.empty')" />
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import ragApi from '@/api/rag';
import { Refresh } from '@element-plus/icons-vue';

const { t } = useI18n();

const loading = ref(false);
const rebuilding = ref(false);
const documents = ref([]);
const stats = reactive({
  total_documents: 0,
  total_chunks: 0,
  total_conversations: 0,
  total_messages: 0,
  last_indexed_at: null,
  index_status: 'pending',
  embedding_model: null,
});

const statsList = computed(() => [
  { key: 'documents', label: t('rag_page.stats.indexed_documents'), value: stats.total_documents, color: '#303133' },
  { key: 'chunks', label: t('rag_page.stats.text_chunks'), value: stats.total_chunks, color: '#67c23a' },
  { key: 'conversations', label: t('rag_page.stats.total_conversations'), value: stats.total_conversations, color: '#0f172a' },
  { key: 'messages', label: t('rag_page.stats.total_messages'), value: stats.total_messages, color: '#e6a23c' },
]);

const lastIndexedLabel = computed(() => stats.last_indexed_at || t('rag_page.overview.not_indexed_yet'));

const embeddingModelLabel = computed(() => stats.embedding_model || t('rag_page.overview.default_model'));

function indexStatusLabel(status) {
  const map = {
    ready: t('rag_page.index_status.ready'),
    pending: t('rag_page.index_status.pending'),
  };
  return map[status] || status;
}

function docStatusLabel(status) {
  const map = {
    indexed: t('rag_page.doc_status.indexed'),
  };
  return map[status] || status;
}

function articleTitle(row) {
  return row.article?.title || t('rag_page.documents.article_fallback', { id: row.article_id });
}

async function fetchStats() {
  try {
    const { data: res } = await ragApi.stats();
    if (res.success) {
      Object.assign(stats, res.data || {});
    }
  } catch {
    // ignore
  }
}

async function fetchDocuments() {
  loading.value = true;
  try {
    const { data: res } = await ragApi.stats();
    if (res.success && res.data?.documents) {
      documents.value = res.data.documents;
    }
  } catch {
    // ignore
  } finally {
    loading.value = false;
  }
}

async function handleRebuildIndex() {
  try {
    await ElMessageBox.confirm(
      t('rag_page.confirm.rebuild_message'),
      t('rag_page.confirm.rebuild_title'),
      { confirmButtonText: t('rag_page.confirm.rebuild_btn'), cancelButtonText: t('actions.cancel'), type: 'warning' },
    );
  } catch {
    return;
  }

  rebuilding.value = true;
  try {
    const { data: res } = await ragApi.rebuildIndex();
    if (res.success) {
      ElMessage.success(res.message || t('rag_page.messages.rebuild_done'));
      fetchStats();
      fetchDocuments();
    }
  } catch {
    ElMessage.error(t('rag_page.messages.rebuild_failed'));
  } finally {
    rebuilding.value = false;
  }
}

async function handleReindex(row) {
  try {
    const { data: res } = await ragApi.indexArticle(row.article_id);
    if (res.success) {
      ElMessage.success(t('rag_page.messages.reindex_done'));
      fetchDocuments();
    }
  } catch {
    ElMessage.error(t('rag_page.messages.reindex_failed'));
  }
}

onMounted(() => {
  fetchStats();
  fetchDocuments();
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
.stat-card { cursor: default; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.index-stat {
  padding: 8px 0;
}
.index-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 4px;
}
.index-value {
  font-size: 14px;
  color: #303133;
}
</style>
