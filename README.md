# CakephpHarTools

[![CI](https://github.com/jamisonbryant/cakephp-har-tools/actions/workflows/ci.yml/badge.svg)](https://github.com/jamisonbryant/cakephp-har-tools/actions/workflows/ci.yml)
[![Coverage](https://codecov.io/gh/jamisonbryant/cakephp-har-tools/branch/main/graph/badge.svg)](https://codecov.io/gh/jamisonbryant/cakephp-har-tools)
[![AI Assisted](https://img.shields.io/badge/AI-assisted-yes?style=flat-square)](#ai-disclosure)
[![Latest Stable Version](https://img.shields.io/packagist/v/jamisonbryant/cakephp-har-recorder?style=flat-square)](https://packagist.org/packages/jamisonbryant/cakephp-har-recorder)
[![Total Downloads](https://img.shields.io/packagist/dt/jamisonbryant/cakephp-har-recorder?style=flat-square)](https://packagist.org/packages/jamisonbryant/cakephp-har-recorder/stats)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

A CakePHP 5 toolkit for working with HAR (HTTP Archive) data, including recorder middleware, redaction utilities, and file writing helpers.

## Recording first, replaying experimental

This plugin focuses on capturing HAR output for debugging, support, and audit trails. A minimal, best-effort CLI replay command is included for experimentation and may not reproduce all browser semantics.

## Tools

- HAR Recorder: middleware that captures HTTP traffic as HAR files.

## Installation

You can install this plugin into your CakePHP application using composer:

```
composer require jamisonbryant/cakephp-har-recorder
```

Then load the plugin:

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

### Replay (experimental)

Replay a HAR file from the CLI:

```
bin/cake har:replay path/to/recording.har
```

By default, replay is a dry run. Pass `--send` to actually send requests.

Useful options:

```
bin/cake har:replay path/to/recording.har --limit 10 --base-url https://staging.example.com
bin/cake har:replay path/to/recording.har --methods GET,POST --limit 10
bin/cake har:replay path/to/recording.har --send --limit 10 --base-url https://staging.example.com
```

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

## Documentation

This README and `config/har_recorder.php` are the primary documentation for now.

## IDE compatibility improvements

No special IDE helpers are required. Standard PHP type hints and PSR interfaces provide autocomplete support.

## AI Disclosure

This project was developed with AI assistance and reviewed by the maintainer.

## Notes

- HAR output uses version 1.2.
- Body data is truncated at `maxBodySize` bytes.
- Output is written atomically (tmp file + rename).

## License

MIT
