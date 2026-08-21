<?php

declare (strict_types = 1);

namespace app\traits;

use think\Response;
use think\response\Json;

/**
 * API 统一响应 Trait
 * 响应格式：
 * {
 *   "code": 0,              // 0=成功，非0=业务错误码
 *   "message": "success",   // 提示信息
 *   "data": {},             // 业务数据
 *   "timestamp": 1234567890 // 服务器时间戳
 * }
 */
trait ApiResponseTrait
{
    /**
     * 成功响应
     * @param mixed  $data    数据
     * @param string $message 提示语
     * @param int    $code    业务码（0=成功）
     */
    protected function success(mixed $data = [], string $message = 'success', int $code = 0): Json
    {
        return $this->renderJson($code, $message, $data);
    }

    /**
     * 失败响应（业务错误）
     * @param string $message 错误提示
     * @param int    $code    错误码（必须 > 0）
     * @param mixed  $data    附加数据
     */
    protected function error(string $message = 'error', int $code = 1, mixed $data = []): Json
    {
        if ($code <= 0) {
            $code = 1;
        }
        return $this->renderJson($code, $message, $data);
    }

    /**
     * 分页响应（附带 meta 分页信息）
     */
    protected function paginated(
        mixed $list,
        int $total,
        int $page = 1,
        int $pageSize = 15,
        string $message = 'success'
    ): Json {
        $totalPage = $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;

        return $this->renderJson(0, $message, [
            'list' => $list,
            'meta' => [
                'page'        => $page,
                'page_size'   => $pageSize,
                'total'       => $total,
                'total_page'  => $totalPage,
                'has_more'    => $page < $totalPage,
            ],
        ]);
    }

    /**
     * 无数据的 OK 响应（新增/修改/删除操作完成）
     */
    protected function ok(string $message = '操作成功'): Json
    {
        return $this->success([], $message);
    }

    /**
     * 构造最终 JSON 响应
     */
    protected function renderJson(int $code, string $message, mixed $data): Json
    {
        $payload = [
            'code'      => $code,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => time(),
        ];

        $httpStatus = $code === 0 ? 200 : 200; // 业务错误也返回 200（HTTP 层不报错）
        return json($payload, $httpStatus, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }
}
