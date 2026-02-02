<?php
declare(strict_types=1);

namespace JamisonBryant\CakephpHarRecorder\Middleware;

use Cake\Utility\Text;
use JamisonBryant\CakephpHarRecorder\Service\HarEncoder;
use JamisonBryant\CakephpHarRecorder\Service\HarRedactor;
use JamisonBryant\CakephpHarRecorder\Service\HarWriter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HarRecorderMiddleware implements MiddlewareInterface
{
    private array $config;
    private HarWriter $writer;
    private HarEncoder $encoder;
    private HarRedactor $redactor;

    /**
     * @param array<string, mixed> $config Configuration options.
     * @param \JamisonBryant\CakephpHarRecorder\Service\HarWriter|null $writer HAR writer.
     * @param \JamisonBryant\CakephpHarRecorder\Service\HarEncoder|null $encoder HAR encoder.
     * @param \JamisonBryant\CakephpHarRecorder\Service\HarRedactor|null $redactor HAR redactor.
     */
    public function __construct(
        array $config = [],
        ?HarWriter $writer = null,
        ?HarEncoder $encoder = null,
        ?HarRedactor $redactor = null,
    ) {
        $this->config = $config;
        $this->writer = $writer ?? new HarWriter($config);
        $this->encoder = $encoder ?? new HarEncoder($config);
        $this->redactor = $redactor ?? new HarRedactor($config);
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request Request instance.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Request handler.
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startedAt = microtime(true);
        $response = $handler->handle($request);
        $endedAt = microtime(true);

        $har = $this->encoder->encode($request, $response, $startedAt, $endedAt);
        $har = $this->redactor->apply($har);

        $filename = $this->buildFilename();
        $this->writer->write($har, $filename);

        return $response;
    }

    /**
     * Build the HAR filename from config and placeholders.
     *
     * @return string
     */
    private function buildFilename(): string
    {
        $pattern = $this->config['filenamePattern'] ?? 'har-{date}-{uniqid}.har';
        $replacements = [
            '{date}' => date('Ymd-His'),
            '{uniqid}' => uniqid('', true),
            '{random}' => Text::uuid(),
        ];

        return strtr($pattern, $replacements);
    }
}
