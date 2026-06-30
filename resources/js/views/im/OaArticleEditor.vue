<template>
    <div class="oa-editor-page">
        <!-- 顶部工具栏 -->
        <div class="editor-topbar">
            <div class="topbar-left">
                <el-button text size="small" @click="handleClose">
                    <el-icon><ArrowLeft /></el-icon> 返回列表
                </el-button>
                <el-divider direction="vertical" />
                <span class="topbar-title">
                    {{ isEdit ? '编辑文章' : '📝 创作新文章' }}
                    <el-tag v-if="targetAccount" size="small">{{ targetAccount.name }}</el-tag>
                </span>
            </div>
            <div class="topbar-right">
                <el-button size="small" text @click="previewVisible = true">👁️ 预览</el-button>
                <el-button size="small" text @click="saveDraft">💾 存草稿</el-button>
                <el-dropdown trigger="click" @command="handlePublishAction">
                    <el-button size="small" type="primary">
                        {{ isEdit ? '更新' : '📤 发布文章' }}
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="publish">📤 发布文章</el-dropdown-item>
                            <el-dropdown-item command="schedule">⏰ 定时发布</el-dropdown-item>
                            <el-dropdown-item command="draft">💾 仅存草稿</el-dropdown-item>
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
                    <el-input v-model="articleForm.title" placeholder="请输入文章标题..." size="large"
                        class="title-input" maxlength="200" />
                    <div class="meta-row">
                        <el-select v-model="articleForm.account_id" placeholder="选择投稿公众号..." size="small" style="width:180px">
                            <el-option v-for="a in myAccounts" :key="a.id" :label="a.name" :value="a.id">
                                <span>{{ a.avatar || '📢' }} {{ a.name }}</span>
                            </el-option>
                        </el-select>
                        <el-input v-model="articleForm.summary" placeholder="文章摘要（选填）" size="small" style="width:240px" maxlength="300" />
                        <div style="display:flex;gap:6px;align-items:center">
                            <el-button size="small" text @click="uploadCoverImage" title="上传封面图">🖼️ 封面</el-button>
                            <el-input v-model="articleForm.cover_image" placeholder="封面URL" size="small" style="width:180px" maxlength="500" />
                        </div>
                        <el-switch v-model="articleForm.is_original" active-text="原创" size="small" style="margin-left:4px" />
                        <el-switch v-model="articleForm.allow_comments" active-text="允许评论" size="small" />
                        <el-date-picker v-model="articleForm.scheduled_at" type="datetime" placeholder="定时发布（选填）" size="small" style="width:180px" :disabled-date="d => d <= new Date()" format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DD HH:mm:ss" clearable />
                    </div>
                </div>

                                <!-- 第一行工具栏：基础排版 -->
                <div class="editor-toolbar" v-if="editor">
                    <el-button-group size="small">
                        <el-button :type="editor.isActive('bold') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBold().run()" title="加粗"><b>B</b></el-button>
                        <el-button :type="editor.isActive('italic') ? 'primary' : 'default'" @click="editor.chain().focus().toggleItalic().run()" title="斜体"><i>I</i></el-button>
                        <el-button :type="editor.isActive('underline') ? 'primary' : 'default'" @click="editor.chain().focus().toggleUnderline().run()" title="下划线"><u>U</u></el-button>
                        <el-button :type="editor.isActive('strike') ? 'primary' : 'default'" @click="editor.chain().focus().toggleStrike().run()" title="删除线"><s>S</s></el-button>
                    </el-button-group>
                    <el-divider direction="vertical" />
                    <el-button-group size="small">
                        <el-button :type="editor.isActive('heading', { level: 1 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()">H1</el-button>
                        <el-button :type="editor.isActive('heading', { level: 2 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</el-button>
                        <el-button :type="editor.isActive('heading', { level: 3 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</el-button>
                    </el-button-group>
                    <el-divider direction="vertical" />
                    <el-button-group size="small">
                        <el-button :type="editor.isActive('bulletList') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBulletList().run()" title="无序列表">•</el-button>
                        <el-button :type="editor.isActive('orderedList') ? 'primary' : 'default'" @click="editor.chain().focus().toggleOrderedList().run()" title="有序列表">1.</el-button>
                        <el-button :type="editor.isActive('blockquote') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBlockquote().run()" title="引用">❝</el-button>
                        <el-button :type="editor.isActive('code') ? 'primary' : 'default'" @click="editor.chain().focus().toggleCode().run()" title="行内代码">`code`</el-button>
                        <el-button :type="editor.isActive('codeBlock') ? 'primary' : 'default'" @click="editor.chain().focus().toggleCodeBlock().run()" title="代码块">&lt;/&gt;</el-button>
                    </el-button-group>
                    <el-divider direction="vertical" />
                    <el-button-group size="small">
                        <el-button :type="editor.isActive({ textAlign: 'left' }) ? 'primary' : 'default'" @click="editor.chain().focus().setTextAlign('left').run()" title="左对齐">⬅</el-button>
                        <el-button :type="editor.isActive({ textAlign: 'center' }) ? 'primary' : 'default'" @click="editor.chain().focus().setTextAlign('center').run()" title="居中">⇔</el-button>
                        <el-button :type="editor.isActive({ textAlign: 'right' }) ? 'primary' : 'default'" @click="editor.chain().focus().setTextAlign('right').run()" title="右对齐">➔</el-button>
                    </el-button-group>
                    <el-divider direction="vertical" />
                    <el-button-group size="small">
                        <el-button @click="editor?.chain().focus().undo().run()" title="撤销">↩️</el-button>
                        <el-button @click="editor?.chain().focus().redo().run()" title="重做">↪️</el-button>
                    </el-button-group>
                </div>
                <!-- 第二行工具栏：插入 + 颜色/高亮 + 工具 -->
                <div class="editor-toolbar-row2" v-if="editor">
                    <el-dropdown trigger="click" @command="setTextColor">
                        <el-button size="small" title="文字颜色">🎨 <el-icon><ArrowDown /></el-icon></el-button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="default"><span style="color:#333">默认</span></el-dropdown-item>
                                <el-dropdown-item command="#f56c6c"><span style="color:#f56c6c">红色</span></el-dropdown-item>
                                <el-dropdown-item command="#e6a23c"><span style="color:#e6a23c">橙色</span></el-dropdown-item>
                                <el-dropdown-item command="#67c23a"><span style="color:#67c23a">绿色</span></el-dropdown-item>
                                <el-dropdown-item command="#409eff"><span style="color:#409eff">蓝色</span></el-dropdown-item>
                                <el-dropdown-item command="#909399"><span style="color:#909399">灰色</span></el-dropdown-item>
                                <el-dropdown-item command="__custom__" divided>
                                    <div style="display:flex;align-items:center;gap:6px">
                                        <span>🎨 自定义</span>
                                        <input type="color" id="customColorPicker" style="width:30px;height:24px;border:none;padding:0;cursor:pointer" @click.stop @change="applyCustomColor" />
                                    </div>
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                    <el-popover placement="bottom" :width="210" trigger="click" v-model:visible="showHighlightPicker">
                        <template #reference>
                            <el-button size="small" title="背景高亮" :style="{background:editor?.getAttributes('highlight')?.color||'transparent'}">🖊</el-button>
                        </template>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;padding:4px">
                            <div v-for="c in ['#ffd8bf','#ffd6e7','#d3adf7','#bae7ff','#b7eb8f','#ffe58f','#ffffff']" :key="c"
                                @click="editor?.chain().focus().toggleHighlight({color:c}).run(); showHighlightPicker=false"
                                :style="{width:28,height:28,borderRadius:4,background:c,cursor:'pointer',border:c==='#ffffff'?'1px solid #dcdfe6':'none'}">
                            </div>
                            <div style="width:100%;margin-top:4px;border-top:1px solid #eee;padding-top:4px">
                                <el-button size="small" text @click="editor?.chain().focus().toggleHighlight().run(); showHighlightPicker=false">清除高亮</el-button>
                            </div>
                        </div>
                    </el-popover>
                    <el-divider direction="vertical" />
                    <el-button-group size="small">
                        <el-button @click="insertImage" title="插入图片">🖼️</el-button>
                        <el-button @click="insertVideo" title="插入视频">🎬</el-button>
                        <el-button @click="insertAudio" title="插入音频">🎵</el-button>
                        <el-button @click="insertTable" title="插入表格">⊞</el-button>
                        <el-button @click="insertLink" title="插入链接">🔗</el-button>
                        <el-button @click="editor?.chain().focus().setHorizontalRule().run()" title="插入分割线">—</el-button>
                    </el-button-group>
                    <el-dropdown trigger="click" @command="insertEmbedItem">
                        <el-button size="small">📦 插入内容 <el-icon><ArrowDown /></el-icon></el-button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="product">🏷️ 商城产品</el-dropdown-item>
                                <el-dropdown-item command="affiliate">🤝 分销联盟推荐</el-dropdown-item>
                                <el-dropdown-item command="ad">📢 广告位</el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                    <el-divider direction="vertical" />
                    <el-button size="small" :type="showSearch ? 'primary' : 'default'" @click="toggleSearch" title="搜索替换">🔍 搜索</el-button>
                    <el-button size="small" @click="showTemplateDialog = true" title="文章模板">📋 模板</el-button>
                    <el-button size="small" @click="showTocDialog = true" title="目录">📑 目录</el-button>
                    <el-divider direction="vertical" />
                    <el-select v-model="fontSize" size="small" style="width:72px" placeholder="字号" @change="setFontSize">
                        <el-option label="12" value="12px" /><el-option label="14" value="14px" /><el-option label="15" value="15px" />
                        <el-option label="16" value="16px" /><el-option label="18" value="18px" /><el-option label="20" value="20px" />
                        <el-option label="24" value="24px" /><el-option label="28" value="28px" /><el-option label="32" value="32px" />
                    </el-select>
                    <el-select v-model="fontFamily" size="small" style="width:90px" placeholder="字体" @change="setFontFamily">
                        <el-option label="默认" value="" /><el-option label="宋体" value="SimSun, serif" />
                        <el-option label="黑体" value="SimHei, sans-serif" /><el-option label="微软雅黑" value="'Microsoft YaHei', sans-serif'" />
                        <el-option label="等线" value="DengXian, sans-serif" />
                    </el-select>
                    <el-select v-model="lineHeight" size="small" style="width:72px" placeholder="行距" @change="setLineHeight">
                        <el-option label="1.0" value="1" /><el-option label="1.5" value="1.5" /><el-option label="1.8" value="1.8" />
                        <el-option label="2.0" value="2" />
                    </el-select>
                    <el-divider direction="vertical" />
                    <el-button size="small" @click="clearFormatting" title="清除格式">🧹 清格式</el-button>
                    <el-divider direction="vertical" />
                    <el-button :type="showSource ? 'primary' : 'default'" size="small" @click="toggleSource" title="HTML源码">&lt;/&gt; HTML</el-button>
                </div>
<div v-if="!showSource" class="editor-content">
                    <div v-if="editor">
                        <editor-content :editor="editor" class="prose-editor" />
                    </div>
                    <div v-else class="editor-loading">
                        <el-icon class="is-loading"><Loading /></el-icon> 加载编辑器...
                    </div>
                </div>
                <!-- HTML 源码编辑模式 -->
                <div v-else class="editor-source-area">
                    <el-input v-model="sourceHtml" type="textarea" class="source-textarea" :autosize="{ minRows: 24, maxRows: 50 }" placeholder="在此编辑 HTML 源码..." />
                    <div class="source-actions">
                        <el-button size="small" @click="cancelSource">取消</el-button>
                        <el-button size="small" type="primary" @click="applySource">✔ 应用 HTML</el-button>
                    </div>
                </div>
                <!-- 底部状态栏 -->
                <div class="editor-footer">
                    <div class="footer-left">
                        <span class="footer-stat">📝 {{ wordCount }} 字</span>
                        <span class="footer-stat">⏱️ 约 {{ readingTime }} 分钟</span>
                    </div>
                    <div class="footer-right">
                        <span v-if="draftSaved" class="draft-saved">💾 已自动保存</span>
                    </div>
                </div>
            </div>

            <!-- 右侧素材面板 -->
            <div class="editor-sidebar">
                <el-tabs v-model="sidebarTab" class="sidebar-tabs">
                    <!-- 素材库 -->
                    <el-tab-pane label="🖼️ 素材" name="media" lazy>
                        <div class="media-upload">
                            <el-upload drag multiple :show-file-list="false" :http-request="uploadMediaFile" accept="image/*,video/*,audio/*">
                                <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
                                <div class="el-upload__text">拖拽或点击上传素材</div>
                                <template #tip><div class="el-upload__tip">支持 jpg/png/gif/mp4/mp3，单文件最大 10MB</div></template>
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
                    <el-tab-pane label="🏷️ 商品" name="products" lazy>
                        <div class="sidebar-search">
                            <el-input v-model="productSearch" placeholder="搜索商品..." size="small" clearable @change="handleProductSearch" />
                        </div>
                        <!-- 分类筛选 -->
                        <div class="affiliate-categories" v-if="productCategories.length > 0">
                            <el-tag
                                :type="!productCategory ? 'primary' : 'info'"
                                size="small" effect="plain" class="cat-tag"
                                @click="productCategory = ''; loadProducts()"
                            >全部</el-tag>
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
                                    <div class="item-price">¥{{ p.sku_price_min != null ? (p.sku_price_max != null && p.sku_price_max != p.sku_price_min ? p.sku_price_min + '~' + p.sku_price_max : p.sku_price_min) : (p.base_price != null ? p.base_price : '面议') }}</div>
                                </div>
                            </div>
                            <!-- 加载更多 -->
                            <div v-if="filteredProducts.length && productHasMore && !productSearch" style="text-align:center;padding:8px">
                                <el-button size="small" text :loading="loadingMoreProducts" @click="loadMoreProducts">
                                    {{ loadingMoreProducts ? '加载中...' : '加载更多 (' + filteredProducts.length + '/' + (productTotal || '...') + ')' }}
                                </el-button>
                            </div>
                            <el-empty v-if="!filteredProducts.length && !loadingProducts" description="暂无商品" :image-size="40" />
                        </div>
                    </el-tab-pane>
                    <!-- 分销联盟 -->
                    <el-tab-pane label="🤝 联盟" name="affiliate" lazy>
                        <!-- 未开通状态 -->
                        <div v-if="!loadingAffiliates && !affiliateEnabled" class="affiliate-empty">
                            <div class="affiliate-icon">🤝</div>
                            <div class="affiliate-title">分销联盟</div>
                            <div class="affiliate-desc">推广商品赚取佣金，让内容创造价值</div>
                            <div class="affiliate-benefits">
                                <div class="benefit-item">📈 高达 30% 佣金比例</div>
                                <div class="benefit-item">🛒 海量商品可选</div>
                                <div class="benefit-item">📊 实时数据追踪</div>
                            </div>
                            <el-button type="primary" size="small" @click="applyAffiliate" class="affiliate-apply-btn">
                                申请开通分销联盟
                            </el-button>
                        </div>
                        <!-- 已开通：显示可推广商品 -->
                        <div v-else>
                            <div class="sidebar-search">
                                <el-input v-model="affiliateSearch" placeholder="搜索联盟商品..." size="small" clearable />
                            </div>
                            <!-- 分类筛选 -->
                            <div class="affiliate-categories" v-if="affiliateCategories.length > 0">
                                <el-tag
                                    :type="!affiliateCategory ? 'primary' : 'info'"
                                    size="small" effect="plain" class="cat-tag"
                                    @click="affiliateCategory = ''"
                                >全部</el-tag>
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
                                            佣金 ¥{{ a.commission_amount }}（{{ a.commission_rate }}%）
                                        </div>
                                    </div>
                                </div>
                                <!-- 加载更多 -->
                                <div v-if="filteredAffiliateItems.length && affiliateHasMore && !affiliateSearch" style="text-align:center;padding:8px">
                                    <el-button size="small" text :loading="loadingMoreAffiliates" @click="loadMoreAffiliates">
                                        {{ loadingMoreAffiliates ? '加载中...' : '加载更多 (' + filteredAffiliateItems.length + '/' + (affiliateTotal || '...') + ')' }}
                                    </el-button>
                                </div>
                                <el-empty v-if="!filteredAffiliateItems.length && !loadingAffiliates" description="暂无可推广商品" :image-size="40" />
                            </div>
                        </div>
                    </el-tab-pane>
                    <!-- 广告 -->
                    <el-tab-pane label="📢 广告" name="ads" lazy>
                        <div class="sidebar-list">
                            <div class="sidebar-item" @click="insertAdBanner('banner')">
                                <div class="item-img"><span>📢</span></div>
                                <div class="item-info">
                                    <div class="item-name">横幅广告</div>
                                    <div class="item-desc">728×90 横幅</div>
                                </div>
                            </div>
                            <div class="sidebar-item" @click="insertAdBanner('rectangle')">
                                <div class="item-img"><span>📐</span></div>
                                <div class="item-info">
                                    <div class="item-name">矩形广告</div>
                                    <div class="item-desc">300×250 矩形</div>
                                </div>
                            </div>
                            <div class="sidebar-item" @click="insertAdBanner('custom')">
                                <div class="item-img"><span>✏️</span></div>
                                <div class="item-info">
                                    <div class="item-name">自定义广告</div>
                                    <div class="item-desc">自定义尺寸和链接</div>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>
                    <!-- SEO -->
                    <el-tab-pane label="🔍 SEO" name="seo" lazy>
                        <el-form label-position="top" size="small">
                            <el-form-item label="Meta 标题">
                                <el-input v-model="seoMetaTitle" placeholder="SEO标题..." maxlength="70" />
                            </el-form-item>
                            <el-form-item label="Meta 描述">
                                <el-input v-model="seoMetaDescription" type="textarea" :rows="3" placeholder="SEO描述..." maxlength="160" />
                            </el-form-item>
                            <el-form-item label="标签">
                                <el-select v-model="articleTags" multiple filterable allow-create default-first-option
                                    placeholder="输入标签按回车..." style="width:100%">
                                    <el-option v-for="t in articleTags" :key="t" :label="t" :value="t" />
                                </el-select>
                            </el-form-item>
                        </el-form>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>

        <!-- 预览对话框（支持桌面/手机预览） -->
        <el-dialog v-model="previewVisible" :title="'预览' + (previewDevice === 'mobile' ? ' 📱' : ' 💻')" :fullscreen="previewDevice === 'desktop'" :width="previewDevice === 'mobile' ? '420px' : ''" top="3vh" :close-on-click-modal="false" :style="previewDevice === 'mobile' ? 'margin:0 auto' : ''">
            <div style="display:flex;gap:8px;margin-bottom:12px;justify-content:center">
                <el-radio-group v-model="previewDevice" size="small">
                    <el-radio-button value="desktop">💻 桌面</el-radio-button>
                    <el-radio-button value="mobile">📱 手机</el-radio-button>
                </el-radio-group>
            </div>
            <div :class="['article-preview', previewDevice === 'mobile' ? 'mobile-preview' : '']">
                <h1 class="preview-title">{{ articleForm.title || '（无标题）' }}</h1>
                <div class="preview-meta">
                    <span v-if="targetAccount">{{ targetAccount.name }}</span>
                    <span>{{ new Date().toLocaleDateString('zh-CN') }}</span>
                    <span v-if="articleTags.length">🏷️ {{ articleTags.join(', ') }}</span>
                </div>
                <div v-if="articleForm.cover_image" class="preview-cover">
                    <img :src="articleForm.cover_image" />
                </div>
                <div class="preview-content" v-html="articleForm.content" />
            </div>
        </el-dialog>

        <!-- 插入链接对话框 -->
        <el-dialog v-model="showLinkDialog" title="插入链接" width="400px">
            <el-form label-width="60px">
                <el-form-item label="文本">
                    <el-input v-model="linkText" placeholder="链接文本..." />
                </el-form-item>
                <el-form-item label="URL">
                    <el-input v-model="linkUrl" placeholder="https://..." />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showLinkDialog = false">取消</el-button>
                <el-button size="small" type="primary" @click="doInsertLink">插入</el-button>
            </template>
        </el-dialog>

        <!-- 文章模板对话框 -->
        <el-dialog v-model="showTemplateDialog" title="📋 文章模板" width="600px">
            <div class="template-grid">
                <div class="template-card" @click="insertTemplate('product')">
                    <div class="template-icon">🏷️</div>
                    <div class="template-name">产品介绍</div>
                    <div class="template-desc">包含产品图片、标题、特点、价格和购买引导</div>
                </div>
                <div class="template-card" @click="insertTemplate('announcement')">
                    <div class="template-icon">📢</div>
                    <div class="template-name">活动公告</div>
                    <div class="template-desc">活动标题、时间、地点、详情和报名引导</div>
                </div>
                <div class="template-card" @click="insertTemplate('news')">
                    <div class="template-icon">📰</div>
                    <div class="template-name">行业资讯</div>
                    <div class="template-desc">资讯标题、来源、正文摘要和相关链接</div>
                </div>
                <div class="template-card" @click="insertTemplate('guide')">
                    <div class="template-icon">📖</div>
                    <div class="template-name">使用指南</div>
                    <div class="template-desc">步骤说明、注意事项和图文搭配</div>
                </div>
            </div>
        </el-dialog>

        <!-- 目录导航对话框 -->
        <el-dialog v-model="showTocDialog" title="📑 目录导航" width="400px">
            <div v-if="tocItems.length === 0" style="padding:20px;text-align:center;color:#909399">
                当前文章没有标题（H1/H2/H3），请先添加标题
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
        <el-dialog v-model="showCardWidthDialog" title="📦 设置卡片" width="420px" :close-on-click-modal="false">
            <div style="text-align:center;margin-bottom:16px">
                <!-- 商品预览 -->
                <div v-if="pendingProduct" style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid #ebeef5;border-radius:8px;text-align:left">
                    <img v-if="pendingProduct.image_url" :src="pendingProduct.image_url" style="width:60px;height:60px;border-radius:4px;object-fit:cover;flex-shrink:0" />
                    <span v-else style="font-size:32px">📦</span>
                    <div>
                        <div style="font-weight:600;font-size:14px">{{ pendingProduct?.name || pendingProduct?.product_name }}</div>
                        <div style="color:#f56c6c;font-weight:700;font-size:16px">¥{{ pendingProduct?.sku_price_min != null ? (pendingProduct?.sku_price_max != null && pendingProduct?.sku_price_max != pendingProduct?.sku_price_min ? pendingProduct.sku_price_min + '~' + pendingProduct.sku_price_max : pendingProduct.sku_price_min) : (pendingProduct?.price != null ? pendingProduct.price : (pendingProduct?.base_price != null ? pendingProduct.base_price : '面议')) }}</div>
                    </div>
                </div>
                <!-- 联盟推广预览 -->
                <div v-else-if="pendingAffiliateItem" style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid #ebeef5;border-radius:8px;text-align:left">
                    <img v-if="pendingAffiliateItem.image_url" :src="pendingAffiliateItem.image_url" style="width:60px;height:60px;border-radius:4px;object-fit:cover;flex-shrink:0" />
                    <span v-else style="font-size:32px">🛒</span>
                    <div>
                        <div style="font-weight:600;font-size:14px">{{ pendingAffiliateItem?.product_name || pendingAffiliateItem?.name }}</div>
                        <div style="color:#f56c6c;font-weight:700;font-size:16px">¥{{ pendingAffiliateItem?.price != null ? pendingAffiliateItem.price : '面议' }}</div>
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
                <span style="font-size:13px;color:#666">自定义宽度：</span>
                <el-input v-model="cardWidth" size="small" style="width:120px" placeholder="如 400px" />
            </div>
            <!-- 联盟推广：佣金显示开关 -->
            <div v-if="pendingAffiliateItem" style="display:flex;align-items:center;justify-content:center;gap:8px;padding-top:10px;border-top:1px solid #f0f0f0">
                <el-switch v-model="affiliateShowCommission" size="small" />
                <span style="font-size:13px;color:#606266">在卡片上显示推广佣金信息</span>
                <span v-if="pendingAffiliateItem?.commission_amount != null" style="font-size:12px;color:#e6a23c">
                    （¥{{ pendingAffiliateItem.commission_amount }}）
                </span>
            </div>
            <template #footer>
                <el-button size="small" @click="showCardWidthDialog = false">取消</el-button>
                <el-button size="small" type="primary" @click="confirmCardInsert">插入卡片</el-button>
            </template>
        </el-dialog>

        <!-- 图片插入尺寸对话框 -->
        <el-dialog v-model="showImageSizeDialog" title="🖼️ 设置图片尺寸" width="420px" :close-on-click-modal="false">
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
                <span style="font-size:13px;color:#666">自定义宽度：</span>
                <el-input-number v-model="imageCustomWidth" :min="50" :max="1200" :step="10" size="small" style="width:140px" />
                <span style="font-size:12px;color:#999">px</span>
            </div>
            <div style="font-size:12px;color:#909399;text-align:center;margin-top:10px">
                💡 高度自动等比缩放，图片不变形
            </div>
            <template #footer>
                <el-button size="small" @click="showImageSizeDialog = false">取消</el-button>
                <el-button size="small" type="primary" @click="confirmImageInsert">插入图片</el-button>
            </template>
        </el-dialog>

        <!-- 图片编辑对话框 -->
        <el-dialog v-model="showImageEditDialog" title="🖼️ 编辑图片" width="450px">
            <el-form label-width="70px" size="small">
                <el-form-item label="图片URL">
                    <el-input v-model="editImageSrc" placeholder="图片地址..." />
                </el-form-item>
                <el-form-item label="宽度">
                    <el-input v-model="editImageWidth" placeholder="如 400px 或 100%" style="width:150px" />
                </el-form-item>
                <el-form-item label="对齐">
                    <el-radio-group v-model="editImageAlign">
                        <el-radio value="left">居左</el-radio>
                        <el-radio value="center">居中</el-radio>
                        <el-radio value="right">居右</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="预览">
                    <img v-if="editImageSrc" :src="editImageSrc" style="max-width:100%;max-height:150px;border-radius:4px" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showImageEditDialog = false">取消</el-button>
                <el-button size="small" type="primary" @click="applyImageEdit">应用修改</el-button>
            </template>
        </el-dialog>

        <!-- Emoji 选择器对话框 -->
        <el-dialog v-model="showEmojiDialog" title="😊 插入表情" width="420px">
            <div class="emoji-grid">
                <span v-for="emoji in emojiList" :key="emoji" class="emoji-item" @click="insertEmoji(emoji)">{{ emoji }}</span>
            </div>
        </el-dialog>

        <!-- 快捷键帮助对话框 -->
        <el-dialog v-model="showHelpDialog" title="⌨️ 快捷键帮助" width="500px">
            <div class="shortcut-list">
                <div class="shortcut-item" v-for="s in shortcuts" :key="s.key">
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
                <div v-else-if="!aiLoading" style="text-align:center;padding:32px 0;color:#999">点击按钮开始分析...</div>
            </div>
            <template #footer>
                <el-button v-if="aiResult && aiAction === 'typo'" size="small" type="primary" @click="applyAiFix">✅ 应用修复</el-button>
                <el-button v-if="aiResult && aiAction === 'improve'" size="small" type="primary" @click="applyAiFix">✅ 应用建议</el-button>
                <el-button v-if="aiResult && aiAction === 'polish'" size="small" type="primary" @click="applyAiFix">✅ 替换为润色结果</el-button>
                <el-button v-if="aiResult && aiAction === 'summary'" size="small" type="primary" @click="copyAiResult">📋 复制</el-button>
                <el-button size="small" @click="showAiResult = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- AI 助手对话对话框 -->
        <el-dialog v-model="showAiAssistant" title="💬 AI 助手" width="480px" :close-on-click-modal="false">
            <div style="max-height:360px;overflow-y:auto;margin-bottom:8px">
                <div v-for="(msg, i) in aiChatMessages" :key="i" class="ai-chat-msg" :class="msg.role">
                    <div style="font-size:12px;font-weight:500;margin-bottom:2px;color:#909399">{{ msg.role === 'user' ? '我' : 'AI' }}</div>
                    <div style="font-size:13px;white-space:pre-wrap">{{ msg.content }}</div>
                </div>
                <div v-if="aiChatLoading" style="text-align:center;padding:16px;color:#999">AI 思考中...</div>
            </div>
            <div style="display:flex;gap:6px">
                <el-input v-model="aiChatInput" placeholder="输入你的问题..." size="small" style="flex:1" @keydown.enter="sendAiChat" />
                <el-button size="small" type="primary" :loading="aiChatLoading" @click="sendAiChat">发送</el-button>
            </div>
        </el-dialog>

        <!-- AI 创作对话框 -->
        <el-dialog v-model="showAiCreate" title="✍️ AI 创作" width="560px" :close-on-click-modal="false">
            <el-form label-position="top" size="small">
                <el-form-item label="创作主题" required>
                    <el-input v-model="aiCreateTopic" placeholder="输入文章主题，如：2026年人工智能发展趋势" :rows="2" type="textarea" />
                </el-form-item>
                <el-form-item label="文章风格">
                    <el-radio-group v-model="aiCreateStyle">
                        <el-radio value="general">通用</el-radio>
                        <el-radio value="professional">专业</el-radio>
                        <el-radio value="popular">通俗易懂</el-radio>
                        <el-radio value="news">新闻报导</el-radio>
                        <el-radio value="story">故事叙述</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="字数要求">
                    <el-select v-model="aiCreateLength" style="width:140px">
                        <el-option label="简短（~500字）" value="short" />
                        <el-option label="适中（~1000字）" value="medium" />
                        <el-option label="详细（~2000字）" value="long" />
                    </el-select>
                </el-form-item>
                <el-form-item label="额外要求（选填）">
                    <el-input v-model="aiCreateExtra" placeholder="如：需要包含数据引用、分三个章节、语气正式等" :rows="2" type="textarea" />
                </el-form-item>
            </el-form>
            <div v-if="aiCreateResult" class="ai-create-preview" style="border:1px solid #e4e7ed;border-radius:6px;padding:12px;margin-bottom:8px;max-height:280px;overflow-y:auto;background:#fafafa">
                <div style="font-size:12px;color:#909399;margin-bottom:6px">📖 生成预览</div>
                <div style="font-size:13px;line-height:1.6;white-space:pre-wrap">{{ aiCreateResult.substring(0, 500) }}{{ aiCreateResult.length > 500 ? '...' : '' }}</div>
            </div>
            <template #footer>
                <el-button size="small" @click="showAiCreate = false">取消</el-button>
                <el-button size="small" type="primary" :loading="aiCreateLoading" @click="doAiCreate">{{ aiCreateResult ? '🔄 重新生成' : '✍️ 开始创作' }}</el-button>
                <el-button v-if="aiCreateResult" size="small" type="success" @click="insertAiCreate">📥 插入到编辑器</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
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
            sellerName: { default: '商家' },
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
                        ['span', { style: 'font-size:22px;font-weight:700;color:#409eff' }, '¥' + a.price],
                    ],
                    ['div', { style: 'font-size:12px;color:#909399;margin-bottom:10px;display:flex;align-items:center;gap:8px' },
                        ['span', {}, '已售 ' + (a.salesCount || 0)],
                        ['span', {}, a.sellerName],
                    ],
                    ['div', { style: 'display:flex;gap:10px;margin-top:6px' },
                        ['span', { style: 'flex:1;background:#409eff;color:#fff;font-size:13px;padding:8px 0;border-radius:6px;text-align:center;font-weight:500;display:block' }, '加入购物车'],
                        ['span', { style: 'flex:1;background:#fff;color:#f56c6c;font-size:13px;padding:8px 0;border-radius:6px;text-align:center;font-weight:500;display:block;border:1px solid #f56c6c' }, '立即购买'],
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
                        <span style="font-size:22px;font-weight:700;color:#409eff">¥${a.price}</span>
                    </div>
                    <div style="font-size:12px;color:#909399;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                        <span>已售 ${a.salesCount || 0}</span>
                        <span>${a.sellerName}</span>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:6px">
                        <span style="flex:1;background:#409eff;color:#fff;font-size:13px;padding:8px 0;border-radius:6px;text-align:center;font-weight:500;display:block">加入购物车</span>
                        <span style="flex:1;background:#fff;color:#f56c6c;font-size:13px;padding:8px 0;border-radius:6px;text-align:center;font-weight:500;display:block;border:1px solid #f56c6c">立即购买</span>
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
        aiResultTitle.value = '✏️ 错别字识别'
        callLlm('你是一个中文校对专家。请检查以下文章中的错别字、语法错误和标点问题。列出每个问题及其位置和修改建议。\n\n' + text)
    } else if (cmd === 'improve') {
        aiAction.value = 'improve'
        aiResultTitle.value = '💡 AI 改进建议'
        callLlm('你是一个资深内容编辑。请分析以下文章，给出具体的改进建议，包括：标题吸引力、段落结构、表达清晰度、读者吸引力等方面。\n\n' + text)
    } else if (cmd === 'polish') {
        aiAction.value = 'polish'
        aiResultTitle.value = '✨ AI 润色全文'
        callLlm('你是一个专业文字编辑。请润色以下文章，改进表达方式，使语言更流畅、专业，但不改变原意。直接返回润色后的完整文章。\n\n' + text)
    } else if (cmd === 'summary') {
        aiAction.value = 'summary'
        aiResultTitle.value = '📝 AI 生成摘要'
        callLlm('请为以下文章生成一段简洁的摘要（100-200字），突出核心内容和亮点。\n\n' + text)
    }
}
async function callLlm(prompt) {
    try {
        const res = await apiClient.post('/user-chat/ai-conversation', { message: prompt })
        aiResult.value = res.data?.data?.reply || '暂无结果'
    } catch (e) {
        aiResult.value = 'AI 服务暂时不可用，请稍后重试。\n' + (e.response?.data?.message || '')
    }
    finally { aiLoading.value = false }
}
function copyArticle() {
    const html = getArticleHtml()
    const text = getArticleText() || html
    navigator.clipboard.writeText(text).then(() => ElMessage.success('内容已复制')).catch(() => ElMessage.error('复制失败'))
}
function copyAiResult() {
    if (aiResult.value) navigator.clipboard.writeText(aiResult.value).then(() => ElMessage.success('已复制')).catch(() => {})
}
function applyAiFix() {
    if (!aiResult.value) return
    if (aiAction.value === 'polish') {
        // 替换编辑器内容为润色结果
        const html = aiResult.value.replace(/\n/g, '<p>') + '</p>'
        editor.value?.commands.setContent('<p>' + aiResult.value.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>')
        ElMessage.success('已应用润色结果')
    } else if (aiAction.value === 'summary') {
        articleForm.summary = aiResult.value
        ElMessage.success('摘要已填入')
    } else {
        ElMessage.success('请手动参考建议修改')
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
        const reply = res.data?.data?.reply || '抱歉，无法回答。'
        aiChatMessages.value.push({ role: 'assistant', content: reply })
    } catch {
        aiChatMessages.value.push({ role: 'assistant', content: 'AI 服务暂时不可用。' })
    }
    finally { aiChatLoading.value = false }
}

async function doAiCreate() {
    if (!aiCreateTopic.value.trim()) { ElMessage.warning('请输入创作主题'); return }
    aiCreateLoading.value = true
    aiCreateResult.value = ''
    try {
        const styleMap = { general: '通用风格', professional: '专业正式风格', popular: '通俗易懂的风格', news: '新闻报导风格', story: '故事叙述风格' }
        const lengthMap = { short: '约500字', medium: '约1000字', long: '约2000字' }
        let prompt = `请以「${aiCreateTopic.value}」为主题，写一篇${styleMap[aiCreateStyle.value] || '通用风格'}的文章，字数${lengthMap[aiCreateLength.value] || '约1000字'}。`
        if (aiCreateExtra.value.trim()) prompt += `\n额外要求：${aiCreateExtra.value.trim()}`
        prompt += '\n请使用标题和段落组织内容，用 Markdown 格式输出。'
        const res = await apiClient.post('/user-chat/ai-conversation', { message: prompt })
        aiCreateResult.value = res.data?.data?.reply || '生成失败，请重试。'
        // 自动填入标题
        if (!articleForm.title && aiCreateTopic.value) {
            articleForm.title = aiCreateTopic.value
        }
    } catch (e) {
        aiCreateResult.value = 'AI 服务暂时不可用：' + (e.response?.data?.message || '请稍后重试')
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
    ElMessage.success('AI 创作内容已插入编辑器')
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
const imageSizePresets = [
    { label: '小', value: 250 },
    { label: '中', value: 400 },
    { label: '大', value: 600 },
    { label: '原图', value: 0 },
];

// ── 商品卡片宽度设置 ──
const showCardWidthDialog = ref(false);
const pendingProduct = ref(null);
const pendingAffiliateItem = ref(null);
const affiliateShowCommission = ref(true);
const cardWidth = ref('100%');
const cardWidthPresets = [
    { label: '自适应', value: '100%' },
    { label: '小', value: '300px' },
    { label: '中', value: '400px' },
    { label: '大', value: '500px' },
];

// ── 格式 ──
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
    scheduled_at: '',
});

const targetAccount = computed(() => {
    if (!articleForm.account_id || !myAccounts.value.length) return null;
    return myAccounts.value.find(a => a.id === articleForm.account_id);
});

const draftKey = computed(() => `oa_editor_draft_${route.query.id || 'new'}`);

const wordCount = computed(() => {
    const html = articleForm.content || '';
    const text = html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').replace(/[\s\n\r]+/g, '');
    const chineseChars = (text.match(/[\u4e00-\u9fff]/g) || []).length;
    const englishWords = text.replace(/[\u4e00-\u9fff]/g, ' ').trim().split(/\s+/).filter(Boolean).length;
    return chineseChars + englishWords;
});

const readingTime = computed(() => Math.max(1, Math.round(wordCount.value / 300)));

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

const editor = useEditor({
    content: '',
    extensions: [
        StarterKit.configure({
            codeBlock: false,
            paragraph: false,
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
        Link.configure({ openOnClick: false, HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' } }),
        CodeBlockLowlight.configure({ lowlight }),
        Placeholder.configure({ placeholder: '开始撰写文章内容...' }),
        CustomTable.configure({ resizable: true }),
        CustomTableRow, CustomTableCell, TableHeader,
        Youtube.configure({ inline: false, width: 640, height: 360 }),
    ],
    onUpdate: ({ editor }) => {
        articleForm.content = editor.getHTML();
    },
});

function handleClose() {
    const backUrl = articleForm.account_id ? `/user-chat?account_id=${articleForm.account_id}` : '/user-chat';
    if (articleForm.content && articleForm.content.length > 50) {
        ElMessageBox.confirm('有未保存的内容，确定离开吗？', '确认离开').then(() => {
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
        ElMessage.error('文章加载失败');
    }
}

async function saveDraft() {
    if (!articleForm.account_id) { ElMessage.warning('请选择公众号'); return; }
    if (!articleForm.title.trim()) { ElMessage.warning('请输入文章标题'); return; }

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
            ElMessage.success('草稿已更新');
        } else {
            await apiClient.post('/official-accounts/' + articleForm.account_id + '/articles', payload);
            ElMessage.success('草稿已保存');
        }
        isEdit.value = true;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败');
    }
}

function handlePublishAction(cmd) {
    if (cmd === 'publish') doPublish();
    else if (cmd === 'schedule') doSchedule();
    else saveDraft();
}

async function doSchedule() {
    if (!articleForm.account_id) { ElMessage.warning('请选择公众号'); return; }
    if (!articleForm.title.trim()) { ElMessage.warning('请输入文章标题'); return; }
    if (!articleForm.content || articleForm.content.length < 20) { ElMessage.warning('文章内容不能为空'); return; }
    if (!articleForm.scheduled_at) { ElMessage.warning('请选择定时发布时间'); return; }

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
            ElMessage.success('定时发布已更新');
        } else {
            await apiClient.post('/official-accounts/' + articleForm.account_id + '/articles', {
                ...payload,
                account_id: articleForm.account_id,
            });
            ElMessage.success('已设置定时发布，将在 ' + articleForm.scheduled_at + ' 自动发布');
        }
        router.push(`/user-chat?account_id=${articleForm.account_id}`);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '提交失败');
    }
}

async function doPublish() {
    if (!articleForm.account_id) { ElMessage.warning('请选择投稿公众号'); return; }
    if (!articleForm.title.trim()) { ElMessage.warning('请输入文章标题'); return; }
    if (!articleForm.content || articleForm.content.length < 20) { ElMessage.warning('文章内容不能为空'); return; }

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
            ElMessage.success('文章已更新');
        } else {
            // 号主直接发布文章，无需审核
            await apiClient.post('/official-accounts/' + articleForm.account_id + '/articles', {
                ...payload,
                account_id: articleForm.account_id,
                status: 'published',
            });
            ElMessage.success('文章已发布');
        }
        router.push(`/user-chat?account_id=${articleForm.account_id}`);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '提交失败');
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
                ElMessage.success('封面已上传');
            }
        } catch { ElMessage.error('上传失败'); }
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
    ElMessageBox.alert('点击"上传"选择本地视频文件，或输入视频URL', '插入视频', {
        confirmButtonText: '上传',
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
                const url = prompt('请输入视频URL（支持YouTube/通用视频）：');
                if (url && editor.value) {
                    if (url.includes('youtube.com') || url.includes('youtu.be')) {
                        const videoId = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/);
                        if (videoId) {
                            editor.value.chain().focus().setYoutubeVideo({ src: url, width: 640, height: 360 }).run();
                            return;
                        }
                    }
                    editor.value.chain().focus().insertContent(`<video controls src="${url}" style="max-width:100%;border-radius:6px"></video>`).run();
                }
            }
        }
    });
}

function insertAudio() {
    ElMessageBox.alert('点击"上传"选择本地音频文件，或输入音频URL', '插入音频', {
        confirmButtonText: '上传',
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
                const url = prompt('请输入音频URL：');
                if (url && editor.value) {
                    editor.value.chain().focus().insertContent(`<audio controls src="${url}"></audio>`).run();
                }
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
    }
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
        } catch { ElMessage.error('上传失败'); }
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
            ElMessage.success('推广链接已生成 ✓');
        } catch {
            ElMessage.error('推广链接生成失败');
        }
        showCardWidthDialog.value = false;
        pendingAffiliateItem.value = null;
        return;
    }
    // 普通商品卡片
    if (!pendingProduct.value) return;
    const product = pendingProduct.value;
    const slug = product.slug || product.id;
    const sellerName = product.seller?.name || product.merchant?.name || product.account?.name || '商家';
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
    const sizes = { banner: '728×90', rectangle: '300×250', custom: '自定义' };
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
    ElMessageBox.alert('点击"上传"选择本地图片，或直接输入图片URL', '插入图片', {
        confirmButtonText: '上传',
        cancelButtonText: 'URL',
        showCancelButton: true,
        callback: (action) => {
            if (action === 'confirm') uploadImageFile();
            else if (action === 'cancel') {
                const url = prompt('请输入图片URL：');
                if (url && editor.value) {
                    pendingMediaUrl.value = url;
                    imageCustomWidth.value = 400;
                    showImageSizeDialog.value = true;
                }
            }
        }
    });
}

function onMediaUploaded(resp) {
    if (resp?.data?.url) {
        mediaList.value.unshift({ id: Date.now(), url: resp.data.url, name: resp.data.name || '素材', type: resp.data.type });
        ElMessage.success('素材已上传');
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
            mediaList.value.unshift({ id: Date.now(), url: res.data.data.url, name: file.name || '素材', type: file.type });
            ElMessage.success('素材已上传');
        }
    } catch { ElMessage.error('上传失败'); }
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

async function loadMedia() {
    try {
        const res = await apiClient.get('/im/uploads');
        mediaList.value = res.data?.data || [];
    } catch { mediaList.value = []; }
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
    ElMessage.success('HTML 已应用');
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
                ElMessage.success('✅ 代码已复制')
            }).catch(() => ElMessage.error('复制失败'))
            return
        }
    }
    const selectedText = editor.value.state.doc.textBetween(
        editor.value.state.selection.from,
        editor.value.state.selection.to
    )
    if (selectedText) {
        navigator.clipboard.writeText(selectedText).then(() => {
            ElMessage.success('✅ 代码已复制')
        }).catch(() => ElMessage.error('复制失败'))
    } else {
        ElMessage.warning('未找到代码块')
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
        ElMessage.success('已替换 1 处');
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
    ElMessage.success(`已全部替换 ${count} 处`);
}

// ── 文章模板 ──
const templates = {
    product: {
        title: '🏷️ 产品介绍',
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
        title: '📢 活动公告',
        html: `<h2>🎉 活动标题</h2>
<div style="background:#f0f7ff;border:1px solid #409eff;border-radius:8px;padding:16px;margin:16px 0">
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
        title: '📰 行业资讯',
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
        title: '📖 使用指南',
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
function insertTemplate(name) {
    if (!editor.value) return;
    const tpl = templates[name];
    if (!tpl) return;
    editor.value.chain().focus().insertContent(tpl.html).run();
    showTemplateDialog.value = false;
    ElMessage.success(`已插入「${tpl.title}」模板`);
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
        ElMessage.warning('请先选中编辑器中的一张图片');
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
    ElMessage.success('图片已更新');
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
        .replace(/<blockquote[^>]*>/gi, '<blockquote style="border-left:4px solid #409eff;padding:8px 16px;margin:12px 0;background:#f5f7fa;color:#606266">')
        .replace(/<img /gi, '<img style="max-width:100%;border-radius:6px;margin:12px 0" ');
    editor.value.commands.setContent(cleaned);
    ElMessage.success('一键排版完成');
}

// ── 快捷键帮助 ──
const shortcuts = [
    { keys: 'Ctrl + B', desc: '加粗' },
    { keys: 'Ctrl + I', desc: '斜体' },
    { keys: 'Ctrl + U', desc: '下划线' },
    { keys: 'Ctrl + Z', desc: '撤销' },
    { keys: 'Ctrl + Shift + Z', desc: '重做' },
    { keys: 'Ctrl + K', desc: '插入链接' },
    { keys: 'Ctrl + Shift + H', desc: '插入水平线' },
    { keys: 'Ctrl + Alt + 1/2/3', desc: '标题 H1/H2/H3' },
    { keys: 'Ctrl + Shift + 7/8', desc: '有序/无序列表' },
    { keys: 'Ctrl + Shift + B', desc: '引用块' },
    { keys: 'Ctrl + Enter', desc: '发布文章' },
    { keys: '# + 空格', desc: '标题 H1' },
    { keys: '## + 空格', desc: '标题 H2' },
    { keys: '### + 空格', desc: '标题 H3' },
    { keys: '-/ * + 空格', desc: '无序列表' },
    { keys: '1. + 空格', desc: '有序列表' },
    { keys: '> + 空格', desc: '引用块' },
    { keys: '--- + 回车', desc: '分割线' },
];

onMounted(() => {
    loadMyAccounts();
    loadProducts();
    loadAffiliates();
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
            ElMessageBox.confirm('检测到未发布的草稿，是否恢复？', '恢复草稿', {
                confirmButtonText: '恢复',
                cancelButtonText: '忽略',
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
                ElMessage.success('草稿已恢复');
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
    border-left: 4px solid #409eff; padding: 8px 16px; margin: 12px 0;
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
.code-lang-badge { font-size: 11px; background: #e6f0ff; color: #409eff; padding: 1px 8px; border-radius: 3px; font-weight: 500; }

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
.search-count { font-size: 12px; color: #409eff; font-weight: 600; min-width: 50px; }
.search-count.no-result { color: #f56c6c; }

/* 文章模板 */
.template-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.template-card {
    border: 1px solid #ebeef5; border-radius: 8px; padding: 16px;
    cursor: pointer; transition: all .2s; text-align: center;
}
.template-card:hover { border-color: #409eff; box-shadow: 0 2px 8px rgba(64,158,255,.15); }
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
.toc-h1 { background: #ecf5ff; color: #409eff; }
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
.sidebar-item:hover { border-color: #409eff; background: #f0f7ff; }
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
.media-item:hover { border-color: #409eff; }
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
</style>
