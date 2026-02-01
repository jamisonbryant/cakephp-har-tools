<?php

declare(strict_types=1);

namespace JamisonBryant\CakephpHarRecorder\Test;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use JamisonBryant\CakephpHarRecorder\Middleware\HarRecorderMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HarRecorderMiddlewareTest extends TestCase
{
    private string $outputDir = '';

    protected function tearDown(): void
    {
        if ($this->outputDir !== '' && is_dir($this->outputDir)) {
            $this->deleteDirectory($this->outputDir);
        }
        $this->outputDir = '';
    }

    public function testMiddlewareWritesHarAndRedacts(): void
    {
        $this->outputDir = sys_get_temp_dir() . DS . 'cakephp-har-recorder-' . uniqid();
        $config = [
            'outputDir' => $this->outputDir,
            'filenamePattern' => 'test-{uniqid}.har',
            'redactions' => [
                'log.entries[0].request.headers[*].value' => '/Bearer\s+[^\s]+/i',
            ],
            'maxBodySize' => 1024,
        ];

        $middleware = new HarRecorderMiddleware($config);

        $request = new ServerRequest([
            'environment' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/test',
                'HTTP_AUTHORIZATION' => 'Bearer secret-token',
            ],
            'query' => [
                'q' => '1',
            ],
        ]);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = new Response();
                return $response->withStringBody('ok');
            }
        };

        $middleware->process($request, $handler);

        $files = glob($this->outputDir . DS . 'test-*.har');
        $this->assertNotEmpty($files, 'Expected at least one HAR file to be written.');

        $har = json_decode((string)file_get_contents($files[0]), true);
        $this->assertIsArray($har);

        $headers = $har['log']['entries'][0]['request']['headers'] ?? [];
        $values = array_column($headers, 'value');
        $this->assertContains('[REDACTED]', $values);
    }

    public function testMaxBodySizeTruncatesResponse(): void
    {
        $this->outputDir = sys_get_temp_dir() . DS . 'cakephp-har-recorder-' . uniqid();
        $config = [
            'outputDir' => $this->outputDir,
            'filenamePattern' => 'test-{uniqid}.har',
            'maxBodySize' => 5,
        ];

        $middleware = new HarRecorderMiddleware($config);

        $request = new ServerRequest([
            'environment' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/test',
            ],
        ]);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = new Response();
                return $response->withStringBody('0123456789');
            }
        };

        $middleware->process($request, $handler);

        $files = glob($this->outputDir . DS . 'test-*.har');
        $this->assertNotEmpty($files, 'Expected at least one HAR file to be written.');

        $har = json_decode((string)file_get_contents($files[0]), true);
        $this->assertIsArray($har);

        $text = $har['log']['entries'][0]['response']['content']['text'] ?? '';
        $this->assertSame('01234', $text);
    }

    private function deleteDirectory(string $path): void
    {
        $iterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
                continue;
            }
            @unlink($file->getPathname());
        }
        @rmdir($path);
    }
}
