<?php

declare (strict_types = 1);

namespace app\traits;

/**
 * 分页参数解析 Trait
 * 在 Controller / Service 中 use 即可快速获取 page、page_size、offset
 */
trait PaginatesTrait
{
    /**
     * 解析分页参数
     * @param  int $defaultPageSize 默认每页数量
     * @param  int $maxPageSize     最大每页数量（防止一次拉太多）
     * @return array{page:int, page_size:int, offset:int, limit:int}
     */
    protected function resolvePagination(int $defaultPageSize = 15, int $maxPageSize = 100): array
    {
        $page = (int) request()->param('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $pageSize = (int) request()->param('page_size', $defaultPageSize);
        if ($pageSize < 1) {
            $pageSize = $defaultPageSize;
        }
        if ($pageSize > $maxPageSize) {
            $pageSize = $maxPageSize;
        }

        $offset = ($page - 1) * $pageSize;

        return [
            'page'      => $page,
            'page_size' => $pageSize,
            'offset'    => $offset,
            'limit'     => $pageSize,
        ];
    }
}
