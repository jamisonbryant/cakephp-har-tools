<?php

declare(strict_types=1);

return [
    'HarRecorder' => [
        'enabled' => true,
        'outputDir' => 'logs/har',
        'filenamePattern' => 'har-{date}-{uniqid}.har',
        'redactions' => [
            // 'log.entries[0].request.headers[*].value' => '/Bearer\s+[^\s]+/i',
            // 'log.entries[0].request.postData.text' => '/"password"\s*:\s*"[^"]+"/i',
        ],
        'maxBodySize' => 1048576,
    ],
];
