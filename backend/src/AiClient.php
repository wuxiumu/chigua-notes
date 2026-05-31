<?php
declare(strict_types=1);
namespace App;

/**
 * 通义千问（DashScope）HTTP 客户端
 *
 * 使用 OpenAI 兼容接口，纯 curl 调用，零外部依赖。
 */
class AiClient
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?: (require __DIR__ . '/../config/ai.php');
    }

    /**
     * 健康检查：仅测试网络连通性和 API 可用性
     * 不调用模型，只 GET /models 端点验证 key 是否有效
     */
    public function healthCheck(): array
    {
        $config = $this->config;

        if (empty($config['api_key'])) {
            return [
                'status'      => 'error',
                'message'     => 'API Key 未配置',
                'hint'        => '请设置环境变量 DASHSCOPE_API_KEY 或填写 config/ai.php',
                'model'       => $config['model'],
                'api_url'     => $config['api_url'],
                'configured'  => false,
            ];
        }

        // 方法 1: 调用 /models 端点（最轻量，不消耗 token）
        $result = $this->httpGet('/models', $config['timeout']);

        if ($result['success']) {
            return [
                'status'      => 'ok',
                'message'     => 'API 连通',
                'model'       => $config['model'],
                'api_url'     => $config['api_url'],
                'fallback_model' => $config['fallback_model'],
                'enabled'     => $config['enabled'],
                'configured'  => true,
                'latency_ms'  => $result['latency_ms'],
                'models'      => $this->extractModelNames($result['data'] ?? []),
            ];
        }

        // /models 可能不支持，降级为聊天测试
        if ($result['http_code'] === 404) {
            return $this->healthCheckViaChat();
        }

        return [
            'status'      => 'error',
            'message'     => 'API 不可达',
            'http_code'   => $result['http_code'],
            'error'       => $result['error'] ?? '',
            'model'       => $config['model'],
            'configured'  => true,
            'latency_ms'  => $result['latency_ms'],
        ];
    }

    /**
     * 通过实际聊天请求验证 key 是否有效
     */
    private function healthCheckViaChat(): array
    {
        $config = $this->config;
        $start = microtime(true);

        $body = json_encode([
            'model'       => $config['model'],
            'messages'    => [['role' => 'user', 'content' => 'Hi']],
            'max_tokens'  => 5,
        ]);

        $result = $this->httpPost('/chat/completions', $body, $config['timeout']);
        $latency = round((microtime(true) - $start) * 1000);

        if ($result['success']) {
            return [
                'status'     => 'ok',
                'message'    => '模型调用成功',
                'model'      => $config['model'],
                'configured' => true,
                'latency_ms' => $latency,
                'usage'      => $result['data']['usage'] ?? null,
            ];
        }

        // 判断是 key 错误还是其他问题
        $error = strtolower($result['error'] ?? '');
        $isAuthError = strpos($error, 'authentication') !== false
                    || strpos($error, 'api-key') !== false
                    || strpos($error, 'invalid') !== false
                    || $result['http_code'] === 401
                    || $result['http_code'] === 403;

        return [
            'status'     => $isAuthError ? 'auth_error' : 'api_error',
            'message'    => $isAuthError ? 'API Key 无效或已过期' : 'API 调用失败',
            'http_code'  => $result['http_code'],
            'error'      => $result['error'] ?? '',
            'model'      => $config['model'],
            'configured' => true,
            'latency_ms' => $latency,
        ];
    }

    /**
     * 完整测试：发送一个简单 prompt，验证模型输出是否正常
     * 消耗少量 token，用于确认 AI 可以正常回答
     */
    public function testChat(string $prompt = '你好，请简短回复'): array
    {
        $config = $this->config;
        return $this->chat($prompt, 100);
    }

    /**
     * 通用聊天：支持自定义 max_tokens 和 timeout
     */
    public function chat(string $prompt, int $maxTokens = 4000, string $systemPrompt = '', ?int $timeout = null): array
    {
        $config = $this->config;

        if (empty($config['api_key'])) {
            return [
                'ok'      => false,
                'error'   => 'API Key 未配置',
                'model'   => $config['model'],
            ];
        }

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $body = json_encode([
            'model'       => $config['model'],
            'messages'    => $messages,
            'temperature' => $config['temperature'],
            'max_tokens'  => $maxTokens,
        ]);

        $actualTimeout = $timeout ?? $config['test_timeout'];
        $start = microtime(true);
        $result = $this->httpPost('/chat/completions', $body, $actualTimeout);
        $latency = round((microtime(true) - $start) * 1000);

        if (!$result['success']) {
            return [
                'ok'        => false,
                'error'     => $result['error'] ?? '请求失败',
                'http_code' => $result['http_code'],
                'model'     => $config['model'],
                'latency_ms' => $latency,
            ];
        }

        $message = $result['data']['choices'][0]['message']['content'] ?? '';

        return [
            'ok'        => true,
            'model'     => $config['model'],
            'prompt'    => $prompt,
            'reply'     => $message,
            'latency_ms' => $latency,
            'usage'     => $result['data']['usage'] ?? null,
            'finish_reason' => $result['data']['choices'][0]['finish_reason'] ?? '',
        ];
    }

    // ===================== 私有方法 =====================

    /**
     * GET 请求
     */
    private function httpGet(string $path, int $timeout): array
    {
        $url = rtrim($this->config['api_url'], '/') . $path;
        $start = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->config['api_key'],
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $latency = round((microtime(true) - $start) * 1000);

        if ($error) {
            return ['success' => false, 'error' => $error, 'http_code' => $httpCode, 'latency_ms' => $latency];
        }

        $data = json_decode($response, true);
        return [
            'success'    => $httpCode >= 200 && $httpCode < 300,
            'http_code'  => $httpCode,
            'data'       => $data,
            'error'      => $data['error']['message'] ?? null,
            'latency_ms' => $latency,
        ];
    }

    /**
     * POST 请求
     */
    private function httpPost(string $path, string $body, int $timeout): array
    {
        $url = rtrim($this->config['api_url'], '/') . $path;
        $start = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->config['api_key'],
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $latency = round((microtime(true) - $start) * 1000);

        if ($error) {
            return ['success' => false, 'error' => $error, 'http_code' => $httpCode, 'latency_ms' => $latency];
        }

        $data = json_decode($response, true);
        return [
            'success'    => $httpCode >= 200 && $httpCode < 300,
            'http_code'  => $httpCode,
            'data'       => $data,
            'error'      => $data['error']['message'] ?? null,
            'latency_ms' => $latency,
        ];
    }

    /**
     * 从 /models 响应中提取模型名称列表
     */
    private function extractModelNames(array $modelsData): ?array
    {
        $names = [];
        foreach ($modelsData as $m) {
            if (isset($m['id']) && stripos($m['id'], 'qwen') !== false) {
                $names[] = $m['id'];
            }
        }
        return $names ?: null;
    }
}
