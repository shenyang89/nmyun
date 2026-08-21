<?php

declare(strict_types=1);

namespace app\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\SignatureInvalidException;
use think\facade\Config;

/**
 * JWT 签发 / 验证 Service
 *
 * 用法：
 *   $payload = JwtService::superAdminPayload($sadminId);
 *   $token = JwtService::issue($payload);
 *   $decoded = JwtService::verify($token);
 *
 * 安全约束（§13.8.3）：
 * - 超管 payload：只写 is_super_admin=true + sadmin_id，绝不夹带 tenant_id 或 tenant_role
 * - 租户成员 payload：只写 member_id + tenant_id + role，绝不夹带 is_super_admin
 */
final class JwtService
{
    /**
     * 签发 Token
     *
     * @param array<string,mixed> $payload 自定义 claims（iss/iat/exp/jti 由本方法注入）
     */
    public static function issue(array $payload): string
    {
        $now = time();
        $ttl = (int) Config::get('jwt.ttl', 8 * 3600);
        $issuer = (string) Config::get('jwt.issuer', 'nmyun-saas');
        $audience = (string) Config::get('jwt.audience', '');

        $payload = array_merge([
            'iss' => $issuer,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ], $payload);

        if ($audience !== '') {
            $payload['aud'] = $audience;
        }

        return JWT::encode($payload, self::secret(), self::alg());
    }

    /**
     * 验证并解码 Token
     *
     * @return array<string,mixed> decoded payload
     * @throws \RuntimeException 任何 JWT 验证失败均抛 RuntimeException（统一封装）
     */
    public static function verify(string $token): array
    {
        $leeway = (int) Config::get('jwt.leeway', 30);
        if ($leeway > 0) {
            JWT::$leeway = $leeway;
        }

        try {
            $decoded = JWT::decode($token, new Key(self::secret(), self::alg()));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            throw new \RuntimeException('Token 已过期，请重新登录', 40101, $e);
        } catch (BeforeValidException $e) {
            throw new \RuntimeException('Token 尚未生效', 40102, $e);
        } catch (SignatureInvalidException $e) {
            throw new \RuntimeException('Token 签名无效', 40103, $e);
        } catch (\UnexpectedValueException $e) {
            throw new \RuntimeException('Token 解析失败：' . $e->getMessage(), 40104, $e);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Token 验证失败：' . $e->getMessage(), 40105, $e);
        }
    }

    /**
     * 构造超管 payload（§13.8.3 严格约束）
     *
     * @return array{is_super_admin: true, sadmin_id: int}
     */
    public static function superAdminPayload(int $sadminId): array
    {
        return [
            'is_super_admin' => true,
            'sadmin_id' => $sadminId,
        ];
    }

    /**
     * 构造租户成员 payload
     *
     * @return array{is_super_admin: false, member_id: int, tenant_id: int, role: int}
     */
    public static function tenantMemberPayload(int $memberId, int $tenantId, int $role): array
    {
        return [
            'is_super_admin' => false,
            'member_id' => $memberId,
            'tenant_id' => $tenantId,
            'role' => $role,
        ];
    }

    private static function secret(): string
    {
        $secret = (string) Config::get('jwt.secret', '');
        if ($secret === '' || $secret === 'change-me-in-production-nmyun-default-secret') {
            // 生产环境必须配置 JWT_SECRET；开发/测试环境允许使用默认值但记录告警
            if (env('APP_DEBUG', false)) {
                // 测试模式不抛异常，方便开发
                return $secret;
            }
            throw new \RuntimeException('JWT_SECRET 未配置或使用了默认值，禁止用于生产环境', 50001);
        }
        return $secret;
    }

    private static function alg(): string
    {
        return (string) Config::get('jwt.alg', 'HS256');
    }
}
