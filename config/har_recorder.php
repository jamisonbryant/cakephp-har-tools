<?php

declare(strict_types=1);

return [
    'HarRecorder' => [
        'enabled' => true,
        'outputDir' => 'logs/har',
        'filenamePattern' => 'har-{date}-{uniqid}.har',
        'redactions' => [
            'log.entries[*].request.headers[*]' => '/^(authorization|cookie|x-api-key|proxy-authorization)$/i',
            'log.entries[*].response.headers[*]' => '/^(authorization|cookie|x-api-key|proxy-authorization)$/i',
            'log.entries[*].request.postData.text' => '/"(token|access_token|refresh_token|password|secret|api_key)"\s*:\s*"[^"]*"/i',
            'log.entries[*].response.content.text' => '/"(token|access_token|refresh_token|password|secret|api_key)"\s*:\s*"[^"]*"/i',
            // 'log.entries[0].request.headers[*].value' => '/Bearer\s+[^\s]+/i',
            // 'log.entries[0].request.postData.text' => '/"password"\s*:\s*"[^"]+"/i',
        ],
        'maxBodySize' => 1048576,
    ],
];
