<?php

declare(strict_types=1);

namespace app;

use app\exceptions\BusinessException;
use app\exceptions\NotFoundException;
use app\exceptions\UnauthorizedException;
use app\exceptions\ValidationException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 * 所有异常统一输出：{ code, message, data, timestamp } 的 JSON 格式
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
        // 业务异常不记日志（业务逻辑问题，非运行时 bug）
        BusinessException::class,
        NotFoundException::class,
        UnauthorizedException::class,
        ValidationException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     */
    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    /**
     * 将异常渲染为统一 JSON 响应
     */
    public function render($request, Throwable $e): Response
    {
        // ---- 1. 自定义业务异常 ----
        if ($e instanceof BusinessException) {
            return $this->toJson(
                $e->getCode() ?: 1,
                $e->getMessage(),
                $e->getExtra() ?? [],
                $this->resolveHttpStatus($e)
            );
        }

        // ---- 2. ThinkPHP 原生 ValidateException（参数校验失败）----
        if ($e instanceof ValidateException) {
            return $this->toJson(40001, $e->getMessage(), [], 200);
        }

        // ---- 3. ThinkPHP Model/Data NotFound ----
        if ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            return $this->toJson(40401, '资源不存在', [], 404);
        }

        // ---- 4. HTTP 异常（404/405 等路由层）----
        if ($e instanceof HttpException) {
            return $this->toJson(
                $e->getStatusCode() * 100 + 0, // 404 -> 40400
                $e->getMessage() ?: 'HTTP 异常',
                [],
                $e->getStatusCode()
            );
        }

        // ---- 5. HttpResponseException（原样透传，响应重定向之类）----
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        // ---- 6. 其他未捕获异常：运行时 BUG ----
        $debug = env('APP_DEBUG', false);
        $message = $debug
            ? sprintf('[BUG] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine())
            : '服务器内部错误，请稍后重试';

        return $this->toJson(50000, $message, $debug ? [
            'trace' => collect($e->getTrace())->slice(0, 20)->map(fn ($t) => [
                'file' => $t['file'] ?? '',
                'line' => $t['line'] ?? 0,
                'call' => ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? ''),
            ])->values()->all(),
        ] : [], 500);
    }

    /**
     * 按异常类型决定 HTTP 状态码
     */
    protected function resolveHttpStatus(BusinessException $e): int
    {
        return match (true) {
            $e instanceof UnauthorizedException && $e->getCode() >= 40300 => 403,
            $e instanceof UnauthorizedException => 401,
            $e instanceof NotFoundException => 404,
            $e instanceof ValidationException => 200,
            default => 200,
        };
    }

    /**
     * 统一 JSON 输出
     */
    protected function toJson(int $code, string $message, mixed $data, int $httpStatus): Response
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ], $httpStatus, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }
}
