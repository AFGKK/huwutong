<template>
  <div class="rag-admin">
    <div class="page-header">
      <h2>RAG 知识库管理</h2>
      <div class="header-actions">
        <el-button type="primary" :loading="rebuilding" @click="handleRebuildIndex">
          <el-icon><Refresh /></el-icon> 重建索引
        </el-button>
      </div>
    </div>

    <!-- Stats -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-card">
            <div class="stat-value">{{ stats.total_documents }}</div>
            <div class="stat-label">已索引文档</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-card">
            <div class="stat-value" style="color: #67c23a">{{ stats.total_chunks }}</div>
            <div class="stat-label">文本片段</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-card">
            <div class="stat-value" style="color: #409eff">{{ stats.total_conversations }}</div>
            <div class="stat-label">总对话数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-card">
            <div class="stat-value" style="color: #e6a23c">{{ stats.total_messages }}</div>
            <div class="stat-label">总消息数</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 索引状态 -->
    <el-card class="mb-4" shadow="never">
      <template #header>
        <span>索引概览</span>
      </template>
      <el-row :gutter="24">
        <el-col :span="8">
          <div class="index-stat">
            <div class="index-label">最后索引时间</div>
            <div class="index-value">{{ stats.last_indexed_at || '尚未索引' }}</div>
          </div>
        </el-col>
        <el-col :span="8">
          <div class="index-stat">
            <div class="index-label">索引状态</div>
            <el-tag :type="stats.index_status === 'ready' ? 'success' : 'warning'" size="small">
              {{ stats.index_status === 'ready' ? '就绪' : '待更新' }}
            </el-tag>
          </div>
        </el-col>
        <el-col :span="8">
          <div class="index-stat">
            <div class="index-label">嵌入模型</div>
            <div class="index-value">{{ stats.embedding_model || 'default' }}</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- 已索引文章列表 -->
    <el-card shadow="never">
      <template #header>
        <div class="card-header">
          <span>已索引文章</span>
          <el-button size="small" @click="fetchDocuments">刷新</el-button>
        </div>
      </template>
      <el-table :data="documents" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="文章标题" min-width="200">
          <template #default="{ row }">
            <el-link type="primary" :underline="'never'" @click="$router.push(`/knowledge-base`)">
              {{ row.article?.title || `文章 #${row.article_id}` }}
            </el-link>
          </template>
        </el-table-column>
        <el-table-column prop="chunk_count" label="片段数" width="80" align="center" />
        <el-table-column prop="char_count" label="字符数" width="90" align="center">
          <template #default="{ row }">{{ (row.char_count || 0).toLocaleString() }}</template>
        </el-table-column>
        <el-table-column prop="status" label="索引状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'indexed' ? 'success' : 'warning'" size="small">
              {{ row.status === 'indexed' ? '已索引' : row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="indexed_at" label="索引时间" width="170" />
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button size="small" text type="primary" @click="handleReindex(row)">
              重新索引
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!loading && documents.length === 0" description="暂无已索引的文章" />
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import ragApi from '@/api/rag';
import { Refresh } from '@element-plus/icons-vue';

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
      '确定要重建所有 RAG 索引吗？这将重新处理所有已发布的帮助中心文章。',
      '确认重建',
      { confirmButtonText: '确认重建', cancelButtonText: '取消', type: 'warning' },
    );
  } catch {
    return;
  }

  rebuilding.value = true;
  try {
    const { data: res } = await ragApi.rebuildIndex();
    if (res.success) {
      ElMessage.success(res.message || '索引重建完成');
      fetchStats();
      fetchDocuments();
    }
  } catch {
    ElMessage.error('索引重建失败');
  } finally {
    rebuilding.value = false;
  }
}

async function handleReindex(row) {
  try {
    const { data: res } = await ragApi.indexArticle(row.article_id);
    if (res.success) {
      ElMessage.success('文章已重新索引');
      fetchDocuments();
    }
  } catch {
    ElMessage.error('重新索引失败');
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
