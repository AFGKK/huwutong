<?php

namespace App\Http\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait QueryBuilder
{
    /**
     * 从 Request 构建带筛选/排序/分页的查询
     *
     * 用法:
     *   ?filter[status]=active&filter[type]=standard&sort=-created_at&per_page=15
     *   ?search=keyword&search_fields=name,email
     *
     * @param Builder $query
     * @param Request $request
     * @return LengthAwarePaginator
     */
    public function buildPaginatedQuery(Builder $query, Request $request): LengthAwarePaginator
    {
        // 筛选
        if ($request->has('filter')) {
            foreach ($request->input('filter') as $field => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
        }

        // 搜索
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $searchFields = $request->input('search_fields', []);

            if (is_string($searchFields)) {
                $searchFields = explode(',', $searchFields);
            }

            if (! empty($searchFields)) {
                $query->where(function (Builder $q) use ($searchTerm, $searchFields) {
                    foreach ($searchFields as $field) {
                        $field = trim($field);
                        $q->orWhere($field, 'like', "%{$searchTerm}%");
                    }
                });
            }
        }

        // 日期范围筛选
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        // 排序
        if ($request->has('sort')) {
            $sorts = explode(',', $request->input('sort'));
            foreach ($sorts as $sort) {
                $sort = trim($sort);
                if (str_starts_with($sort, '-')) {
                    $query->orderBy(substr($sort, 1), 'desc');
                } else {
                    $query->orderBy($sort, 'asc');
                }
            }
        } else {
            $query->latest();
        }

        // 分页
        $perPage = min((int) $request->input('per_page', 15), 100);

        return $query->paginate($perPage);
    }
}
