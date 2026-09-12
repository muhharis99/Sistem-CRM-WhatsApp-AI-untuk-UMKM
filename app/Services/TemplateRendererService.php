<?php
namespace App\Services;

class TemplateRendererService
{
    public function render(string $body, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($variables) {
            $key = $m[1];
            return array_key_exists($key, $variables) ? (string)$variables[$key] : $m[0];
        }, $body) ?? $body;
    }

    public function variables(string $body): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $body, $m);
        return array_values(array_unique($m[1] ?? []));
    }
}
