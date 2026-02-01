<?php

declare(strict_types=1);

namespace JamisonBryant\CakephpHarRecorder\Service;

class HarRedactor
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function apply(array $har): array
    {
        $redactions = $this->config['redactions'] ?? [];
        if ($redactions === []) {
            return $har;
        }

        foreach ($redactions as $path => $regex) {
            $tokens = $this->parsePath((string)$path);
            if ($tokens === []) {
                continue;
            }
            $this->applyPath($har, $tokens, (string)$regex);
        }

        return $har;
    }

    private function applyPath(mixed &$data, array $tokens, string $regex): void
    {
        if ($tokens === []) {
            if (is_string($data)) {
                $data = preg_replace($regex, '[REDACTED]', $data) ?? $data;
            }
            return;
        }

        $token = array_shift($tokens);
        if ($token['type'] === 'wildcard') {
            if (is_array($data)) {
                foreach ($data as &$item) {
                    $this->applyPath($item, $tokens, $regex);
                }
                unset($item);
            }
            return;
        }

        if (!is_array($data)) {
            return;
        }

        if ($token['type'] === 'index') {
            $index = $token['value'];
            if (array_key_exists($index, $data)) {
                $this->applyPath($data[$index], $tokens, $regex);
            }
            return;
        }

        $key = $token['value'];
        if (array_key_exists($key, $data)) {
            $this->applyPath($data[$key], $tokens, $regex);
        }
    }

    private function parsePath(string $path): array
    {
        $tokens = [];
        $length = strlen($path);
        $i = 0;

        while ($i < $length) {
            $char = $path[$i];
            if ($char === '.') {
                $i++;
                continue;
            }

            if ($char === '[') {
                $end = strpos($path, ']', $i);
                if ($end === false) {
                    break;
                }
                $content = substr($path, $i + 1, $end - $i - 1);
                $tokens[] = $this->parseBracketToken($content);
                $i = $end + 1;
                continue;
            }

            if (preg_match('/[A-Za-z0-9_-]/', $char) !== 1) {
                $i++;
                continue;
            }

            $start = $i;
            while ($i < $length && preg_match('/[A-Za-z0-9_-]/', $path[$i]) === 1) {
                $i++;
            }
            $tokens[] = [
                'type' => 'key',
                'value' => substr($path, $start, $i - $start),
            ];
        }

        return $tokens;
    }

    private function parseBracketToken(string $content): array
    {
        $content = trim($content);
        if ($content === '*') {
            return ['type' => 'wildcard', 'value' => '*'];
        }

        if ($content !== '' && $content[0] === '"' && str_ends_with($content, '"')) {
            $content = substr($content, 1, -1);
        }

        if ($content !== '' && $content[0] === '\'' && str_ends_with($content, '\'')) {
            $content = substr($content, 1, -1);
        }

        if (ctype_digit($content)) {
            return ['type' => 'index', 'value' => (int)$content];
        }

        return ['type' => 'key', 'value' => $content];
    }
}
