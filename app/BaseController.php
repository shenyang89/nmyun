<?php

declare (strict_types = 1);

namespace app;

use app\traits\ApiResponseTrait;
use app\traits\PaginatesTrait;
use think\App;
use think\exception\ValidateException;
use think\Validate;

/**
 * 控制器基础类 —— 智慧农贸云增强版
 *
 * 分层约束：
 * - 只做三件事：① 接收请求 + 参数校验  ② 调用 Service  ③ 调用 Trait 转 JSON 返回
 * - 禁止直接 use Model / Repository
 * - 禁止在此类里写业务逻辑（if-else 循环等）
 */
abstract class BaseController
{
    use ApiResponseTrait;
    use PaginatesTrait;

    /**
     * Request 实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;
        $this->initialize();
    }

    protected function initialize()
    {}

    /**
     * 验证数据（失败自动抛 ValidateException，被 ExceptionHandle 捕获转 JSON）
     *
     * @param  array                    $data
     * @param  string|array             $validate 验证器名或规则数组
     * @param  array                    $message  自定义提示
     * @param  bool                     $batch    是否批量校验
     * @throws ValidateException
     * @return true
     */
    protected function validate(
        array $data,
        string|array $validate,
        array $message = [],
        bool $batch = false
    ): bool {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                [$validate, $scene] = explode('.', $validate);
            }
            $class = str_contains($validate, '\\')
                ? $validate
                : $this->app->parseClass('validate', $validate);
            $v = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }
}
