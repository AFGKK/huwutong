<template>
    <div class="oa-editor-page">
        <!-- 顶部工具栏 -->
        <div class="editor-topbar">
            <div class="topbar-left">
                <el-button text size="small" @click="handleClose">
                    <el-icon><ArrowLeft /></el-icon> {{ t('oa_article_detail_page.back') }}
                </el-button>
                <el-divider direction="vertical" />
                <span class="topbar-title">
                    {{ isEdit ? tp('edit_title') : tp('create_title') }}
                    <el-tag v-if="targetAccount" size="small">{{ targetAccount.name }}</el-tag>
                </span>
            </div>
            <div class="topbar-right">
                <el-button size="small" text @click="previewVisible = true">{{ tp('preview') }}</el-button>
                <el-button size="small" text @click="openDistributeDialog" :disabled="!articleForm.content || articleForm.content.length < 20">{{ tp('distribute') }}</el-button>
                <el-button size="small" text @click="saveDraft">{{ tp('save_draft') }}</el-button>
                <el-dropdown trigger="click" @command="handlePublishAction">
                    <el-button size="small" type="primary">
                        {{ isEdit ? t('actions.update') : tp('publish') }}
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="publish">{{ tp('publish') }}</el-dropdown-item>
                            <el-dropdown-item command="schedule">{{ tp('schedule_publish') }}</el-dropdown-item>
                            <el-dropdown-item command="draft">{{ tp('draft_only') }}</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </div>

        <div class="editor-body">
            <!-- 左侧编辑区 -->
            <div class="editor-main">
                <!-- 文章元信息 -->
                <div class="meta-form">
                    <el-input v-model="articleForm.title" :placeholder="tp('title_ph')" size="large"
                        class="title-input" maxlength="200" />
                    <div class="meta-row">
                        <el-select v-model="articleForm.account_id" :placeholder="tp('account_ph')" size="small" style="width:180px">
                            <el-option v-for="a in myAccounts" :key="a.id" :label="a.name" :value="a.id">
                                <span>{{ a.avatar || '📢' }} {{ a.name }}</span>
                            </el-option>
                        </el-select>
                        <el-input v-model="articleForm.summary" :placeholder="tp('summary_ph')" size="small" style="width:240px" maxlength="300" />
                        <div style="display:flex;gap:6px;align-items:center">
                            <el-button size="small" text @click="uploadCoverImage" :title="t('blog_page.upload_cover')">{{ tp('cover_btn') }}</el-button>
                            <el-input v-model="articleForm.cover_image" :placeholder="tp('cover_url_ph')" size="small" style="width:180px" maxlength="500" />
                        </div>
                        <el-switch v-model="articleForm.is_original" :active-text="t('oa_article_detail_page.original')" size="small" style="margin-left:4px" />
                        <el-switch v-model="articleForm.allow_comments" :active-text="tp('allow_comments')" size="small" />
                        <el-switch v-model="articleForm.is_paid" :active-text="tp('paid_reading')" size="small" style="margin-left:4px" @change="onPaidToggle" />
                        <template v-if="articleForm.is_paid">
                            <el-input-number v-model="articleForm.price" :min="1" :max="99999" size="small" style="width:100px" />
                            <el-select v-model="articleForm.price_type" size="small" style="width:90px">
                                <el-option value="points"><span style="display:inline-flex;align-items:center;gap:4px"><PointsIcon :size="16" /> {{ tp('points_unit') }}</span></el-option>
                                <el-option :label="tp('money')" value="money" />
                            </el-select>
                        </template>
                        <el-date-picker v-model="articleForm.scheduled_at" type="datetime" :placeholder="tp('schedule_ph')" size="small" style="width:180px" :disabled-date="d => d <= new Date()" format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DD HH:mm:ss" clearable />
                    </div>
                </div>

                                <!-- 第一行工具栏：基础排版 -->
                <div class="editor-toolbar" v-if="editor">
                    <el-button-group size="small">
                        <el-button :type="editor.isActive('bold') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBold().run()" :title="t('blog_page.tb_bold')"><b>B</b></el-button>
                        <el-button :type="editor.isActive('italic') ? 'primary' : 'default'" @click="editor.chain().focus().toggleItalic().run()" :title="t('blog_page.tb_italic')"><i>I</i></el-button>
                        <el-button :type="editor.isActive('underline') ? 'primary' : 'default'" @click="editor.chain().focus().toggleUnderline().run()" :title="t('blog_page.tb_underline')"><u>U</u></el-button>
                        <el-button :type="editor.isActive('strike') ? 'primary' : 'default'" @click="editor.chain().focus().toggleStrike().run()" :title="t('blog_page.tb_strike')"><s>S</s></el-button>
                    </el-button-group>
                    <el-divider direction="vertical" />
                    <el-button-group size="small">
                        <el-button :type="editor.isActive('heading', { level: 1 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()">H1</el-button>
                        <el-button :type="editor.isActive('heading', { level: 2 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</el-button>
                        <el-button :type="editor.isActive('heading', { level: 3 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</el-button>
                    </el-button-group>
                    <el-divider direction="vertical" />
                    <el-button-group size="small">
                        <el-button :type="editor.isActive('bulletList') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBulletList().run()" :title="t('blog_page.tb_bullet_list')">•</el-button>
                        <el-button :type="editor.isActive('orderedList') ? 'primary' : 'default'" @click="editor.chain().focus().toggleOrderedList().run()" :title="t('blog_page.tb_ordered_list')">1.</el-button>
                        <el-button :type="editor.isActive('blockquote') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBlockquote().run()" :title="t('blog_page.tb_blockquote')">❝</el-button>
                        <el-button :type="editor.isActive('code') ? 'primary' : 'default'" @click="editor.chain().focus().toggleCode().run()" :title="t('blog_page.tb_inline_code')">`code`</el-button>
                        <el-button :type="editor.isActive('codeBlock') ? 'primary' : 'default'" @click="editor.chain().focus().toggleCodeBlock().run()" :title="t('blog_page.tb_code_block')">&lt;/&gt;</el-button>
                    </el-button-group>
                    <el-divider direction="vertical" />
                    <el-button-group size="small">
                        <el-button :type="editor.isActive({ textAlign: 'left' }) ? 'primary' : 'default'" @click="editor.chain().focus().setTextAlign('left').run()" :title="t('blog_page.tb_align_left')">⬅</el-button>
                        <el-button :type="editor.isActive({ textAlign: 'center' }) ? 'primary' : 'default'" @click="editor.chain().focus().setTextAlign('center').run()" :title="t('blog_page.tb_align_center')">⇔</el-button>
                        <el-button :type="editor.isActive({ textAlign: 'right' }) ? 'primary' : 'default'" @click="editor.chain().focus().setTextAlign('right').run()" :title="t('blog_page.tb_align_right')">➔</el-button>
                    </el-button-group>
                    <el-divider direction="vertical" />
                    <el-button-group size="small">
                        <el-button @click="editor?.chain().focus().undo().run()" :title="t('blog_page.tb_undo')">↩</el-button>
                        <el-button @click="editor?.chain().focus().redo().run()" :title="t('blog_page.tb_redo')">↪</el-button>
                    </el-button-group>
                </div>
                <!-- 第二行工具栏：插入 + 颜色/高亮 + 工具 -->
                <div class="editor-toolbar-row2" v-if="editor">
                    <el-dropdown trigger="click" @command="setTextColor">
                        <el-button size="small" :title="t('blog_page.tb_text_color')"><el-icon><ArrowDown /></el-icon></el-button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item v-for="c in colorOptions" :key="c.command" :command="c.command"><span :style="{ color: c.color }">{{ c.label }}</span></el-dropdown-item>
                                <el-dropdown-item command="__custom__" divided>
                                    <div style="display:flex;align-items:center;gap:6px">
                                        <span>{{ tp('color_custom') }}</span>
                                        <input type="color" id="customColorPicker" style="width:30px;height:24px;border:none;padding:0;cursor:pointer" @click.stop @change="applyCustomColor" />
                                    </div>
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                    <el-popover placement="bottom" :width="210" trigger="click" v-model:visible="showHighlightPicker">
                        <template #reference>
                            <el-button size="small" :title="t('blog_page.tb_highlight')" :style="{background:editor?.getAttributes('highlight')?.color||'transparent'}">A</el-button>
                        </template>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;padding:4px">
                            <div v-for="c in ['#ffd8bf','#ffd6e7','#d3adf7','#bae7ff','#b7eb8f','#ffe58f','#ffffff']" :key="c"
                                @click="editor?.chain().focus().toggleHighlight({color:c}).run(); showHighlightPicker=false"
                                :style="{width:28,height:28,borderRadius:4,background:c,cursor:'pointer',border:c==='#ffffff'?'1px solid #dcdfe6':'none'}">
                            </div>
                            <div style="width:100%;margin-top:4px;border-top:1px solid #eee;padding-top:4px">
                                <el-button size="small" text @click="editor?.chain().focus().toggleHighlight().run(); showHighlightPicker=false">{{ t('blog_page.clear_highlight') }}</el-button>
                            </div>
                        </div>
                    </el-popover>
                    <el-divider direction="vertical" />
                    <el-button-group size="small">
                        <el-button @click="insertImage" :title="t('blog_page.tb_upload_image')">IMG</el-button>
                        <el-button @click="insertVideo" :title="t('blog_page.tb_insert_video')">VID</el-button>
                        <el-button @click="insertAudio" :title="tp('insert_audio_title')">AUD</el-button>
                        <el-button @click="insertTable" :title="t('blog_page.tb_insert_table')">TBL</el-button>
                        <el-button @click="insertLink" :title="t('blog_page.tb_insert_link')">URL</el-button>
                        <el-button @click="editor?.chain().focus().setHorizontalRule().run()" :title="t('blog_page.tb_hr')">—</el-button>
                    </el-button-group>
                    <el-dropdown trigger="click" @command="insertEmbedItem">
                        <el-button size="small">{{ tp('insert_content') }} <el-icon><ArrowDown /></el-icon></el-button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="product">{{ tp('embed_product') }}</el-dropdown-item>
                                <el-dropdown-item command="affiliate">{{ tp('embed_affiliate') }}</el-dropdown-item>
                                <el-dropdown-item command="ad">{{ tp('embed_ad') }}</el-dropdown-item>
                                <el-dropdown-item command="poll">{{ tp('embed_poll') }}</el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                    <el-dropdown trigger="click" @command="handleAiAction">
                        <el-button size="small" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none">{{ t('blog_page.ai_tools') }} <el-icon><ArrowDown /></el-icon></el-button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="assistant">{{ t('blog_page.ai_assistant') }}</el-dropdown-item>
                                <el-dropdown-item command="create">{{ t('blog_page.ai_create') }}</el-dropdown-item>
                                <el-dropdown-item divided command="typo">{{ t('blog_page.ai_typo') }}</el-dropdown-item>
                                <el-dropdown-item command="improve">{{ tp('ai_improve') }}</el-dropdown-item>
                                <el-dropdown-item command="polish">{{ t('blog_page.ai_polish') }}</el-dropdown-item>
                                <el-dropdown-item command="summary">{{ t('blog_page.ai_summary') }}</el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                    <el-divider direction="vertical" />
                    <el-button size="small" @click="showEmojiDialog = true" :title="t('blog_page.tb_emoji')">:)</el-button>
                    <el-button size="small" @click="showHelpDialog = true" :title="t('blog_page.shortcuts_help')">?</el-button>
                    <el-divider direction="vertical" />
                    <el-button size="small" :type="showSearch ? 'primary' : 'default'" @click="toggleSearch" :title="t('blog_page.tb_search_replace')">{{ t('blog_page.tb_search') }}</el-button>
                    <el-button size="small" @click="showTemplateDialog = true" :title="t('blog_page.tb_template')">{{ t('blog_page.tb_template') }}</el-button>
                    <el-button size="small" @click="showTocDialog = true" :title="t('blog_page.tb_toc')">{{ t('oa_article_detail_page.toc') }}</el-button>
                    <el-divider direction="vertical" />
                    <el-select v-model="fontSize" size="small" style="width:72px" :placeholder="tp('font_size_ph')" @change="setFontSize">
                        <el-option label="12" value="12px" /><el-option label="14" value="14px" /><el-option label="15" value="15px" />
                        <el-option label="16" value="16px" /><el-option label="18" value="18px" /><el-option label="20" value="20px" />
                        <el-option label="24" value="24px" /><el-option label="28" value="28px" /><el-option label="32" value="32px" />
                    </el-select>
                    <el-select v-model="fontFamily" size="small" style="width:90px" :placeholder="tp('font_family_ph')" @change="setFontFamily">
                        <el-option v-for="f in fontFamilyOptions" :key="f.value || 'default'" :label="f.label" :value="f.value" />
                    </el-select>
                    <el-select v-model="lineHeight" size="small" style="width:72px" :placeholder="tp('line_height_ph')" @change="setLineHeight">
                        <el-option label="1.0" value="1" /><el-option label="1.5" value="1.5" /><el-option label="1.8" value="1.8" />
                        <el-option label="2.0" value="2" />
                    </el-select>
                    <el-divider direction="vertical" />
                    <el-button size="small" @click="clearFormatting" :title="t('blog_page.clear_format')">{{ t('blog_page.clear_format') }}</el-button>
                    <el-divider direction="vertical" />
                    <el-button :type="showSource ? 'primary' : 'default'" size="small" @click="toggleSource" :title="t('blog_page.html_source')">{{ tp('html_source') }}</el-button>
                </div>
<div v-if="!showSource" class="editor-content">
                    <div v-if="editor">
                        <editor-content :editor="editor" class="prose-editor" />
                    </div>
                    <div v-else class="editor-loading">
                        <el-icon class="is-loading"><Loading /></el-icon> {{ t('blog_page.loading_editor') }}
                    </div>
                </div>
                <!-- HTML 源码编辑模式 -->
                <div v-else class="editor-source-area">
                    <el-input v-model="sourceHtml" type="textarea" class="source-textarea" :autosize="{ minRows: 24, maxRows: 50 }" :placeholder="tp('source_ph')" />
                    <div class="source-actions">
                        <el-button size="small" @click="cancelSource">{{ t('actions.cancel') }}</el-button>
                        <el-button size="small" type="primary" @click="applySource">{{ tp('apply_html') }}</el-button>
                    </div>
                </div>
                <!-- 底部状态栏 -->
                <div class="editor-footer">
                    <div class="footer-left">
                        <span class="footer-stat">{{ t('oa_article_detail_page.word_count', { n: wordCount }) }}</span>
                        <span class="footer-stat">{{ t('oa_article_detail_page.reading_minutes', { n: readingTime }) }}</span>
                        <span v-if="scanningContent" class="footer-stat" style="color:#e6a23c">{{ tp('scanning') }}</span>
                        <span v-else-if="scanResult?.hasSensitive" class="footer-stat" style="color:#f56c6c;cursor:pointer" @click="showScanWarning = true">{{ tp('sensitive_found', { n: scanResult.matched.length }) }}</span>
                        <span v-else-if="scanResult" class="footer-stat" style="color:#67c23a">{{ tp('content_compliant') }}</span>
                    </div>
                    <div class="footer-right">
                        <span v-if="draftSaved" class="draft-saved">{{ tp('autosaved') }}</span>
                    </div>
                </div>
            </div>

            <!-- 右侧素材面板 -->
            <div class="editor-sidebar">
                <el-tabs v-model="sidebarTab" class="sidebar-tabs">
                    <!-- 素材库 -->
                    <el-tab-pane :label="tp('tab_media')" name="media" lazy>
                        <div class="media-upload">
                            <el-upload drag multiple :show-file-list="false" :http-request="uploadMediaFile" accept="image/*,video/*,audio/*">
                                <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
                                <div class="el-upload__text">{{ tp('media_upload') }}</div>
                                <template #tip><div class="el-upload__tip">{{ tp('media_upload_tip') }}</div></template>
                            </el-upload>
                            <div class="media-list" v-if="mediaList.length">
                                <div v-for="m in mediaList" :key="m.id" class="media-item" @click="insertMediaToEditor(m)">
                                    <img v-if="m.type?.startsWith('image')" :src="m.url" class="media-thumb" />
                                    <div v-else class="media-icon">{{ m.type?.startsWith('video') ? '🎬' : '🎵' }}</div>
                                    <div class="media-name">{{ m.name }}</div>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>
                    <!-- 商品 -->
                    <el-tab-pane :label="tp('tab_products')" name="products" lazy>
                        <div class="sidebar-search">
                            <el-input v-model="productSearch" :placeholder="tp('search_products_ph')" size="small" clearable @change="handleProductSearch" />
                        </div>
                        <!-- 分类筛选 -->
                        <div class="affiliate-categories" v-if="productCategories.length > 0">
                            <el-tag
                                :type="!productCategory ? 'primary' : 'info'"
                                size="small" effect="plain" class="cat-tag"
                                @click="productCategory = ''; loadProducts()"
                            >{{ tp('all') }}</el-tag>
                            <el-tag
                                v-for="cat in productCategories" :key="cat.id"
                                :type="productCategory == cat.id ? 'primary' : 'info'"
                                size="small" effect="plain" class="cat-tag"
                                @click="productCategory = String(cat.id); loadProducts()"
                            >{{ cat.name }}</el-tag>
                        </div>
                        <div class="sidebar-list" v-loading="loadingProducts">
                            <div v-for="p in filteredProducts" :key="p.id" class="sidebar-item" @click="insertProductCard(p)">
                                <div class="item-img"><img v-if="p.image_url" :src="p.image_url" /><span v-else>📦</span></div>
                                <div class="item-info">
                                    <div class="item-name">{{ p.name }}</div>
                                    <div class="item-price">¥{{ p.sku_price_min != null ? (p.sku_price_max != null && p.sku_price_max != p.sku_price_min ? p.sku_price_min + '~' + p.sku_price_max : p.sku_price_min) : (p.base_price != null ? p.base_price : tp('price_negotiable')) }}</div>
                                </div>
                            </div>
                            <!-- 加载更多 -->
                            <div v-if="filteredProducts.length && productHasMore && !productSearch" style="text-align:center;padding:8px">
                                <el-button size="small" text :loading="loadingMoreProducts" @click="loadMoreProducts">
                                    {{ loadingMoreProducts ? t('actions.loading') : tp('load_more', { loaded: filteredProducts.length, total: productTotal || '...' }) }}
                                </el-button>
                            </div>
                            <el-empty v-if="!filteredProducts.length && !loadingProducts" :description="tp('no_products')" :image-size="40" />
                        </div>
                    </el-tab-pane>
                    <!-- 分销联盟 -->
                    <el-tab-pane :label="tp('tab_affiliate')" name="affiliate" lazy>
                        <!-- 未开通状态 -->
                        <div v-if="!loadingAffiliates && !affiliateEnabled" class="affiliate-empty">
                            <div class="affiliate-icon">A</div>
                            <div class="affiliate-title">{{ tp('affiliate_title') }}</div>
                            <div class="affiliate-desc">{{ tp('affiliate_desc') }}</div>
                            <div class="affiliate-benefits">
                                <div class="benefit-item">{{ tp('affiliate_benefit_rate') }}</div>
                                <div class="benefit-item">{{ tp('affiliate_benefit_products') }}</div>
                                <div class="benefit-item">{{ tp('affiliate_benefit_tracking') }}</div>
                            </div>
                            <el-button type="primary" size="small" @click="applyAffiliate" class="affiliate-apply-btn">
                                {{ tp('affiliate_apply') }}
                            </el-button>
                        </div>
                        <!-- 已开通：显示可推广商品 -->
                        <div v-else>
                            <div class="sidebar-search">
                                <el-input v-model="affiliateSearch" :placeholder="tp('search_affiliate_ph')" size="small" clearable />
                            </div>
                            <!-- 分类筛选 -->
                            <div class="affiliate-categories" v-if="affiliateCategories.length > 0">
                                <el-tag
                                    :type="!affiliateCategory ? 'primary' : 'info'"
                                    size="small" effect="plain" class="cat-tag"
                                    @click="affiliateCategory = ''"
                                >{{ tp('all') }}</el-tag>
                                <el-tag
                                    v-for="cat in affiliateCategories" :key="cat.id"
                                    :type="affiliateCategory == cat.id ? 'primary' : 'info'"
                                    size="small" effect="plain" class="cat-tag"
                                    @click="affiliateCategory = String(cat.id)"
                                >{{ cat.name }}</el-tag>
                            </div>
                            <div class="sidebar-list" v-loading="loadingAffiliates">
                                <div v-for="a in filteredAffiliateItems" :key="a.id" class="sidebar-item" @click="insertAffiliateCard(a)">
                                    <div class="item-img"><img v-if="a.image_url" :src="a.image_url" /><span v-else>🛒</span></div>
                                    <div class="item-info">
                                        <div class="item-name">{{ a.product_name || a.name }}</div>
                                        <div class="item-price" v-if="a.price != null">¥{{ a.price }}</div>
                                        <div v-if="a.commission_rate != null" class="item-commission">
                                            {{ tp('commission_label', { amount: a.commission_amount, rate: a.commission_rate }) }}
                                        </div>
                                    </div>
                                </div>
                                <!-- 加载更多 -->
                                <div v-if="filteredAffiliateItems.length && affiliateHasMore && !affiliateSearch" style="text-align:center;padding:8px">
                                    <el-button size="small" text :loading="loadingMoreAffiliates" @click="loadMoreAffiliates">
                                    {{ loadingMoreAffiliates ? t('actions.loading') : tp('load_more', { loaded: filteredAffiliateItems.length, total: affiliateTotal || '...' }) }}
                                </el-button>
                            </div>
                                <el-empty v-if="!filteredAffiliateItems.length && !loadingAffiliates" :description="tp('no_affiliate_products')" :image-size="40" />
                            </div>
                        </div>
                    </el-tab-pane>
                    <!-- 广告+推广素材 -->
                    <el-tab-pane :label="tp('tab_ads')" name="ads" lazy>
                        <div class="sidebar-section">
                            <div style="font-weight:600;font-size:13px;color:#303133;margin-bottom:4px;display:flex;align-items:center;gap:6px">
                                {{ tp('promo_materials') }}
                                <span style="font-size:11px;font-weight:400;color:#909399;margin-left:auto">{{ tp('campaign_count', { n: promoCampaigns.length }) }}</span>
                            </div>
                            <p style="font-size:11px;color:#909399;margin:0 0 10px 0">{{ tp('promo_hint') }}</p>
                            <div v-if="promoCampaigns.length" class="promo-ads-list">
                                <div v-for="camp in promoCampaigns" :key="camp.id" class="promo-camp-item" style="margin-bottom:8px;border:1px solid #ebeef5;border-radius:10px;overflow:hidden">
                                    <div @click="togglePromoCamp(camp.id)" style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:linear-gradient(135deg,#f8f9fb,#f0f2f5);cursor:pointer;transition:background .2s"
                                        @mouseenter="$event.target.style.background='linear-gradient(135deg,#eef0f5,#e8eaf0)'"
                                        @mouseleave="$event.target.style.background='linear-gradient(135deg,#f8f9fb,#f0f2f5)'">
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <span style="font-size:16px">{{ {referral:'🤝',commission:'💰',reward:'🎁',rebate:'💸'}[camp.type] || '📢' }}</span>
                                            <div>
                                                <div style="font-weight:600;font-size:13px;color:#303133">{{ camp.name }}</div>
                                                <div style="font-size:11px;color:#909399;display:flex;gap:8px;margin-top:1px">
                                                    <span>{{ campTypeLabel(camp.type) }}</span>
                                                    <span v-if="camp.reward_first">{{ tp('commission_pct', { n: camp.reward_first }) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <span style="transition:transform .25s;font-size:10px;color:#909399" :style="{transform:expandedPromoCamp===camp.id?'rotate(180deg)':''}">▼</span>
                                    </div>
                                    <div v-if="expandedPromoCamp===camp.id" style="padding:4px 8px 8px">
                                        <div v-for="cr in promoCampCreatives" :key="cr.id" @click="promptAdWidth(cr, camp)"
                                            style="display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid #f0f0f0;border-radius:8px;margin-bottom:4px;cursor:pointer;background:#fff;transition:all .15s"
                                            @mouseenter="$event.target.style.borderColor='#e2e8f0';$event.target.style.boxShadow='0 2px 8px rgba(15,23,42,0.1)'"
                                            @mouseleave="$event.target.style.borderColor='#f0f0f0';$event.target.style.boxShadow='none'">
                                            <div v-if="cr.image_url" style="width:48px;height:48px;border-radius:8px;overflow:hidden;flex-shrink:0;background:#f5f5f5;border:1px solid #f0f0f0">
                                                <img :src="cr.image_url" style="width:100%;height:100%;object-fit:cover" loading="lazy" />
                                            </div>
                                            <div v-else style="width:48px;height:48px;border-radius:8px;flex-shrink:0;background:linear-gradient(135deg,#f0f5ff,#e6f0ff);display:flex;align-items:center;justify-content:center;font-size:20px;border:1px solid #e0e8f5">
                                                {{ cr.type==='video'?'🎬':cr.type==='qr_code'?'📱':cr.type==='coupon'?'🏷️':'📝' }}
                                            </div>
                                            <div style="flex:1;min-width:0">
                                                <div style="font-size:13px;font-weight:500;color:#303133;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ cr.name }}</div>
                                                <div style="display:flex;align-items:center;gap:6px;margin-top:3px">
                                                    <span style="font-size:10px;background:#f1f5f9;color:#0f172a;padding:1px 6px;border-radius:3px">{{ adStyleTag(cr).label }}</span>
                                                    <span style="font-size:11px;color:#e6a23c;font-weight:500">{{ adEarnings(cr, camp) }}</span>
                                                </div>
                                            </div>
                                            <span style="font-size:14px;color:#0f172a;flex-shrink:0;background:#f1f5f9;width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center">+</span>
                                        </div>
                                        <div v-if="!promoCampCreatives.length" style="text-align:center;padding:16px;color:#909399;font-size:12px">
                                            {{ tp('no_creatives') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else style="text-align:center;padding:24px 0;color:#909399">
                                <div style="font-size:36px;margin-bottom:8px">Ad</div>
                                <div style="font-size:13px">{{ tp('no_campaigns') }}</div>
                                <div style="font-size:11px;margin-top:4px">{{ tp('no_campaigns_hint') }}</div>
                            </div>
                        </div>
                    </el-tab-pane>
                    <!-- SEO -->
                    <el-tab-pane :label="tp('tab_seo')" name="seo" lazy>
                        <!-- 综合评分 -->
                        <div style="text-align:center;padding:12px 0 8px">
                            <div style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:50%;border:4px solid;margin:0 auto"
                                :style="{borderColor:seoScore>=80?'#67c23a':seoScore>=50?'#e6a23c':'#f56c6c',color:seoScore>=80?'#67c23a':seoScore>=50?'#e6a23c':'#f56c6c'}">
                                <span style="font-size:26px;font-weight:700">{{ seoScore }}</span>
                            </div>
                            <div style="font-size:11px;color:#909399;margin-top:6px">
                                {{ seoScore>=80?tp('seo_excellent'):seoScore>=50?tp('seo_fair'):tp('seo_poor') }}
                            </div>
                        </div>

                        <!-- 各项评分 -->
                        <div style="padding:0 4px">
                            <div v-for="item in seoChecks" :key="item.label" style="margin-bottom:8px">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px">
                                    <span style="font-size:12px;color:#606266">{{ item.icon }} {{ item.label }}</span>
                                    <span style="font-size:11px" :style="{color:item.score>=80?'#67c23a':item.score>=50?'#e6a23c':'#f56c6c'}">{{ tp('seo_score_unit', { n: item.score }) }}</span>
                                </div>
                                <div style="height:5px;background:#e8e8e8;border-radius:3px;overflow:hidden">
                                    <div :style="{width:item.score+'%',background:item.score>=80?'#67c23a':item.score>=50?'#e6a23c':'#f56c6c',height:'100%',borderRadius:'3px',transition:'width .4s'}"></div>
                                </div>
                                <div v-if="item.tip" style="font-size:10px;color:#f56c6c;margin-top:2px">⚠ {{ item.tip }}</div>
                            </div>
                        </div>

                        <el-divider style="margin:10px 0" />

                        <!-- 表单字段 -->
                        <el-form label-position="top" size="small">
                            <el-form-item :label="tp('seo_meta_title')">
                                <el-input v-model="seoMetaTitle" :placeholder="tp('seo_meta_title_ph')" maxlength="70" />
                                <div style="font-size:10px;margin-top:2px" :style="{color:seoMetaTitle.length>=50&&seoMetaTitle.length<=70?'#67c23a':seoMetaTitle.length>0?'#e6a23c':'#909399'}">
                                    {{ tp('seo_meta_title_hint_ok', { len: seoMetaTitle.length }) }} {{ seoMetaTitle.length>=50&&seoMetaTitle.length<=70?'':seoMetaTitle.length>0?tp('seo_meta_title_hint_short'):tp('seo_meta_title_hint_empty') }}
                                </div>
                            </el-form-item>
                            <el-form-item :label="tp('seo_meta_desc')">
                                <el-input v-model="seoMetaDescription" type="textarea" :rows="3" :placeholder="tp('seo_meta_desc_ph')" maxlength="160" />
                                <div style="font-size:10px;margin-top:2px" :style="{color:seoMetaDescription.length>=120&&seoMetaDescription.length<=160?'#67c23a':seoMetaDescription.length>0?'#e6a23c':'#909399'}">
                                    {{ tp('seo_meta_desc_hint', { len: seoMetaDescription.length }) }}
                                </div>
                            </el-form-item>
                            <el-form-item :label="tp('tags_label')">
                                <el-select v-model="articleTags" multiple filterable allow-create default-first-option
                                    :placeholder="tp('tags_ph')" style="width:100%">
                                    <el-option v-for="t in articleTags" :key="t" :label="t" :value="t" />
                                </el-select>
                            </el-form-item>
                        </el-form>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>

        <!-- 预览对话框（支持桌面/手机预览） -->
        <el-dialog v-model="previewVisible" :title="tp('preview_title')" :fullscreen="previewDevice === 'desktop'" :width="previewDevice === 'mobile' ? '420px' : ''" top="3vh" :close-on-click-modal="false" :style="previewDevice === 'mobile' ? 'margin:0 auto' : ''">
            <div style="display:flex;gap:8px;margin-bottom:12px;justify-content:center">
                <el-radio-group v-model="previewDevice" size="small">
                    <el-radio-button value="desktop">{{ t('blog_page.device_desktop') }}</el-radio-button>
                    <el-radio-button value="mobile">{{ t('blog_page.device_mobile') }}</el-radio-button>
                </el-radio-group>
            </div>
            <div :class="['article-preview', previewDevice === 'mobile' ? 'mobile-preview' : '']">
                <h1 class="preview-title">{{ articleForm.title || t('blog_page.no_title') }}</h1>
                <div class="preview-meta">
                    <span v-if="targetAccount">{{ targetAccount.name }}</span>
                    <span>{{ previewDate }}</span>
                    <span v-if="articleTags.length">🏷️ {{ articleTags.join(', ') }}</span>
                </div>
                <div v-if="articleForm.cover_image" class="preview-cover">
                    <img :src="articleForm.cover_image" />
                </div>
                <div class="preview-content" v-html="articleForm.content" />
            </div>
        </el-dialog>

        <!-- 插入链接对话框 -->
        <el-dialog v-model="showLinkDialog" :title="tp('link_dialog_title')" width="400px">
            <el-form label-width="60px">
                <el-form-item :label="tp('link_text')">
                    <el-input v-model="linkText" :placeholder="tp('link_text_ph')" />
                </el-form-item>
                <el-form-item label="URL">
                    <el-input v-model="linkUrl" placeholder="https://..." />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showLinkDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button size="small" type="primary" @click="doInsertLink">{{ tp('insert') }}</el-button>
            </template>
        </el-dialog>

        <!-- 文章模板对话框 -->
        <el-dialog v-model="showTemplateDialog" :title="t('blog_page.template_dialog_title')" width="600px">
            <div class="template-grid">
                <div class="template-card" @click="insertTemplate('product')">
                    <div class="template-icon">P</div>
                    <div class="template-name">{{ templateMeta.product.title }}</div>
                    <div class="template-desc">{{ templateMeta.product.desc }}</div>
                </div>
                <div class="template-card" @click="insertTemplate('announcement')">
                    <div class="template-icon">A</div>
                    <div class="template-name">{{ templateMeta.announcement.title }}</div>
                    <div class="template-desc">{{ templateMeta.announcement.desc }}</div>
                </div>
                <div class="template-card" @click="insertTemplate('news')">
                    <div class="template-icon">N</div>
                    <div class="template-name">{{ templateMeta.news.title }}</div>
                    <div class="template-desc">{{ templateMeta.news.desc }}</div>
                </div>
                <div class="template-card" @click="insertTemplate('guide')">
                    <div class="template-icon">G</div>
                    <div class="template-name">{{ templateMeta.guide.title }}</div>
                    <div class="template-desc">{{ templateMeta.guide.desc }}</div>
                </div>
            </div>
        </el-dialog>

        <!-- 目录导航对话框 -->
        <el-dialog v-model="showTocDialog" :title="t('blog_page.toc_dialog_title')" width="400px">
            <div v-if="tocItems.length === 0" style="padding:20px;text-align:center;color:#909399">
                {{ t('blog_page.toc_empty') }}
            </div>
            <div v-else class="toc-list">
                <div v-for="(item, idx) in tocItems" :key="idx" class="toc-item"
                    :style="{ paddingLeft: (item.level - 1) * 20 + 'px' }"
                    @click="scrollToHeading(idx)">
                    <span class="toc-level" :class="'toc-h' + item.level">H{{ item.level }}</span>
                    <span class="toc-text">{{ item.text }}</span>
                </div>
            </div>
        </el-dialog>

        <!-- 商品卡片宽度对话框（通用：支持商品和联盟推广） -->
        <el-dialog v-model="showCardWidthDialog" :title="tp('card_dialog_title')" width="420px" :close-on-click-modal="false">
            <div style="text-align:center;margin-bottom:16px">
                <!-- 商品预览 -->
                <div v-if="pendingProduct" style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid #ebeef5;border-radius:8px;text-align:left">
                    <img v-if="pendingProduct.image_url" :src="pendingProduct.image_url" style="width:60px;height:60px;border-radius:4px;object-fit:cover;flex-shrink:0" />
                    <span v-else style="font-size:32px">📦</span>
                    <div>
                        <div style="font-weight:600;font-size:14px">{{ pendingProduct?.name || pendingProduct?.product_name }}</div>
                        <div style="color:#f56c6c;font-weight:700;font-size:16px">¥{{ pendingProduct?.sku_price_min != null ? (pendingProduct?.sku_price_max != null && pendingProduct?.sku_price_max != pendingProduct?.sku_price_min ? pendingProduct.sku_price_min + '~' + pendingProduct.sku_price_max : pendingProduct.sku_price_min) : (pendingProduct?.price != null ? pendingProduct.price : (pendingProduct?.base_price != null ? pendingProduct.base_price : tp('price_negotiable'))) }}</div>
                    </div>
                </div>
                <!-- 联盟推广预览 -->
                <div v-else-if="pendingAffiliateItem" style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid #ebeef5;border-radius:8px;text-align:left">
                    <img v-if="pendingAffiliateItem.image_url" :src="pendingAffiliateItem.image_url" style="width:60px;height:60px;border-radius:4px;object-fit:cover;flex-shrink:0" />
                    <span v-else style="font-size:32px">🛒</span>
                    <div>
                        <div style="font-weight:600;font-size:14px">{{ pendingAffiliateItem?.product_name || pendingAffiliateItem?.name }}</div>
                        <div style="color:#f56c6c;font-weight:700;font-size:16px">¥{{ pendingAffiliateItem?.price != null ? pendingAffiliateItem.price : tp('price_negotiable') }}</div>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:8px;justify-content:center;margin-bottom:12px;flex-wrap:wrap">
                <el-button v-for="preset in cardWidthPresets" :key="preset.value"
                    :type="cardWidth === preset.value ? 'primary' : 'default'"
                    size="small" @click="cardWidth = preset.value">
                    {{ preset.label }} ({{ preset.value }})
                </el-button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;justify-content:center;margin-bottom:12px">
                <span style="font-size:13px;color:#666">{{ tp('custom_width') }}</span>
                <el-input v-model="cardWidth" size="small" style="width:120px" :placeholder="tp('custom_width_ph')" />
            </div>
            <!-- 联盟推广：佣金显示开关 -->
            <div v-if="pendingAffiliateItem" style="display:flex;align-items:center;justify-content:center;gap:8px;padding-top:10px;border-top:1px solid #f0f0f0">
                <el-switch v-model="affiliateShowCommission" size="small" />
                <span style="font-size:13px;color:#606266">{{ tp('show_commission') }}</span>
                <span v-if="pendingAffiliateItem?.commission_amount != null" style="font-size:12px;color:#e6a23c">
                    （¥{{ pendingAffiliateItem.commission_amount }}）
                </span>
            </div>
            <template #footer>
                <el-button size="small" @click="showCardWidthDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button size="small" type="primary" @click="confirmCardInsert">{{ tp('insert_card') }}</el-button>
            </template>
        </el-dialog>

        <!-- 图片插入尺寸对话框 -->
        <el-dialog v-model="showImageSizeDialog" :title="tp('image_size_dialog_title')" width="420px" :close-on-click-modal="false">
            <div style="text-align:center;margin-bottom:16px">
                <img v-if="pendingMediaUrl" :src="pendingMediaUrl" style="max-width:100%;max-height:180px;border-radius:6px;object-fit:contain;background:#f5f7fa" />
            </div>
            <div style="display:flex;gap:8px;justify-content:center;margin-bottom:16px">
                <el-button v-for="preset in imageSizePresets" :key="preset.value"
                    :type="imageCustomWidth === preset.value ? 'primary' : 'default'"
                    size="small" @click="imageCustomWidth = preset.value">
                    {{ preset.label }}{{ preset.value ? ` (${preset.value}px)` : '' }}
                </el-button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;justify-content:center">
                <span style="font-size:13px;color:#666">{{ tp('custom_width') }}</span>
                <el-input-number v-model="imageCustomWidth" :min="50" :max="1200" :step="10" size="small" style="width:140px" />
                <span style="font-size:12px;color:#999">px</span>
            </div>
            <div style="font-size:12px;color:#909399;text-align:center;margin-top:10px">
                {{ t('blog_page.image_scale_hint') }}
            </div>
            <template #footer>
                <el-button size="small" @click="showImageSizeDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button size="small" type="primary" @click="confirmImageInsert">{{ t('blog_page.insert_image') }}</el-button>
            </template>
        </el-dialog>

        <!-- 图片编辑对话框 -->
        <el-dialog v-model="showImageEditDialog" :title="tp('image_edit_dialog_title')" width="450px">
            <el-form label-width="70px" size="small">
                <el-form-item :label="tp('image_url')">
                    <el-input v-model="editImageSrc" :placeholder="tp('image_url_ph')" />
                </el-form-item>
                <el-form-item :label="tp('width')">
                    <el-input v-model="editImageWidth" :placeholder="tp('width_ph')" style="width:150px" />
                </el-form-item>
                <el-form-item :label="t('blog_page.align')">
                    <el-radio-group v-model="editImageAlign">
                        <el-radio value="left">{{ t('blog_page.align_left') }}</el-radio>
                        <el-radio value="center">{{ t('blog_page.align_center') }}</el-radio>
                        <el-radio value="right">{{ t('blog_page.align_right') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="tp('image_preview')">
                    <img v-if="editImageSrc" :src="editImageSrc" style="max-width:100%;max-height:150px;border-radius:4px" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showImageEditDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button size="small" type="primary" @click="applyImageEdit">{{ tp('apply_changes') }}</el-button>
            </template>
        </el-dialog>

        <!-- ── 广告卡片尺寸选择器 ── -->
        <el-dialog v-model="adWidthDialog" :title="tp('ad_card_dialog_title')" width="580px" top="12vh" :close-on-click-modal="false">
            <div v-if="pendingCreative" style="margin-bottom:14px">
                <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f5f7fa;border-radius:8px;margin-bottom:10px">
                    <div v-if="pendingCreative.image_url" style="width:40px;height:40px;border-radius:6px;overflow:hidden;flex-shrink:0"><img :src="pendingCreative.image_url" style="width:100%;height:100%;object-fit:cover" /></div>
                    <div v-else style="width:40px;height:40px;border-radius:6px;background:#e8ecf1;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">Ad</div>
                    <div style="flex:1">
                        <div style="font-weight:600;font-size:14px;color:#303133">{{ pendingCreative.name }}</div>
                        <div style="display:flex;align-items:center;gap:8px;margin-top:2px">
                            <span style="font-size:11px;color:#e6a23c;font-weight:500" v-if="pendingCampaign">{{ adEarnings(pendingCreative, pendingCampaign) }}</span>
                            <span style="font-size:11px;color:#909399">·</span>
                            <span style="font-size:11px;color:#909399">{{ tp('recommended_style') }}: <strong style="color:#0f172a">{{ adStyleTag(pendingCreative).label }}</strong></span>
                        </div>
                    </div>
                </div>
                <div style="background:#fafbfc;border:1px dashed #d9dde3;border-radius:8px;padding:14px;text-align:center">
                    <div style="font-size:11px;color:#909399;margin-bottom:10px;display:flex;align-items:center;justify-content:center;gap:4px">
                        <span>{{ tp('article_preview') }}</span>
                        <el-tooltip :content="tp('article_preview_tip')" placement="top" :show-after="300">
                            <span style="cursor:help;color:#c0c4cc">i</span>
                        </el-tooltip>
                    </div>
                    <div style="font-size:12px;color:#909399;line-height:1.7;text-align:left;margin-bottom:10px;padding:0 4px">
                        {{ tp('sample_para_before') }}
                    </div>
                    <!-- 广告卡片 -->
                    <div class="ad-preview-wrapper" :style="{width:adWidth==='custom'?adWidthCustom||'100%':adWidth,maxWidth:'100%',margin:'0 auto',textAlign:'left',transition:'all 0.25s ease'}">
                        <div v-if="adStyle==='auto'?adStyleTag(pendingCreative).type==='style1':adStyle==='style1'"
                            class="ad-preview-card ad-card-style1"
                            style="display:flex;gap:10px;align-items:stretch;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#fff;position:relative">
                            <div v-if="pendingCreative.image_url" style="width:100px;min-height:72px;flex-shrink:0;background:#f3f4f6"><img :src="pendingCreative.image_url" style="width:100%;height:100%;object-fit:cover" /></div>
                            <div style="flex:1;padding:10px;display:flex;flex-direction:column;justify-content:center">
                                <div style="font-weight:600;font-size:13px;color:#1f2937">{{ pendingCreative.name }}</div>
                                <div v-if="pendingCreative.content" style="font-size:11px;color:#6b7280;margin-top:4px;line-height:1.5">{{ pendingCreative.content.substring(0,60) }}{{ pendingCreative.content.length>60?'...':'' }}</div>
                                <div style="margin-top:6px"><span style="display:inline-block;padding:3px 12px;background:#0f172a;color:#fff;font-size:11px;border-radius:5px;font-weight:500">{{ tp('ad_cta_learn') }} →</span></div>
                            </div>
                            <span style="position:absolute;top:6px;right:6px;padding:1px 7px;background:rgba(0,0,0,0.4);color:#fff;font-size:10px;border-radius:4px;line-height:1.4">{{ tp('ad_label') }}</span>
                        </div>
                        <div v-else-if="adStyle==='auto'?adStyleTag(pendingCreative).type==='style2':adStyle==='style2'"
                            class="ad-preview-card ad-card-style2"
                            style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#fff;position:relative">
                            <div v-if="pendingCreative.image_url" style="width:100%;height:110px;overflow:hidden;background:#f3f4f6"><img :src="pendingCreative.image_url" style="width:100%;height:100%;object-fit:cover" /></div>
                            <div style="padding:12px;text-align:center">
                                <div style="font-weight:700;font-size:14px;color:#1f2937">{{ pendingCreative.name }}</div>
                                <div style="margin-top:8px"><span style="display:inline-block;padding:5px 20px;background:linear-gradient(135deg,#0f172a,#66b1ff);color:#fff;font-size:12px;border-radius:16px;font-weight:600">{{ tp('ad_cta_now') }}</span></div>
                            </div>
                            <span style="position:absolute;top:6px;right:6px;padding:1px 7px;background:rgba(0,0,0,0.4);color:#fff;font-size:10px;border-radius:4px;line-height:1.4">{{ tp('ad_label') }}</span>
                        </div>
                        <div v-else-if="adStyle==='auto'?adStyleTag(pendingCreative).type==='style3':adStyle==='style3'"
                            class="ad-preview-card ad-card-style3"
                            style="display:flex;gap:8px;padding:10px 12px;border-radius:8px;background:linear-gradient(135deg,#f0f5ff,#e6f0ff);border-left:4px solid #0f172a;position:relative">
                            <div style="flex:1">
                                <div style="font-weight:600;font-size:13px;color:#1f2937">{{ pendingCreative.name }}</div>
                                <div v-if="pendingCreative.content" style="font-size:11px;color:#6b7280;margin-top:3px;line-height:1.5">{{ pendingCreative.content.substring(0,50) }}{{ pendingCreative.content.length>50?'...':'' }}</div>
                                <div style="margin-top:5px;font-size:11px;color:#0f172a;font-weight:600">{{ tp('ad_cta_more') }} →</div>
                            </div>
                            <span style="position:absolute;top:6px;right:6px;padding:1px 7px;background:rgba(0,0,0,0.35);color:#fff;font-size:10px;border-radius:4px;line-height:1.4">{{ tp('ad_label') }}</span>
                        </div>
                        <div v-else
                            class="ad-preview-card ad-card-style4"
                            style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-radius:8px;background:#f5f7fa;border:1px solid #e5e7eb;position:relative">
                            <div style="display:flex;align-items:center;gap:6px"><span style="font-size:14px">🔗</span><span style="font-size:13px;font-weight:500;color:#374151">{{ pendingCreative.name }}</span></div>
                            <span style="font-size:11px;color:#0f172a;font-weight:600">{{ tp('ad_cta_view') }}</span>
                            <span style="position:absolute;top:4px;right:6px;padding:1px 7px;background:rgba(0,0,0,0.3);color:#fff;font-size:10px;border-radius:3px;line-height:1.4">{{ tp('ad_label') }}</span>
                        </div>
                    </div>
                    <div style="font-size:12px;color:#909399;line-height:1.7;text-align:left;margin-top:10px;padding:0 4px">
                        {{ tp('sample_para_after') }}
                    </div>
                </div>
            </div>
            <div style="margin-bottom:14px">
                <div style="font-size:13px;font-weight:600;color:#303133;margin-bottom:8px">{{ tp('card_style') }}</div>
                <el-radio-group v-model="adStyle" size="small">
                    <el-radio-button value="auto">
                        {{ tp('style_auto') }}
                        <el-tag size="small" type="warning" style="margin-left:4px;vertical-align:middle;font-size:10px;height:18px;line-height:16px;padding:0 5px;border:0" v-if="pendingCreative">
                            {{ adStyleTag(pendingCreative).label }}
                        </el-tag>
                    </el-radio-button>
                    <el-radio-button value="style1">{{ tp('style_mixed') }}</el-radio-button>
                    <el-radio-button value="style2">{{ tp('style_big_cta') }}</el-radio-button>
                    <el-radio-button value="style3">{{ tp('style_bar_text') }}</el-radio-button>
                    <el-radio-button value="style4">{{ tp('style_link') }}</el-radio-button>
                </el-radio-group>
            </div>
            <div style="margin-bottom:10px">
                <div style="font-size:13px;font-weight:600;color:#303133;margin-bottom:8px">{{ tp('display_width') }}</div>
                <el-radio-group v-model="adWidth" size="small">
                    <el-radio-button value="100%">{{ tp('width_full') }}</el-radio-button>
                    <el-radio-button value="75%">{{ tp('width_75') }}</el-radio-button>
                    <el-radio-button value="50%">{{ tp('width_50') }}</el-radio-button>
                    <el-radio-button value="custom">{{ tp('width_custom') }}</el-radio-button>
                </el-radio-group>
                <el-input v-if="adWidth==='custom'" v-model="adWidthCustom" :placeholder="tp('width_custom_ph')" size="small" style="width:160px;margin-top:8px" clearable />
            </div>
            <div v-if="pendingCreative?.commission_rate || pendingCampaign?.reward_first" style="font-size:12px;color:#e6a23c;background:#fefce8;padding:6px 10px;border-radius:6px;margin-top:4px">
                {{ tp('ad_commission_hint', { n: pendingCreative?.commission_rate || pendingCampaign?.reward_first || 0 }) }}
            </div>
            <template #footer>
                <el-button size="small" @click="adWidthDialog=false">{{ t('actions.cancel') }}</el-button>
                <el-button size="small" type="primary" @click="confirmAdWidth">{{ tp('insert_ad') }}</el-button>
            </template>
        </el-dialog>

        <!-- 投票/问卷对话框 -->
        <el-dialog v-model="showPollDialog" :title="tp('poll_dialog_title')" width="480px" top="18vh" :close-on-click-modal="false">
            <div style="margin-bottom:12px">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px">{{ tp('poll_question') }} *</label>
                <el-input v-model="pollForm.question" :placeholder="tp('poll_question_ph')" maxlength="500" />
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px">{{ tp('poll_type') }}</label>
                <el-radio-group v-model="pollForm.type">
                    <el-radio value="single">{{ t('oa_article_detail_page.poll.single') }}</el-radio>
                    <el-radio value="multiple">{{ t('oa_article_detail_page.poll.multiple') }}</el-radio>
                </el-radio-group>
            </div>
            <div style="margin-bottom:8px">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px">{{ tp('poll_options') }} *</label>
                <div v-for="(opt, idx) in pollForm.options" :key="idx" style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                    <el-input v-model="pollForm.options[idx]" :placeholder="tp('poll_option_ph', { n: idx + 1 })" size="small" maxlength="200" style="flex:1" />
                    <el-button v-if="pollForm.options.length > 2" size="small" text type="danger" @click="removePollOption(idx)">×</el-button>
                </div>
                <el-button v-if="pollForm.options.length < 20" size="small" text type="primary" @click="addPollOption">+ {{ tp('add_option') }}</el-button>
            </div>
            <template #footer>
                <el-button size="small" @click="showPollDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button size="small" type="primary" :loading="creatingPoll" @click="insertPollCard">{{ tp('insert_poll') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showScanWarning" :title="tp('scan_dialog_title')" width="450px" top="22vh" :close-on-click-modal="false">
            <div style="text-align:center;margin-bottom:12px">
                <p style="color:#f56c6c;font-size:14px;font-weight:500;margin:8px 0">{{ tp('scan_warning') }}</p>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-bottom:8px">
                <el-tag v-for="word in (scanResult?.matched || [])" :key="word" type="danger" size="small" closable @close="removeSensitiveWord(word)">
                    {{ word }}
                </el-tag>
            </div>
            <p style="font-size:11px;color:#909399;text-align:center">{{ tp('scan_rescan_hint') }}</p>
            <template #footer>
                <el-button size="small" @click="scanArticleContent">{{ tp('rescan') }}</el-button>
                <el-button size="small" @click="showScanWarning = false">{{ tp('continue_edit') }}</el-button>
                <el-button size="small" type="danger" @click="forcePublishHandler">{{ tp('force_publish') }}</el-button>
            </template>
        </el-dialog>

        <!-- Emoji 选择器对话框 -->
        <el-dialog v-model="showEmojiDialog" :title="t('blog_page.emoji_dialog_title')" width="420px">
            <div class="emoji-grid">
                <span v-for="emoji in emojiList" :key="emoji" class="emoji-item" @click="insertEmoji(emoji)">{{ emoji }}</span>
            </div>
        </el-dialog>

        <!-- 快捷键帮助对话框 -->
        <el-dialog v-model="showHelpDialog" :title="t('blog_page.shortcuts_help')" width="500px">
            <div class="shortcut-list">
                <div class="shortcut-item" v-for="(s, idx) in shortcuts" :key="idx">
                    <span class="shortcut-keys">
                        <kbd v-for="k in s.keys.split(' + ')" :key="k">{{ k }}</kbd>
                    </span>
                    <span class="shortcut-desc">{{ s.desc }}</span>
                </div>
            </div>
        </el-dialog>

        <!-- AI 结果对话框 -->
        <el-dialog v-model="showAiResult" :title="aiResultTitle" width="600px" :close-on-click-modal="true">
            <div v-loading="aiLoading">
                <div v-if="aiResult" class="ai-result-content" style="white-space:pre-wrap;font-size:14px;line-height:1.7;max-height:400px;overflow-y:auto">{{ aiResult }}</div>
                <div v-else-if="!aiLoading" style="text-align:center;padding:32px 0;color:#999">{{ t('blog_page.ai_click_to_start') }}</div>
            </div>
            <template #footer>
                <el-button v-if="aiResult && aiAction === 'typo'" size="small" type="primary" @click="applyAiFix">{{ tp('apply_fix') }}</el-button>
                <el-button v-if="aiResult && aiAction === 'improve'" size="small" type="primary" @click="applyAiFix">{{ tp('apply_suggestion') }}</el-button>
                <el-button v-if="aiResult && aiAction === 'polish'" size="small" type="primary" @click="applyAiFix">{{ tp('replace_polish') }}</el-button>
                <el-button v-if="aiResult && aiAction === 'summary'" size="small" type="primary" @click="copyAiResult">{{ t('actions.copy') }}</el-button>
                <el-button size="small" @click="showAiResult = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- AI 助手对话对话框 -->
        <el-dialog v-model="showAiAssistant" :title="t('blog_page.ai_assistant_title')" width="480px" :close-on-click-modal="false">
            <div style="max-height:360px;overflow-y:auto;margin-bottom:8px">
                <div v-for="(msg, i) in aiChatMessages" :key="i" class="ai-chat-msg" :class="msg.role">
                    <div style="font-size:12px;font-weight:500;margin-bottom:2px;color:#909399">{{ msg.role === 'user' ? t('blog_page.role_user') : t('blog_page.role_ai') }}</div>
                    <div style="font-size:13px;white-space:pre-wrap">{{ msg.content }}</div>
                </div>
                <div v-if="aiChatLoading" style="text-align:center;padding:16px;color:#999">{{ t('blog_page.ai_thinking') }}</div>
            </div>
            <div style="display:flex;gap:6px">
                <el-input v-model="aiChatInput" :placeholder="t('blog_page.ai_question_ph')" size="small" style="flex:1" @keydown.enter="sendAiChat" />
                <el-button size="small" type="primary" :loading="aiChatLoading" @click="sendAiChat">{{ t('oa_article_detail_page.send') }}</el-button>
            </div>
        </el-dialog>

        <!-- AI 创作对话框 -->
        <el-dialog v-model="showAiCreate" :title="t('blog_page.ai_create_title')" width="560px" :close-on-click-modal="false">
            <el-form label-position="top" size="small">
                <el-form-item :label="t('blog_page.create_topic')" required>
                    <el-input v-model="aiCreateTopic" :placeholder="tp('ai_create_topic_ph')" :rows="2" type="textarea" />
                </el-form-item>
                <el-form-item :label="t('blog_page.article_style')">
                    <el-radio-group v-model="aiCreateStyle">
                        <el-radio value="general">{{ tp('ai_create_style_general') }}</el-radio>
                        <el-radio value="professional">{{ tp('ai_create_style_pro') }}</el-radio>
                        <el-radio value="popular">{{ tp('ai_create_style_popular') }}</el-radio>
                        <el-radio value="news">{{ tp('ai_create_style_news') }}</el-radio>
                        <el-radio value="story">{{ tp('ai_create_style_story') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('blog_page.word_length')">
                    <el-select v-model="aiCreateLength" style="width:140px">
                        <el-option :label="tp('ai_create_len_short')" value="short" />
                        <el-option :label="tp('ai_create_len_medium')" value="medium" />
                        <el-option :label="tp('ai_create_len_long')" value="long" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('blog_page.extra_requirements')">
                    <el-input v-model="aiCreateExtra" :placeholder="t('blog_page.extra_requirements_ph')" :rows="2" type="textarea" />
                </el-form-item>
            </el-form>
            <div v-if="aiCreateResult" class="ai-create-preview" style="border:1px solid #e4e7ed;border-radius:6px;padding:12px;margin-bottom:8px;max-height:280px;overflow-y:auto;background:#fafafa">
                <div style="font-size:12px;color:#909399;margin-bottom:6px">{{ tp('ai_create_preview') }}</div>
                <div style="font-size:13px;line-height:1.6;white-space:pre-wrap">{{ aiCreateResult.substring(0, 500) }}{{ aiCreateResult.length > 500 ? '...' : '' }}</div>
            </div>
            <template #footer>
                <el-button size="small" @click="showAiCreate = false">{{ t('actions.cancel') }}</el-button>
                <el-button size="small" type="primary" :loading="aiCreateLoading" @click="doAiCreate">{{ aiCreateResult ? tp('ai_regenerate') : tp('ai_start_create') }}</el-button>
                <el-button v-if="aiCreateResult" size="small" type="success" @click="insertAiCreate">{{ tp('ai_insert_editor') }}</el-button>
            </template>
        </el-dialog>

        <!-- 跨平台分发对话框 -->
        <el-dialog v-model="showDistributeDialog" :title="tp('distribute_dialog_title')" width="520px" top="15vh" :close-on-click-modal="false" @open="loadDistributePlatforms">
            <div v-if="!distPlatforms.length" class="text-center py-6">
                <p style="color:#909399;font-size:13px;margin-bottom:6px">{{ tp('distribute_empty') }}</p>
                <p style="color:#c0c4cc;font-size:11px">{{ tp('distribute_empty_hint') }}</p>
                <el-button size="small" type="primary" style="margin-top:12px" @click="goToChannelDistribution">{{ tp('go_bind_platforms') }}</el-button>
            </div>
            <div v-else>
                <div style="margin-bottom:14px">
                    <p style="font-size:13px;color:#606266;margin-bottom:4px">{{ tp('distribute_select_hint') }}</p>
                </div>
                <div class="space-y-3 mb-4">
                    <div v-for="pa in distPlatforms" :key="pa.id"
                        class="flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-all"
                        :class="{
                            'border-blue-400 bg-blue-50': pa._selected,
                            'border-gray-200 bg-white hover:border-gray-300': !pa._selected,
                            'opacity-60': pa._status === 'success' || pa._status === 'distributing'
                        }"
                        @click="pa._status !== 'success' && pa._status !== 'distributing' && (pa._selected = !pa._selected)">
                        <div class="flex items-center gap-3">
                            <el-checkbox v-model="pa._selected" :disabled="pa._status === 'success' || pa._status === 'distributing'" @click.stop />
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-lg"
                                :style="{ background: { wechat_mp: '#e6f7e6', weibo: '#fde8e8', zhihu: '#e6f0ff', toutiao: '#fff3e0', other: '#f0f0f0' }[pa.platform] || '#f0f0f0' }">
                                {{ pa.platform?.slice(0, 1)?.toUpperCase() || '?' }}
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ pa.platform_user_name || pa.label || pa.platform }}</div>
                                <div class="text-xs text-gray-400">{{ platformLabels[pa.platform] || pa.platform }}</div>
                            </div>
                        </div>
                        <div>
                            <span v-if="pa._status === 'success'" class="text-xs text-green-500 font-medium">{{ tp('dist_success') }}</span>
                            <span v-else-if="pa._status === 'failed'" class="text-xs text-red-400 font-medium">{{ tp('dist_failed') }}</span>
                            <span v-else-if="pa._status === 'distributing'" class="text-xs text-amber-500">{{ tp('dist_distributing') }}</span>
                            <span v-else class="text-xs text-gray-300">{{ tp('dist_pending') }}</span>
                        </div>
                    </div>
                </div>
                <div v-if="distributeStats.total > 0" class="mb-3 p-3 rounded-lg text-sm" :class="distributeStats.failed > 0 ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'">
                    {{ tp('distribute_summary', {
                        total: distributeStats.total,
                        success: distributeStats.success,
                        failed: distributeStats.failed > 0 ? tp('distribute_failed_part', { n: distributeStats.failed }) : '',
                        pending: distributeStats.pending > 0 ? tp('distribute_pending_part', { n: distributeStats.pending }) : '',
                    }) }}
                </div>
            </div>
            <template #footer>
                <el-button size="small" @click="showDistributeDialog = false; loadArticleDistributions()">{{ t('actions.close') }}</el-button>
                <el-button size="small" type="primary" :loading="distributing" :disabled="!distPlatforms.some(p => p._selected)" @click="distributeToSelected">
                    {{ tp('distribute_btn', { n: distPlatforms.filter(p => p._selected).length }) }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
// Ad features: promo campaigns, creative browser, live preview, ad styles
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import i18n from '@/i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import PointsIcon from '@/components/PointsIcon.vue';
import { ArrowLeft, ArrowDown, UploadFilled, Loading } from '@element-plus/icons-vue';
import { useEditor, EditorContent, Node, Mark, mergeAttributes } from '@tiptap/vue-3';
import { StarterKit } from '@tiptap/starter-kit';
import { Image } from '@tiptap/extension-image';
import { Paragraph } from '@tiptap/extension-paragraph';
// 扩展 Paragraph 以支持 style 属性
const CustomParagraph = Paragraph.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            style: {
                default: null,
                parseHTML: el => el.getAttribute('style'),
                renderHTML: attrs => {
                    if (!attrs.style) return {};
                    return { style: attrs.style };
                },
            },
        };
    },
});
// ── 扩展 Table / TableRow / TableCell 支持 style 属性 ──
const CustomTable = Table.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            style: {
                default: null,
                parseHTML: el => el.getAttribute('style'),
                renderHTML: attrs => {
                    if (!attrs.style) return {};
                    return { style: attrs.style };
                },
            },
        };
    },
});
const CustomTableRow = TableRow.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            style: {
                default: null,
                parseHTML: el => el.getAttribute('style'),
                renderHTML: attrs => {
                    if (!attrs.style) return {};
                    return { style: attrs.style };
                },
            },
        };
    },
});
const CustomTableCell = TableCell.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            style: {
                default: null,
                parseHTML: el => el.getAttribute('style'),
                renderHTML: attrs => {
                    if (!attrs.style) return {};
                    return { style: attrs.style };
                },
            },
        };
    },
});
// ── Div 节点（支持 style 属性，让 HTML 源码中的 <div> 不被丢弃） ──
const HtmlDiv = Node.create({
    name: 'htmlDiv',
    group: 'block',
    content: 'block*',
    draggable: true,
    addAttributes() {
        return {
            style: {
                default: null,
                parseHTML: el => el.getAttribute('style'),
                renderHTML: attrs => {
                    if (!attrs.style) return {};
                    return { style: attrs.style };
                },
            },
            'data-link-href': {
                default: null,
                parseHTML: el => el.getAttribute('data-link-href'),
                renderHTML: attrs => {
                    if (!attrs['data-link-href']) return {};
                    return { 'data-link-href': attrs['data-link-href'] };
                },
            },
            'data-link-target': {
                default: null,
                parseHTML: el => el.getAttribute('data-link-target'),
                renderHTML: attrs => {
                    if (!attrs['data-link-target']) return {};
                    return { 'data-link-target': attrs['data-link-target'] };
                },
            },
        };
    },
    parseHTML() {
        return [{ tag: 'div' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes), 0];
    },
});

// ── Span 标记（支持 style 属性，让 HTML 源码中的 <span> 不被丢弃） ──
const HtmlSpan = Mark.create({
    name: 'htmlSpan',
    addAttributes() {
        return {
            style: {
                default: null,
                parseHTML: el => el.getAttribute('style'),
                renderHTML: attrs => {
                    if (!attrs.style) return {};
                    return { style: attrs.style };
                },
            },
        };
    },
    parseHTML() {
        return [{ tag: 'span' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['span', mergeAttributes(HTMLAttributes), 0];
    },
});

// ── 商品卡片节点（NodeView 方式，保留完整样式） ──
const ProductCard = Node.create({
    name: 'productCard',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes() {
        return {
            name: { default: '' },
            slug: { default: '' },
            price: { default: '面议' },
            description: { default: '' },
            imageUrl: { default: '' },
            sellerName: { default: i18n.global.t('oa_article_editor_page.seller_name') },
            sellerAvatar: { default: '' },
            salesCount: { default: 0 },
            cardWidth: { default: '100%' },
        };
    },
    parseHTML() {
        return [{ tag: 'div[data-type="product-card"]' }];
    },
    renderHTML({ node }) {
        const a = node.attrs;
        const link = `/products/${a.slug}`;
        const w = a.cardWidth === '100%' ? '' : `max-width:${a.cardWidth};`;
        return ['div', { 'data-type': 'product-card', style: `${w}border:1px solid #e4e7ed;border-radius:10px;overflow:hidden;margin:12px auto;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.06)` },
            ['a', { href: link, target: '_blank', style: 'text-decoration:none;color:inherit;display:block' },
                ['div', { style: 'width:100%;height:160px;background:#f5f7fa;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative' },
                    ['span', { style: 'font-size:48px;color:#ccc' }, '📦'],
                ],
                ['div', { style: 'padding:14px 16px' },
                    ['div', { style: 'font-size:16px;font-weight:600;color:#303133;margin-bottom:6px' }, a.name],
                    ['div', { style: 'font-size:13px;color:#606266;margin-bottom:8px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden' }, a.description],
                    ['div', { style: 'display:flex;align-items:baseline;gap:8px;margin:10px 0 4px' },
                        ['span', { style: 'font-size:22px;font-weight:700;color:#0f172a' }, '¥' + a.price],
                    ],
                    ['div', { style: 'font-size:12px;color:#909399;margin-bottom:10px;display:flex;align-items:center;gap:8px' },
                        ['span', {}, i18n.global.t('oa_article_editor_page.sold_label') + ' ' + (a.salesCount || 0)],
                        ['span', {}, a.sellerName],
                    ],
                    ['div', { style: 'display:flex;gap:10px;margin-top:6px' },
                        ['span', { style: 'flex:1;background:#0f172a;color:#fff;font-size:13px;padding:8px 0;border-radius:6px;text-align:center;font-weight:500;display:block' }, i18n.global.t('oa_article_editor_page.add_to_cart')],
                        ['span', { style: 'flex:1;background:#fff;color:#f56c6c;font-size:13px;padding:8px 0;border-radius:6px;text-align:center;font-weight:500;display:block;border:1px solid #f56c6c' }, i18n.global.t('oa_article_editor_page.buy_now_label')],
                    ],
                ],
            ],
        ];
    },
    addNodeView() {
        return ({ node }) => {
            const a = node.attrs;
            const wrapper = document.createElement('div');
            wrapper.setAttribute('data-type', 'product-card');
            const w = a.cardWidth && a.cardWidth !== '100%' ? `max-width:${a.cardWidth};` : '';
            wrapper.style.cssText = `${w}border:1px solid #e4e7ed;border-radius:10px;overflow:hidden;margin:12px auto;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.06)`;
            const productUrl = `/products/${a.slug}`;
            wrapper.innerHTML = `<a href="${productUrl}" target="_blank" style="text-decoration:none;color:inherit;display:block">
                <div style="width:100%;height:160px;background:#f5f7fa;display:flex;align-items:center;justify-content:center;overflow:hidden">
                    <img src="${a.imageUrl || ''}" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'" />
                    <span style="font-size:48px;color:#ccc;position:absolute">📦</span>
                </div>
                <div style="padding:14px 16px">
                    <div style="font-size:16px;font-weight:600;color:#303133;margin-bottom:6px">${a.name}</div>
                    <div style="font-size:13px;color:#606266;margin-bottom:8px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">${a.description || ''}</div>
                    <div style="display:flex;align-items:baseline;gap:8px;margin:10px 0 4px">
                        <span style="font-size:22px;font-weight:700;color:#0f172a">¥${a.price}</span>
                    </div>
                    <div style="font-size:12px;color:#909399;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                        <span>${i18n.global.t('oa_article_editor_page.sold_label')} ${a.salesCount || 0}</span>
                        <span>${a.sellerName}</span>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:6px">
                        <span style="flex:1;background:#0f172a;color:#fff;font-size:13px;padding:8px 0;border-radius:6px;text-align:center;font-weight:500;display:block">${i18n.global.t('oa_article_editor_page.add_to_cart')}</span>
                        <span style="flex:1;background:#fff;color:#f56c6c;font-size:13px;padding:8px 0;border-radius:6px;text-align:center;font-weight:500;display:block;border:1px solid #f56c6c">${i18n.global.t('oa_article_editor_page.buy_now_label')}</span>
                    </div>
                </div>
            </a>`;
            wrapper.contentEditable = false;
            return { dom: wrapper };
        };
    },
});

// 扩展 Image 节点以支持 style 属性（保留 width/height/border-radius 等）
const CustomImage = Image.extend({
    addAttributes() {
        return {
            src: { default: null },
            alt: { default: null },
            title: { default: null },
            width: {
                default: null,
                parseHTML: el => el.getAttribute('width'),
                renderHTML: attrs => {
                    if (!attrs.width) return {};
                    return { width: attrs.width };
                },
            },
            height: {
                default: null,
                parseHTML: el => el.getAttribute('height'),
                renderHTML: attrs => {
                    if (!attrs.height) return {};
                    return { height: attrs.height };
                },
            },
            style: {
                default: null,
                parseHTML: el => el.getAttribute('style'),
                renderHTML: attrs => {
                    if (!attrs.style) return {};
                    return { style: attrs.style };
                },
            },
        };
    },
});
import { Link } from '@tiptap/extension-link';
import { CodeBlockLowlight } from '@tiptap/extension-code-block-lowlight';
import { Placeholder } from '@tiptap/extension-placeholder';
import { Underline } from '@tiptap/extension-underline';
import { TextAlign } from '@tiptap/extension-text-align';
import { Highlight } from '@tiptap/extension-highlight';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { TextStyle } from '@tiptap/extension-text-style';
import { FontFamily } from '@tiptap/extension-font-family';
import Youtube from '@tiptap/extension-youtube';
import { common, createLowlight } from 'lowlight';
import apiClient from '@/api/client';

const lowlight = createLowlight(common);

const router = useRouter();
const route = useRoute();
const { t, locale } = useI18n();
const tp = (key, params) => t('oa_article_editor_page.' + key, params);
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

const isEdit = ref(!!route.query.id);
const previewVisible = ref(false);
const previewDevice = ref('desktop');
const showHighlightPicker = ref(false);
const showLinkDialog = ref(false);
const codeLang = ref('')
const codeLanguages = ref([
    'javascript','typescript','html','css','scss','vue','php','python','java','go','rust','c','cpp','csharp',
    'sql','bash','json','xml','yaml','markdown','plain text'
])
const linkText = ref('');
const linkUrl = ref('');
const sidebarTab = ref('media');
const productSearch = ref('');
const productCategory = ref('');
const products = ref([]);
const loadingProducts = ref(false);
const loadingMoreProducts = ref(false);
const productPage = ref(1);
const productHasMore = ref(false);
const productTotal = ref(0);
const affiliateItems = ref([]);
const loadingAffiliates = ref(false);
const loadingMoreAffiliates = ref(false);
const affiliateEnabled = ref(false);
const affiliateSearch = ref('');
const affiliateCategory = ref('');
const affiliatePage = ref(1);
const affiliateHasMore = ref(false);
const affiliateTotal = ref(0);
const mediaList = ref([]);

// ── 推广活动广告 ──
const promoCampaigns = ref([])
const promoCampCreatives = ref([])
const expandedPromoCamp = ref(null)
const adWidthDialog = ref(false)
const adWidth = ref('100%')
const adWidthCustom = ref('')
const adStyle = ref('auto')
const pendingCreative = ref(null)
const pendingCampaign = ref(null)

// ── 投票/问卷 ──
const showPollDialog = ref(false)
const pollForm = ref({ question: '', type: 'single', options: ['', ''] })
const creatingPoll = ref(false)
const myAccounts = ref([]);
const articleTags = ref([]);
const seoMetaTitle = ref('');
const seoMetaDescription = ref('');
const showSource = ref(false);
const sourceHtml = ref('');
const draftSaved = ref(false);
let autoSaveTimer = null;

// ── 搜索替换 ──
const showSearch = ref(false);
const searchQuery = ref('');
const searchReplace = ref('');
const searchMatches = ref(0);
const currentMatch = ref(-1);

// ── 对话框 ──
const showTemplateDialog = ref(false);
const showTocDialog = ref(false);
const showImageEditDialog = ref(false);
const showEmojiDialog = ref(false);
const showHelpDialog = ref(false);

// ── AI 工具 ──
const showAiResult = ref(false);
const showAiAssistant = ref(false);
const aiResultTitle = ref('');
const aiResult = ref('');
const aiLoading = ref(false);
const aiAction = ref('');
const aiChatMessages = ref([]);
const aiChatInput = ref('');
const aiChatLoading = ref(false);
const showAiCreate = ref(false);
const aiCreateTopic = ref('');
const aiCreateStyle = ref('general');
const aiCreateLength = ref('medium');
const aiCreateExtra = ref('');
const aiCreateResult = ref('');
const aiCreateLoading = ref(false);

function getArticleText() {
    return editor.value?.getText() || articleForm.content?.replace(/<[^>]*>/g, '') || '';
}
function getArticleHtml() {
    return editor.value?.getHTML() || articleForm.content || '';
}
function handleAiAction(cmd) {
    if (cmd === 'copy') { copyArticle(); return }
    if (cmd === 'assistant') { showAiAssistant.value = true; return }
    if (cmd === 'create') {
        aiCreateTopic.value = articleForm.title || ''
        aiCreateResult.value = ''
        showAiCreate.value = true
        return
    }
    aiLoading.value = true; aiResult.value = ''; showAiResult.value = true
    const text = getArticleText()
    if (cmd === 'typo') {
        aiAction.value = 'typo'
        aiResultTitle.value = tp('ai_typo_title')
        callLlm('你是一个中文校对专家。请检查以下文章中的错别字、语法错误和标点问题。列出每个问题及其位置和修改建议。\n\n' + text)
    } else if (cmd === 'improve') {
        aiAction.value = 'improve'
        aiResultTitle.value = tp('ai_improve_title')
        callLlm('你是一个资深内容编辑。请分析以下文章，给出具体的改进建议，包括：标题吸引力、段落结构、表达清晰度、读者吸引力等方面。\n\n' + text)
    } else if (cmd === 'polish') {
        aiAction.value = 'polish'
        aiResultTitle.value = tp('ai_polish_title')
        callLlm('你是一个专业文字编辑。请润色以下文章，改进表达方式，使语言更流畅、专业，但不改变原意。直接返回润色后的完整文章。\n\n' + text)
    } else if (cmd === 'summary') {
        aiAction.value = 'summary'
        aiResultTitle.value = tp('ai_summary_title')
        callLlm('请为以下文章生成一段简洁的摘要（100-200字），突出核心内容和亮点。\n\n' + text)
    }
}
async function callLlm(prompt) {
    try {
        const res = await apiClient.post('/user-chat/ai-conversation', { message: prompt })
        aiResult.value = res.data?.data?.reply || tp('ai_no_result')
    } catch (e) {
        aiResult.value = tp('ai_unavailable') + '\n' + (e.response?.data?.message || '')
    }
    finally { aiLoading.value = false }
}
function copyArticle() {
    const html = getArticleHtml()
    const text = getArticleText() || html
    navigator.clipboard.writeText(text).then(() => ElMessage.success(tp('content_copied'))).catch(() => ElMessage.error(t('oa_article_detail_page.toast.copy_failed')))
}
function copyAiResult() {
    if (aiResult.value) navigator.clipboard.writeText(aiResult.value).then(() => ElMessage.success(tp('copied'))).catch(() => {})
}
function applyAiFix() {
    if (!aiResult.value) return
    if (aiAction.value === 'polish') {
        const html = aiResult.value.replace(/\n/g, '<p>') + '</p>'
        editor.value?.commands.setContent('<p>' + aiResult.value.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>')
        ElMessage.success(tp('polish_applied'))
    } else if (aiAction.value === 'summary') {
        articleForm.summary = aiResult.value
        ElMessage.success(tp('summary_filled'))
    } else {
        ElMessage.success(tp('apply_manually'))
    }
    showAiResult.value = false
}
async function sendAiChat() {
    if (!aiChatInput.value.trim()) return
    const q = aiChatInput.value.trim()
    aiChatMessages.value.push({ role: 'user', content: q })
    aiChatInput.value = ''
    aiChatLoading.value = true
    try {
        const context = '当前文章内容：\n' + getArticleText().substring(0, 2000) + '\n\n'
        const res = await apiClient.post('/user-chat/ai-conversation', { message: context + '用户问题：' + q })
        const reply = res.data?.data?.reply || tp('ai_cannot_answer')
        aiChatMessages.value.push({ role: 'assistant', content: reply })
    } catch {
        aiChatMessages.value.push({ role: 'assistant', content: tp('ai_service_down') })
    }
    finally { aiChatLoading.value = false }
}

async function doAiCreate() {
    if (!aiCreateTopic.value.trim()) { ElMessage.warning(tp('topic_required')); return }
    aiCreateLoading.value = true
    aiCreateResult.value = ''
    try {
        const styleMap = { general: t('oa_article_editor_page.ai_create_style_general'), professional: t('oa_article_editor_page.ai_create_style_pro'), popular: t('oa_article_editor_page.ai_create_style_popular'), news: t('oa_article_editor_page.ai_create_style_news'), story: t('oa_article_editor_page.ai_create_style_story') }
        const lengthMap = { short: t('oa_article_editor_page.ai_create_len_short'), medium: t('oa_article_editor_page.ai_create_len_medium'), long: t('oa_article_editor_page.ai_create_len_long') }
        let prompt = `请以「${aiCreateTopic.value}」为主题，写一篇${styleMap[aiCreateStyle.value] || t('oa_article_editor_page.ai_create_style_general')}的文章，字数${lengthMap[aiCreateLength.value] || t('oa_article_editor_page.ai_create_len_medium')}。`
        if (aiCreateExtra.value.trim()) prompt += `\n额外要求：${aiCreateExtra.value.trim()}`
        prompt += '\n请使用标题和段落组织内容，用 Markdown 格式输出。'
        const res = await apiClient.post('/user-chat/ai-conversation', { message: prompt })
        aiCreateResult.value = res.data?.data?.reply || tp('ai_no_result')
        // 自动填入标题
        if (!articleForm.title && aiCreateTopic.value) {
            articleForm.title = aiCreateTopic.value
        }
    } catch (e) {
        aiCreateResult.value = tp('ai_unavailable') + (e.response?.data?.message ? ': ' + e.response.data.message : '')
    }
    finally { aiCreateLoading.value = false }
}
function insertAiCreate() {
    if (!aiCreateResult.value) return
    // 将 Markdown 转换为简单 HTML 后插入编辑器
    const html = aiCreateResult.value
        .replace(/^### (.+)$/gm, '<h3>$1</h3>')
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        .replace(/^# (.+)$/gm, '<h2>$1</h2>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/^\- (.+)$/gm, '<li>$1</li>')
        .replace(/\n\n/g, '</p><p>')
        .replace(/\n/g, '<br>')
    const finalHtml = '<p>' + html + '</p>'
    if (editor.value) {
        editor.value.commands.setContent(finalHtml)
    } else {
        articleForm.content = finalHtml
    }
    ElMessage.success(tp('ai_inserted'))
    showAiCreate.value = false
}

// ── 图片编辑 ──
const editImageSrc = ref('');
const editImageWidth = ref('');
const editImageAlign = ref('left');

// ── 图片插入尺寸 ──
const showImageSizeDialog = ref(false);
const pendingMediaUrl = ref('');
const imageCustomWidth = ref(400);
const imageSizePresets = computed(() => [
    { label: tp('size_small'), value: 250 },
    { label: tp('size_medium'), value: 400 },
    { label: tp('size_large'), value: 600 },
    { label: tp('size_original'), value: 0 },
]);

// ── 商品卡片宽度设置 ──
const showCardWidthDialog = ref(false);
const pendingProduct = ref(null);
const pendingAffiliateItem = ref(null);
const affiliateShowCommission = ref(true);
const cardWidth = ref('100%');
const cardWidthPresets = computed(() => [
    { label: tp('card_width_auto'), value: '100%' },
    { label: tp('card_width_small'), value: '300px' },
    { label: tp('card_width_medium'), value: '400px' },
    { label: tp('card_width_large'), value: '500px' },
]);

// ── 格式 ──
const fontFamilyOptions = computed(() => [
    { label: tp('font_default'), value: '' },
    { label: tp('font_simsun'), value: 'SimSun, serif' },
    { label: tp('font_simhei'), value: 'SimHei, sans-serif' },
    { label: tp('font_yahei'), value: "'Microsoft YaHei', sans-serif" },
    { label: tp('font_dengxian'), value: 'DengXian, sans-serif' },
]);
const colorOptions = computed(() => [
    { command: 'default', label: tp('color_default'), color: '#333' },
    { command: '#f56c6c', label: tp('color_red'), color: '#f56c6c' },
    { command: '#e6a23c', label: tp('color_orange'), color: '#e6a23c' },
    { command: '#67c23a', label: tp('color_green'), color: '#67c23a' },
    { command: '#0f172a', label: tp('color_blue'), color: '#0f172a' },
    { command: '#909399', label: tp('color_gray'), color: '#909399' },
]);
const platformLabels = computed(() => ({
    wechat_mp: tp('platform_wechat_mp'),
    weibo: tp('platform_weibo'),
    zhihu: tp('platform_zhihu'),
    toutiao: tp('platform_toutiao'),
    other: tp('platform_other'),
}));
const previewDate = computed(() => new Date().toLocaleDateString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'));
const fontSize = ref('');
const fontFamily = ref('');
const lineHeight = ref('');

const articleForm = reactive({
    account_id: null,
    title: '',
    content: '',
    summary: '',
    cover_image: '',
    images: [],
    is_original: false,
    allow_comments: true,
    is_paid: false,
    price: 10,
    price_type: 'points',
    scheduled_at: '',
});

const targetAccount = computed(() => {
    if (!articleForm.account_id || !myAccounts.value.length) return null;
    return myAccounts.value.find(a => a.id === articleForm.account_id);
});

function onPaidToggle() {
    if (articleForm.is_paid && articleForm.price <= 0) {
        articleForm.price = 10;
    }
}

const draftKey = computed(() => `oa_editor_draft_${route.query.id || 'new'}`);

const wordCount = computed(() => {
    const html = articleForm.content || '';
    const text = html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').replace(/[\s\n\r]+/g, '');
    const chineseChars = (text.match(/[\u4e00-\u9fff]/g) || []).length;
    const englishWords = text.replace(/[\u4e00-\u9fff]/g, ' ').trim().split(/\s+/).filter(Boolean).length;
    return chineseChars + englishWords;
});

const readingTime = computed(() => Math.max(1, Math.round(wordCount.value / 300)));

// ── SEO 诊断评分 ──
const seoContentText = computed(() => (articleForm.content || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' '))
const seoImageCount = computed(() => (articleForm.content || '').match(/<img[^>]*>/gi)?.length || 0)
const seoImageAltCount = computed(() => (articleForm.content || '').match(/<img[^>]*alt="[^"]+"/gi)?.length || 0)
const seoLinkCount = computed(() => (articleForm.content || '').match(/<a\s[^>]*href="[^"]*"/gi)?.length || 0)
const seoKeywordDensity = computed(() => {
    const text = seoContentText.value.toLowerCase()
    if (!text || !articleForm.title.value) return 0
    const keywords = articleForm.title.value.replace(/[，,。.！!？?、\s]/g, ' ').split(' ').filter(k => k.length >= 2)
    if (!keywords.length) return 0
    const mainKeyword = keywords[0].toLowerCase()
    const count = (text.match(new RegExp(mainKeyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi')) || []).length
    return Math.min(100, Math.round((count / Math.max(1, text.length / 500)) * 100))
})
const seoChecks = computed(() => {
    const title = articleForm.title.value || ''
    const desc = seoMetaDescription.value || ''
    const content = seoContentText.value
    const imgCount = seoImageCount.value
    const altCount = seoImageAltCount.value
    const linkCount = seoLinkCount.value
    const kwDensity = seoKeywordDensity.value
    const items = [
        { icon: '📝', label: tp('seo_check_title'), score: 0, tip: '' },
        { icon: '📄', label: tp('seo_check_desc'), score: 0, tip: '' },
        { icon: '📖', label: tp('seo_check_content'), score: 0, tip: '' },
        { icon: '🔑', label: tp('seo_check_keyword'), score: 0, tip: '' },
        { icon: '🖼️', label: tp('seo_check_alt'), score: 0, tip: '' },
        { icon: '🔗', label: tp('seo_check_links'), score: 0, tip: '' },
        { icon: '🏷️', label: tp('seo_check_tags'), score: 0, tip: '' },
        { icon: '📋', label: tp('seo_check_summary'), score: 0, tip: '' },
    ]
    const tLen = title.length
    items[0].score = tLen >= 50 && tLen <= 70 ? 100 : tLen >= 30 && tLen < 50 ? 70 : tLen > 70 ? 60 : tLen > 0 ? 30 : 0
    if (tLen === 0) items[0].tip = tp('seo_tip_title_empty')
    else if (tLen < 30) items[0].tip = tp('seo_tip_title_short')
    else if (tLen > 70) items[0].tip = tp('seo_tip_title_long')
    const dLen = desc.length
    items[1].score = dLen >= 120 && dLen <= 160 ? 100 : dLen >= 80 && dLen < 120 ? 70 : dLen > 160 ? 60 : dLen > 0 ? 30 : 0
    if (dLen === 0) items[1].tip = tp('seo_tip_desc_empty')
    const cLen = content.length
    items[2].score = cLen >= 1000 ? 100 : cLen >= 500 ? 80 : cLen >= 200 ? 50 : cLen > 0 ? 25 : 0
    if (cLen > 0 && cLen < 500) items[2].tip = tp('seo_tip_content_short')
    items[3].score = kwDensity >= 1 && kwDensity <= 3 ? 100 : kwDensity > 3 ? 70 : kwDensity > 0 ? 40 : 0
    if (kwDensity > 3) items[3].tip = tp('seo_tip_kw_high')
    else if (kwDensity > 0 && kwDensity < 1) items[3].tip = tp('seo_tip_kw_low')
    items[4].score = imgCount === 0 ? 50 : altCount >= imgCount ? 100 : altCount > 0 ? Math.round(50 + (altCount/imgCount)*50) : 0
    if (imgCount > 0 && altCount < imgCount) items[4].tip = tp('seo_tip_alt_missing', { missing: imgCount - altCount, total: imgCount })
    items[5].score = linkCount >= 3 ? 100 : linkCount >= 1 ? 60 : 0
    if (linkCount < 1 && cLen > 400) items[5].tip = tp('seo_tip_links')
    const tagCount = articleTags.value.length
    items[6].score = tagCount >= 5 ? 100 : tagCount >= 3 ? 80 : tagCount >= 1 ? 50 : 0
    if (tagCount < 3) items[6].tip = tp('seo_tip_tags')
    const summary = articleForm.summary || ''
    items[7].score = summary.length >= 80 ? 100 : summary.length >= 40 ? 70 : summary.length > 0 ? 40 : 0
    if (!summary) items[7].tip = tp('seo_tip_summary')
    return items
})
const seoScore = computed(() => {
    const checks = seoChecks.value
    if (!checks.length) return 0
    return Math.round(checks.reduce((s, c) => s + c.score, 0) / checks.length)
})

// ── 内容合规扫描 ──
const scanningContent = ref(false);
const scanResult = ref(null);
const showScanWarning = ref(false);
let scanTimer = null;

function triggerScan() {
    clearTimeout(scanTimer);
    scanTimer = setTimeout(async () => {
        const text = (articleForm.title || '') + ' ' + (articleForm.content || '').replace(/<[^>]*>/g, '');
        if (text.trim().length < 5) { scanResult.value = null; return; }
        scanningContent.value = true;
        try {
            const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') };
            const res = await apiClient.post('/official-accounts/scan-content', { title: articleForm.title, content: articleForm.content }, { headers: h });
            scanResult.value = res.data?.data || { hasSensitive: false, matched: [] };
        } catch { scanResult.value = null; }
        finally { scanningContent.value = false; }
    }, 800);
}

// 手动重新扫描
function scanArticleContent() {
    scanningContent.value = true;
    scanResult.value = null;
    clearTimeout(scanTimer);
    triggerScan();
}

// 移除已处理的敏感词
function removeSensitiveWord(word) {
    if (!scanResult.value?.matched) return;
    scanResult.value.matched = scanResult.value.matched.filter(w => w !== word);
    if (scanResult.value.matched.length === 0) {
        scanResult.value.hasSensitive = false;
        showScanWarning.value = false;
    }
}

// 忽略警告直接发布
function forcePublishHandler() {
    scanResult.value = null; // 清除扫描结果，绕过拦截
    showScanWarning.value = false;
    doPublish();
}

// ── 跨平台分发 ──
const showDistributeDialog = ref(false);
const distPlatforms = ref([]);
const distributing = ref(false);
const distDistributionRecords = ref([]);

const distributeStats = computed(() => {
    const ps = distPlatforms.value;
    return {
        total: ps.filter(p => p._status && p._status !== 'idle').length,
        success: ps.filter(p => p._status === 'success').length,
        failed: ps.filter(p => p._status === 'failed').length,
        pending: ps.filter(p => p._selected && p._status !== 'success' && p._status !== 'failed').length,
    };
});

async function loadDistributePlatforms() {
    const accountId = articleForm.account_id;
    if (!accountId) { distPlatforms.value = []; return; }
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') };
    try {
        const res = await apiClient.get(`/official-accounts/${accountId}/platform-accounts`, { headers: h });
        const platforms = (res.data?.data || []).map(p => ({ ...p, _selected: false, _status: 'idle' }));
        distPlatforms.value = platforms;
        // 加载已有分发记录
        await loadArticleDistributions();
    } catch { distPlatforms.value = []; }
}

async function loadArticleDistributions() {
    const articleId = route.query.id;
    if (!articleId) { distDistributionRecords.value = []; return; }
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') };
    try {
        const res = await apiClient.get(`/official-accounts/articles/${articleId}/distributions`, { headers: h });
        distDistributionRecords.value = res.data?.data || [];
        // 回填状态
        for (const rec of distDistributionRecords.value) {
            const pa = distPlatforms.value.find(p => p.id === rec.platform_account_id);
            if (pa) {
                pa._status = rec.status === 'success' ? 'success' : rec.status === 'failed' ? 'failed' : 'idle';
                pa._selected = rec.status === 'success';
            }
        }
    } catch { distDistributionRecords.value = []; }
}

async function distributeToSelected() {
    const selected = distPlatforms.value.filter(p => p._selected && p._status !== 'success');
    if (!selected.length) { ElMessage.warning(tp('select_platform')); return; }
    const articleId = route.query.id;
    if (!articleId) { ElMessage.warning(tp('save_before_distribute')); return; }

    distributing.value = true;
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') };
    let successCount = 0, failCount = 0;

    for (const pa of selected) {
        pa._status = 'distributing';
        try {
            const res = await apiClient.post(`/official-accounts/articles/${articleId}/distribute`, {
                platform_account_id: pa.id,
                platform: pa.platform,
            }, { headers: h });
            pa._status = 'success';
            successCount++;
        } catch (e) {
            pa._status = 'failed';
            failCount++;
        }
    }

    distributing.value = false;
    if (successCount > 0 && failCount === 0) {
        ElMessage.success(tp('distribute_all_ok', { n: successCount }));
    } else if (successCount > 0) {
        ElMessage.warning(tp('distribute_partial', { success: successCount, failed: failCount }));
    } else {
        ElMessage.error(tp('distribute_all_failed'));
    }
}

function openDistributeDialog() {
    showDistributeDialog.value = true;
}

function goToChannelDistribution() {
    showDistributeDialog.value = false;
    const accountId = articleForm.account_id;
    router.push(accountId ? `/channels?account_id=${accountId}&section=distribution` : '/channels?section=distribution');
}

const affiliateCategories = computed(() => {
    const cats = new Map();
    affiliateItems.value.forEach(a => {
        if (a.category_id && a.category_name) {
            cats.set(a.category_id, a.category_name);
        }
    });
    return Array.from(cats.entries()).map(([id, name]) => ({ id, name }));
});

const productCategories = computed(() => {
    const cats = new Map();
    products.value.forEach(p => {
        if (p.category_id && p.category?.name) {
            cats.set(p.category_id, p.category.name);
        }
    });
    return Array.from(cats.entries()).map(([id, name]) => ({ id, name }));
});

const filteredProducts = computed(() => {
    const cat = productCategory.value;
    if (!cat) return products.value;
    return products.value.filter(p => p.category_id == cat);
});

const filteredAffiliateItems = computed(() => {
    let items = affiliateItems.value;
    const cat = affiliateCategory.value;
    if (cat) {
        items = items.filter(a => a.category_id == cat);
    }
    const q = affiliateSearch.value.trim().toLowerCase();
    if (q) {
        items = items.filter(a =>
            (a.product_name && a.product_name.toLowerCase().includes(q)) ||
            (a.name && a.name.toLowerCase().includes(q)) ||
            (a.sku_code && a.sku_code.toLowerCase().includes(q))
        );
    }
    return items;
});

// ── 投票/问卷卡片 ──
const PollCard = Node.create({
    name: 'pollCard',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes() {
        return {
            pollId: { default: 0 },
            question: { default: '' },
            options: { default: [] },
            type: { default: 'single' },
            totalVotes: { default: 0 },
        };
    },
    parseHTML() {
        return [{ tag: 'div[data-type="poll-card"]' }];
    },
    renderHTML({ node }) {
        const a = node.attrs;
        const opts = (a.options || []).map(o =>
            '<div style="padding:6px 10px;margin:3px 0;background:#f5f7fa;border-radius:6px;font-size:13px;color:#303133">○ ' + (o.label || o) + '</div>'
        ).join('');
        return ['div', { 'data-type': 'poll-card', style: 'border:1px solid #e4e7ed;border-radius:8px;padding:12px;margin:10px 0;background:#fff' },
            ['div', { style: 'font-weight:600;margin-bottom:8px' }, '📊 ' + a.question],
            ['div', {}, opts],
            ['div', { style: 'font-size:11px;color:#909399;margin-top:6px;text-align:center' }, t('oa_article_detail_page.poll.participants_count', { n: a.totalVotes || 0 })],
        ];
    },
});

const editor = useEditor({
    content: '',
    extensions: [
        StarterKit.configure({
            codeBlock: false,
            paragraph: false,
            link: false,
            underline: false,
        }),
        CustomParagraph,
        TextStyle,
        FontFamily,
        Underline,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Highlight,
        CustomImage.configure({ inline: true, allowBase64: true }),
        HtmlDiv,
        HtmlSpan,
        ProductCard,
        PollCard,
        Link.configure({ openOnClick: false, HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' } }),
        CodeBlockLowlight.configure({ lowlight }),
        Placeholder.configure({ placeholder: tp('editor_placeholder') }),
        CustomTable.configure({ resizable: true }),
        CustomTableRow, CustomTableCell, TableHeader,
        Youtube.configure({ inline: false, width: 640, height: 360 }),
    ],
    onUpdate: ({ editor }) => {
        articleForm.content = editor.getHTML();
        triggerScan();
    },
});

function handleClose() {
    const backUrl = articleForm.account_id ? `/channels?account_id=${articleForm.account_id}` : '/channels';
    if (articleForm.content && articleForm.content.length > 50) {
        ElMessageBox.confirm(tp('leave_confirm'), tp('leave_title'), {
            confirmButtonText: t('actions.confirm'),
            cancelButtonText: t('actions.cancel'),
        }).then(() => {
            router.push(backUrl);
        }).catch(() => {});
    } else {
        router.push(backUrl);
    }
}

async function loadMyAccounts() {
    try {
        const res = await apiClient.get('/official-accounts/my-owned');
        myAccounts.value = res.data?.data || [];
    } catch { myAccounts.value = []; }
}

async function loadArticle(id) {
    try {
        const r = await apiClient.get('/official-accounts/articles/' + id);
        const data = r.data?.data;
        if (data) {
            articleForm.title = data.title || '';
            articleForm.content = data.content || '';
            articleForm.summary = data.summary || '';
            articleForm.cover_image = data.cover_image || '';
            articleForm.images = data.images || [];
            articleForm.is_original = !!data.is_original;
            articleForm.allow_comments = data.allow_comments !== false;
            articleForm.scheduled_at = data.scheduled_at || '';
            articleForm.account_id = data.account_id || null;
            articleTags.value = data.tags || [];
            seoMetaTitle.value = data.seo_title || '';
            seoMetaDescription.value = data.seo_description || '';
            // 更新编辑器内容
            if (editor.value && data.content) {
                editor.value.commands.setContent(data.content);
            }
        }
    } catch {
        ElMessage.error(tp('article_load_failed'));
    }
}

async function saveDraft() {
    if (!articleForm.account_id) { ElMessage.warning(tp('select_account')); return; }
    if (!articleForm.title.trim()) { ElMessage.warning(tp('title_required')); return; }

    const payload = {
        title: articleForm.title,
        content: articleForm.content || '',
        summary: articleForm.summary || undefined,
        cover_image: articleForm.cover_image || undefined,
        images: articleForm.images?.length ? articleForm.images : undefined,
        is_original: articleForm.is_original,
        allow_comments: articleForm.allow_comments,
        tags: articleTags.value.length ? articleTags.value : undefined,
        status: 'draft',
    };

    try {
        if (isEdit.value) {
            await apiClient.put('/official-accounts/articles/' + route.query.id, { ...payload, status: 'draft' });
            ElMessage.success(tp('draft_updated'));
        } else {
            await apiClient.post('/official-accounts/' + articleForm.account_id + '/articles', payload);
            ElMessage.success(tp('draft_saved'));
        }
        isEdit.value = true;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || tp('save_failed'));
    }
}

function handlePublishAction(cmd) {
    if (cmd === 'publish') doPublish();
    else if (cmd === 'schedule') doSchedule();
    else saveDraft();
}

async function doSchedule() {
    if (!articleForm.account_id) { ElMessage.warning(tp('select_account')); return; }
    if (!articleForm.title.trim()) { ElMessage.warning(tp('title_required')); return; }
    if (!articleForm.content || articleForm.content.length < 20) { ElMessage.warning(tp('content_required')); return; }
    if (!articleForm.scheduled_at) { ElMessage.warning(tp('schedule_required')); return; }

    // 📋 内容合规预检
    if (scanResult.value?.hasSensitive) {
        showScanWarning.value = true;
        return;
    }

    const payload = {
        title: articleForm.title,
        content: articleForm.content,
        summary: articleForm.summary || undefined,
        cover_image: articleForm.cover_image || undefined,
        images: articleForm.images?.length ? articleForm.images : undefined,
        is_original: articleForm.is_original,
        allow_comments: articleForm.allow_comments,
        tags: articleTags.value.length ? articleTags.value : undefined,
        status: 'scheduled',
        scheduled_at: articleForm.scheduled_at,
    };

    try {
        if (isEdit.value) {
            await apiClient.put('/official-accounts/articles/' + route.query.id, payload);
            ElMessage.success(tp('schedule_updated'));
        } else {
            await apiClient.post('/official-accounts/' + articleForm.account_id + '/articles', {
                ...payload,
                account_id: articleForm.account_id,
            });
            ElMessage.success(tp('schedule_set', { time: articleForm.scheduled_at }));
        }
        router.push(`/channels?account_id=${articleForm.account_id}`);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || tp('submit_failed'));
    }
}

async function doPublish() {
    if (!articleForm.account_id) { ElMessage.warning(tp('select_account_submit')); return; }
    if (!articleForm.title.trim()) { ElMessage.warning(tp('title_required')); return; }
    if (!articleForm.content || articleForm.content.length < 20) { ElMessage.warning(tp('content_required')); return; }

    // 📋 内容合规预检
    if (scanResult.value?.hasSensitive) {
        showScanWarning.value = true;
        return;
    }

    const payload = {
        title: articleForm.title,
        content: articleForm.content,
        summary: articleForm.summary || undefined,
        cover_image: articleForm.cover_image || undefined,
        images: articleForm.images?.length ? articleForm.images : undefined,
        is_original: articleForm.is_original,
        allow_comments: articleForm.allow_comments,
        tags: articleTags.value.length ? articleTags.value : undefined,
        seo_title: seoMetaTitle.value || undefined,
        seo_description: seoMetaDescription.value || undefined,
    };

    try {
        if (isEdit.value) {
            await apiClient.put('/official-accounts/articles/' + route.query.id, { ...payload, status: 'published' });
            ElMessage.success(tp('article_updated'));
        } else {
            await apiClient.post('/official-accounts/' + articleForm.account_id + '/articles', {
                ...payload,
                account_id: articleForm.account_id,
                status: 'published',
            });
            ElMessage.success(tp('article_published'));
        }
        router.push(`/channels?account_id=${articleForm.account_id}`);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || tp('submit_failed'));
    }
}

function uploadCoverImage() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
        const file = input.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('file', file);
        try {
            const res = await apiClient.post('/official-accounts/upload-avatar', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            if (res.data?.data?.url) {
                articleForm.cover_image = res.data.data.url;
                ElMessage.success(tp('cover_uploaded'));
            }
        } catch { ElMessage.error(tp('upload_failed')); }
    };
    input.click();
}

// ── 编辑器操作 ──
async function uploadMedia(options) {
    const formData = new FormData();
    formData.append('file', options.file);
    try {
        const res = await apiClient.post('/official-accounts/upload-avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return res.data?.data?.url || null;
    } catch { return null; }
}

function insertVideo() {
    ElMessageBox.alert(tp('insert_video_msg'), tp('insert_video_title'), {
        confirmButtonText: t('actions.upload'),
        cancelButtonText: 'URL',
        showCancelButton: true,
        callback: async (action) => {
            if (action === 'confirm') {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = 'video/*';
                input.onchange = async () => {
                    const file = input.files?.[0];
                    if (!file) return;
                    const url = await uploadMedia({ file });
                    if (url && editor.value) {
                        editor.value.chain().focus().insertContent(`<video controls src="${url}" style="max-width:100%;border-radius:6px"></video>`).run();
                    }
                };
                input.click();
            } else if (action === 'cancel') {
                ElMessageBox.prompt(tp('insert_video_url_ph'), tp('insert_video_title'), {
                    inputPattern: /^https?:\/\/.+/,
                    inputErrorMessage: tp('url_invalid'),
                    confirmButtonText: t('actions.confirm'),
                    cancelButtonText: t('actions.cancel')
                }).then(({ value }) => {
                    if (value && editor.value) {
                        if (value.includes('youtube.com') || value.includes('youtu.be')) {
                            const videoId = value.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/);
                            if (videoId) {
                                editor.value.chain().focus().setYoutubeVideo({ src: value, width: 640, height: 360 }).run();
                                return;
                            }
                        }
                        editor.value.chain().focus().insertContent(`<video controls src="${value}" style="max-width:100%;border-radius:6px"></video>`).run();
                    }
                }).catch(() => {});
            }
        }
    });
}

function insertAudio() {
    ElMessageBox.alert(tp('insert_audio_msg'), tp('insert_audio_title'), {
        confirmButtonText: t('actions.upload'),
        cancelButtonText: 'URL',
        showCancelButton: true,
        callback: async (action) => {
            if (action === 'confirm') {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = 'audio/*';
                input.onchange = async () => {
                    const file = input.files?.[0];
                    if (!file) return;
                    const url = await uploadMedia({ file });
                    if (url && editor.value) {
                        editor.value.chain().focus().insertContent(`<audio controls src="${url}" style="max-width:100%"></audio>`).run();
                    }
                };
                input.click();
            } else if (action === 'cancel') {
                ElMessageBox.prompt(tp('insert_audio_url_ph'), tp('insert_audio_title'), {
                    inputPattern: /^https?:\/\/.+/,
                    inputErrorMessage: tp('url_invalid'),
                    confirmButtonText: t('actions.confirm'),
                    cancelButtonText: t('actions.cancel')
                }).then(({ value }) => {
                    if (value && editor.value) {
                        editor.value.chain().focus().insertContent(`<audio controls src="${value}"></audio>`).run();
                    }
                }).catch(() => {});
            }
        }
    });
}

function insertTable() {
    if (editor.value) {
        editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
    }
}

function insertLink() {
    showLinkDialog.value = true;
}

function doInsertLink() {
    if (linkText.value && linkUrl.value && editor.value) {
        editor.value.chain().focus().insertContent(`<a href="${linkUrl.value}" target="_blank">${linkText.value}</a>`).run();
        showLinkDialog.value = false;
        linkText.value = '';
        linkUrl.value = '';
    }
}

// ── 插入嵌入内容 ──
function insertEmbedItem(cmd) {
    if (!editor.value) return;
    if (cmd === 'product') {
        editor.value.chain().focus().insertContent({
            type: 'productCard',
            attrs: {
                name: '[商品名称]',
                slug: 'placeholder',
                price: '0.00',
                description: '点击右侧"商品"标签搜索商品并插入实际数据',
                imageUrl: '',
                sellerName: '商家名称',
                sellerAvatar: '',
                salesCount: 0,
            },
        }).run();
    } else if (cmd === 'affiliate') {
        editor.value.chain().focus().insertContent('<blockquote><p><strong>🤝 推荐产品</strong></p><p>[通过联盟链接购买可享受优惠]</p></blockquote>').run();
    } else if (cmd === 'ad') {
        editor.value.chain().focus().insertContent('<p><strong>📢 广告位</strong> — 可放置自定义广告内容</p>').run();
    } else if (cmd === 'poll') {
        showPollDialog.value = true
    }
}

// ── 投票/问卷 ──
function addPollOption() { pollForm.value.options.push('') }
function removePollOption(idx) { pollForm.value.options.splice(idx, 1) }
function insertPollCard() {
    if (!pollForm.value.question.trim()) { ElMessage.warning(tp('poll_question_required')); return }
    if (pollForm.value.options.filter(o => o.trim()).length < 2) { ElMessage.warning(tp('poll_options_required')); return }
    if (!editor.value) return
    creatingPoll.value = true
    const opts = pollForm.value.options.filter(o => o.trim())
    editor.value.chain().focus().insertContent({
        type: 'pollCard',
        attrs: { pollId: 0, question: pollForm.value.question, options: opts.map((o, i) => ({ id: i, label: o })), type: pollForm.value.type, totalVotes: 0 }
    }).run()
    showPollDialog.value = false
    pollForm.value = { question: '', type: 'single', options: ['', ''] }
    creatingPoll.value = false
    ElMessage.success(tp('poll_inserted'))
}

function setTextColor(color) {
    if (!editor.value) return;
    if (color === 'default') {
        editor.value.chain().focus().unsetMark('textStyle').run();
    } else if (color === '__custom__') {
        document.getElementById('customColorPicker')?.click();
    } else {
        editor.value.chain().focus().setMark('textStyle', { color }).run();
    }
}
function applyCustomColor(e) {
    const color = e.target?.value;
    if (color && editor.value) {
        editor.value.chain().focus().setMark('textStyle', { color }).run();
    }
}
function insertImageFromUpload(url) {
    if (url && editor.value) {
        pendingMediaUrl.value = url;
        imageCustomWidth.value = 400;
        showImageSizeDialog.value = true;
    }
}
function uploadImageFile() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
        const file = input.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('file', file);
        try {
            const res = await apiClient.post('/official-accounts/upload-avatar', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            if (res.data?.data?.url) {
                insertImageFromUpload(res.data.data.url);
            }
        } catch { ElMessage.error(tp('upload_failed')); }
    };
    input.click();
}

function insertProductCard(product) {
    if (!editor.value) return;
    pendingProduct.value = product;
    cardWidth.value = '100%';
    showCardWidthDialog.value = true;
}

async function confirmCardInsert() {
    if (!editor.value) return;
    // 联盟推广卡片
    if (pendingAffiliateItem.value) {
        const item = pendingAffiliateItem.value;
        try {
            const res = await apiClient.post('/store-affiliate/generate-links', {
                sku_ids: [item.id],
            });
            const linkData = res.data?.data?.[0];
            const link = linkData?.link;
            const productSlug = link?.match(/\/products\/([^?]+)/)?.[1] || 'product';
            const desc = affiliateShowCommission.value
                ? '推广佣金 ¥' + (item.commission_amount || 0) + '（' + (item.commission_rate || 0) + '%）'
                : '';
            editor.value.chain().focus().insertContent({
                type: 'productCard',
                attrs: {
                    name: item.product_name || item.name || '推荐产品',
                    slug: productSlug,
                    price: item.price != null ? item.price : '面议',
                    description: desc,
                    imageUrl: item.image_url || '',
                    sellerName: '',
                    sellerAvatar: '',
                    salesCount: item.sold_count || 0,
                    cardWidth: cardWidth.value,
                },
            }).run();
            ElMessage.success(tp('affiliate_link_ok'));
        } catch {
            ElMessage.error(tp('affiliate_link_failed'));
        }
        showCardWidthDialog.value = false;
        pendingAffiliateItem.value = null;
        return;
    }
    // 普通商品卡片
    if (!pendingProduct.value) return;
    const product = pendingProduct.value;
    const slug = product.slug || product.id;
    const sellerName = product.seller?.name || product.merchant?.name || product.account?.name || i18n.global.t('oa_article_editor_page.seller_name');
    const sellerAvatar = product.seller?.avatar || product.merchant?.avatar || '';
    editor.value.chain().focus().insertContent({
        type: 'productCard',
        attrs: {
            name: product.name || '[商品名称]',
            slug: slug,
            price: product.sku_price_min != null ? product.sku_price_min : (product.base_price != null ? product.base_price : '面议'),
            description: product.description || '',
            imageUrl: product.image_url || '',
            sellerName: sellerName,
            sellerAvatar: sellerAvatar,
            salesCount: product.sales_count || 0,
            cardWidth: cardWidth.value,
        },
    }).run();
    showCardWidthDialog.value = false;
    pendingProduct.value = null;
}

function insertAffiliateCard(item) {
    if (!editor.value) return;
    pendingAffiliateItem.value = item;
    cardWidth.value = '100%';
    affiliateShowCommission.value = true;
    showCardWidthDialog.value = true;
}

function insertAdBanner(type) {
    if (!editor.value) return;
    const sizes = { banner: '728×90', rectangle: '300×250', custom: t('oa_article_editor_page.width_custom') };
    editor.value.chain().focus().insertContent('<p><strong>📢 广告位 (' + sizes[type] + ')</strong> — 此处放置您的广告内容</p>').run();
}

function insertMediaToEditor(media) {
    if (!editor.value) return;
    if (media.type?.startsWith('image')) {
        pendingMediaUrl.value = media.url;
        imageCustomWidth.value = 400;
        showImageSizeDialog.value = true;
    } else if (media.type?.startsWith('video')) {
        editor.value.chain().focus().insertContent(`<video controls src="${media.url}" style="max-width:100%;border-radius:6px"></video>`).run();
    } else if (media.type?.startsWith('audio')) {
        editor.value.chain().focus().insertContent(`<audio controls src="${media.url}" style="max-width:100%"></audio>`).run();
    }
}
function confirmImageInsert() {
    if (!editor.value || !pendingMediaUrl.value) return;
    const width = imageCustomWidth.value > 0 ? imageCustomWidth.value : null;
    const style = width
        ? `max-width:100%;width:${width}px;height:auto;border-radius:6px`
        : `max-width:100%;height:auto;border-radius:6px`;
    editor.value.chain().focus().insertContent(`<img src="${pendingMediaUrl.value}" style="${style}" />`).run();
    showImageSizeDialog.value = false;
}

// Also update toolbar image insert to use size dialog
function insertImage() {
    ElMessageBox.alert(tp('insert_image_msg'), tp('insert_image_title'), {
        confirmButtonText: t('actions.upload'),
        cancelButtonText: 'URL',
        showCancelButton: true,
        callback: (action) => {
            if (action === 'confirm') uploadImageFile();
            else if (action === 'cancel') {
                ElMessageBox.prompt(tp('insert_image_url_ph'), tp('insert_image_title'), {
                    inputPattern: /^https?:\/\/.+/,
                    inputErrorMessage: tp('url_invalid'),
                    confirmButtonText: t('actions.confirm'),
                    cancelButtonText: t('actions.cancel')
                }).then(({ value }) => {
                    if (value && editor.value) {
                        pendingMediaUrl.value = value;
                        imageCustomWidth.value = 400;
                        showImageSizeDialog.value = true;
                    }
                }).catch(() => {});
            }
        }
    });
}

function onMediaUploaded(resp) {
    if (resp?.data?.url) {
        mediaList.value.unshift({ id: Date.now(), url: resp.data.url, name: resp.data.name || tp('media_fallback'), type: resp.data.type });
        ElMessage.success(tp('media_uploaded'));
    }
}
async function uploadMediaFile(options) {
    const formData = new FormData();
    formData.append('file', options.file);
    try {
        const res = await apiClient.post('/official-accounts/upload-avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (res.data?.data?.url) {
            const file = options.file;
            mediaList.value.unshift({ id: Date.now(), url: res.data.data.url, name: file.name || tp('media_fallback'), type: file.type });
            ElMessage.success(tp('media_uploaded'));
        }
    } catch { ElMessage.error(tp('upload_failed')); }
}

// ── 数据加载 ──
async function loadProducts(reset = true) {
    if (reset) {
        loadingProducts.value = true;
        products.value = [];
        productPage.value = 1;
    } else {
        loadingMoreProducts.value = true;
    }
    try {
        const params = { per_page: 12, page: productPage.value };
        if (productSearch.value.trim()) params.search = productSearch.value.trim();
        if (productCategory.value) params['filter.category_id'] = productCategory.value;
        const res = await apiClient.get('/products', { params });
        const result = res.data?.data || {};
        const items = Array.isArray(result) ? result : (result.data || []);
        products.value = reset ? items : [...products.value, ...items];
        const meta = res.data?.meta || res.data?.pagination || {};
        productHasMore.value = (meta.current_page || 1) < (meta.last_page || 1);
        productTotal.value = meta.total || 0;
    } catch { if (reset) products.value = []; }
    finally {
        loadingProducts.value = false;
        loadingMoreProducts.value = false;
    }
}
function handleProductSearch() {
    productPage.value = 1;
    loadProducts(true);
}
function loadMoreProducts() {
    if (loadingMoreProducts.value || !productHasMore.value || productPage.value >= 5) return;
    productPage.value++;
    loadProducts(false);
}

async function loadAffiliates(reset = true) {
    if (reset) {
        loadingAffiliates.value = true;
        affiliateItems.value = [];
        affiliatePage.value = 1;
    } else {
        loadingMoreAffiliates.value = true;
    }
    affiliateEnabled.value = false;
    try {
        // 先检查分销状态
        const dashResp = await apiClient.get('/store-affiliate/dashboard');
        const dashData = dashResp.data?.data;
        const hasAgent = dashData && (dashData.total_orders !== undefined || dashData.total_commission !== undefined);
        affiliateEnabled.value = hasAgent || false;

        if (affiliateEnabled.value) {
            // 已开通：加载可推广商品
            const res = await apiClient.get('/store-affiliate/promotable-skus', {
                params: { per_page: 12, page: affiliatePage.value },
            });
            const result = res.data?.data || {};
            const items = Array.isArray(result) ? result : (result.data || []);
            affiliateItems.value = reset ? items : [...affiliateItems.value, ...items];
            affiliateHasMore.value = (result.has_more === true || (result.current_page || 1) < (result.last_page || 1)) && affiliatePage.value < 5;
            affiliateTotal.value = result.total || 0;
        }
    } catch {
        if (reset) {
            affiliateEnabled.value = false;
            affiliateItems.value = [];
        }
    }
    finally {
        loadingAffiliates.value = false;
        loadingMoreAffiliates.value = false;
    }
}
function loadMoreAffiliates() {
    if (loadingMoreAffiliates.value || !affiliateHasMore.value) return;
    affiliatePage.value++;
    loadAffiliates(false);
}
function applyAffiliate() {
    window.open('/build/affiliate-enhanced', '_blank');
}

async function loadPromoCampaigns() {
    try {
        const res = await apiClient.get('/store-affiliate/campaigns', { params: { status: 'active', per_page: 50 } })
        promoCampaigns.value = res.data?.data?.data || res.data?.data || []
    } catch { promoCampaigns.value = [] }
}
async function togglePromoCamp(campId) {
    if (expandedPromoCamp.value === campId) { expandedPromoCamp.value = null; return }
    expandedPromoCamp.value = campId
    try {
        const res = await apiClient.get('/store-affiliate/campaigns/' + campId + '/creatives')
        promoCampCreatives.value = res.data?.data?.data || res.data?.data || []
    } catch { promoCampCreatives.value = [] }
}
function campTypeLabel(type) {
    return { referral: tp('camp_referral'), commission: tp('camp_commission'), reward: tp('camp_reward'), rebate: tp('camp_rebate') }[type] || type
}
function adStyleTag(cr) {
    if (cr.image_url && cr.content) return { type: 'style1', label: tp('ad_style_mixed') }
    if (cr.image_url && !cr.content) return { type: 'style2', label: tp('ad_style_big_cta') }
    if (cr.content && !cr.image_url) return { type: 'style3', label: tp('ad_style_bar') }
    return { type: 'style4', label: tp('ad_style_link') }
}
function adEarnings(cr, camp) {
    const pct = Number(cr.commission_rate) || 0
    const amt = Number(cr.commission_amount) || 0
    if (pct > 0) return tp('earnings_commission', { pct, amt: amt > 0 ? ' (~¥' + amt + ')' : '' })
    if (amt > 0) return tp('earnings_approx', { n: amt })
    const rwd = Number(camp.reward_first) || Number(camp.reward_renewal) || 0
    return rwd > 0 ? '¥' + rwd : tp('earnings_negotiable')
}
function promptAdWidth(cr, camp) {
    pendingCreative.value = cr
    pendingCampaign.value = camp
    adWidth.value = '100%'
    adStyle.value = 'auto'
    adWidthCustom.value = ''
    adWidthDialog.value = true
}
function confirmAdWidth() {
    if (!pendingCreative.value) return
    const cr = pendingCreative.value
    const camp = pendingCampaign.value
    const style = adStyle.value === 'auto' ? adStyleTag(cr).type : adStyle.value
    const width = adWidth.value === 'custom' ? adWidthCustom.value : adWidth.value
    let html = '<div style="margin:16px auto;width:' + width + ';max-width:100%">'
    if (style === 'style1') {
        html += '<div style="position:relative;display:flex;gap:12px;align-items:stretch;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff;width:100%">' +
            '<span style="position:absolute;top:8px;right:8px;z-index:1;padding:2px 8px;background:rgba(0,0,0,0.4);color:#fff;font-size:10px;border-radius:4px;line-height:1.4">' + i18n.global.t('oa_article_editor_page.ad_label') + '</span>' +
            (cr.image_url ? '<div style="width:120px;min-height:90px;flex-shrink:0"><img src="' + cr.image_url + '" style="width:100%;height:100%;object-fit:cover" /></div>' : '') +
            '<div style="flex:1;padding:12px;display:flex;flex-direction:column;justify-content:center"><div style="font-weight:600;font-size:14px;margin-bottom:4px">' + (cr.name || i18n.global.t('oa_article_editor_page.promotion_fallback')) + '</div>' +
            (cr.content ? '<div style="font-size:12px;color:#6b7280;line-height:1.5">' + cr.content.substring(0, 80) + '</div>' : '') +
            '<div style="margin-top:8px"><span style="display:inline-block;padding:4px 14px;background:#0f172a;color:#fff;font-size:12px;border-radius:6px;font-weight:500">' + i18n.global.t('oa_article_editor_page.ad_cta_learn') + ' →</span></div></div></div>'
    } else if (style === 'style2') {
        html += '<div style="position:relative;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff;width:100%">' +
            '<span style="position:absolute;top:8px;right:8px;z-index:1;padding:2px 8px;background:rgba(0,0,0,0.4);color:#fff;font-size:10px;border-radius:4px;line-height:1.4">' + i18n.global.t('oa_article_editor_page.ad_label') + '</span>' +
            (cr.image_url ? '<div style="width:100%;height:140px;overflow:hidden"><img src="' + cr.image_url + '" style="width:100%;height:100%;object-fit:cover" /></div>' : '') +
            '<div style="padding:14px;text-align:center"><div style="font-weight:700;font-size:15px;margin-bottom:8px">' + (cr.name || i18n.global.t('oa_article_editor_page.promotion_fallback')) + '</div>' +
            '<span style="display:inline-block;padding:6px 24px;background:linear-gradient(135deg,#0f172a,#66b1ff);color:#fff;font-size:13px;border-radius:20px;font-weight:600">' + i18n.global.t('oa_article_editor_page.ad_cta_now') + '</span></div></div>'
    } else if (style === 'style3') {
        html += '<div style="position:relative;display:flex;gap:10px;padding:12px 14px;border-radius:10px;background:linear-gradient(135deg,#f0f5ff,#e6f0ff);border-left:4px solid #0f172a;width:100%">' +
            '<span style="position:absolute;top:8px;right:8px;z-index:1;padding:2px 8px;background:rgba(0,0,0,0.35);color:#fff;font-size:10px;border-radius:4px;line-height:1.4">' + i18n.global.t('oa_article_editor_page.ad_label') + '</span>' +
            '<div style="flex:1"><div style="font-weight:600;font-size:14px;margin-bottom:4px">' + (cr.name || i18n.global.t('oa_article_editor_page.promotion_fallback')) + '</div>' +
            (cr.content ? '<div style="font-size:12px;color:#6b7280;line-height:1.5">' + cr.content.substring(0, 60) + '</div>' : '') +
            '<div style="margin-top:6px;font-size:12px;color:#0f172a;font-weight:600">' + i18n.global.t('oa_article_editor_page.ad_cta_more') +  ' →</div></div></div>'
    } else {
        html += '<div style="position:relative;display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:8px;background:#f5f7fa;border:1px solid #e5e7eb;width:100%">' +
            '<span style="position:absolute;top:6px;right:8px;z-index:1;padding:1px 7px;background:rgba(0,0,0,0.3);color:#fff;font-size:10px;border-radius:3px;line-height:1.4">' + i18n.global.t('oa_article_editor_page.ad_label') + '</span>' +
            '<div style="display:flex;align-items:center;gap:8px"><span style="font-size:16px">🔗</span><span style="font-size:13px;font-weight:500">' + (cr.name || i18n.global.t('oa_article_editor_page.promotion_link')) + '</span></div>' +
            '<span style="font-size:12px;color:#0f172a;font-weight:600">' + i18n.global.t('oa_article_editor_page.ad_cta_view') + '</span></div>'
    }
    html += '</div>'
    editor.value?.chain().focus().insertContent(html).run()
    adWidthDialog.value = false
    pendingCreative.value = null
}

async function loadMedia() {
    mediaList.value = [];
}

// ── HTML 源码编辑 ──
function toggleSource() {
    if (!showSource.value) {
        sourceHtml.value = editor.value ? editor.value.getHTML() : articleForm.content || '';
        showSource.value = true;
    } else {
        showSource.value = false;
    }
}
function cancelSource() {
    showSource.value = false;
}
function applySource() {
    if (editor.value && sourceHtml.value) {
        // 预处理：将 <a> 包裹块级元素（如 <table>）转为带 data-link 属性的 div
        // 因为 ProseMirror 的 Link 是 inline mark，不能包裹 block 节点
        let html = sourceHtml.value
            // <a href="..." target="..." style="..."><table → <div data-link-href="..." data-link-target="..." style="..."><table
            .replace(/<a\s+([^>]*?)href="([^"]*)"([^>]*)>\s*<(table|div|h[1-6]|p|ul|ol|blockquote)/gi,
                (match, before, href, after, tag) => {
                    const target = match.match(/target="([^"]*)"/i);
                    const style = match.match(/style="([^"]*)"/i);
                    let attrs = `data-link-href="${href}"`;
                    if (target) attrs += ` data-link-target="${target[1]}"`;
                    if (style) attrs += ` style="${style[1]}"`;
                    return `<div ${attrs}><${tag}`;
                })
            // </a> 在块级元素后 → </div>
            .replace(/<\/(table|div|h[1-6]|p|ul|ol|blockquote)>\s*<\/a>/gi, '</$1></div>');

        editor.value.commands.setContent(html);
        articleForm.content = html;
    }
    showSource.value = false;
    ElMessage.success(tp('html_applied'));
}

// ── 代码块操作 ──
function changeCodeLang(lang) {
    if (!editor.value) return
    editor.value.chain().focus().updateAttributes('codeBlock', { language: lang }).run()
}
function exitCodeBlock() {
    if (!editor.value) return
    editor.value.chain().focus().toggleCodeBlock().run()
}
function copyCodeBlock() {
    if (!editor.value) return
    const pos = editor.value.state.selection.$from
    for (let i = pos.depth; i > 0; i--) {
        const node = pos.node(i)
        if (node.type.name === 'codeBlock') {
            navigator.clipboard.writeText(node.textContent).then(() => {
                ElMessage.success(tp('code_copied'))
            }).catch(() => ElMessage.error(t('oa_article_detail_page.toast.copy_failed')))
            return
        }
    }
    const selectedText = editor.value.state.doc.textBetween(
        editor.value.state.selection.from,
        editor.value.state.selection.to
    )
    if (selectedText) {
        navigator.clipboard.writeText(selectedText).then(() => {
            ElMessage.success(tp('code_copied'))
        }).catch(() => ElMessage.error(t('oa_article_detail_page.toast.copy_failed')))
    } else {
        ElMessage.warning(tp('code_block_not_found'))
    }
}

// ── 本地草稿自动保存 ──
function saveDraftToLocal() {
    if (!articleForm.title && !articleForm.content) return;
    const data = {
        title: articleForm.title,
        content: articleForm.content,
        summary: articleForm.summary,
        cover_image: articleForm.cover_image,
        images: articleForm.images,
        is_original: articleForm.is_original,
        allow_comments: articleForm.allow_comments,
        account_id: articleForm.account_id,
        tags: articleTags.value,
        seo_title: seoMetaTitle.value,
        seo_description: seoMetaDescription.value,
    };
    localStorage.setItem(draftKey.value, JSON.stringify(data));
    draftSaved.value = true;
    setTimeout(() => { draftSaved.value = false; }, 3000);
}
function loadDraftFromLocal() {
    try {
        const raw = localStorage.getItem(draftKey.value);
        if (!raw) return false;
        const data = JSON.parse(raw);
        if (!data.title && !data.content) return false;
        return data;
    } catch { return false; }
}
function clearDraft() {
    localStorage.removeItem(draftKey.value);
}

// ── 搜索替换 ──
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
function toggleSearch() {
    showSearch.value = !showSearch.value;
    if (!showSearch.value) {
        searchQuery.value = '';
        searchReplace.value = '';
        searchMatches.value = 0;
        currentMatch.value = -1;
    }
}
function doSearch() {
    if (!searchQuery.value || !editor.value) {
        searchMatches.value = 0;
        currentMatch.value = -1;
        return;
    }
    const text = editor.value.view.state.doc.textBetween(0, editor.value.view.state.doc.content.size);
    const regex = new RegExp(escapeRegex(searchQuery.value), 'gi');
    const matches = text.match(regex);
    searchMatches.value = matches ? matches.length : 0;
    currentMatch.value = searchMatches.value > 0 ? 0 : -1;
}
function focusOnMatch() {
    if (!searchQuery.value || !editor.value || searchMatches.value === 0) return;
    const query = searchQuery.value;
    const text = editor.value.view.state.doc.textBetween(0, editor.value.view.state.doc.content.size);
    const regex = new RegExp(escapeRegex(query), 'gi');
    let match, count = 0;
    while ((match = regex.exec(text)) !== null) {
        if (count === currentMatch.value) {
            const from = match.index;
            const to = from + query.length;
            editor.value.chain().focus().setTextSelection({ from, to }).run();
            break;
        }
        count++;
    }
}
function nextMatch() { if (searchMatches.value > 0) { currentMatch.value = (currentMatch.value + 1) % searchMatches.value; focusOnMatch(); } }
function prevMatch() { if (searchMatches.value > 0) { currentMatch.value = (currentMatch.value - 1 + searchMatches.value) % searchMatches.value; focusOnMatch(); } }
function doReplace() {
    if (!editor.value || searchMatches.value === 0 || !searchReplace.value) return;
    const html = editor.value.getHTML();
    const regex = new RegExp(escapeRegex(searchQuery.value), 'i');
    const match = html.match(regex);
    if (match) {
        const newHtml = html.replace(regex, searchReplace.value);
        editor.value.commands.setContent(newHtml);
        doSearch();
        ElMessage.success(tp('replaced_one'));
    }
}
function doReplaceAll() {
    if (!editor.value || searchMatches.value === 0 || !searchReplace.value) return;
    const html = editor.value.getHTML();
    const regex = new RegExp(escapeRegex(searchQuery.value), 'gi');
    const count = (html.match(regex) || []).length;
    const newHtml = html.replace(regex, searchReplace.value);
    editor.value.commands.setContent(newHtml);
    showSearch.value = false;
    searchQuery.value = '';
    searchReplace.value = '';
    searchMatches.value = 0;
    ElMessage.success(tp('replaced_all', { n: count }));
}

// ── 文章模板 ──
const templates = {
    product: {
        html: `<h2>产品名称</h2>
<p style="font-size:15px;color:#666">一句话产品简介，突出核心卖点。</p>
<div style="display:flex;gap:12px;margin:16px 0">
    <div style="flex:1;background:#f5f7fa;border-radius:8px;padding:16px;text-align:center">
        <div style="font-size:28px;font-weight:700;color:#f56c6c">核心特点一</div>
        <div style="font-size:13px;color:#666;margin-top:4px">特点描述</div>
    </div>
    <div style="flex:1;background:#f5f7fa;border-radius:8px;padding:16px;text-align:center">
        <div style="font-size:28px;font-weight:700;color:#f56c6c">核心特点二</div>
        <div style="font-size:13px;color:#666;margin-top:4px">特点描述</div>
    </div>
    <div style="flex:1;background:#f5f7fa;border-radius:8px;padding:16px;text-align:center">
        <div style="font-size:28px;font-weight:700;color:#f56c6c">核心特点三</div>
        <div style="font-size:13px;color:#666;margin-top:4px">特点描述</div>
    </div>
</div>
<p>详细的产品功能介绍、使用场景和客户案例。可以插入图片和链接来丰富内容。</p>
<div style="background:linear-gradient(135deg,#f56c6c,#e6a23c);color:#fff;border-radius:8px;padding:16px;text-align:center;margin:16px 0">
    <div style="font-size:18px;font-weight:600">🔥 限时优惠 · 立即抢购</div>
    <div style="font-size:13px;margin-top:4px">点击下方按钮了解更多</div>
</div>`
    },
    announcement: {
        html: `<h2>🎉 活动标题</h2>
<div style="background:#f0f7ff;border:1px solid #0f172a;border-radius:8px;padding:16px;margin:16px 0">
    <div style="display:flex;gap:20px;flex-wrap:wrap">
        <div><strong>📅 时间：</strong>2026年X月X日</div>
        <div><strong>📍 地点：</strong>活动地点</div>
        <div><strong>👥 人数：</strong>限额 100 人</div>
    </div>
</div>
<p>活动详情介绍，包括活动背景、流程安排、参与方式等信息。</p>
<h3>活动流程</h3>
<ul>
    <li><strong>14:00 - 14:30</strong> 签到入场</li>
    <li><strong>14:30 - 15:30</strong> 主题分享</li>
    <li><strong>15:30 - 16:00</strong> 互动交流</li>
    <li><strong>16:00 - 16:30</strong> 抽奖环节</li>
</ul>
<div style="background:#f56c6c;color:#fff;border-radius:6px;padding:12px;text-align:center;margin:16px 0;font-size:16px;font-weight:600">
    👉 点击此处报名参加
</div>`
    },
    news: {
        html: `<h2>资讯标题</h2>
<div style="font-size:13px;color:#909399;margin-bottom:16px">
    <span>来源：行业媒体</span> · <span>{{ new Date().toLocaleDateString('zh-CN') }}</span>
</div>
<p>资讯正文内容，介绍行业最新动态、政策变化或技术创新。</p>
<blockquote>
    <p>关键引用或核心观点摘要，突出本文最重要的信息。</p>
</blockquote>
<p>详细分析和解读，可以从多个角度展开讨论。包括数据支持、专家观点和市场反应。</p>
<h3>相关链接</h3>
<ul>
    <li><a href="#">延伸阅读一</a></li>
    <li><a href="#">延伸阅读二</a></li>
</ul>`
    },
    guide: {
        html: `<h2>📖 使用指南：功能名称</h2>
<p>本指南将帮助你快速上手使用该功能。</p>
<h3>第一步：准备工作</h3>
<ul>
    <li>确保已登录账号</li>
    <li>检查系统版本要求</li>
</ul>
<h3>第二步：操作步骤</h3>
<ol>
    <li>打开功能页面</li>
    <li>填写必要信息</li>
    <li>点击确认提交</li>
</ol>
<div style="background:#f0f9eb;border:1px solid #67c23a;border-radius:8px;padding:12px;margin:16px 0">
    <strong>💡 小提示：</strong>操作过程中如有疑问，可联系客服获取帮助。
</div>
<h3>注意事项</h3>
<ul>
    <li>请确保信息准确无误</li>
    <li>操作不可撤销，请谨慎</li>
</ul>`
    }
};
const templateMeta = computed(() => ({
    product: { title: tp('tpl_product_title'), desc: t('blog_page.tpl_product_desc') },
    announcement: { title: tp('tpl_announcement'), desc: tp('tpl_announcement_desc') },
    news: { title: tp('tpl_news'), desc: tp('tpl_news_desc') },
    guide: { title: t('blog_page.tpl_guide'), desc: t('blog_page.tpl_guide_desc') },
}));
function insertTemplate(name) {
    if (!editor.value) return;
    const tpl = templates[name];
    if (!tpl) return;
    editor.value.chain().focus().insertContent(tpl.html).run();
    showTemplateDialog.value = false;
    ElMessage.success(tp('template_inserted', { name: templateMeta.value[name]?.title || name }));
}

// ── 目录导航 TOC ──
const tocItems = computed(() => {
    const html = articleForm.content || '';
    const regex = /<h([1-3])(?:\s[^>]*)?>(.*?)<\/h[1-3]>/gi;
    const items = [];
    let match;
    while ((match = regex.exec(html)) !== null) {
        const level = parseInt(match[1]);
        const text = match[2].replace(/<[^>]*>/g, '');
        items.push({ level, text });
    }
    return items;
});
function scrollToHeading(idx) {
    const item = tocItems.value[idx];
    if (!item || !editor.value) return;
    const html = articleForm.content || '';
    const regex = /<h([1-3])(?:\s[^>]*)?>(.*?)<\/h[1-3]>/gi;
    let match, count = 0;
    while ((match = regex.exec(html)) !== null) {
        if (count === idx) {
            const text = match[2].replace(/<[^>]*>/g, '');
            // Search in editor and select the heading
            if (editor.value) {
                const docText = editor.value.view.state.doc.textBetween(0, editor.value.view.state.doc.content.size);
                const idx2 = docText.indexOf(text);
                if (idx2 >= 0) {
                    editor.value.chain().focus().setTextSelection({ from: idx2, to: idx2 + text.length }).run();
                }
            }
            showTocDialog.value = false;
            break;
        }
        count++;
    }
}

// ── 字体大小/字体族/行距 ──
function setFontSize(val) {
    if (!editor.value || !val) return;
    editor.value.chain().focus().setMark('textStyle', { fontSize: val }).run();
}
function setFontFamily(val) {
    if (!editor.value) return;
    if (!val) {
        editor.value.chain().focus().unsetMark('textStyle').run();
    } else {
        editor.value.chain().focus().setMark('textStyle', { fontFamily: val }).run();
    }
}
function setLineHeight(val) {
    if (!editor.value || !val) return;
    editor.value.chain().focus().insertContent(`<div style="line-height:${val};margin:8px 0">`).run();
}

// ── 图片编辑 ──
function editSelectedImage() {
    if (!editor.value) return;
    const { selection } = editor.value.view.state;
    const node = selection.$head.parent;
    // Find image node in selection or nearby
    let imgSrc = '', imgWidth = '';
    editor.value.view.state.doc.nodesBetween(selection.from - 50, selection.to + 50, (n) => {
        if (n.type.name === 'image') {
            imgSrc = n.attrs.src || '';
            const style = n.attrs.style || '';
            const wMatch = style.match(/width:\s*([^;]+)/);
            imgWidth = wMatch ? wMatch[1] : '';
        }
    });
    if (!imgSrc) {
        ElMessage.warning(tp('select_image_first'));
        return;
    }
    editImageSrc.value = imgSrc;
    editImageWidth.value = imgWidth || '100%';
    editImageAlign.value = 'left';
    showImageEditDialog.value = true;
}
function applyImageEdit() {
    if (!editor.value || !editImageSrc.value) return;
    let style = `max-width:100%;height:auto;border-radius:6px`;
    if (editImageWidth.value) style += `;width:${editImageWidth.value}`;
    if (editImageAlign.value === 'center') style += ';display:block;margin:12px auto';
    else if (editImageAlign.value === 'right') style += ';float:right;margin:0 0 12px 12px';
    else style += ';display:inline';
    // Find and update the image
    const { selection } = editor.value.view.state;
    editor.value.view.state.doc.nodesBetween(0, editor.value.view.state.doc.content.size, (node, pos) => {
        if (node.type.name === 'image') {
            editor.value.chain().focus().setImage({ src: editImageSrc.value }).run();
            return false;
        }
    });
    showImageEditDialog.value = false;
    ElMessage.success(tp('image_updated'));
}

// ── Emoji ──
const emojiList = [
    '😀','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','😉','😌','😍','🥰','😘','😗','😋','😛','😜','🤪','😝','🤑',
    '👍','👎','👊','✊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','💪','✌️','🤟','🤘','👌',
    '❤️','🧡','💛','💚','💙','💜','🖤','🤍','💔','💖','💗','💫','⭐','🌟','✨','🔥','💯',
    '🎉','🎊','🎈','🎁','🎀','🏆','🥇','🥈','🥉','🏅','💎','🔔','📣','💬','🗨️','💡',
    '😎','🤩','🥳','🤗','🤔','🤭','🤫','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥',
    '😢','😭','😤','😠','😡','🤬','😱','😨','😰','😥','😓','🤒','🤕','🥺','😳','🤯',
];
function insertEmoji(emoji) {
    if (!editor.value) return;
    editor.value.chain().focus().insertContent(emoji).run();
    showEmojiDialog.value = false;
}

// ── 一键排版 ──
function applyQuickFormat() {
    if (!editor.value) return;
    const html = editor.value.getHTML();
    // Clean up: normalize whitespace, ensure consistent paragraph styles
    let cleaned = html
        .replace(/<p[^>]*>/gi, '<p style="margin:8px 0;line-height:1.8;font-size:15px">')
        .replace(/<h1[^>]*>/gi, '<h1 style="font-size:26px;font-weight:700;margin:20px 0 10px">')
        .replace(/<h2[^>]*>/gi, '<h2 style="font-size:22px;font-weight:600;margin:18px 0 8px">')
        .replace(/<h3[^>]*>/gi, '<h3 style="font-size:18px;font-weight:600;margin:14px 0 6px">')
        .replace(/<blockquote[^>]*>/gi, '<blockquote style="border-left:4px solid #0f172a;padding:8px 16px;margin:12px 0;background:#f5f7fa;color:#606266">')
        .replace(/<img /gi, '<img style="max-width:100%;border-radius:6px;margin:12px 0" ');
    editor.value.commands.setContent(cleaned);
    ElMessage.success(tp('quick_format_done'));
}

// ── 快捷键帮助 ──
const shortcuts = computed(() => [
    { keys: 'Ctrl + B', desc: tp('shortcut_bold') },
    { keys: 'Ctrl + I', desc: tp('shortcut_italic') },
    { keys: 'Ctrl + U', desc: tp('shortcut_underline') },
    { keys: 'Ctrl + Z', desc: tp('shortcut_undo') },
    { keys: 'Ctrl + Shift + Z', desc: tp('shortcut_redo') },
    { keys: 'Ctrl + K', desc: tp('shortcut_link') },
    { keys: 'Ctrl + Shift + H', desc: tp('shortcut_hr') },
    { keys: 'Ctrl + Alt + 1/2/3', desc: tp('shortcut_headings') },
    { keys: 'Ctrl + Shift + 7/8', desc: tp('shortcut_lists') },
    { keys: 'Ctrl + Shift + B', desc: tp('shortcut_blockquote') },
    { keys: 'Ctrl + Enter', desc: tp('shortcut_publish') },
    { keys: '# + 空格', desc: tp('shortcut_h1') },
    { keys: '## + 空格', desc: tp('shortcut_h2') },
    { keys: '### + 空格', desc: tp('shortcut_h3') },
    { keys: '-/ * + 空格', desc: tp('shortcut_bullet') },
    { keys: '1. + 空格', desc: tp('shortcut_ordered') },
    { keys: '> + 空格', desc: tp('shortcut_quote') },
    { keys: '--- + 回车', desc: tp('shortcut_divider') },
]);

onMounted(() => {
    loadMyAccounts();
    loadProducts();
    loadAffiliates();
    loadPromoCampaigns();
    loadMedia();
    // 预选从 URL 传入的公众号
    if (route.query.account_id) {
        articleForm.account_id = Number(route.query.account_id);
    }
    // 编辑模式：加载已有文章
    if (route.query.id) {
        isEdit.value = true;
        loadArticle(route.query.id);
    }
    // 检查是否有未发布的本地草稿
    if (!route.query.id) {
        const draft = loadDraftFromLocal();
        if (draft && draft.title) {
            ElMessageBox.confirm(tp('restore_draft_confirm'), tp('restore_draft_title'), {
                confirmButtonText: tp('restore'),
                cancelButtonText: tp('ignore'),
            }).then(() => {
                articleForm.title = draft.title || '';
                articleForm.content = draft.content || '';
                articleForm.summary = draft.summary || '';
                articleForm.cover_image = draft.cover_image || '';
                articleForm.images = draft.images || [];
                articleForm.is_original = !!draft.is_original;
                articleForm.allow_comments = draft.allow_comments !== false;
                articleForm.account_id = draft.account_id || null;
                articleTags.value = draft.tags || [];
                seoMetaTitle.value = draft.seo_title || '';
                seoMetaDescription.value = draft.seo_description || '';
                if (editor.value && draft.content) {
                    editor.value.commands.setContent(draft.content);
                }
                ElMessage.success(tp('draft_restored'));
            }).catch(() => {
                clearDraft();
            });
        }
    }
    // 自动保存定时器（每 30 秒）
    autoSaveTimer = setInterval(saveDraftToLocal, 30000);
    // 为 data-link-href 元素添加点击跳转（编辑器内 Alt+点击）
    document.addEventListener('click', handleDataLinkClick);
});

onBeforeUnmount(() => {
    if (editor.value) editor.value.destroy();
    if (autoSaveTimer) clearInterval(autoSaveTimer);
    saveDraftToLocal();
    document.removeEventListener('click', handleDataLinkClick);
});

function handleDataLinkClick(e) {
    const el = e.target.closest('[data-link-href]');
    if (!el) return;
    if (!e.altKey) return; // Alt+点击才跳转，避免干扰编辑
    e.preventDefault();
    e.stopPropagation();
    const href = el.getAttribute('data-link-href');
    const target = el.getAttribute('data-link-target') || '_blank';
    if (href) window.open(href, target);
}
</script>

<style scoped>
.oa-editor-page {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    z-index: 2000; background: #fff; display: flex; flex-direction: column;
}
.editor-topbar {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 16px; border-bottom: 1px solid #e4e7ed;
    background: #fafafa; flex-shrink: 0;
}
.topbar-left, .topbar-right { display: flex; align-items: center; gap: 8px; }
.topbar-title { font-size: 15px; font-weight: 600; color: #303133; }
.editor-body {
    flex: 1; display: flex; min-height: 0; overflow: hidden;
}
.editor-main {
    flex: 1; display: flex; flex-direction: column; min-width: 0;
    border-right: 1px solid #e4e7ed;
}
.meta-form { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; }
.title-input :deep(.el-input__inner) { font-size: 22px; font-weight: 700; border: none; padding-left: 0; }
.title-input :deep(.el-input__inner):focus { box-shadow: none; }
.meta-row { display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap; }
.editor-toolbar {
    display: flex; align-items: center; gap: 4px; padding: 6px 12px;
    border-bottom: 1px solid #e4e7ed; background: #fafafa;
    flex-wrap: wrap; flex-shrink: 0;
}
.editor-content { flex: 1; overflow-y: auto; padding: 0; }
.prose-editor { min-height: 400px; padding: 20px 24px; outline: none; }
.prose-editor :deep(p) { margin: 8px 0; line-height: 1.8; font-size: 15px; }
.prose-editor :deep(h1) { font-size: 26px; font-weight: 700; margin: 20px 0 10px; }
.prose-editor :deep(h2) { font-size: 22px; font-weight: 600; margin: 18px 0 8px; }
.prose-editor :deep(h3) { font-size: 18px; font-weight: 600; margin: 14px 0 6px; }
.prose-editor :deep(pre) {
    background: #1e1e2e; color: #cdd6f4; padding: 16px; border-radius: 8px;
    overflow-x: auto; font-size: 13px; line-height: 1.6;
}
.prose-editor :deep(code) { font-family: 'Fira Code', 'Consolas', monospace; }
.prose-editor :deep(blockquote) {
    border-left: 4px solid #0f172a; padding: 8px 16px; margin: 12px 0;
    background: #f5f7fa; color: #606266;
}
.prose-editor :deep(img) { max-width: 100%; height: auto; border-radius: 6px; margin: 12px 0; }
.prose-editor :deep(table) { width: 100%; border-collapse: collapse; margin: 12px 0; }
.prose-editor :deep(th), .prose-editor :deep(td) {
    border: 1px solid #e0e0e0; padding: 8px 12px; text-align: left;
}
.prose-editor :deep(th) { background: #f5f7fa; font-weight: 600; }
.prose-editor :deep(.ProseMirror-gapcursor) { display: none; }
.prose-editor :deep(p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder); color: #c0c4cc; pointer-events: none; float: left; height: 0;
}
.editor-loading { flex: 1; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #909399; gap: 8px; }

/* 代码语言栏 */
.code-lang-bar { display: flex; align-items: center; padding: 6px 12px; background: #f5f7fa; border: 1px solid #e4e7ed; border-bottom: none; border-radius: 4px 4px 0 0; gap: 6px; }
.code-lang-badge { font-size: 11px; background: #e6f0ff; color: #0f172a; padding: 1px 8px; border-radius: 3px; font-weight: 500; }

/* 代码块右上角角标复制按钮 */
.prose-editor :deep(.ProseMirror pre) { position: relative; padding: 16px 44px 16px 16px; }
.prose-editor :deep(.ProseMirror pre)::after {
    content: '📋'; position: absolute; top: 6px; right: 8px; font-size: 14px; cursor: pointer;
    opacity: 0; transition: opacity .2s; padding: 2px 6px; border-radius: 4px;
    background: rgba(255,255,255,.1); line-height: 1.4; pointer-events: none;
}
.prose-editor :deep(.ProseMirror pre:hover)::after { opacity: .85; }

/* HTML 源码编辑 */
.editor-source-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.source-textarea { flex: 1; }
.source-textarea :deep(textarea) {
    font-family: 'Fira Code', 'Consolas', monospace !important;
    font-size: 13px !important;
    line-height: 1.6 !important;
    padding: 16px !important;
    border: none !important;
    border-radius: 0 !important;
    background: #1e1e2e !important;
    color: #cdd6f4 !important;
    resize: vertical !important;
}
.source-actions { display: flex; gap: 8px; padding: 8px 16px; border-top: 1px solid #e4e7ed; background: #fafafa; justify-content: flex-end; }

/* 底部状态栏 */
.editor-footer {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 16px; border-top: 1px solid #e4e7ed;
    background: #fafafa; font-size: 12px; color: #909399; flex-shrink: 0;
}
.footer-left { display: flex; gap: 16px; }
.footer-stat { display: flex; align-items: center; gap: 4px; }
.draft-saved { color: #67c23a; animation: fadeInOut 3s ease-in-out; }
@keyframes fadeInOut {
    0% { opacity: 0; } 10% { opacity: 1; } 80% { opacity: 1; } 100% { opacity: 0; }
}

/* 第二行工具栏 */
.editor-toolbar-row2 {
    display: flex; align-items: center; gap: 4px; padding: 4px 12px;
    border-bottom: 1px solid #e4e7ed; background: #f5f7fa;
    flex-wrap: wrap; flex-shrink: 0;
}
.editor-toolbar-row2 .el-button { font-size: 12px; }
.editor-toolbar-row2 .el-select { --el-font-size-base: 12px; }

/* 搜索替换面板 */
.search-panel {
    padding: 8px 12px; border-bottom: 1px solid #e4e7ed;
    background: #fefce8; display: flex; flex-direction: column; gap: 6px;
    flex-shrink: 0;
}
.search-row { display: flex; align-items: center; gap: 6px; }
.search-count { font-size: 12px; color: #0f172a; font-weight: 600; min-width: 50px; }
.search-count.no-result { color: #f56c6c; }

/* 文章模板 */
.template-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.template-card {
    border: 1px solid #ebeef5; border-radius: 8px; padding: 16px;
    cursor: pointer; transition: all .2s; text-align: center;
}
.template-card:hover { border-color: #0f172a; box-shadow: 0 2px 8px rgba(15,23,42,.15); }
.template-icon { font-size: 36px; margin-bottom: 8px; }
.template-name { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 4px; }
.template-desc { font-size: 12px; color: #909399; line-height: 1.4; }

/* 目录导航 */
.toc-list { max-height: 400px; overflow-y: auto; }
.toc-item {
    display: flex; align-items: center; gap: 8px; padding: 8px 12px;
    cursor: pointer; border-radius: 4px; transition: all .15s;
}
.toc-item:hover { background: #f0f7ff; }
.toc-level {
    font-size: 11px; padding: 2px 6px; border-radius: 3px;
    font-weight: 700; flex-shrink: 0;
}
.toc-h1 { background: #f1f5f9; color: #0f172a; }
.toc-h2 { background: #f0f9eb; color: #67c23a; }
.toc-h3 { background: #fdf6ec; color: #e6a23c; }
.toc-text { font-size: 13px; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Emoji 选择器 */
.emoji-grid { display: flex; flex-wrap: wrap; gap: 4px; max-height: 320px; overflow-y: auto; }
.emoji-item {
    font-size: 24px; width: 40px; height: 40px; display: flex;
    align-items: center; justify-content: center; cursor: pointer;
    border-radius: 4px; transition: all .15s;
}
.emoji-item:hover { background: #f0f7ff; transform: scale(1.2); }

/* 快捷键帮助 */
.shortcut-list { max-height: 400px; overflow-y: auto; }
.shortcut-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; border-bottom: 1px solid #f0f0f0;
}
.shortcut-item:last-child { border-bottom: none; }
.shortcut-keys { display: flex; gap: 4px; }
.shortcut-keys kbd {
    display: inline-block; padding: 2px 8px; font-size: 12px;
    background: #f5f7fa; border: 1px solid #e0e0e0; border-radius: 4px;
    font-family: 'Fira Code', 'Consolas', monospace; color: #303133;
}
.shortcut-desc { font-size: 13px; color: #606266; }

/* 右侧面板 */
.editor-sidebar { width: 320px; flex-shrink: 0; display: flex; flex-direction: column; }
.sidebar-tabs { display: flex; flex-direction: column; height: 100%; }
.sidebar-tabs :deep(.el-tabs__content) { flex: 1; overflow-y: auto; padding: 12px; }
.sidebar-search { margin-bottom: 8px; }
.sidebar-list { display: flex; flex-direction: column; gap: 6px; }
.sidebar-item {
    display: flex; align-items: center; gap: 10px; padding: 8px;
    border: 1px solid #ebeef5; border-radius: 6px; cursor: pointer; transition: all .2s;
}
.sidebar-item:hover { border-color: #0f172a; background: #f0f7ff; }
.item-img { width: 48px; height: 48px; border-radius: 4px; overflow: hidden; background: #f5f7fa; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
.item-img img { width: 100%; height: 100%; object-fit: cover; }
.item-info { flex: 1; min-width: 0; }
.item-name { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.item-price { font-size: 12px; color: #f56c6c; font-weight: 600; margin-top: 2px; }
.item-commission { font-size: 11px; color: #e6a23c; margin-top: 2px; }
.item-desc { font-size: 11px; color: #909399; margin-top: 2px; }

/* 分销联盟 - 未开通 */
.affiliate-empty { text-align: center; padding: 32px 16px; }
.affiliate-icon { font-size: 48px; margin-bottom: 12px; }
.affiliate-title { font-size: 18px; font-weight: 600; color: #303133; margin-bottom: 8px; }
.affiliate-desc { font-size: 13px; color: #909399; margin-bottom: 20px; line-height: 1.5; }
.affiliate-benefits { display: flex; flex-direction: column; gap: 10px; margin-bottom: 24px; }
.benefit-item { font-size: 13px; color: #606266; display: flex; align-items: center; gap: 6px; justify-content: center; }
.affiliate-apply-btn { width: 100%; max-width: 200px; }

/* 联盟分类筛选 */
.affiliate-categories { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 8px; }
.cat-tag { cursor: pointer; }

/* 素材上传 */
.media-upload { display: flex; flex-direction: column; gap: 12px; }
.media-list { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.media-item { border: 1px solid #ebeef5; border-radius: 6px; overflow: hidden; cursor: pointer; }
.media-item:hover { border-color: #0f172a; }
.media-thumb { width: 100%; height: 80px; object-fit: cover; }
.media-icon { height: 80px; display: flex; align-items: center; justify-content: center; font-size: 32px; background: #f5f7fa; }
.media-name { font-size: 11px; padding: 4px 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #666; }

/* 预览 */
.article-preview { max-width: 800px; margin: 0 auto; padding: 20px; }
.article-preview.mobile-preview { max-width: 100%; padding: 16px 12px; }
.article-preview.mobile-preview .preview-title { font-size: 22px; }
.preview-title { font-size: 28px; font-weight: 700; margin-bottom: 12px; }
.preview-meta { color: #909399; font-size: 13px; margin-bottom: 16px; display: flex; gap: 12px; }
.preview-cover img { width: 100%; max-height: 400px; object-fit: cover; border-radius: 8px; margin-bottom: 16px; }
.preview-content :deep(p) { margin: 8px 0; line-height: 1.8; }
.preview-content :deep(img) { max-width: 100%; }
/* 代码高亮预览样式 */
.preview-content :deep(pre) { background: #1e1e1e; color: #d4d4d4; padding: 14px 16px; border-radius: 6px; overflow-x: auto; font-size: 13px; line-height: 1.5; }
.preview-content :deep(code) { font-family: 'Fira Code', Consolas, monospace; font-size: 13px; }
.preview-content :deep(.hljs-keyword) { color: #c586c0; }
.preview-content :deep(.hljs-string) { color: #ce9178; }
.preview-content :deep(.hljs-number) { color: #b5cea8; }
.preview-content :deep(.hljs-comment) { color: #6a9955; font-style: italic; }
.preview-content :deep(.hljs-built_in) { color: #4ec9b0; }
.preview-content :deep(.hljs-literal) { color: #569cd6; }
.preview-content :deep(.hljs-function) { color: #dcdcaa; }
.preview-content :deep(.hljs-title) { color: #dcdcaa; }
.preview-content :deep(.hljs-params) { color: #9cdcfe; }
.preview-content :deep(.hljs-attr) { color: #9cdcfe; }
.preview-content :deep(.hljs-attribute) { color: #9cdcfe; }
.preview-content :deep(.hljs-type) { color: #4ec9b0; }
.preview-content :deep(.hljs-meta) { color: #d4d4d4; }
.preview-content :deep(.hljs-tag) { color: #569cd6; }
.preview-content :deep(.hljs-name) { color: #569cd6; }
.preview-content :deep(.hljs-selector-class) { color: #d7ba7d; }
.preview-content :deep(.hljs-selector-id) { color: #d7ba7d; }
.preview-content :deep(.hljs-selector-tag) { color: #569cd6; }
.preview-content :deep(.hljs-regexp) { color: #d16969; }
.preview-content :deep(.hljs-symbol) { color: #569cd6; }
.preview-content :deep(.hljs-bullet) { color: #d7ba7d; }
.preview-content :deep(.hljs-link) { color: #569cd6; text-decoration: underline; }
.preview-content :deep(.hljs-deletion) { color: #d16969; background: #470000; }
.preview-content :deep(.hljs-addition) { color: #6a9955; background: #003100; }

/* ── AI 工具 ── */
.ai-chat-msg { padding: 8px 10px; margin-bottom: 6px; border-radius: 6px; }
.ai-chat-msg.user { background: #e6f0ff; }
.ai-chat-msg.assistant { background: #f0f0f0; }
.ai-result-content { font-size: 14px; line-height: 1.7; white-space: pre-wrap; }
.ai-result-content :deep(p) { margin: 6px 0; }

/* ── 广告卡片预览（对话框内） ── */
.ad-preview-wrapper {
    border-radius: 8px;
}
.ad-preview-card {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: default;
}
.ad-preview-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    transform: translateY(-1px);
}
.ad-card-style1:hover {
    border-color: #0f172a !important;
}
.ad-card-style2:hover {
    border-color: #0f172a !important;
}
.ad-card-style3:hover {
    box-shadow: 0 4px 16px rgba(15,23,42,0.12);
}
.ad-card-style4:hover {
    border-color: #c0c4cc !important;
}
</style>
