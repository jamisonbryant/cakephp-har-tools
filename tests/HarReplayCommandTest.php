<?php
declare(strict_types=1);

namespace JamisonBryant\CakephpHarRecorder\Test;

use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Http\Client;
use JamisonBryant\CakephpHarRecorder\Command\HarReplayCommand;
use PHPUnit\Framework\TestCase;

class HarReplayCommandTest extends TestCase
{
    public function testExecuteDefaultsToDryRun(): void
    {
        $path = $this->writeHarFile(['GET', 'POST']);
        try {
            $command = new class extends HarReplayCommand {
                public array $sent = [];

                protected function sendRequest(Client $client, string $method, string $url, array $options): int
                {
                    $this->sent[] = [$method, $url, $options];

                    return 204;
                }
            };

            $args = $this->makeArguments($command, [], $path);
            $out = new StubConsoleOutput();
            $err = new StubConsoleOutput();
            $io = new ConsoleIo($out, $err);

            $result = $command->execute($args, $io);

            $this->assertSame(CommandInterface::CODE_SUCCESS, $result);
            $this->assertStringContainsString('DRY RUN GET https://example.com', $out->output());
            $this->assertStringContainsString('Replayed 1 request(s).', $out->output());
            $this->assertSame([], $command->sent);
            $this->assertSame('', $err->output());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testExecuteSendsWhenSendOptionProvided(): void
    {
        $path = $this->writeHarFile(['GET']);
        try {
            $command = new class extends HarReplayCommand {
                public array $sent = [];

                protected function sendRequest(Client $client, string $method, string $url, array $options): int
                {
                    $this->sent[] = [$method, $url, $options];

                    return 204;
                }
            };

            $args = $this->makeArguments($command, ['--send'], $path);
            $out = new StubConsoleOutput();
            $err = new StubConsoleOutput();
            $io = new ConsoleIo($out, $err);

            $result = $command->execute($args, $io);

            $this->assertSame(CommandInterface::CODE_SUCCESS, $result);
            $this->assertStringContainsString('GET https://example.com -> 204', $out->output());
            $this->assertCount(1, $command->sent);
            $this->assertSame('', $err->output());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testExecuteAllowsCustomMethods(): void
    {
        $path = $this->writeHarFile(['GET', 'POST']);
        try {
            $command = new class extends HarReplayCommand {
                public array $sent = [];

                protected function sendRequest(Client $client, string $method, string $url, array $options): int
                {
                    $this->sent[] = [$method, $url, $options];

                    return 200;
                }
            };

            $args = $this->makeArguments($command, ['--methods', 'get,post'], $path);
            $out = new StubConsoleOutput();
            $err = new StubConsoleOutput();
            $io = new ConsoleIo($out, $err);

            $result = $command->execute($args, $io);

            $this->assertSame(CommandInterface::CODE_SUCCESS, $result);
            $this->assertStringContainsString('DRY RUN GET https://example.com', $out->output());
            $this->assertStringContainsString('DRY RUN POST https://example.com', $out->output());
            $this->assertStringContainsString('Replayed 2 request(s).', $out->output());
            $this->assertSame([], $command->sent);
            $this->assertSame('', $err->output());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testExecuteRejectsSendAndDryRunTogether(): void
    {
        $path = $this->writeHarFile();
        try {
            $command = new HarReplayCommand();

            $args = $this->makeArguments($command, ['--send', '--dry-run'], $path);
            $out = new StubConsoleOutput();
            $err = new StubConsoleOutput();
            $io = new ConsoleIo($out, $err);

            $result = $command->execute($args, $io);

            $this->assertSame(CommandInterface::CODE_ERROR, $result);
            $this->assertStringContainsString('Options --send and --dry-run cannot be used together.', $err->output());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

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
            'https://staging.example.com/api',
        );

        $this->assertSame('https://staging.example.com/api/v1/users?active=1', $result);
    }

    private function makeArguments(HarReplayCommand $command, array $argv, string $path): Arguments
    {
        $command->setName('cake har:replay');
        $parser = $command->getOptionParser();
        [$options, $arguments] = $parser->parse(array_merge($argv, [$path]));

        return new Arguments($arguments, $options, $parser->argumentNames());
    }

    private function writeHarFile(array $methods = ['GET']): string
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'har');
        $entries = [];
        foreach ($methods as $method) {
            $entries[] = [
                'request' => [
                    'method' => $method,
                    'url' => 'https://example.com',
                    'headers' => [],
                ],
            ];
        }
        $payload = ['log' => ['entries' => $entries]];

        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        return $path;
    }
}
