<template>
  <div class="user-interactions">
    <!-- 📊 阅读统计仪表盘 -->
    <div class="stats-dashboard" v-if="stats">
      <div class="stats-grid">
        <div class="stat-card stat-card-primary">
          <div class="stat-icon">📚</div>
          <div class="stat-body">
            <div class="stat-value">{{ stats.total_read }}</div>
            <div class="stat-label">已读文章</div>
          </div>
        </div>
        <div class="stat-card stat-card-success">
          <div class="stat-icon">⏱️</div>
          <div class="stat-body">
            <div class="stat-value">{{ stats.total_minutes }}</div>
            <div class="stat-label">累计分钟</div>
          </div>
        </div>
        <div class="stat-card stat-card-warning">
          <div class="stat-icon">🔥</div>
          <div class="stat-body">
            <div class="stat-value">{{ stats.streak_days }}</div>
            <div class="stat-label">连续天数</div>
          </div>
        </div>
        <div class="stat-card stat-card-info">
          <div class="stat-icon">📊</div>
          <div class="stat-body">
            <div class="stat-value">{{ stats.blog_read }}/{{ stats.oa_read }}</div>
            <div class="stat-label">博客/OA</div>
          </div>
        </div>
      </div>

      <!-- 🎯 猜你喜欢 -->
      <div class="recommend-section" v-if="recItems.length > 0">
        <div class="rec-header">
          <span class="trend-header">🎯 猜你喜欢</span>
          <el-button text size="small" @click="loadRecommendations" :loading="recLoading">🔄 换一批</el-button>
        </div>
        <div class="rec-grid">
          <div v-for="(item, idx) in recItems" :key="'rec-'+idx" class="rec-card" @click="openRec(item)">
            <div v-if="item.cover" class="rec-cover"><img :src="item.cover" :alt="item.title" /></div>
            <div v-else class="rec-cover rec-cover-placeholder">{{ item.type === 'blog_post' ? '📝' : '📄' }}</div>
            <div class="rec-body">
              <div class="rec-title">{{ item.title }}</div>
              <div class="rec-reason">{{ item.reason }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 📋 阅读报告 -->
      <div class="report-section" v-if="reportData">
        <div class="report-header">
          <span class="trend-header">📋 {{ reportData.period_label }}阅读报告</span>
          <div class="report-header-actions">
            <el-button size="small" text @click="shareReport">
              <el-icon><Share /></el-icon> 分享
            </el-button>
            <el-radio-group v-model="reportPeriod" size="small" @change="loadReport">
              <el-radio-button value="weekly">周报</el-radio-button>
              <el-radio-button value="monthly">月报</el-radio-button>
            </el-radio-group>
          </div>
        </div>
        <div class="report-grid">
          <div class="report-main">
            <div class="report-stat">
              <span class="report-num">{{ reportData.total_read }}</span>
              <span class="report-unit">篇</span>
              <span class="report-label">阅读量</span>
              <span class="report-growth" :class="reportData.growth_percent >= 0 ? 'up' : 'down'">
                {{ reportData.growth_label }} 较{{ reportData.prev_period_label }}
              </span>
            </div>
            <div class="report-stat">
              <span class="report-num">{{ reportData.avg_daily }}</span>
              <span class="report-unit">篇/天</span>
              <span class="report-label">日均阅读</span>
            </div>
            <div class="report-stat">
              <span class="report-num">{{ reportData.streak_days }}</span>
              <span class="report-unit">天</span>
              <span class="report-label">最长连续</span>
            </div>
          </div>
          <div class="report-details">
            <div class="report-detail-item">
              <span class="report-detail-icon">📝</span>
              <span>博客 <strong>{{ reportData.blog_read }}</strong> 篇</span>
            </div>
            <div class="report-detail-item">
              <span class="report-detail-icon">📄</span>
              <span>OA <strong>{{ reportData.oa_read }}</strong> 篇</span>
            </div>
            <div class="report-detail-item">
              <span class="report-detail-icon">⭐</span>
              <span>收藏 <strong>{{ reportData.total_favorites }}</strong> 次</span>
            </div>
            <div class="report-detail-item">
              <span class="report-detail-icon">👍</span>
              <span>点赞 <strong>{{ reportData.total_likes }}</strong> 次</span>
            </div>
            <div class="report-detail-item">
              <span class="report-detail-icon">⏰</span>
              <span>最活跃时段 <strong>{{ reportData.peak_hour }}:00-{{ reportData.peak_hour + 1 }}:00</strong></span>
            </div>
            <div class="report-detail-item" v-if="reportData.top_tags?.length">
              <span class="report-detail-icon">🏷️</span>
              <span>最爱标签 <strong>{{ reportData.top_tags.join(' · ') }}</strong></span>
            </div>
          </div>
        </div>
      </div>

      <!-- 本周阅读趋势 -->
      <div class="trend-section">
        <div class="trend-header">📈 本周阅读趋势</div>
        <div class="trend-bars">
          <div v-for="day in stats.weekly_trend" :key="day.date" class="trend-day">
            <div class="trend-bar-wrap">
              <div class="trend-bar blog-bar" :style="{ height: barHeight(day.blog) + 'px' }" :title="'博客: ' + day.blog"></div>
              <div class="trend-bar oa-bar" :style="{ height: barHeight(day.oa) + 'px' }" :title="'OA: ' + day.oa"></div>
            </div>
            <div class="trend-label">{{ day.label }}</div>
            <div class="trend-value" v-if="day.total > 0">{{ day.total }}</div>
          </div>
        </div>
        <div class="trend-legend">
          <span><span class="legend-dot blog-dot"></span> 博客</span>
          <span><span class="legend-dot oa-dot"></span> OA文章</span>
        </div>
      </div>

      <!-- � 今日阅读目标 -->
      <div class="goal-section">
        <div class="goal-header">
          <span class="trend-header">🎯 今日阅读目标</span>
          <el-button v-if="!showGoalEditor" text size="small" @click="showGoalEditor = true">⚙️ 设置</el-button>
        </div>
        <div class="goal-body">
          <div class="goal-info">
            <span class="goal-emoji">📖</span>
            <span>今日已读 <strong>{{ stats.today_read }}</strong> 篇</span>
            <span class="goal-divider">/</span>
            <span>目标 <strong>{{ stats.daily_goal }}</strong> 篇</span>
          </div>
          <div class="goal-bar-wrap">
            <div class="goal-bar" :style="{ width: stats.goal_progress + '%' }" :class="goalBarClass">
              <span v-if="stats.goal_progress > 15">{{ stats.goal_progress }}%</span>
            </div>
          </div>
          <div class="goal-message" v-if="stats.goal_progress >= 100">✅ 今日目标已完成！</div>
          <div class="goal-editor" v-if="showGoalEditor">
            <span style="font-size:12px;color:#909399">每日目标：</span>
            <el-input-number v-model="editGoal" :min="1" :max="100" size="small" style="width:100px" />
            <el-button size="small" type="primary" @click="saveGoal" :loading="goalSaving">保存</el-button>
            <el-button size="small" @click="showGoalEditor = false">取消</el-button>
          </div>
        </div>
      </div>
      <!-- 📊 互动热力图 -->
      <div class="heatmap-section" v-if="heatmapData">
        <div class="heatmap-header">
          <span class="trend-header">📊 互动热力图</span>
          <div class="heatmap-controls">
            <el-button v-if="heatmapYear > 2024" text size="small" @click="switchHeatmapYear(-1)">‹ {{ heatmapYear - 1 }}</el-button>
            <span style="font-weight:600;font-size:13px">{{ heatmapYear }}</span>
            <el-button v-if="heatmapYear < nowYear" text size="small" @click="switchHeatmapYear(1)">{{ heatmapYear + 1 }} ›</el-button>
          </div>
        </div>
        <div class="heatmap-stats">
          <span>{{ heatmapData.total_interactions }} 次互动</span>
          <span>·</span>
          <span>{{ heatmapData.total_active_days }} 天活跃</span>
          <span>·</span>
          <span>今日 {{ heatmapData.today_count }}</span>
        </div>
        <div class="heatmap-wrap">
          <div class="heatmap-months">
            <span v-for="m in monthLabels" :key="m.label" :style="{ marginLeft: m.offset + 'px' }">{{ m.label }}</span>
          </div>
          <div class="heatmap-grid">
            <div class="heatmap-days">
              <span>一</span><span>三</span><span>五</span>
            </div>
            <div class="heatmap-cells">
              <div v-for="cell in heatmapDays" :key="cell.date"
                class="heatmap-cell"
                :class="'level-' + cell.level"
                :title="cell.date + ': ' + cell.count + ' 次互动'">
              </div>
            </div>
          </div>
          <div class="heatmap-legend">
            <span>少</span>
            <span class="legend-cell level-0"></span>
            <span class="legend-cell level-1"></span>
            <span class="legend-cell level-2"></span>
            <span class="legend-cell level-3"></span>
            <span class="legend-cell level-4"></span>
            <span>多</span>
          </div>
        </div>
      </div>
      <!-- �🏅 成就系统 -->
      <div class="achievement-section">
        <div class="trend-header">🏅 成就</div>
        <div class="achievement-grid">          <div v-for="ach in stats.achievements" :key="ach.key" class="achievement-item" :class="{ unlocked: isUnlocked(ach.key) }">
            <div class="ach-icon">{{ ach.icon }}</div>
            <div class="ach-name">{{ ach.name }}</div>
            <div class="ach-status">{{ isUnlocked(ach.key) ? '✅' : '🔒' }}</div>
          </div>
        </div>
      </div>

      <!-- 📤 数据导出 -->
      <div class="export-section">
        <span class="trend-header">📤 导出数据</span>
        <div class="export-body">
          <el-select v-model="exportFormat" size="small" style="width:120px">
            <el-option label="Markdown" value="markdown" />
            <el-option label="JSON" value="json" />
            <el-option label="CSV" value="csv" />
          </el-select>
          <el-select v-model="exportType" size="small" style="width:130px">
            <el-option label="全部数据" value="all" />
            <el-option label="仅关注" value="follows" />
            <el-option label="仅收藏" value="favorites" />
            <el-option label="仅点赞" value="likes" />
          </el-select>
          <el-button size="small" type="primary" @click="handleExport" :loading="exporting">📥 下载</el-button>
        </div>
      </div>
    </div>
    <div v-else-if="statsLoading" class="loading-wrap"><el-icon class="is-loading" :size="24"><Loading /></el-icon></div>

    <el-divider />

    <!-- 互动列表标签页 -->
    <el-tabs v-model="activeInteractionTab" @tab-change="onTabChange">
      <!-- 🔥 关注动态 (新) -->
      <el-tab-pane label="🔥 关注动态" name="feed">
        <div class="feed-toolbar">
          <span style="font-size:12px;color:#909399">共 {{ feedItems.length }} 条动态</span>
          <a :href="'/build/following-feed'" target="_blank" style="font-size:12px;color:#409eff;text-decoration:none">查看全部 ›</a>
        </div>
        <div v-if="feedLoading" class="loading-wrap"><el-icon class="is-loading" :size="24"><Loading /></el-icon></div>
        <div v-else-if="feedItems.length === 0" class="empty-wrap"><el-empty description="关注的账号还没有发布内容" :image-size="60" /></div>
        <div v-else class="feed-list">
          <div v-for="item in feedItems" :key="'fd-'+item.id" class="feed-card" @click="openFeedItem(item)" style="cursor:pointer">
            <div class="feed-header">
              <el-avatar :size="28" :src="item.account?.avatar" class="feed-avatar">{{ item.account?.name?.charAt(0) || '?' }}</el-avatar>
              <div class="feed-account">
                <span class="feed-account-name">{{ item.account?.name || '未知账号' }}</span>
                <span class="feed-time">{{ formatTime(item.published_at) }}</span>
              </div>
            </div>
            <div class="feed-body">
              <div v-if="getCoverImage(item)" class="feed-cover"><img :src="getCoverImage(item)" :alt="item.title" /></div>
              <div class="feed-content">
                <div class="feed-title">{{ item.title }}</div>
                <div class="feed-summary" v-if="item.summary">{{ item.summary }}</div>
                <div class="feed-meta">
                  <span>👍 {{ item.likes_count }}</span>
                  <span>⭐ {{ item.favorites_count }}</span>
                  <span>💬 {{ item.comments_count }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </el-tab-pane>
      <el-tab-pane label="📋 阅读清单" name="queue">
        <div class="queue-tabs">
          <el-radio-group v-model="queueTab" size="small" @change="loadQueue">
            <el-radio-button value="pending">⏳ 待读 ({{ queuePendingCount }})</el-radio-button>
            <el-radio-button value="completed">✅ 已完成 ({{ queueDoneCount }})</el-radio-button>
          </el-radio-group>
        </div>
        <div v-if="queueLoading" class="loading-wrap"><el-icon class="is-loading" :size="24"><Loading /></el-icon></div>
        <div v-else-if="queueItems.length === 0" class="empty-wrap"><el-empty :description="queueTab === 'pending' ? '阅读清单是空的，从收藏中添加内容吧' : '还没有完成的阅读'" :image-size="60" /></div>
        <div v-else class="queue-list" ref="queueListRef">
          <div v-for="(item, idx) in queueItems" :key="item.id" class="queue-item" :class="{ 'is-completed': item.is_completed }" draggable="true"
            @dragstart="onDragStart(idx)" @dragover.prevent @drop="onDrop(idx)">
            <div class="queue-drag-handle" title="拖动排序">⠿</div>
            <div v-if="item.cover" class="queue-cover"><img :src="item.cover" :alt="item.title" /></div>
            <div v-else class="queue-cover queue-cover-placeholder">{{ typeIcon(item.type) }}</div>
            <div class="queue-body">
              <div class="queue-title">{{ item.title || '(无标题)' }}</div>
              <div class="queue-meta">
                <el-tag size="small" :type="typeTag(item.type)">{{ typeLabel(item.type) }}</el-tag>
                <span v-if="item.note" class="queue-note">📌 {{ item.note }}</span>
              </div>
            </div>
            <div class="queue-actions">
              <el-button text size="small" :type="item.is_completed ? 'warning' : 'success'" @click="toggleQueueItem(item)" :title="item.is_completed ? '移回待读' : '标记完成'">
                {{ item.is_completed ? '↩️' : '✅' }}
              </el-button>
              <el-button text size="small" type="danger" @click="removeQueueItem(item)" title="移除">🗑️</el-button>
            </div>
          </div>
        </div>
      </el-tab-pane>
      <el-tab-pane label="❤️ 关注" name="follows">
        <div v-if="loading" class="loading-wrap"><el-icon class="is-loading" :size="24"><Loading /></el-icon></div>
        <div v-else-if="interactions.follows?.length === 0" class="empty-wrap"><el-empty description="还没有关注任何账号" :image-size="60" /></div>
        <div v-else class="interaction-list">
          <div v-for="item in interactions.follows" :key="'f-'+item.id" class="interaction-item">
            <el-avatar :size="40" :src="item.avatar" class="item-avatar">{{ item.name?.charAt(0) || '?' }}</el-avatar>
            <div class="item-body">
              <div class="item-title">{{ item.name }}</div>
              <div class="item-meta">{{ item.description }} · 👥 {{ item.followers_count }} 人关注</div>
            </div>
            <div class="item-action"><span class="item-time">{{ formatTime(item.interacted_at) }}</span></div>
          </div>
        </div>
      </el-tab-pane>
      <el-tab-pane label="⭐ 收藏" name="favorites">
        <!-- 视图切换 + 搜索+筛选 -->
        <div class="filter-bar">
          <el-radio-group v-model="favViewMode" size="small" @change="favViewMode === 'collections' ? loadCollections() : loadFavorites()">
            <el-radio-button value="collections">📁 分类</el-radio-button>
            <el-radio-button value="list">📋 列表</el-radio-button>
          </el-radio-group>
          <template v-if="favViewMode === 'list'">
            <el-input v-model="favSearch" size="small" placeholder="🔍 搜索收藏..." clearable style="width:200px" @input="loadFavorites" @clear="loadFavorites" />
            <el-radio-group v-model="favTypeFilter" size="small" @change="loadFavorites">
              <el-radio-button value="">全部</el-radio-button>
              <el-radio-button value="blog_post">📝 博客</el-radio-button>
              <el-radio-button value="oa_article">📄 OA</el-radio-button>
              <el-radio-button value="forum_post">🌐 广场</el-radio-button>
            </el-radio-group>
          </template>
          <span class="filter-count" v-if="collectionsTotal > 0">共 {{ collectionsTotal }} 项</span>
        </div>

        <!-- 分类视图 -->
        <div v-if="favViewMode === 'collections'">
          <div v-if="colLoading" class="loading-wrap"><el-icon class="is-loading" :size="24"><Loading /></el-icon></div>
          <div v-else-if="collections.length === 0" class="empty-wrap"><el-empty description="还没有收藏任何内容" :image-size="60" /></div>
          <div v-else class="collections-grid">
            <div v-for="col in collections" :key="col.key" class="collection-card" @click="switchToFilteredList(col)">
              <div class="collection-header">
                <span class="collection-icon">{{ col.icon }}</span>
                <span class="collection-name">{{ col.name }}</span>
                <span class="collection-count">{{ col.count }}</span>
              </div>
              <div class="collection-preview" v-if="col.items?.length">
                <div v-for="item in col.items" :key="item.id" class="preview-item">
                  <div v-if="item.cover" class="preview-cover"><img :src="item.cover" :alt="item.title" /></div>
                  <div class="preview-info">
                    <div class="preview-title">{{ item.title }}</div>
                  </div>
                </div>
              </div>
              <div class="collection-empty" v-else><span>暂无内容</span></div>
            </div>
          </div>
        </div>

        <!-- 列表视图 -->
        <template v-if="favViewMode === 'list'">
          <div v-if="favLoading" class="loading-wrap"><el-icon class="is-loading" :size="24"><Loading /></el-icon></div>
          <div v-else-if="interactions.favorites?.length === 0" class="empty-wrap"><el-empty :description="favSearch ? '没有匹配的收藏' : '还没有收藏任何内容'" :image-size="60" /></div>
          <div v-else class="interaction-list">
            <div v-for="(item, idx) in interactions.favorites" :key="'fav-'+idx" class="interaction-item">
              <div v-if="item.cover" class="item-cover"><img :src="item.cover" :alt="item.title" /></div>
              <div v-else class="item-cover item-cover-placeholder">{{ typeIcon(item.type) }}</div>
              <div class="item-body">
                <div class="item-title">{{ item.title || '(无标题)' }}</div>
                <div class="item-meta">
                  <el-tag size="small" :type="typeTag(item.type)">{{ typeLabel(item.type) }}</el-tag>
                  {{ formatTime(item.interacted_at) }}
                </div>
              </div>
            </div>
          </div>
        </template>
      </el-tab-pane>
      <el-tab-pane label="👍 点赞" name="likes">
        <div v-if="loading" class="loading-wrap"><el-icon class="is-loading" :size="24"><Loading /></el-icon></div>
        <div v-else-if="interactions.likes?.length === 0" class="empty-wrap"><el-empty description="还没有点赞任何内容" :image-size="60" /></div>
        <div v-else class="interaction-list">
          <div v-for="(item, idx) in interactions.likes" :key="'lik-'+idx" class="interaction-item">
            <div v-if="item.cover" class="item-cover"><img :src="item.cover" :alt="item.title" /></div>
            <div v-else class="item-cover item-cover-placeholder">{{ typeIcon(item.type) }}</div>
            <div class="item-body">
              <div class="item-title">{{ item.title || '(无标题)' }}</div>
              <div class="item-meta"><el-tag size="small" :type="typeTag(item.type)">{{ typeLabel(item.type) }}</el-tag> {{ formatTime(item.interacted_at) }}</div>
            </div>
          </div>
        </div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Loading, Share } from '@element-plus/icons-vue';
import { getInteractions, getReadingStats, getFollowingFeed, getFavoriteCollections, saveReadingGoal, exportData, getHeatmap, getReadingReport, getRecommendations, getReadingQueue, addToReadingQueue, removeFromReadingQueue, toggleReadingQueueItem, sortReadingQueue } from '@/api/interaction';

function extractFirstImage(html) {
  if (!html) return '';
  var m = html.match(/<img[^>]+src=["']([^"']+)["']/);
  return m ? m[1] : '';
}

function getCoverImage(item) {
  return item?.cover_image || (item?.content ? extractFirstImage(item.content) : '');
}

const activeInteractionTab = ref('feed');
const loading = ref(false);
const statsLoading = ref(false);
const feedLoading = ref(false);
const favLoading = ref(false);
const colLoading = ref(false);
const favSearch = ref('');
const favTypeFilter = ref('');
const favViewMode = ref('collections');
const collections = ref([]);
const collectionsTotal = ref(0);
const stats = ref(null);
const feedItems = ref([]);
const goalSaving = ref(false);
const showGoalEditor = ref(false);
const editGoal = ref(3);
const exportFormat = ref('markdown');
const exportType = ref('all');
const exporting = ref(false);
const heatmapData = ref(null);
const heatmapYear = ref(new Date().getFullYear());
const nowYear = ref(new Date().getFullYear());
const heatmapDays = ref([]);
const monthLabels = ref([]);
const reportData = ref(null);
const reportPeriod = ref('monthly');
const recItems = ref([]);
const recLoading = ref(false);
const queueTab = ref('pending');
const queueItems = ref([]);
const queueLoading = ref(false);
const queuePendingCount = ref(0);
const queueDoneCount = ref(0);
const dragIndex = ref(-1);
const interactions = reactive({ follows: [], favorites: [], likes: [] });
const unlockedKeys = ref(new Set());

const goalBarClass = computed(() => {
  if (!stats.value) return '';
  if (stats.value.goal_progress >= 100) return 'goal-done';
  if (stats.value.goal_progress >= 50) return 'goal-half';
  return '';
});

async function saveGoal() {
  goalSaving.value = true;
  try {
    await saveReadingGoal(editGoal.value);
    ElMessage.success('阅读目标已更新');
    showGoalEditor.value = false;
    loadStats();
  } catch { ElMessage.error('保存失败'); }
  finally { goalSaving.value = false; }
}

async function handleExport() {
  exporting.value = true;
  try {
    const res = await exportData(exportFormat.value, exportType.value);
    const blob = new Blob([res.data], { type: res.headers['content-type'] || 'text/markdown' });
    const ext = { markdown: 'md', json: 'json', csv: 'csv' }[exportFormat.value] || 'md';
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `my-data-${new Date().toISOString().slice(0,10)}.${ext}`;
    a.click();
    URL.revokeObjectURL(url);
    ElMessage.success('📤 数据已导出');
  } catch { ElMessage.error('导出失败'); }
  finally { exporting.value = false; }
}

async function loadHeatmap() {
  try {
    const res = await getHeatmap(heatmapYear.value);
    heatmapData.value = res.data?.data || null;
    if (heatmapData.value?.days) {
      // 计算月份标签偏移
      const months = [];
      const days = heatmapData.value.days;
      let currentMonth = '';
      days.forEach((d, i) => {
        const m = d.date.slice(5, 7);
        if (m !== currentMonth) {
          currentMonth = m;
          months.push({ label: parseInt(m) + '月', offset: (i % 7) * 14 + Math.floor(i / 7) * 14 });
        }
      });
      monthLabels.value = months;
      heatmapDays.value = days;
    }
  } catch { /* ignore */ }
}

function switchHeatmapYear(dir) {
  heatmapYear.value += dir;
  if (heatmapYear.value < 2024) heatmapYear.value = 2024;
  if (heatmapYear.value > nowYear.value) heatmapYear.value = nowYear.value;
  loadHeatmap();
}

async function loadReport() {
  try {
    const res = await getReadingReport(reportPeriod.value);
    reportData.value = res.data?.data || null;
  } catch { /* ignore */ }
}

async function loadRecommendations() {
  recLoading.value = true;
  try {
    const res = await getRecommendations();
    recItems.value = res.data?.data?.items || [];
  } catch { /* ignore */ }
  finally { recLoading.value = false; }
}

function openRec(item) {
  if (item.slug) window.open('/blog/' + item.slug, '_blank');
}

// ── 阅读清单队列 ──
async function loadQueue() {
  queueLoading.value = true;
  try {
    const res = await getReadingQueue(queueTab.value);
    const data = res.data?.data || {};
    queueItems.value = data.items || [];
    queuePendingCount.value = data.pending_count || 0;
    queueDoneCount.value = data.completed_count || 0;
  } catch { /* ignore */ }
  finally { queueLoading.value = false; }
}

async function toggleQueueItem(item) {
  try {
    const res = await toggleReadingQueueItem(item.id);
    item.is_completed = res.data?.data?.is_completed;
    ElMessage.success(item.is_completed ? '✅ 标记完成' : '↩️ 移回待读');
    loadQueue();
  } catch { ElMessage.error('操作失败'); }
}

async function removeQueueItem(item) {
  try {
    await removeFromReadingQueue(item.id);
    ElMessage.success('已移除');
    loadQueue();
  } catch { ElMessage.error('移除失败'); }
}

function onDragStart(idx) {
  dragIndex.value = idx;
}

async function onDrop(idx) {
  if (dragIndex.value === -1 || dragIndex.value === idx) return;
  const items = [...queueItems.value];
  const [moved] = items.splice(dragIndex.value, 1);
  items.splice(idx, 0, moved);
  queueItems.value = items;
  dragIndex.value = -1;
  // 保存排序
  try {
    const sortData = items.map((item, i) => ({ id: item.id, sort_order: i }));
    await sortReadingQueue(sortData);
  } catch { /* ignore */ }
}

function shareReport() {
  if (!reportData.value) return;
  const d = reportData.value;

  ElMessageBox({
    title: '📤 分享阅读报告',
    message: `<div style="text-align:center;padding:8px 0">
      <canvas id="report-share-canvas" style="width:100%;max-width:380px;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.1);margin:0 auto 12px"></canvas>
      <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
        <el-button type="primary" id="report-download-btn" onclick="(function(){var c=document.getElementById('report-share-canvas');var a=document.createElement('a');a.href=c.toDataURL('image/png');a.download='reading-report-${new Date().toISOString().slice(0,10)}.png';a.click();})()">📥 下载图片</el-button>
        <el-button id="report-copy-btn" onclick="(function(){var c=document.getElementById('report-share-canvas');c.toBlob(function(b){navigator.clipboard.write([new ClipboardItem({'image/png':b})]).then(function(){ElMessage.success('✅ 图片已复制到剪贴板')}).catch(function(){ElMessage.warning('复制失败，请尝试下载')})})})()">📋 复制图片</el-button>
      </div>
    </div>`,
    dangerouslyUseHTMLString: true,
    showConfirmButton: false,
    showCancelButton: true,
    cancelButtonText: '关闭',
    beforeClose: (action, instance, done) => {
      done();
    },
  });

  // 异步生成图片
  setTimeout(() => generateReportImage(d), 100);
}

function generateReportImage(d) {
  const canvas = document.getElementById('report-share-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const W = 380, H = 560;
  canvas.width = W * 2; canvas.height = H * 2;
  canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
  ctx.scale(2, 2);

  // 渐变色背景
  const grad = ctx.createLinearGradient(0, 0, W, H);
  grad.addColorStop(0, '#667eea');
  grad.addColorStop(0.5, '#764ba2');
  grad.addColorStop(1, '#409eff');
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, W, H);

  // 白色卡片
  ctx.fillStyle = 'rgba(255,255,255,0.95)';
  roundRect(ctx, 16, 16, W - 32, H - 32, 16);
  ctx.fill();

  // 头部装饰条
  ctx.fillStyle = '#667eea';
  roundRect(ctx, 16, 16, W - 32, 6, 3);
  ctx.fill();

  // 用户头像（圆形）
  const avatarX = 30, avatarY = 38, avatarR = 20;
  ctx.save();
  ctx.beginPath();
  ctx.arc(avatarX + avatarR, avatarY + avatarR, avatarR, 0, Math.PI * 2);
  ctx.closePath();
  ctx.clip();
  ctx.fillStyle = '#e8e8e8';
  ctx.fillRect(avatarX, avatarY, avatarR * 2, avatarR * 2);
  if (d.user_avatar) {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = d.user_avatar;
    img.onload = () => {
      ctx.drawImage(img, avatarX, avatarY, avatarR * 2, avatarR * 2);
    };
  }
  ctx.restore();

  // 用户名称
  ctx.fillStyle = '#303133';
  ctx.font = 'bold 15px sans-serif';
  ctx.textAlign = 'left';
  ctx.fillText(d.user_name || '用户', avatarX + avatarR * 2 + 10, avatarY + avatarR + 5);

  // 标题
  ctx.fillStyle = '#303133';
  ctx.font = 'bold 22px sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText('📋 ' + d.period_label + '阅读报告', W / 2, 110);

  // 日期范围
  ctx.fillStyle = '#909399';
  ctx.font = '12px sans-serif';
  ctx.fillText(d.date_range || '', W / 2, 132);

  // 主统计 - 阅读量
  ctx.fillStyle = '#303133';
  ctx.font = 'bold 48px sans-serif';
  ctx.fillText(d.total_read, W / 2, 192);

  ctx.fillStyle = '#909399';
  ctx.font = '13px sans-serif';
  ctx.fillText('篇阅读量', W / 2, 214);

  // 增长率
  ctx.fillStyle = d.growth_percent >= 0 ? '#67c23a' : '#f56c6c';
  ctx.font = 'bold 18px sans-serif';
  ctx.fillText(d.growth_label + ' 较' + d.prev_period_label, W / 2, 242);

  // 分隔线
  ctx.strokeStyle = '#eee';
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.moveTo(40, 260);
  ctx.lineTo(W - 40, 260);
  ctx.stroke();

  // 统计详情 - 两列布局
  const stats = [
    { label: '博客', value: d.blog_read, icon: '📝', x: W * 0.25 },
    { label: 'OA文章', value: d.oa_read, icon: '📄', x: W * 0.75 },
  ];
  stats.forEach(s => {
    ctx.fillStyle = '#303133';
    ctx.font = 'bold 24px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(s.icon + ' ' + s.value, s.x, 300);
    ctx.fillStyle = '#909399';
    ctx.font = '12px sans-serif';
    ctx.fillText(s.label, s.x, 320);
  });

  // 第二行详情
  const details = [
    { label: '收藏', value: d.total_favorites, x: W * 0.2 },
    { label: '点赞', value: d.total_likes, x: W * 0.5 },
    { label: '连续', value: d.streak_days + '天', x: W * 0.8 },
  ];
  details.forEach(s => {
    ctx.fillStyle = '#303133';
    ctx.font = 'bold 18px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(s.value, s.x, 360);
    ctx.fillStyle = '#909399';
    ctx.font = '12px sans-serif';
    ctx.fillText(s.label, s.x, 380);
  });

  // 最爱时段
  ctx.fillStyle = '#606266';
  ctx.font = '13px sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText('⏰ 最爱时段 ' + d.peak_hour + ':00-' + (d.peak_hour + 1) + ':00', W / 2, 415);

  // 最爱标签
  if (d.top_tags?.length) {
    ctx.fillStyle = '#606266';
    ctx.font = '13px sans-serif';
    ctx.fillText('🏷️ ' + d.top_tags.join(' · '), W / 2, 440);
  }

  // 底部品牌
  ctx.fillStyle = '#c0c4cc';
  ctx.font = '12px sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText('📊 来自 HWT License', W / 2, H - 55);
  ctx.font = '11px sans-serif';
  ctx.fillText('88.huwutong.com', W / 2, H - 38);
}

function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.lineTo(x + w - r, y);
  ctx.quadraticCurveTo(x + w, y, x + w, y + r);
  ctx.lineTo(x + w, y + h - r);
  ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
  ctx.lineTo(x + r, y + h);
  ctx.quadraticCurveTo(x, y + h, x, y + h - r);
  ctx.lineTo(x, y + r);
  ctx.quadraticCurveTo(x, y, x + r, y);
  ctx.closePath();
}

async function loadFeed() {
  feedLoading.value = true;
  try {
    const res = await getFollowingFeed({ limit: 20 });
    feedItems.value = res.data?.data?.items || [];
  } catch { /* ignore */ }
  finally { feedLoading.value = false; }
}

function openFeedItem(item) {
  window.open('/build/oa-article/' + item.id, '_blank');
}

async function loadFavorites() {
  favLoading.value = true;
  try {
    const params = { tab: 'favorites' };
    if (favSearch.value) params.search = favSearch.value;
    if (favTypeFilter.value) params.type_filter = favTypeFilter.value;
    const res = await getInteractions(params);
    interactions.favorites = res.data?.data?.favorites || [];
  } catch { /* ignore */ }
  finally { favLoading.value = false; }
}

async function loadCollections() {
  colLoading.value = true;
  try {
    const res = await getFavoriteCollections();
    const data = res.data?.data || {};
    collections.value = data.collections || [];
    collectionsTotal.value = data.total || 0;
  } catch { /* ignore */ }
  finally { colLoading.value = false; }
}

function switchToFilteredList(col) {
  if (col.type_filter) favTypeFilter.value = col.type_filter;
  if (col.search) favSearch.value = col.search;
  favViewMode.value = 'list';
  loadFavorites();
}

function onTabChange(tab) {
  if (tab === 'feed') { loadFeed(); }
  else if (tab === 'favorites') {
    if (favViewMode.value === 'collections') loadCollections();
    else loadFavorites();
  }
  else if (tab === 'queue') { loadQueue(); }
  else { loadData(); }
}

async function loadStats() {
  statsLoading.value = true;
  try {
    const res = await getReadingStats();
    stats.value = res.data?.data || null;
    if (stats.value?.unlocked_achievements) {
      unlockedKeys.value = new Set(stats.value.unlocked_achievements.map(a => a.key));
    }
  } catch { /* ignore */ }
  finally {
    statsLoading.value = false;
    if (stats.value) editGoal.value = stats.value.daily_goal || 3;
  }
}

function isUnlocked(key) {
  return unlockedKeys.value.has(key);
}

function barHeight(count) {
  return Math.max(4, Math.min(60, count * 20));
}

function typeIcon(type) {
  return type === 'blog_post' ? '📝' : type === 'oa_article' ? '📄' : '🌐';
}

async function loadData() {
  loading.value = true;
  try {
    const res = await getInteractions({ tab: activeInteractionTab.value === 'follows' ? 'all' : activeInteractionTab.value });
    const data = res.data?.data || {};
    if (data.follows) interactions.follows = data.follows;
    if (data.favorites) interactions.favorites = data.favorites;
    if (data.likes) interactions.likes = data.likes;
  } catch { /* ignore */ }
  finally { loading.value = false; }
}

function formatTime(date) {
  if (!date) return '';
  const d = new Date(date);
  const now = new Date();
  const diff = now - d;
  if (diff < 86400000) return '今天';
  if (diff < 172800000) return '昨天';
  return d.toLocaleDateString('zh-CN', { month: '2-digit', day: '2-digit' });
}

function typeLabel(type) {
  return { blog_post: '博客', oa_article: 'OA 文章', forum_post: '广场' }[type] || type;
}

function typeTag(type) {
  return { blog_post: 'primary', oa_article: 'success', forum_post: 'warning' }[type] || 'info';
}

onMounted(() => { loadStats(); loadFeed(); loadCollections(); loadHeatmap(); loadReport(); loadRecommendations(); loadData(); });
</script>

<style scoped>
.user-interactions { min-height: 300px; }
.loading-wrap { display: flex; justify-content: center; padding: 60px 0; }
.empty-wrap { padding: 40px 0; }
.stats-dashboard { margin-bottom: 8px; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.stat-card { display: flex; align-items: center; gap: 12px; padding: 16px; border-radius: 8px; background: #f8f9fa; border: 1px solid #eee; transition: transform .2s; }
.stat-card:hover { transform: translateY(-2px); }
.stat-card-primary { border-left: 3px solid #409eff; }
.stat-card-success { border-left: 3px solid #67c23a; }
.stat-card-warning { border-left: 3px solid #e6a23c; }
.stat-card-info { border-left: 3px solid #909399; }
.stat-icon { font-size: 28px; flex-shrink: 0; }
.stat-body { flex: 1; }
.stat-value { font-size: 24px; font-weight: 700; color: #303133; line-height: 1.2; }
.stat-label { font-size: 12px; color: #909399; margin-top: 2px; }
.trend-section { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 16px; border: 1px solid #eee; }
.trend-header { font-size: 13px; font-weight: 600; color: #303133; margin-bottom: 12px; }
.trend-bars { display: flex; justify-content: space-between; align-items: flex-end; height: 80px; padding: 0 8px; }
.trend-day { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; }
.trend-bar-wrap { display: flex; align-items: flex-end; gap: 2px; height: 70px; }
.trend-bar { width: 12px; border-radius: 3px 3px 0 0; min-height: 4px; transition: height .3s; }
.blog-bar { background: #409eff; }
.oa-bar { background: #67c23a; }
.trend-label { font-size: 11px; color: #909399; }
.trend-value { font-size: 10px; color: #606266; font-weight: 600; }
.trend-legend { display: flex; justify-content: center; gap: 16px; margin-top: 8px; font-size: 11px; color: #909399; }
.legend-dot { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 4px; }
.blog-dot { background: #409eff; }
.oa-dot { background: #67c23a; }
.achievement-section { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 16px; border: 1px solid #eee; }

/* 阅读目标 */
.goal-section { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 16px; border: 1px solid #eee; }
.goal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.goal-body { }
.goal-info { display: flex; align-items: center; gap: 6px; font-size: 14px; color: #303133; margin-bottom: 8px; }
.goal-emoji { font-size: 20px; }
.goal-divider { color: #c0c4cc; }
.goal-bar-wrap { height: 20px; background: #e8e8e8; border-radius: 10px; overflow: hidden; margin-bottom: 6px; }
.goal-bar { height: 100%; border-radius: 10px; transition: width .5s; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #fff; font-weight: 600; background: #409eff; }
.goal-bar.goal-half { background: #e6a23c; }
.goal-bar.goal-done { background: #67c23a; }
.goal-message { font-size: 13px; color: #67c23a; font-weight: 500; margin-bottom: 6px; }
.goal-editor { display: flex; align-items: center; gap: 8px; margin-top: 8px; padding-top: 8px; border-top: 1px solid #eee; }

/* 数据导出 */
.export-section { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 16px; border: 1px solid #eee; }
.export-body { display: flex; align-items: center; gap: 8px; margin-top: 8px; flex-wrap: wrap; }

/* 互动热力图 */
.heatmap-section { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 16px; border: 1px solid #eee; }

/* 阅读报告 */
.report-section { background: linear-gradient(135deg, #f0f4ff, #e8f0fe); border-radius: 10px; padding: 16px; margin-bottom: 16px; border: 1px solid #d0e0ff; }

/* 猜你喜欢 */
.recommend-section { margin-bottom: 16px; }
.rec-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.rec-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
.rec-card {
  display: flex; gap: 10px; padding: 10px; border-radius: 8px;
  background: #fff; border: 1px solid #eee; cursor: pointer;
  transition: all .2s;
}
.rec-card:hover { border-color: #409eff; box-shadow: 0 2px 8px rgba(64,158,255,0.08); }
.rec-cover { width: 56px; height: 56px; border-radius: 4px; overflow: hidden; flex-shrink: 0; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.rec-cover img { width: 100%; height: 100%; object-fit: cover; }
.rec-body { flex: 1; min-width: 0; }
.rec-title { font-size: 13px; font-weight: 500; color: #303133; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
.rec-reason { font-size: 11px; color: #409eff; margin-top: 4px; }

/* 阅读清单 */
.queue-tabs { margin-bottom: 12px; }
.queue-list { display: flex; flex-direction: column; gap: 2px; }
.queue-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 8px; border-radius: 6px; transition: all .2s;
  background: #fff; border: 1px solid #f0f0f0; margin-bottom: 4px;
}
.queue-item:hover { border-color: #d0d0d0; background: #fafafa; }
.queue-item.is-completed { opacity: 0.6; background: #f8f8f8; }
.queue-item.is-completed .queue-title { text-decoration: line-through; color: #999; }
.queue-drag-handle { font-size: 16px; color: #c0c4cc; cursor: grab; flex-shrink: 0; padding: 0 4px; user-select: none; }
.queue-drag-handle:active { cursor: grabbing; }
.queue-cover { width: 40px; height: 32px; border-radius: 3px; overflow: hidden; flex-shrink: 0; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.queue-cover img { width: 100%; height: 100%; object-fit: cover; }
.queue-cover-placeholder { font-size: 16px; }
.queue-body { flex: 1; min-width: 0; }
.queue-title { font-size: 13px; font-weight: 500; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.queue-meta { display: flex; align-items: center; gap: 6px; margin-top: 2px; }
.queue-note { font-size: 11px; color: #909399; }
.queue-actions { display: flex; gap: 2px; flex-shrink: 0; }
.report-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.report-grid { display: flex; gap: 16px; flex-wrap: wrap; }
.report-main { display: flex; gap: 20px; flex-wrap: wrap; flex: 1; }
.report-stat { text-align: center; min-width: 80px; }
.report-num { font-size: 28px; font-weight: 700; color: #303133; line-height: 1; }
.report-unit { font-size: 12px; color: #909399; margin-left: 2px; }
.report-label { display: block; font-size: 11px; color: #909399; margin-top: 2px; }
.report-growth { display: block; font-size: 11px; margin-top: 2px; font-weight: 600; }
.report-growth.up { color: #67c23a; }
.report-growth.down { color: #f56c6c; }
.report-details { display: flex; flex-direction: column; gap: 4px; min-width: 200px; }
.report-detail-item { font-size: 12px; color: #606266; display: flex; align-items: center; gap: 6px; }
.report-detail-icon { font-size: 14px; }
.heatmap-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.heatmap-controls { display: flex; align-items: center; gap: 4px; font-size: 13px; }
.heatmap-stats { font-size: 12px; color: #909399; margin-bottom: 10px; display: flex; gap: 6px; }
.heatmap-wrap { overflow-x: auto; }
.heatmap-months { display: flex; font-size: 10px; color: #909399; margin-bottom: 4px; margin-left: 32px; height: 14px; }
.heatmap-months span { position: absolute; }
.heatmap-grid { display: flex; gap: 4px; }
.heatmap-days { display: flex; flex-direction: column; gap: 3px; font-size: 10px; color: #909399; padding-top: 2px; }
.heatmap-days span { height: 14px; line-height: 14px; }
.heatmap-cells { display: flex; flex-wrap: wrap; gap: 3px; max-height: 130px; flex-direction: column; }
.heatmap-cell { width: 14px; height: 14px; border-radius: 2px; background: #ebedf0; cursor: pointer; transition: all .15s; }
.heatmap-cell:hover { outline: 2px solid #303133; outline-offset: -1px; transform: scale(1.3); }
.heatmap-cell.level-0 { background: #ebedf0; }
.heatmap-cell.level-1 { background: #c6e48b; }
.heatmap-cell.level-2 { background: #7bc96f; }
.heatmap-cell.level-3 { background: #239a3b; }
.heatmap-cell.level-4 { background: #196127; }
.heatmap-legend { display: flex; align-items: center; gap: 3px; margin-top: 6px; font-size: 10px; color: #909399; margin-left: 32px; }
.legend-cell { width: 12px; height: 12px; border-radius: 2px; }

.achievement-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; }
.achievement-item { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 10px 6px; border-radius: 8px; background: #fff; border: 1px solid #eee; transition: all .2s; text-align: center; }
.achievement-item.unlocked { border-color: #e6a23c; background: #fffbf0; }
.achievement-item:not(.unlocked) { opacity: 0.5; filter: grayscale(0.5); }
.ach-icon { font-size: 24px; }
.ach-name { font-size: 11px; color: #606266; line-height: 1.3; }
.ach-status { font-size: 12px; }
.interaction-list { display: flex; flex-direction: column; gap: 2px; }
.interaction-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 6px; transition: background .2s; }
.interaction-item:hover { background: #f5f7fa; }
.item-avatar { flex-shrink: 0; }
.item-cover { width: 48px; height: 48px; border-radius: 4px; overflow: hidden; flex-shrink: 0; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.item-cover img { width: 100%; height: 100%; object-fit: cover; }
.item-cover-placeholder { font-size: 22px; }
.item-body { flex: 1; min-width: 0; }
.item-title { font-size: 14px; font-weight: 500; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.item-meta { font-size: 12px; color: #909399; margin-top: 2px; display: flex; align-items: center; gap: 6px; }
.item-action { flex-shrink: 0; }
.item-time { font-size: 11px; color: #c0c4cc; }
.el-divider { margin: 12px 0; }

/* 筛选栏 */
.filter-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.filter-count { font-size: 12px; color: #c0c4cc; white-space: nowrap; }

/* 智能收藏夹 */
.collections-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
.collection-card {
  border: 1px solid #eee; border-radius: 8px; padding: 14px;
  cursor: pointer; transition: all .2s; background: #fff;
}
.collection-card:hover { border-color: #409eff; box-shadow: 0 2px 8px rgba(64,158,255,0.08); }
.collection-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.collection-icon { font-size: 20px; }
.collection-name { font-size: 13px; font-weight: 600; color: #303133; flex: 1; }
.collection-count {
  font-size: 11px; background: #f0f2f5; color: #606266;
  padding: 1px 8px; border-radius: 10px; font-weight: 600;
}
.collection-preview { display: flex; flex-direction: column; gap: 6px; }
.preview-item { display: flex; align-items: center; gap: 8px; }
.preview-cover { width: 36px; height: 28px; border-radius: 3px; overflow: hidden; flex-shrink: 0; background: #f0f0f0; }
.preview-cover img { width: 100%; height: 100%; object-fit: cover; }
.preview-title { font-size: 12px; color: #606266; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.collection-empty { font-size: 12px; color: #c0c4cc; text-align: center; padding: 8px 0; }

/* 关注动态 Feed */
.feed-list { display: flex; flex-direction: column; gap: 12px; }
.feed-card { border: 1px solid #eee; border-radius: 8px; padding: 14px; transition: all .2s; background: #fff; }
.feed-card:hover { border-color: #409eff; box-shadow: 0 2px 8px rgba(64,158,255,0.08); }
.feed-toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.feed-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.feed-avatar { flex-shrink: 0; }
.feed-account { display: flex; align-items: center; gap: 8px; flex: 1; }
.feed-account-name { font-size: 13px; font-weight: 600; color: #303133; }
.feed-time { font-size: 11px; color: #c0c4cc; margin-left: auto; }
.feed-body { display: flex; gap: 12px; }
.feed-cover { width: 100px; height: 70px; border-radius: 4px; overflow: hidden; flex-shrink: 0; }
.feed-cover img { width: 100%; height: 100%; object-fit: cover; }
.feed-content { flex: 1; min-width: 0; }
.feed-title { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.feed-summary { font-size: 12px; color: #909399; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 6px; }
.feed-meta { display: flex; gap: 12px; font-size: 11px; color: #c0c4cc; }

</style>
