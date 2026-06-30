<template>
    <div class="app-detail-page">
        <div class="page-header">
            <el-button text :icon="ArrowLeft" @click="$router.push('/app-marketplace')">返回应用市场</el-button>
        </div>

        <div v-loading="loading">
            <template v-if="app">
                <!-- 应用头部 -->
                <el-card shadow="never" class="app-header-card mb-4">
                    <div class="app-header">
                        <el-avatar :size="64" :src="app.logo_url" icon="Grid" />
                        <div class="app-header-info">
                            <div class="app-name">{{ app.name }}</div>
                            <div class="app-slug">{{ app.slug }}</div>
                            <div class="app-meta-tags">
                                <el-tag size="small" type="success" v-if="app.status === 'published'">已上架</el-tag>
                                <el-tag size="small" v-else>{{ app.status }}</el-tag>
                                <el-tag size="small" effect="plain">{{ categoryLabel(app.category) }}</el-tag>
                                <el-tag size="small" effect="plain">v{{ app.current_version || '-' }}</el-tag>
                                <el-button text size="small" type="primary" @click="checkUpdate" :loading="checkingUpdate" style="margin-left:4px">
                                    检查更新
                                </el-button>
                                <el-tag size="small" effect="plain">{{ app.install_count || 0 }} 次安装</el-tag>
                            </div>
                        </div>
                        <div class="app-header-actions">
                            <el-button
                                type="primary"
                                size="large"
                                :loading="installingId === app.id"
                                @click="toggleInstall(app)"
                            >
                                {{ app.installed ? '已安装' : '安装应用' }}
                            </el-button>
                        </div>
                    </div>
                </el-card>

                <el-row :gutter="20">
                    <el-col :span="16">
                        <!-- 应用截图 -->
                        <el-card shadow="never" class="mb-4" v-if="app.screenshots?.length">
                            <template #header><span>截图</span></template>
                            <el-carousel :interval="4000" type="card" height="240px">
                                <el-carousel-item v-for="(img, idx) in app.screenshots" :key="idx">
                                    <img :src="img" :alt="app.name + '截图' + (idx+1)" class="screenshot-img" />
                                </el-carousel-item>
                            </el-carousel>
                        </el-card>

                        <!-- 应用描述 -->
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>应用简介</span></template>
                            <p class="app-desc">{{ app.short_description || '暂无简介' }}</p>
                            <el-divider />
                            <div class="app-description">{{ app.description || '暂无描述' }}</div>
                        </el-card>

                        <!-- 版本历史 -->
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>版本历史</span></template>
                            <el-timeline v-if="versions.length">
                                <el-timeline-item
                                    v-for="v in versions"
                                    :key="v.id"
                                    :timestamp="fmtDate(v.released_at || v.created_at)"
                                    placement="top"
                                >
                                    <div class="version-item">
                                        <el-tag size="small" type="primary">v{{ v.version }}</el-tag>
                                        <span class="version-status">
                                            <el-tag v-if="v.status === 'published'" size="small" type="success">当前</el-tag>
                                            <el-tag v-else size="small">{{ v.status }}</el-tag>
                                        </span>
                                    </div>
                                    <div class="version-changelog">{{ v.changelog || '无更新说明' }}</div>
                                </el-timeline-item>
                            </el-timeline>
                            <el-empty v-else description="暂无版本记录" :image-size="40" />
                        </el-card>

                        <!-- 安装记录 -->
                        <el-card shadow="never">
                            <template #header><span>安装记录</span></template>
                            <el-table :data="installations" stripe v-loading="installLoading" size="small">
                                <el-table-column label="租户" min-width="120" prop="tenant?.name" />
                                <el-table-column label="版本" width="80" prop="installed_version" />
                                <el-table-column label="状态" width="80">
                                    <template #default="{ row }">
                                        <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
                                            {{ row.status === 'active' ? '已安装' : row.status }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="安装时间" width="160">
                                    <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                                </el-table-column>
                            </el-table>
                        </el-card>

                        <!-- 用户评价 -->
                        <el-card shadow="never" class="mb-4">
                            <template #header>
                                <div class="card-header-flex">
                                    <span>用户评价 <el-tag size="small" type="warning" v-if="reviewStats">{{ reviewStats.total }} 条</el-tag></span>
                                </div>
                            </template>

                            <!-- 评分统计 -->
                            <div class="review-summary" v-if="reviewStats">
                                <div class="rating-big">
                                    <div class="rating-number">{{ reviewStats.average }}</div>
                                    <el-rate :model-value="Math.round(reviewStats.average)" disabled show-score text-color="#ff9900" score-template="" />
                                    <div class="rating-total">{{ reviewStats.total }} 条评价</div>
                                </div>
                                <div class="rating-bars">
                                    <div v-for="s in 5" :key="s" class="rating-bar-row">
                                        <span class="bar-label">{{ 6 - s }} 星</span>
                                        <el-progress
                                            :percentage="reviewStats.total > 0 ? (reviewStats.distribution[6-s] / reviewStats.total * 100) : 0"
                                            :stroke-width="8"
                                            :color="['#f56c6c','#e6a23c','#f7d06e','#b3e19d','#67c23a'][6-s-1]"
                                        />
                                        <span class="bar-count">{{ reviewStats.distribution[6-s] }}</span>
                                    </div>
                                </div>
                            </div>
                            <el-divider />

                            <!-- 写评价 -->
                            <div class="write-review" v-if="!myReview">
                                <el-rate v-model="newRating" :texts="['差', '一般', '不错', '推荐', '强烈推荐']" show-text />
                                <el-input v-model="newReviewContent" type="textarea" :rows="3" placeholder="分享您的使用体验..." maxlength="2000" show-word-limit class="review-input" />
                                <div class="review-actions">
                                    <el-button type="primary" size="small" :loading="submittingReview" @click="submitReview">提交评价</el-button>
                                </div>
                            </div>
                            <div v-else class="my-review">
                                <div class="my-review-header">我的评价</div>
                                <el-rate :model-value="myReview.rating" disabled />
                                <p>{{ myReview.content }}</p>
                                <el-button text size="small" type="primary" @click="editMyReview">编辑</el-button>
                                <el-button text size="small" type="danger" @click="deleteMyReview">删除</el-button>
                            </div>
                            <el-divider />

                            <!-- 评价列表 -->
                            <div v-loading="reviewsLoading">
                                <div v-for="r in reviews" :key="r.id" class="review-item">
                                    <div class="review-header">
                                        <el-avatar :size="28">{{ r.user?.name?.charAt(0) || '?' }}</el-avatar>
                                        <div class="review-user">
                                            <span class="review-name">{{ r.user?.name || '匿名用户' }}</span>
                                            <el-rate :model-value="r.rating" disabled size="small" />
                                        </div>
                                        <span class="review-time">{{ fmtDate(r.created_at) }}</span>
                                    </div>
                                    <div class="review-content" v-if="r.content">{{ r.content }}</div>
                                    <!-- 开发者回复 -->
                                    <div v-for="reply in (r.replies || [])" :key="reply.id" class="reply-item">
                                        <el-tag size="small" type="success">开发者回复</el-tag>
                                        <span>{{ reply.content }}</span>
                                    </div>
                                    <div class="review-footer">
                                        <el-button v-if="isDeveloper" text size="small" type="success" @click="showReplyInput(r.id)">回复</el-button>
                                    </div>
                                    <!-- 回复输入框 -->
                                    <div v-if="replyingTo === r.id" class="reply-input">
                                        <el-input v-model="replyContent" :rows="2" type="textarea" placeholder="输入回复内容..." />
                                        <el-button size="small" type="primary" :loading="replying" @click="submitReply(r.id)">发送</el-button>
                                    </div>
                                </div>
                                <el-empty v-if="!reviews.length && !reviewsLoading" description="暂无评价" :image-size="40" />
                                <div class="pagination-wrap" v-if="reviewTotal > reviews.length">
                                    <el-pagination
                                        v-model:current-page="reviewPage"
                                        :page-size="10"
                                        :total="reviewTotal"
                                        size="small"
                                        layout="prev, pager, next"
                                        @current-change="loadReviews"
                                    />
                                </div>
                            </div>
                        </el-card>
                    </el-col>

                    <el-col :span="8">
                        <!-- 开发者信息 -->
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>开发者</span></template>
                            <div class="developer-info">
                                <el-avatar :size="40">{{ app.developer?.display_name?.charAt(0) || '?' }}</el-avatar>
                                <div>
                                    <div class="dev-name">{{ app.developer?.display_name || '未知' }}</div>
                                    <div class="text-muted small" v-if="app.developer?.company_name">{{ app.developer.company_name }}</div>
                                </div>
                            </div>
                            <el-divider />
                            <div class="info-row"><span class="label">网站</span><span>{{ app.developer?.website || '-' }}</span></div>
                            <div class="info-row"><span class="label">注册时间</span><span>{{ fmtDate(app.developer?.created_at) }}</span></div>
                        </el-card>

                        <!-- 应用信息 -->
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>应用信息</span></template>
                            <div class="info-row"><span class="label">App ID</span><code>{{ app.id }}</code></div>
                            <div class="info-row"><span class="label">唯一标识</span><code>{{ app.slug }}</code></div>
                            <div class="info-row"><span class="label">分类</span><span>{{ categoryLabel(app.category) }}</span></div>
                            <div class="info-row"><span class="label">当前版本</span><span>v{{ app.current_version || '-' }}</span></div>
                            <div class="info-row"><span class="label">上架时间</span><span>{{ fmtDate(app.published_at) }}</span></div>
                            <div class="info-row"><span class="label">更新时间</span><span>{{ fmtDate(app.updated_at) }}</span></div>
                            <div class="info-row"><span class="label">定价</span><span>{{ pricingLabel(app.pricing_type) }}</span></div>
                            <div class="info-row" v-if="app.price"><span class="label">价格</span><span>¥{{ app.price }}</span></div>
                        </el-card>

                        <!-- 权限信息 -->
                        <el-card shadow="never" v-if="app.permissions?.length">
                            <template #header><span>所需权限</span></template>
                            <el-tag v-for="p in app.permissions" :key="p" size="small" style="margin:2px">{{ p }}</el-tag>
                        </el-card>
                    </el-col>
                </el-row>
            </template>
            <el-empty v-else description="应用不存在" :image-size="60" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { ArrowLeft, Grid } from '@element-plus/icons-vue';
import api from '@/api/openPlatform';
import marketplaceApi from '@/api/marketplace';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const installLoading = ref(false);
const installingId = ref(null);
const app = ref(null);
const versions = ref([]);
const installations = ref([]);

// ─── 评价 ───
const reviews = ref([]);
const reviewsLoading = ref(false);
const reviewTotal = ref(0);
const reviewPage = ref(1);
const reviewStats = ref(null);
const myReview = ref(null);
const newRating = ref(0);
const newReviewContent = ref('');
const submittingReview = ref(false);
const replyingTo = ref(null);
const replyContent = ref('');
const replying = ref(false);

const isDeveloper = computed(() => {
    return app.value?.developer?.user_id === route.meta?.userId;
});

const CATEGORY_MAP = {
    automation: '自动化', analytics: '数据分析', notification: '通知',
    integration: '集成', security: '安全', other: '其他',
};

const PRICING_MAP = {
    free: '免费', paid: '付费', freemium: '免费+内购', subscription: '订阅',
};

function categoryLabel(val) { return CATEGORY_MAP[val] || val || '-'; }
function pricingLabel(val) { return PRICING_MAP[val] || val || '免费'; }

function fmtDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadApp() {
    const id = route.params.id;
    if (!id) { router.push('/app-marketplace'); return; }
    loading.value = true;
    try {
        const { data: res } = await api.showApp(id);
        app.value = res.data || {};
    } catch {
        app.value = null;
    } finally {
        loading.value = false;
    }
}

async function loadVersions() {
    try {
        const { data: res } = await api.apps({ app_id: route.params.id, per_page: 50 });
        versions.value = res.data?.data || res.data || [];
    } catch { /* ignore */ }
}

async function loadInstallations() {
    installLoading.value = true;
    try {
        const { data: res } = await api.installations({ app_id: route.params.id, per_page: 50 });
        installations.value = res.data?.data || res.data || [];
    } catch { /* ignore */ }
    finally { installLoading.value = false; }
}

const checkingUpdate = ref(false);

async function checkUpdate() {
    checkingUpdate.value = true;
    try {
        const { data: res } = await api.checkUpdate(app.value.id, { current_version: app.value.current_version || '0.0.0' });
        const updateInfo = res.data || res;
        if (updateInfo.has_update) {
            ElMessageBox.alert(
                `<div style="text-align:left">
                    <p><b>当前版本：</b>v${updateInfo.current_version || app.value.current_version}</p>
                    <p><b>最新版本：</b>v${updateInfo.latest_version}</p>
                    <p style="margin-top:8px"><b>更新说明：</b><br>${(updateInfo.changelog || '无更新说明').replace(/\n/g, '<br>')}</p>
                 </div>`,
                '发现新版本！',
                { dangerouslyUseHTMLString: true, confirmButtonText: '知道了' }
            );
        } else {
            ElMessage.success('当前已是最新版本');
        }
    } catch { ElMessage.error('检查更新失败'); }
    finally { checkingUpdate.value = false; }
}

async function toggleInstall(appItem) {
    installingId.value = appItem.id;
    try {
        if (appItem.installed) {
            await ElMessageBox.confirm(`确定卸载「${appItem.name}」吗？`, '确认卸载');
            await api.uninstallApp(appItem.id);
            appItem.installed = false;
            ElMessage.success('已卸载');
        } else {
            await api.installApp(appItem.id);
            appItem.installed = true;
            ElMessage.success('安装成功');
        }
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || '操作失败');
        }
    } finally {
        installingId.value = null;
    }
}

// ─── 评价相关 ───

async function loadReviews() {
    if (!route.params.id) return;
    reviewsLoading.value = true;
    try {
        const { data: res } = await marketplaceApi.reviews(route.params.id, { page: reviewPage.value, per_page: 10 });
        if (res.success) {
            reviews.value = res.data || [];
            reviewTotal.value = res.meta?.total || 0;
        }
    } catch { reviews.value = []; }
    finally { reviewsLoading.value = false; }
}

async function loadReviewStats() {
    if (!route.params.id) return;
    try {
        const { data: res } = await marketplaceApi.reviewStats(route.params.id);
        if (res.success) reviewStats.value = res.data;
    } catch {}
}

async function submitReview() {
    if (!newRating.value) { ElMessage.warning('请选择评分'); return; }
    submittingReview.value = true;
    try {
        const { data: res } = await marketplaceApi.reviewCreate(route.params.id, {
            rating: newRating.value,
            content: newReviewContent.value,
        });
        if (res.success) {
            ElMessage.success('评价提交成功');
            myReview.value = res.data;
            newRating.value = 0;
            newReviewContent.value = '';
            loadReviews();
            loadReviewStats();
        }
    } catch (e) {
        if (e.response?.data?.error?.code === 'ALREADY_REVIEWED') {
            ElMessage.warning('您已评价过此应用');
        }
    } finally { submittingReview.value = false; }
}

async function deleteMyReview() {
    if (!myReview.value) return;
    try {
        await ElMessageBox.confirm('确定删除您的评价吗？', '确认删除', { type: 'warning' });
        await marketplaceApi.reviewDelete(myReview.value.id);
        ElMessage.success('评价已删除');
        myReview.value = null;
        loadReviews();
        loadReviewStats();
    } catch {}
}

function editMyReview() {
    if (!myReview.value) return;
    newRating.value = myReview.value.rating;
    newReviewContent.value = myReview.value.content || '';
    myReview.value = null;
}

function showReplyInput(reviewId) {
    replyingTo.value = replyingTo.value === reviewId ? null : reviewId;
    replyContent.value = '';
}

async function submitReply(reviewId) {
    if (!replyContent.value.trim()) return;
    replying.value = true;
    try {
        await marketplaceApi.reviewReply(reviewId, { content: replyContent.value });
        ElMessage.success('回复成功');
        replyingTo.value = null;
        replyContent.value = '';
        loadReviews();
    } catch {} finally { replying.value = false; }
}

onMounted(() => {
    loadApp();
    loadVersions();
    loadInstallations();
    loadReviews();
    loadReviewStats();
});
</script>

<style scoped>
.app-detail-page { padding: 20px; }
.page-header { margin-bottom: 16px; }
.mb-4 { margin-bottom: 16px; }
.app-header-card :deep(.el-card__body) { padding: 24px; }
.app-header { display: flex; align-items: center; gap: 20px; }
.app-header-info { flex: 1; }
.app-name { font-size: 24px; font-weight: 700; }
.app-slug { font-size: 13px; color: #909399; margin: 2px 0 8px; }
.app-meta-tags { display: flex; gap: 6px; flex-wrap: wrap; }
.app-header-actions { flex-shrink: 0; }
.app-desc { font-size: 15px; color: #606266; line-height: 1.6; }
.app-description { white-space: pre-wrap; line-height: 1.7; color: #303133; font-size: 14px; }
.developer-info { display: flex; align-items: center; gap: 12px; }
.dev-name { font-weight: 500; }
.small { font-size: 12px; }
.info-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; }
.info-row .label { color: #909399; }
.version-item { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.version-changelog { font-size: 13px; color: #606266; margin-top: 4px; white-space: pre-wrap; }
.screenshot-img { width: 100%; height: 100%; object-fit: cover; border-radius: 4px; }
.card-header-flex { display: flex; align-items: center; gap: 8px; }
.review-summary { display: flex; gap: 24px; align-items: center; }
.rating-big { text-align: center; min-width: 120px; }
.rating-number { font-size: 48px; font-weight: 700; color: #f7ba2a; line-height: 1; }
.rating-total { font-size: 13px; color: #909399; margin-top: 4px; }
.rating-bars { flex: 1; }
.rating-bar-row { display: flex; align-items: center; gap: 8px; margin: 4px 0; }
.bar-label { width: 40px; font-size: 13px; text-align: right; color: #606266; }
.bar-count { width: 30px; font-size: 13px; color: #909399; text-align: right; }
.write-review { margin: 8px 0; }
.review-input { margin: 12px 0; }
.review-actions { display: flex; justify-content: flex-end; }
.my-review { background: #f5f7fa; padding: 16px; border-radius: 6px; }
.my-review-header { font-weight: 500; margin-bottom: 8px; }
.review-item { padding: 16px 0; border-bottom: 1px solid #f0f0f0; }
.review-item:last-child { border-bottom: none; }
.review-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.review-user { flex: 1; }
.review-name { font-weight: 500; font-size: 14px; margin-right: 8px; }
.review-time { font-size: 12px; color: #909399; }
.review-content { font-size: 14px; line-height: 1.6; color: #303133; padding-left: 38px; }
.review-footer { padding-left: 38px; margin-top: 4px; }
.reply-item { background: #f5f7fa; padding: 8px 12px; margin: 8px 0 4px 38px; border-radius: 4px; font-size: 13px; display: flex; gap: 8px; align-items: flex-start; }
.reply-input { padding-left: 38px; margin-top: 8px; display: flex; gap: 8px; align-items: flex-start; }
.pagination-wrap { margin-top: 12px; display: flex; justify-content: center; }
</style>
