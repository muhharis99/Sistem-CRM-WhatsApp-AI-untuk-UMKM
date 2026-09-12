<?php
namespace App\Services\AI;

interface AIProviderInterface
{
    public function generate(string $systemPrompt, string $userPrompt): array;
}
