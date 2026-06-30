<template>
  <div class="product-reviews">
    <!-- 评分概览 -->
    <div class="reviews-header" v-if="stats">
      <div class="rating-summary">
        <div class="rating-score">{{ stats.avg_rating || '—' }}</div>
        <div class="rating-stars">
          <el-rate :model-value="Math.round(stats.avg_rating || 0)" disabled show-score :score-template="''" />
        </div>
        <div class="rating-count">{{ stats.total || 0 }} 条评价</div>
      </div>
      <div class="rating-bars" v-if="stats.distribution">
        <div v-for="i in 5" :key="i" class="rating-bar-row">
          <span class="bar-label">{{ 6 - i }}星</span>
          <el-progress
            :percentage="stats.distribution[6 - i]?.percent || 0"
            :color="['#f56c6c','#f7ba2a','#f7ba2a','#409eff','#67c23a'][6 - i]"
            :show-text="false"
            class="rating-bar"
          />
          <span class="bar-count">{{ stats.distribution[6 - i]?.count || 0 }}</span>
        </div>
      </div>
    </div>

    <!-- 评论列表 -->
    <div class="reviews-list" v-loading="loading">
      <div v-if="!reviews.length && !loading" class="no-reviews">
        <el-empty :description="'暂无评价'" :image-size="60" />
      </div>
      <div v-for="review in reviews" :key="review.id" class="review-item">
        <div class="review-header">
          <el-avatar :size="32" :src="review.user?.avatar_url" class="review-avatar">
            {{ (review.user?.name || '?').charAt(0) }}
          </el-avatar>
          <div class="review-user">
            <span class="user-name">{{ review.is_anonymous ? '匿名用户' : review.user?.name || '用户' }}</span>
            <el-rate :model-value="review.rating" disabled size="small" />
          </div>
          <span class="review-time">{{ formatTime(review.created_at) }}</span>
        </div>
        <div class="review-body">
          <el-tag v-if="review.is_verified_purchase" size="small" type="success" class="verified-tag">已购</el-tag>
          <el-tag v-for="tag in review.tags" :key="tag" size="small" class="review-tag">{{ tag }}</el-tag>
          <p class="review-content">{{ review.content }}</p>
          <div v-if="review.images?.length" class="review-images">
            <el-image
              v-for="(img, idx) in review.images" :key="idx"
              :src="img"
              class="review-img"
              fit="cover"
              :preview-src-list="review.images"
            />
          </div>
        </div>
        <div v-if="review.admin_reply" class="review-reply">
          <el-icon><ChatDotSquare /></el-icon>
          <div>
            <span class="reply-label">商家回复：</span>
            <span>{{ review.admin_reply }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 分页 -->
    <div v-if="pagination.total > pagination.per_page" class="pagination-wrap">
      <el-pagination
        background
        layout="prev,pager,next"
        :total="pagination.total"
        :page-size="pagination.per_page"
        :current-page="pagination.current_page"
        @current-change="loadReviews"
        small
      />
    </div>

    <!-- 发表评论 -->
    <div v-if="showReviewForm" class="review-form-section">
      <el-divider />
      <h4 class="form-title">发表评价</h4>
      <el-form ref="formRef" :model="form" :rules="rules" size="small">
        <el-form-item prop="rating">
          <el-rate v-model="form.rating" :texts="['非常差','差','一般','好','非常好']" show-text />
        </el-form-item>
        <el-form-item prop="content">
          <el-input
            v-model="form.content"
            type="textarea"
            :rows="4"
            placeholder="分享您的使用体验…（至少5个字）"
            maxlength="5000"
            show-word-limit
          />
        </el-form-item>
        <el-form-item>
          <el-checkbox v-model="form.is_anonymous">匿名评价</el-checkbox>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="submitReview" :loading="submitting">提交评价</el-button>
        </el-form-item>
      </el-form>
    </div>
    <el-button v-else-if="canReview" text type="primary" class="write-review-btn" @click="showReviewForm = true">
      <el-icon><EditPen /></el-icon> 写评价
    </el-button>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage } from 'element-plus';
import { ChatDotSquare, EditPen } from '@element-plus/icons-vue';
import shopApi from '@/api/shop';

const props = defineProps({
  productId: { type: [Number, String], required: true },
});

const reviews = ref([]);
const stats = ref(null);
const loading = ref(false);
const submitting = ref(false);
const showReviewForm = ref(false);
const formRef = ref(null);

const pagination = reactive({
  current_page: 1, per_page: 10, total: 0,
});

const form = reactive({
  rating: 5,
  content: '',
  is_anonymous: false,
});

const rules = {
  rating: [{ required: true, message: '请选择评分' }],
  content: [{ required: true, min: 5, message: '评论至少5个字', trigger: 'blur' }],
};

const canReview = computed(() => {
  return !!localStorage.getItem('auth_token');
});

onMounted(() => {
  loadStats();
  loadReviews();
});

async function loadStats() {
  try {
    const res = await shopApi.getProductReviewStats(props.productId);
    stats.value = res.data?.data || null;
  } catch { /* ignore */ }
}

async function loadReviews(page = 1) {
  loading.value = true;
  pagination.current_page = page;
  try {
    const res = await shopApi.getProductReviews(props.productId, {
      page, per_page: pagination.per_page,
    });
    const data = res.data?.data || res.data;
    reviews.value = data?.data || data || [];
    Object.assign(pagination, {
      current_page: data?.current_page || page,
      total: data?.total || 0,
    });
  } catch { /* ignore */ }
  finally { loading.value = false; }
}

async function submitReview() {
  const valid = await formRef.value?.validate().catch(() => false);
  if (!valid) return;
  submitting.value = true;
  try {
    await shopApi.submitReview({
      product_id: props.productId,
      rating: form.rating,
      content: form.content,
      is_anonymous: form.is_anonymous,
    });
    ElMessage.success('评论已提交，等待审核');
    showReviewForm.value = false;
    form.content = '';
    form.rating = 5;
    form.is_anonymous = false;
    loadReviews();
    loadStats();
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '提交失败');
  } finally { submitting.value = false; }
}

function formatTime(t) {
  if (!t) return '';
  return t?.substring(0, 10);
}
</script>

<style scoped>
.product-reviews { padding: 16px 0; }
.reviews-header { display: flex; gap: 24px; margin-bottom: 24px; padding: 16px; background: #fafafa; border-radius: 8px; }
.rating-summary { text-align: center; min-width: 100px; }
.rating-score { font-size: 36px; font-weight: 700; color: #f7ba2a; line-height: 1; }
.rating-count { font-size: 12px; color: #909399; margin-top: 4px; }
.rating-bars { flex: 1; display: flex; flex-direction: column; gap: 4px; }
.rating-bar-row { display: flex; align-items: center; gap: 8px; font-size: 12px; }
.bar-label { width: 24px; color: #606266; }
.rating-bar { flex: 1; }
.bar-count { width: 24px; text-align: right; color: #909399; }
.review-item { padding: 16px 0; border-bottom: 1px solid #f0f0f0; }
.review-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.review-user { flex: 1; }
.user-name { font-size: 14px; font-weight: 500; display: block; margin-bottom: 2px; }
.review-time { font-size: 12px; color: #c0c4cc; white-space: nowrap; }
.review-body { padding-left: 42px; }
.verified-tag { margin-right: 4px; }
.review-tag { margin-right: 2px; }
.review-content { font-size: 14px; line-height: 1.6; color: #303133; margin: 6px 0; }
.review-images { display: flex; gap: 8px; margin-top: 8px; }
.review-img { width: 80px; height: 80px; border-radius: 4px; cursor: pointer; }
.review-reply { margin-top: 8px; padding: 8px 12px; background: #f5f7fa; border-radius: 6px; font-size: 13px; color: #606266; display: flex; gap: 6px; margin-left: 42px; }
.reply-label { font-weight: 500; }
.no-reviews { padding: 32px 0; }
.pagination-wrap { display: flex; justify-content: center; margin-top: 16px; }
.review-form-section { margin-top: 16px; }
.form-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; }
.write-review-btn { margin-top: 12px; }
</style>
