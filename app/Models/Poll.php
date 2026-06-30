<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    protected $fillable = [
        'conversation_id', 'creator_id', 'question', 'type',
        'is_anonymous', 'is_hide_results', 'max_choices',
        'expires_at', 'is_closed', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'is_hide_results' => 'boolean',
            'is_closed' => 'boolean',
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function options()
    {
        return $this->hasMany(PollOption::class)->orderBy('sort_order');
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function conversation()
    {
        return $this->belongsTo(UserConversation::class, 'conversation_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_closed', false)
            ->where(function ($q2) {
                $q2->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function getTotalVotesAttribute()
    {
        return $this->votes()->count();
    }

    /**
     * 获取投票结果（含排名）
     */
    public function getResults(): array
    {
        $options = $this->options()->get();
        $results = [];

        foreach ($options as $opt) {
            $voteCount = PollVote::where('option_id', $opt->id)->count();
            $avgRank = PollVote::where('option_id', $opt->id)->avg('rank');
            $results[] = [
                'id' => $opt->id,
                'label' => $opt->label,
                'votes' => $voteCount,
                'avg_rank' => $avgRank ? round($avgRank, 1) : null,
            ];
        }

        // 排序：ranked 类型按 avg_rank 升序，其他按 votes 降序
        if ($this->type === 'ranked') {
            usort($results, fn($a, $b) => ($a['avg_rank'] ?? 999) <=> ($b['avg_rank'] ?? 999));
        } else {
            usort($results, fn($a, $b) => $b['votes'] <=> $a['votes']);
        }

        return $results;
    }
}
