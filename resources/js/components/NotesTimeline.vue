<template>
  <div class="license-notes">
    <!-- 备注列表 -->
    <div v-if="notes.length === 0 && !loading" class="notes-empty">
      <el-empty :description="$t('messages.no_data')" :image-size="80" />
    </div>

    <div v-loading="loading" class="notes-timeline">
      <div v-for="note in notes" :key="note.id" class="note-item" :class="{ 'is-self': note.user_id === currentUserId }">
        <div class="note-avatar">
          <el-avatar :size="32">{{ note.user?.name?.charAt(0)?.toUpperCase() }}</el-avatar>
        </div>
        <div class="note-body">
          <div class="note-header">
            <span class="note-author">{{ note.user?.name || $t('notes.unknown_user') }}</span>
            <span class="note-time">{{ formatTime(note.created_at) }}</span>
            <el-button
              v-if="note.user_id === currentUserId"
              size="small"
              text
              type="danger"
              class="note-delete-btn"
              @click="handleDelete(note)"
            >
              <el-icon><Delete /></el-icon>
            </el-button>
          </div>
          <div class="note-content" v-html="renderContent(note)"></div>
          <div v-if="note.mentioned_users && note.mentioned_users.length > 0" class="note-mentions">
            <el-icon><UserFilled /></el-icon>
            <span v-for="(u, idx) in note.mentioned_users" :key="u.id">
              <span v-if="idx > 0">、</span>@{{ u.name }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- 输入区 -->
    <div class="note-input-area">
      <el-input
        v-model="newContent"
        type="textarea"
        :rows="3"
        :placeholder="$t('notes.placeholder')"
        resize="none"
        @keydown="handleKeydown"
      />
      <div class="note-input-footer">
        <div class="mention-hint" v-if="mentionSearch.active">
          <div class="mention-popover">
            <div
              v-for="user in mentionSearch.results"
              :key="user.id"
              class="mention-item"
              :class="{ 'is-active': mentionSearch.activeIndex === mentionSearch.results.indexOf(user) }"
              @click="insertMention(user)"
              @mouseenter="mentionSearch.activeIndex = mentionSearch.results.indexOf(user)"
            >
              <el-avatar :size="20">{{ user.name?.charAt(0)?.toUpperCase() }}</el-avatar>
              <span class="mention-name">{{ user.name }}</span>
              <span class="mention-email">{{ user.email }}</span>
            </div>
            <div v-if="mentionSearch.results.length === 0" class="mention-no-results">
              {{ $t('notes.no_match') }}
            </div>
          </div>
        </div>
        <div class="input-actions">
          <span class="char-count">{{ newContent.length }} / 5000</span>
          <el-button type="primary" size="small" :disabled="!newContent.trim() || submitting" @click="handleSubmit">
            {{ $t('notes.send') }}
          </el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Delete, UserFilled } from '@element-plus/icons-vue';
import { licenseNoteApi } from '@/api/licenseNote';
import { useAuthStore } from '@/stores/auth';

const { t } = useI18n();

const props = defineProps({
  licenseId: { type: [Number, String], required: true },
});

const emit = defineEmits(['note-changed']);

const authStore = useAuthStore();
const currentUserId = computed(() => authStore.user?.id);

const notes = ref([]);
const loading = ref(false);
const newContent = ref('');
const submitting = ref(false);

// @mention 搜索状态
const mentionSearch = reactive({
  active: false,
  query: '',
  results: [],
  activeIndex: 0,
  cursorPos: -1,
  mentionStart: -1,
});

/* ---------- 生命周期 ---------- */
onMounted(() => {
  fetchNotes();
});

/* ---------- 数据获取 ---------- */
async function fetchNotes() {
  loading.value = true;
  try {
    const res = await licenseNoteApi.list(props.licenseId);
    notes.value = res.data?.data || res.data || [];
  } catch (err) {
    // 静默处理
    notes.value = [];
  } finally {
    loading.value = false;
  }
}

/* ---------- 提交 ---------- */
async function handleSubmit() {
  const content = newContent.value.trim();
  if (!content || submitting.value) return;

  submitting.value = true;
  try {
    // 提取 @mention 的用户 ID
    const mentionedIds = extractMentionIds(content);
    await licenseNoteApi.create(props.licenseId, {
      content,
      mentions: mentionedIds,
    });
    newContent.value = '';
    await fetchNotes();
    emit('note-changed');
  } catch (err) {
    ElMessage.error(err.response?.data?.message || t('notes.add_fail'));
  } finally {
    submitting.value = false;
  }
}

/* ---------- 删除 ---------- */
async function handleDelete(note) {
  try {
    await ElMessageBox.confirm(t('notes.delete_confirm'), t('notes.delete_title'), {
      type: 'warning',
      confirmButtonText: t('actions.delete'),
      cancelButtonText: t('actions.cancel'),
    });
    await licenseNoteApi.destroy(props.licenseId, note.id);
    notes.value = notes.value.filter((n) => n.id !== note.id);
    emit('note-changed');
    ElMessage.success(t('notes.deleted'));
  } catch {
    // cancelled
  }
}

/* ---------- 键盘事件（@mention 导航） ---------- */
function handleKeydown(e) {
  if (mentionSearch.active) {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      mentionSearch.activeIndex = Math.min(mentionSearch.activeIndex + 1, mentionSearch.results.length - 1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      mentionSearch.activeIndex = Math.max(mentionSearch.activeIndex - 1, 0);
    } else if (e.key === 'Enter' || e.key === 'Tab') {
      if (mentionSearch.results.length > 0 && mentionSearch.activeIndex >= 0) {
        e.preventDefault();
        insertMention(mentionSearch.results[mentionSearch.activeIndex]);
      }
    } else if (e.key === 'Escape') {
      mentionSearch.active = false;
    }
  }
}

/* ---------- @mention 检测 ---------- */
watch(newContent, (val) => {
  const cursorPos = val.length; // 假设光标在末尾
  // 找到最后一个 @ 符号的位置（从光标往前找）
  const textBeforeCursor = val.substring(0, cursorPos);
  const atIndex = textBeforeCursor.lastIndexOf('@');

  if (atIndex === -1 || (atIndex > 0 && textBeforeCursor[atIndex - 1] !== ' ' && textBeforeCursor[atIndex - 1] !== '\n')) {
    // 如果不是在词首（前面不是空格/换行），可能是邮箱等，忽略
    // 简单处理：在行首也视为有效
    if (atIndex !== 0) {
      mentionSearch.active = false;
      return;
    }
  }

  // 提取 @ 后的查询词
  const afterAt = textBeforeCursor.substring(atIndex + 1);
  // 如果查询词包含空格，说明 @mention 结束
  if (afterAt.includes(' ')) {
    mentionSearch.active = false;
    return;
  }

  mentionSearch.active = true;
  mentionSearch.query = afterAt;
  mentionSearch.mentionStart = atIndex;
  mentionSearch.activeIndex = 0;
  debouncedSearch(afterAt);
});

let searchTimer = null;
function debouncedSearch(q) {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    doSearch(q);
  }, 200);
}

async function doSearch(q) {
  try {
    const res = await licenseNoteApi.searchUsers(q);
    mentionSearch.results = res.data?.data || res.data || [];
  } catch {
    mentionSearch.results = [];
  }
}

function insertMention(user) {
  if (mentionSearch.mentionStart === -1) return;
  const before = newContent.value.substring(0, mentionSearch.mentionStart);
  const after = newContent.value.substring(mentionSearch.mentionStart + 1 + mentionSearch.query.length);
  newContent.value = `${before}@${user.name} ${after}`;
  mentionSearch.active = false;
}

/* ---------- 工具函数 ---------- */
function formatTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function renderContent(note) {
  if (!note.content) return '';
  let html = escapeHtml(note.content);
  // 将 @用户名 渲染为高亮
  if (note.mentioned_users && note.mentioned_users.length > 0) {
    for (const u of note.mentioned_users) {
      const re = new RegExp(`@${escapeRegex(u.name)}`, 'g');
      html = html.replace(re, `<span class="mention-highlight">@${escapeHtml(u.name)}</span>`);
    }
  }
  // 换行转为 <br>
  html = html.replace(/\n/g, '<br>');
  return html;
}

function extractMentionIds(content) {
  const ids = [];
  if (!content) return ids;
  for (const note of notes.value) {
    if (note.mentioned_users) {
      for (const u of note.mentioned_users) {
        const re = new RegExp(`@${escapeRegex(u.name)}`, 'g');
        if (re.test(content) && !ids.includes(u.id)) {
          ids.push(u.id);
        }
      }
    }
  }
  return ids;
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

function escapeRegex(str) {
  return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
</script>

<style scoped>
.license-notes {
  display: flex;
  flex-direction: column;
}

.notes-empty {
  padding: 24px 0;
}

.notes-timeline {
  max-height: 400px;
  overflow-y: auto;
  margin-bottom: 16px;
}

.note-item {
  display: flex;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid var(--el-border-color-lighter);
}

.note-item:last-child {
  border-bottom: none;
}

.note-avatar {
  flex-shrink: 0;
}

.note-body {
  flex: 1;
  min-width: 0;
}

.note-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.note-author {
  font-weight: 600;
  font-size: 13px;
  color: var(--el-text-color-primary);
}

.note-time {
  font-size: 12px;
  color: var(--el-text-color-secondary);
}

.note-delete-btn {
  margin-left: auto;
  opacity: 0;
  transition: opacity 0.2s;
}

.note-item:hover .note-delete-btn {
  opacity: 1;
}

.note-content {
  font-size: 13px;
  line-height: 1.6;
  color: var(--el-text-color-regular);
  word-break: break-word;
}

.note-mentions {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
  font-size: 12px;
  color: var(--el-color-primary);
}

/* @mention 高亮 */
.note-content :deep(.mention-highlight) {
  color: var(--el-color-primary);
  font-weight: 500;
}

/* ---------- 输入区 ---------- */
.note-input-area {
  position: relative;
  border-top: 1px solid var(--el-border-color-lighter);
  padding-top: 12px;
}

.note-input-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
}

.char-count {
  font-size: 12px;
  color: var(--el-text-color-secondary);
}

.input-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* ---------- @mention Popover ---------- */
.mention-hint {
  position: absolute;
  bottom: 100%;
  left: 0;
  right: 0;
  z-index: 10;
  margin-bottom: 4px;
}

.mention-popover {
  background: var(--el-bg-color-overlay);
  border: 1px solid var(--el-border-color-light);
  border-radius: 6px;
  box-shadow: var(--el-box-shadow-light);
  max-height: 180px;
  overflow-y: auto;
}

.mention-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  cursor: pointer;
  font-size: 13px;
  transition: background 0.15s;
}

.mention-item.is-active,
.mention-item:hover {
  background: var(--el-color-primary-light-9);
}

.mention-name {
  font-weight: 500;
  color: var(--el-text-color-primary);
}

.mention-email {
  font-size: 12px;
  color: var(--el-text-color-secondary);
  margin-left: auto;
}

.mention-no-results {
  padding: 8px 12px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
  text-align: center;
}
</style>
