<?php
namespace App\Services\AI;

class OpenAIProvider implements AIProviderInterface
{
    public function generate(string $systemPrompt, string $userPrompt): array
    {
        $key = trim((string) env('AI_API_KEY', ''));
        if ($key === '') throw new \RuntimeException('AI_API_KEY belum dikonfigurasi.');
        $model = trim((string) env('AI_MODEL', 'gpt-5.6-luna'));
        $payload = json_encode([
            'model' => $model,
            'input' => [
                ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $systemPrompt]]],
                ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $userPrompt]]],
            ],
        ], JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer '.$key,
        ];
        $start = microtime(true);
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $payload, 'timeout' => 60, 'ignore_errors' => true]]);
        $body = @file_get_contents('https://api.openai.com/v1/responses', false, $context);
        $latency = (int)((microtime(true) - $start) * 1000);
        if ($body === false) throw new \RuntimeException('AI provider tidak dapat dihubungi.');
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) throw new \RuntimeException('Respons AI tidak valid.');
        if (!empty($decoded['error'])) throw new \RuntimeException((string)($decoded['error']['message'] ?? 'AI request gagal.'));
        $text = (string)($decoded['output_text'] ?? '');
        if ($text === '' && !empty($decoded['output'])) {
            foreach ($decoded['output'] as $item) foreach (($item['content'] ?? []) as $content) if (($content['type'] ?? '') === 'output_text') $text .= (string)($content['text'] ?? '');
        }
        if (trim($text) === '') throw new \RuntimeException('AI tidak menghasilkan teks.');
        $usage = $decoded['usage'] ?? [];
        return ['text' => trim($text), 'provider' => 'openai', 'model' => $model, 'latency_ms' => $latency, 'usage' => $usage];
    }
}
