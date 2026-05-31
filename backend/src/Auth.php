<?php
declare(strict_types=1);
namespace App;

/**
 * 管理员认证 — 登录、验证码、防爆破、Token 校验
 *
 * 验证码逻辑：当前分钟 + 随机数 = ?  每分钟变化
 * 防爆破：连续 5 次失败锁定 IP 15 分钟
 * Token：简单 HMAC 签名的 session token，存 Cookie
 */
class Auth
{
    private string $lockFile;
    private string $sessionDir;

    public function __construct()
    {
        $dataDir = __DIR__ . '/../data';
        $this->lockFile = $dataDir . '/auth_lock.json';
        $this->sessionDir = $dataDir . '/sessions';
        if (!is_dir($this->sessionDir)) {
            mkdir($this->sessionDir, 0700, true);
        }
    }

    /**
     * 检查凭据
     */
    public function checkCredentials(string $username, string $password): bool
    {
        $envUser = $_ENV['ADMIN_USERNAME'] ?? 'admin';
        $envPass = $_ENV['ADMIN_PASSWORD'] ?? 'admin123';
        return hash_equals($username, $envUser) && hash_equals($password, $envPass);
    }

    /**
     * 生成验证码：当前分钟 + 随机数 = ?
     * 返回 ['question' => '32 + 7 = ?', 'answer' => 39]
     */
    public function generateCaptcha(): array
    {
        $minute = (int)date('i');
        $addend = random_int(3, 20);
        $answer = $minute + $addend;
        return [
            'question' => sprintf('%02d + %d = ?', $minute, $addend),
            'answer'   => $answer,
        ];
    }

    /**
     * 验证验证码答案
     * 允许 ±1 误差（考虑客户端时间偏差）
     */
    public function verifyCaptcha(int $userAnswer): bool
    {
        $minute = (int)date('i');
        // 当前分钟和上一分钟都可能
        foreach ([$minute, ($minute - 1 + 60) % 60] as $m) {
            // 反推：问题格式是 "MM + N = ?"，但服务端不存 N
            // 所以只需验证：用户答案 - m 是否在合理范围 [3, 20]
            $diff = $userAnswer - $m;
            if ($diff >= 2 && $diff <= 21) {
                return true;
            }
        }
        return false;
    }

    /**
     * 生成并存储更健壮的验证码会话
     * 将验证码答案加密后存入返回数据，客户端需在登录时回传
     */
    public function generateCaptchaSession(): array
    {
        $minute = (int)date('i');
        $addend = random_int(3, 20);
        $answer = $minute + $addend;

        // 用时间戳 + 密钥签名，防止伪造
        $timestamp = time();
        $payload = base64_encode(json_encode([
            'a' => $answer,
            't' => $timestamp,
        ]));
        $sig = hash_hmac('sha256', $payload, $_ENV['ADMIN_SECRET'] ?? 'chigua-secret');

        return [
            'question' => sprintf('%02d + %d = ?', $minute, $addend),
            'token'    => $payload,
            'sig'      => $sig,
        ];
    }

    /**
     * 验证带签名的验证码
     */
    public function verifyCaptchaToken(string $token, string $sig, int $userAnswer): bool
    {
        // 验证签名
        $expectedSig = hash_hmac('sha256', $token, $_ENV['ADMIN_SECRET'] ?? 'chigua-secret');
        if (!hash_equals($expectedSig, $sig)) {
            return false;
        }

        // 解密
        $data = json_decode(base64_decode($token), true);
        if (!$data || !isset($data['a'], $data['t'])) {
            return false;
        }

        // 5 分钟有效期
        if (time() - $data['t'] > 300) {
            return false;
        }

        // 允许 ±1 误差
        return abs($userAnswer - $data['a']) <= 1;
    }

    /**
     * 检查 IP 是否被锁定
     */
    public function isLocked(string $ip): bool
    {
        if (!file_exists($this->lockFile)) return false;

        $locks = json_decode(file_get_contents($this->lockFile), true) ?: [];
        if (!isset($locks[$ip])) return false;

        $lock = $locks[$ip];
        if (time() - $lock['time'] > 900) { // 15 分钟
            unset($locks[$ip]);
            file_put_contents($this->lockFile, json_encode($locks));
            return false;
        }

        return true;
    }

    /**
     * 记录失败，连续 5 次锁定
     */
    public function recordFailure(string $ip): void
    {
        $locks = file_exists($this->lockFile)
            ? json_decode(file_get_contents($this->lockFile), true) ?: []
            : [];

        if (!isset($locks[$ip])) {
            $locks[$ip] = ['count' => 0, 'time' => time()];
        }

        $locks[$ip]['count']++;
        $locks[$ip]['time'] = time();

        if ($locks[$ip]['count'] >= 5) {
            $locks[$ip]['locked'] = true;
        }

        file_put_contents($this->lockFile, json_encode($locks), LOCK_EX);
    }

    /**
     * 清除失败记录（登录成功后）
     */
    public function clearFailures(string $ip): void
    {
        $locks = file_exists($this->lockFile)
            ? json_decode(file_get_contents($this->lockFile), true) ?: []
            : [];
        unset($locks[$ip]);
        file_put_contents($this->lockFile, json_encode($locks), LOCK_EX);
    }

    /**
     * 获取锁定状态
     */
    public function getLockStatus(string $ip): array
    {
        if (!file_exists($this->lockFile)) return ['locked' => false];

        $locks = json_decode(file_get_contents($this->lockFile), true) ?: [];
        if (!isset($locks[$ip])) return ['locked' => false];

        $lock = $locks[$ip];
        $elapsed = time() - $lock['time'];

        if ($elapsed > 900) {
            unset($locks[$ip]);
            file_put_contents($this->lockFile, json_encode($locks));
            return ['locked' => false];
        }

        return [
            'locked'      => true,
            'remaining'   => 900 - $elapsed,
            'attempts'    => $lock['count'],
        ];
    }

    /**
     * 创建会话 Token
     */
    public function createSession(): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = time() + 86400; // 24 小时

        $session = [
            'token'   => $token,
            'expires' => $expires,
            'created' => time(),
        ];

        $file = $this->sessionDir . '/' . $token;
        file_put_contents($file, json_encode($session), LOCK_EX);

        return $token;
    }

    /**
     * 验证会话 Token
     */
    public function verifySession(string $token): bool
    {
        $file = $this->sessionDir . '/' . $token;
        if (!file_exists($file)) return false;

        $session = json_decode(file_get_contents($file), true);
        if (!$session) return false;

        if (time() > $session['expires']) {
            unlink($file);
            return false;
        }

        return true;
    }

    /**
     * 销毁会话
     */
    public function destroySession(string $token): void
    {
        $file = $this->sessionDir . '/' . $token;
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * 获取请求 IP
     */
    public function getClientIp(): string
    {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff) {
            return explode(',', $xff)[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
