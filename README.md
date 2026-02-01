# CakephpHarRecorder

A CakePHP 5 plugin that records HTTP requests and responses as HAR (HTTP Archive) files via middleware.

## Install

Add the plugin to your project:

```
composer require jamisonbryant/cakephp-har-recorder
```

Load the plugin in your application:

```php
// in src/Application.php
$this->addPlugin('JamisonBryant/CakephpHarRecorder');
```

Or load it via CLI:

```
bin/cake plugin load CakephpHarRecorder
```

## Usage

The plugin registers middleware automatically when enabled.

### Configure

The plugin ships with defaults in `config/har_recorder.php`. Override in your app's
`config/har_recorder.php` or `config/app.php`:

```php
'HarRecorder' => [
    'enabled' => true,
    'outputDir' => 'logs/har',
    'filenamePattern' => 'har-{date}-{uniqid}.har',
    'redactions' => [
        'log.entries[0].request.headers[*].value' => '/Bearer\s+[^\s]+/i',
        'log.entries[0].request.postData.text' => '/"password"\s*:\s*"[^"]+"/i',
    ],
    'maxBodySize' => 1048576,
],
```

### Redaction paths

Redaction keys use a JMESPath-like subset supporting:

- Dot-separated keys: `log.entries[0].request.postData.text`
- Array indices: `[0]`
- Wildcards: `[*]`
- Quoted keys: `['key-name']` or `["key-name"]`

Matches apply regex replacement with `[REDACTED]`.

## Notes

- HAR output uses version 1.2.
- Body data is truncated at `maxBodySize` bytes.
- Output is written atomically (tmp file + rename).

## License

MIT
