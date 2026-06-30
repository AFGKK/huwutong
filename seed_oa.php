<?php
$user = App\Models\User::find(1);
$account = App\Models\OfficialAccount::create([
    'name' => 'HWT技术周刊',
    'slug' => 'hwt-tech-weekly',
    'description' => '分享最新技术资讯和产品动态',
    'avatar' => null,
    'owner_id' => $user->id,
]);
App\Models\OaFollower::create(['account_id' => $account->id, 'user_id' => $user->id]);
App\Models\OaArticle::create([
    'account_id' => $account->id,
    'author_id' => $user->id,
    'title' => '欢迎关注HWT技术周刊',
    'content' => '<h2>欢迎关注！</h2><p>这是我们的第一篇文章。HWT技术周刊将为您带来最新的技术资讯和产品动态。</p><p>敬请期待更多精彩内容！</p>',
    'summary' => 'HWT技术周刊正式上线，为您带来最新技术资讯',
    'status' => 'published',
    'published_at' => now(),
]);
echo "OK: account_id={$account->id}\n";
