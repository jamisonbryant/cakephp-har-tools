<?php
declare(strict_types=1);

namespace JamisonBryant\CakephpHarRecorder\Test;

use JamisonBryant\CakephpHarRecorder\Command\HarReplayCommand;
use PHPUnit\Framework\TestCase;

class HarReplayCommandTest extends TestCase
{
    public function testBuildRequestOptionsUsesBodyTextAndFiltersHeaders(): void
    {
        $command = new class extends HarReplayCommand {
            public function buildOptions(array $request): array
            {
                return $this->buildRequestOptions($request);
            }
        };

        $options = $command->buildOptions([
            'headers' => [
                ['name' => 'Host', 'value' => 'example.test'],
                ['name' => 'Content-Length', 'value' => '10'],
                ['name' => 'X-Test', 'value' => 'ok'],
            ],
            'postData' => [
                'text' => '{"ok":true}',
            ],
        ]);

        $this->assertSame(['X-Test' => 'ok'], $options['headers'] ?? []);
        $this->assertSame('{"ok":true}', $options['body'] ?? null);
        $this->assertArrayNotHasKey('form', $options);
    }

    public function testApplyBaseUrlOverridesSchemeHostAndPrefixesPath(): void
    {
        $command = new class extends HarReplayCommand {
            public function applyBase(string $url, string $baseUrl): string
            {
                return $this->applyBaseUrl($url, $baseUrl);
            }
        };

        $result = $command->applyBase(
            'https://prod.example.com/v1/users?active=1',
            'https://staging.example.com/api'
        );

        $this->assertSame('https://staging.example.com/api/v1/users?active=1', $result);
    }
}
