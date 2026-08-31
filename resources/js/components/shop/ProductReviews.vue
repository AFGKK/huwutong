<template>
  <div class="product-reviews">
    <div class="reviews-header" v-if="stats">
      <div class="rating-summary">
        <div class="rating-score">{{ stats.avg_rating || '—' }}</div>
        <div class="rating-stars">
          <el-rate :model-value="Math.round(stats.avg_rating || 0)" disabled show-score :score-template="''" />
        </div>
        <div class="rating-count">{{ t('reviews.count', { n: stats.total || 0 }) }}</div>
      </div>
      <div class="rating-bars" v-if="stats.distribution">
        <div v-for="i in 5" :key="i" class="rating-bar-row">
          <span class="bar-label">{{ t('reviews.stars', { n: 6 - i }) }}</span>
          <el-progress
            :percentage="stats.distribution[6 - i]?.percent || 0"
            :color="['#f56c6c','#f7ba2a','#f7ba2a','#0f172a','#67c23a'][6 - i]"
            :show-text="false"
            class="rating-bar"
          />
          <span class="bar-count">{{ stats.distribution[6 - i]?.count || 0 }}</span>
        </div>
      </div>
    </div>

    <div class="reviews-list" v-loading="loading">
      <div v-if="!reviews.length && !loading" class="no-reviews">
        <el-empty :description="t('reviews.empty')" :image-size="60" />
      </div>
      <div v-for="review in reviews" :key="review.id" class="review-item">
        <div class="review-header">
          <el-avatar :size="32" :src="review.user?.avatar_url" class="review-avatar">
            {{ (review.user?.name || '?').charAt(0) }}
          </el-avatar>
          <div class="review-user">
            <span class="user-name">{{ review.is_anonymous ? t('reviews.anonymous') : review.user?.name || t('reviews.user') }}</span>
            <el-rate :model-value="review.rating" disabled size="small" />
          </div>
          <span class="review-time">{{ formatTime(review.created_at) }}</span>
        </div>
        <div class="review-body">
          <el-tag v-if="review.is_verified_purchase" size="small" type="success" class="verified-tag">{{ t('reviews.verified') }}</el-tag>
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
            <span class="reply-label">{{ t('reviews.seller_reply') }}</span>
            <span>{{ review.admin_reply }}</span>
          </div>
        </div>
      </div>
    </div>

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

    <div v-if="showReviewForm" class="review-form-section">
      <el-divider />
      <h4 class="form-title">{{ t('reviews.write_title') }}</h4>
      <el-form ref="formRef" :model="form" :rules="rules" size="small">
        <el-form-item prop="rating">
          <el-rate v-model="form.rating" :texts="rateTexts" show-text />
        </el-form-item>
        <el-form-item prop="content">
          <el-input
            v-model="form.content"
            type="textarea"
            :rows="4"
            :placeholder="t('reviews.content_ph')"
            maxlength="5000"
            show-word-limit
          />
        </el-form-item>
        <el-form-item>
          <el-checkbox v-model="form.is_anonymous">{{ t('reviews.anonymous_check') }}</el-checkbox>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="submitReview" :loading="submitting">{{ t('reviews.submit') }}</el-button>
        </el-form-item>
      </el-form>
    </div>
    <el-button v-else-if="canReview" text type="primary" class="write-review-btn" @click="showReviewForm = true">
      <el-icon><EditPen /></el-icon> {{ t('reviews.write') }}
    </el-button>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { ChatDotSquare, EditPen } from '@element-plus/icons-vue';
import shopApi from '@/api/shop';

const { t } = useI18n();

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

const rateTexts = computed(() => [
  t('reviews.rate_1'),
  t('reviews.rate_2'),
  t('reviews.rate_3'),
  t('reviews.rate_4'),
  t('reviews.rate_5'),
]);

const rules = computed(() => ({
  rating: [{ required: true, message: t('reviews.rating_required') }],
  content: [{ required: true, min: 5, message: t('reviews.content_min'), trigger: 'blur' }],
}));

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
    ElMessage.success(t('reviews.submitted'));
    showReviewForm.value = false;
    form.content = '';
    form.rating = 5;
    form.is_anonymous = false;
    loadReviews();
    loadStats();
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || t('reviews.submit_fail'));
  } finally { submitting.value = false; }
}

function formatTime(date) {
  if (!date) return '';
  return date?.substring(0, 10);
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
.bar-label { min-width: 48px; color: #606266; white-space: nowrap; }
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
