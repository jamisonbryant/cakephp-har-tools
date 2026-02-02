<?php
declare(strict_types=1);

namespace JamisonBryant\CakephpHarRecorder\Service;

use Cake\Utility\Text;
use RuntimeException;

class HarWriter
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
     * Write HAR content to disk.
     *
     * @param array<string, mixed> $har HAR data.
     * @param string $filename Target filename.
     * @return void
     */
    public function write(array $har, string $filename): void
    {
        $directory = $this->config['outputDir'] ?? 'logs/har';
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Unable to create HAR directory: %s', $directory));
            }
        }

        $finalPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $tmpPath = $finalPath . '.' . Text::uuid() . '.tmp';

        $encoded = json_encode($har, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            throw new RuntimeException('Failed to encode HAR JSON.');
        }

        if (file_put_contents($tmpPath, $encoded) === false) {
            throw new RuntimeException(sprintf('Unable to write HAR file: %s', $tmpPath));
        }

        if (!rename($tmpPath, $finalPath)) {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
            throw new RuntimeException(sprintf('Unable to finalize HAR file: %s', $finalPath));
        }
    }
}
