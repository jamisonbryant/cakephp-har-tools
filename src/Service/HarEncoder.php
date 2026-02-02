<?php
declare(strict_types=1);

namespace JamisonBryant\CakephpHarRecorder\Service;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class HarEncoder
{
    private array $config;

    /**
     * @param array<string, mixed> $config Configuration options.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Encode request/response into a HAR structure.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request instance.
     * @param \Psr\Http\Message\ResponseInterface $response Response instance.
     * @param float $startedAt Start time (microtime true).
     * @param float $endedAt End time (microtime true).
     * @return array<string, mixed>
     */
    public function encode(
        ServerRequestInterface $request,
        ResponseInterface $response,
        float $startedAt,
        float $endedAt,
    ): array {
        $requestBody = $this->readStream($request->getBody());
        $responseBody = $this->readStream($response->getBody());

        $entries = [[
            'startedDateTime' => gmdate('c', (int)$startedAt),
            'time' => (int)round(($endedAt - $startedAt) * 1000),
            'request' => [
                'method' => $request->getMethod(),
                'url' => (string)$request->getUri(),
                'httpVersion' => $request->getProtocolVersion(),
                'cookies' => [],
                'headers' => $this->formatHeaders($request->getHeaders()),
                'queryString' => $this->formatQuery($request->getQueryParams()),
                'postData' => $this->formatPostData($request, $requestBody),
                'headersSize' => -1,
                'bodySize' => strlen($requestBody),
            ],
            'response' => [
                'status' => $response->getStatusCode(),
                'statusText' => $response->getReasonPhrase(),
                'httpVersion' => $response->getProtocolVersion(),
                'cookies' => [],
                'headers' => $this->formatHeaders($response->getHeaders()),
                'content' => [
                    'size' => strlen($responseBody),
                    'mimeType' => $response->getHeaderLine('Content-Type'),
                    'text' => $this->truncateBody($responseBody),
                ],
                'redirectURL' => $response->getHeaderLine('Location'),
                'headersSize' => -1,
                'bodySize' => strlen($responseBody),
            ],
            'cache' => (object)[],
            'timings' => [
                'send' => 0,
                'wait' => (int)round(($endedAt - $startedAt) * 1000),
                'receive' => 0,
            ],
        ]];

        return [
            'log' => [
                'version' => '1.2',
                'creator' => [
                    'name' => 'CakephpHarRecorder',
                    'version' => '0.1.0',
                ],
                'entries' => $entries,
            ],
        ];
    }

    /**
     * @param array<string, array<int, string>> $headers Headers map.
     * @return array<int, array<string, string>>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $formatted[] = [
                    'name' => $name,
                    'value' => $value,
                ];
            }
        }

        return $formatted;
    }

    /**
     * @param array<string, mixed> $query Query params.
     * @return array<int, array<string, string>>
     */
    private function formatQuery(array $query): array
    {
        $formatted = [];
        foreach ($query as $name => $value) {
            $formatted[] = [
                'name' => (string)$name,
                'value' => is_scalar($value) ? (string)$value : json_encode($value),
            ];
        }

        return $formatted;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request Request instance.
     * @param string $body Request body text.
     * @return array<string, string>
     */
    private function formatPostData(ServerRequestInterface $request, string $body): array
    {
        $mimeType = $request->getHeaderLine('Content-Type');

        return [
            'mimeType' => $mimeType,
            'text' => $this->truncateBody($body),
        ];
    }

    /**
     * @param string $body Body text.
     * @return string
     */
    private function truncateBody(string $body): string
    {
        $max = (int)($this->config['maxBodySize'] ?? 1048576);
        if ($max <= 0) {
            return '';
        }
        if (strlen($body) <= $max) {
            return $body;
        }

        return substr($body, 0, $max);
    }

    /**
     * @param \Psr\Http\Message\StreamInterface $stream Stream instance.
     * @return string
     */
    private function readStream(StreamInterface $stream): string
    {
        $contents = (string)$stream;
        if (is_object($stream) && method_exists($stream, 'isSeekable') && $stream->isSeekable()) {
            $stream->rewind();
        }

        return $contents;
    }
}
