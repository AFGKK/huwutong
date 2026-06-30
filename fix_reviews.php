<?php
/**
 * Fix product-detail.blade.php - upgrade loadReviews function
 * Run: php fix_reviews.php
 */

$file = __DIR__ . '/resources/views/public/product-detail.blade.php';
$content = file_get_contents($file);
if (!$content) { die("Cannot read file\n"); }

// Target the specific part of loadReviews after the images rendering and before the closing
$search1 = "if(r.images&&r.images.length){html+='<div class=\"flex gap-2 mt-3\">';for(var k=0;k<r.images.length;k++)html+='<img src=\"'+r.images[k]+'\" class=\"w-20 h-20 object-cover rounded-lg border\">';html+='</div>'}if(r.admin_reply)html+='<div class=\"ml-11 mt-2 p-3 bg-gray-50 rounded-lg text-sm text-gray-500\"><span class=\"font-medium\">商家回复：</span>'+r.admin_reply+'</div>';html+='</div>'}c.innerHTML=html}else{c.innerHTML='<div class=\"text-center py-12 text-gray-400\"><svg class=\"w-12 h-12 mx-auto mb-3 text-gray-300\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z\"/></svg><p>暂无评价</p></div>'}";

$replace1 = "if(r.images&&r.images.length){html+='<div class=\"flex gap-2 mt-3\">';for(var k=0;k<r.images.length;k++)html+='<img src=\"'+r.images[k]+'\" class=\"w-20 h-20 object-cover rounded-lg border cursor-pointer hover:opacity-80 transition review-img\" onclick=\"openReviewLightbox(\\''+r.images[k]+'\\')\">';html+='</div>'}if(r.admin_reply)html+='<div class=\"ml-11 mt-2 p-3 bg-gray-50 rounded-lg text-sm text-gray-500\"><span class=\"font-medium\">商家回复：</span>'+r.admin_reply+'</div>';var purchaseBadge=r.is_purchased||r.purchased?'<span class=\"ml-1.5 text-[10px] text-green-600 bg-green-50 px-1.5 py-0.5 rounded font-medium\">已购买</span>':'';var tagsHtml='';if(r.tags&&r.tags.length){tagsHtml='<div class=\"flex flex-wrap gap-1.5 mt-2\">';for(var t=0;t<r.tags.length;t++)tagsHtml+='<span class=\"text-[11px] bg-gray-50 text-gray-500 px-2 py-0.5 rounded-full border border-gray-100\">'+r.tags[t]+'</span>';tagsHtml+='</div>'}html=html.replace('</div></div>','</div>'+purchaseBadge+'</div>'+tagsHtml);var helpfulCount=r.helpful_count||r.helpful||0;html+='<div class=\"flex items-center gap-3 mt-3 pt-3 border-t border-gray-50\"><button onclick=\"helpfulReview('+r.id+',this)\" class=\"inline-flex items-center gap-1 text-xs text-gray-400 hover:text-primary-600 transition\"><svg class=\"w-3.5 h-3.5\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5\"/></svg><span>有帮助 <span class=\"helpful-count\">('+helpfulCount+')</span></span></button></div>';html=html.replace('<div class=\"bg-white rounded-xl p-6 border border-gray-100\">','<div class=\"review-item bg-white rounded-xl p-6 border border-gray-100\" data-rating=\"'+(r.rating||0)+'\" data-helpful=\"'+helpfulCount+'\">');html+='</div>'}c.innerHTML=html;document.getElementById('review-toolbar').classList.remove('hidden')}else{c.innerHTML='<div class=\"text-center py-12 text-gray-400\"><svg class=\"w-12 h-12 mx-auto mb-3 text-gray-300\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z\"/></svg><p>暂无评价，成为第一个评价的人</p></div>';document.getElementById('review-toolbar').classList.add('hidden')}";

$pos = strpos($content, $search1);
if ($pos === false) { die("Pattern 1 not found\n"); }

$content = substr_replace($content, $replace1, $pos, strlen($search1));

// Now update submitReview to include tags
$search2 = "body:JSON.stringify({product_id:pId,rating:_currentRating,content:el.value,is_anonymous:isAnon,images:_reviewImages})";
$replace2 = "body:JSON.stringify({product_id:pId,rating:_currentRating,content:el.value,is_anonymous:isAnon,images:_reviewImages,tags:getSelectedTags()})";

$pos2 = strpos($content, $search2);
if ($pos2 === false) { die("Pattern 2 not found\n"); }
$content = substr_replace($content, $replace2, $pos2, strlen($search2));

// Add helpfulReview and openReviewLightbox functions after the last review-related function
$search3 = "function openShareDialog(){";
$addCode = "\nfunction helpfulReview(id,btn){var c=btn.querySelector('.helpful-count');var n=parseInt((c?c.textContent.match(/\\d+/):['0'])[0])||0;c.textContent='('+(n+1)+')';btn.classList.add('text-primary-600');btn.disabled=true;showToast('感谢您的反馈')}\nfunction openReviewLightbox(src){var lb=document.getElementById('image-lightbox');var img=document.getElementById('lightbox-image');if(lb&&img){img.src=src;lb.classList.remove('hidden');lb.style.display='flex';document.body.style.overflow='hidden'}}\nfunction ";

$pos3 = strpos($content, $search3);
if ($pos3 === false) { die("Pattern 3 not found\n"); }
$content = substr_replace($content, $addCode, $pos3, 0);

file_put_contents($file, $content);
echo "Done! File updated successfully.\n";
