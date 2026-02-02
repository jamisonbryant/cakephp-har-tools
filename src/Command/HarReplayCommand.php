<?php
declare(strict_types=1);

namespace JamisonBryant\CakephpHarRecorder\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Http\Client;

class HarReplayCommand extends Command
{
    /**
     * @param \Cake\Console\ConsoleOptionParser $parser The option parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Replay requests from a HAR file.')
            ->addArgument('har', [
                'help' => 'Path to a HAR file to replay.',
                'required' => true,
            ])
            ->addOption('base-url', [
                'help' => 'Override scheme/host (and optional path) for all requests.',
            ])
            ->addOption('send', [
                'help' => 'Send requests (default is dry run).',
                'boolean' => true,
            ])
            ->addOption('dry-run', [
                'help' => 'Print the requests without sending them (default).',
                'boolean' => true,
            ])
            ->addOption('methods', [
                'help' => 'Comma-separated list of HTTP methods to replay.',
                'short' => 'm',
                'default' => 'GET',
            ])
            ->addOption('limit', [
                'help' => 'Limit the number of entries to replay (0 = all).',
                'default' => '0',
            ])
            ->addOption('sleep', [
                'help' => 'Milliseconds to wait between requests.',
                'default' => '0',
            ])
            ->addOption('timeout', [
                'help' => 'Request timeout in seconds.',
                'default' => '30',
            ]);

        return $parser;
    }

    /**
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $path = (string)$args->getArgument('har');
        if ($path === '' || !is_file($path)) {
            $io->err(sprintf('HAR file not found: %s', $path === '' ? '(empty)' : $path));

            return static::CODE_ERROR;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            $io->err(sprintf('Failed to read HAR file: %s', $path));

            return static::CODE_ERROR;
        }

        try {
            $har = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $io->err(sprintf('Invalid HAR JSON: %s', $exception->getMessage()));

            return static::CODE_ERROR;
        }

        $entries = $har['log']['entries'] ?? null;
        if (!is_array($entries) || $entries === []) {
            $io->err('No HAR entries found to replay.');

            return static::CODE_ERROR;
        }

        $limit = (int)$args->getOption('limit');
        $sleepMs = (int)$args->getOption('sleep');
        $timeout = (int)$args->getOption('timeout');
        $baseUrl = $args->getOption('base-url');
        $send = (bool)$args->getOption('send');
        $dryRun = !$send;
        $allowedMethods = $this->parseAllowedMethods((string)$args->getOption('methods'));

        if ($send && (bool)$args->getOption('dry-run')) {
            $io->err('Options --send and --dry-run cannot be used together.');

            return static::CODE_ERROR;
        }
        if ($allowedMethods === []) {
            $io->err('Option --methods must include at least one HTTP method.');

            return static::CODE_ERROR;
        }

        $client = new Client(['timeout' => $timeout]);
        $replayed = 0;

        foreach ($entries as $entry) {
            if ($limit > 0 && $replayed >= $limit) {
                break;
            }

            $request = $entry['request'] ?? null;
            if (!is_array($request)) {
                continue;
            }

            $method = strtoupper((string)($request['method'] ?? 'GET'));
            if (!isset($allowedMethods[$method])) {
                continue;
            }
            $url = (string)($request['url'] ?? '');
            if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
                $io->err('Skipping entry without a valid http(s) URL.');
                continue;
            }

            if (is_string($baseUrl) && $baseUrl !== '') {
                $url = $this->applyBaseUrl($url, $baseUrl);
            }

            $options = $this->buildRequestOptions($request);

            if ($dryRun) {
                $io->out(sprintf('DRY RUN %s %s', $method, $url));
            } else {
                try {
                    $statusCode = $this->sendRequest($client, $method, $url, $options);
                    $io->out(sprintf('%s %s -> %d', $method, $url, $statusCode));
                } catch (\Throwable $exception) {
                    $io->err(sprintf('Failed %s %s: %s', $method, $url, $exception->getMessage()));
                }
            }

            $replayed++;
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $io->out(sprintf('Replayed %d request(s).', $replayed));

        return static::CODE_SUCCESS;
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    protected function buildRequestOptions(array $request): array
    {
        $headers = $this->normalizeHeaders($request['headers'] ?? []);
        $options = ['headers' => $headers];

        $postData = $request['postData'] ?? null;
        if (!is_array($postData)) {
            return $options;
        }

        $text = $postData['text'] ?? null;
        if (is_string($text) && $text !== '') {
            $options['body'] = $text;

            return $options;
        }

        $params = $postData['params'] ?? null;
        if (is_array($params)) {
            $form = [];
            foreach ($params as $param) {
                if (!is_array($param)) {
                    continue;
                }
                $name = $param['name'] ?? null;
                if (!is_string($name) || $name === '') {
                    continue;
                }
                $form[$name] = $param['value'] ?? '';
            }
            if ($form !== []) {
                $options['form'] = $form;
            }
        }

        return $options;
    }

    /**
     * @param \Cake\Http\Client $client
     * @param string $method
     * @param string $url
     * @param array<string, mixed> $options
     * @return int
     */
    protected function sendRequest(Client $client, string $method, string $url, array $options): int
    {
        $response = $client->request($method, $url, $options);

        return $response->getStatusCode();
    }

    /**
     * @param string $methods
     * @return array<string, true>
     */
    protected function parseAllowedMethods(string $methods): array
    {
        $parsed = [];
        foreach (explode(',', $methods) as $method) {
            $method = strtoupper(trim($method));
            if ($method === '') {
                continue;
            }
            $parsed[$method] = true;
        }

        return $parsed;
    }

    /**
     * @param array<int, array<string, mixed>> $headers
     * @return array<string, string>
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            if (!is_array($header)) {
                continue;
            }
            $name = $header['name'] ?? null;
            $value = $header['value'] ?? null;
            if (!is_string($name) || $name === '' || !is_string($value)) {
                continue;
            }

            $lower = strtolower($name);
            if ($lower === 'host' || $lower === 'content-length') {
                continue;
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    protected function applyBaseUrl(string $url, string $baseUrl): string
    {
        $base = parse_url($baseUrl);
        $target = parse_url($url);
        if ($base === false || $target === false) {
            return $url;
        }

        $scheme = $base['scheme'] ?? null;
        $host = $base['host'] ?? null;
        if (!is_string($scheme) || $scheme === '' || !is_string($host) || $host === '') {
            return $url;
        }

        $port = $base['port'] ?? null;
        $basePath = rtrim((string)($base['path'] ?? ''), '/');
        $path = (string)($target['path'] ?? '/');
        if ($basePath !== '') {
            $path = $basePath . '/' . ltrim($path, '/');
        }

        $rebuilt = $scheme . '://' . $host;
        if (is_int($port)) {
            $rebuilt .= ':' . $port;
        }
        $rebuilt .= $path;

        if (isset($target['query']) && $target['query'] !== '') {
            $rebuilt .= '?' . $target['query'];
        }
        if (isset($target['fragment']) && $target['fragment'] !== '') {
            $rebuilt .= '#' . $target['fragment'];
        }

        return $rebuilt;
    }
}
