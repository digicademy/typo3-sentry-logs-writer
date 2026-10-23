# TYPO3 Sentry Logs Writer

[![Packagist Downloads](https://img.shields.io/packagist/dt/digicademy/typograph?logo=packagist)](https://packagist.org/packages/digicademy/typo3-sentry-logs-writer)
[![GitHub commit activity](https://img.shields.io/github/commit-activity/m/digicademy/typograph?logo=github)](https://github.com/digicademy/typo3-sentry-transaction-handler/commits/main/)

A TYPO3 log writer that sends log records to the Sentry Logs API, so that they
show up as structured logs in Sentry or GlitchTip.

It is intended as a companion to [networkteam/sentry-client][sentry-client],
which sets up the Sentry SDK and reports exceptions.

[sentry-client]: https://github.com/networkteam/sentry_client

## Why this exists

The Sentry Client for TYPO3 ships its own `SentryLogWriter`. That writer turns
every log record into an error event via `captureMessage()`, so the records end
up as **issues** only. It does not use the structured Logs API of the Sentry PHP SDK (`\Sentry\logger()`). That's what this small extension adds.

If needed, both writers can be used simultaneously:

| | `SentryLogWriter` (networkteam) | `SentryLogsWriter` (this extension) |
|---|---|---|
| Ends up in | Issues | Logs |
| Grouping and alerts | Yes | No, a searchable stream |
| Good for | Errors you want to act on | Context, tracing what a request did |

## Requirements

- PHP 8.3 – 8.5
- TYPO3 12.4, 13.4, or 14.3
- [`networkteam/sentry-client`][sentry-client] 6.x
- `sentry/sentry` 4.31 or newer
- For GlitchTip: version 6.1 or newer, with `GLITCHTIP_ENABLE_LOGS` not set to
  `False` (it is on by default)

## Installation

```bash
composer require digicademy/typo3-sentry-logs-writer
```

## Configuration

Add the writer it to `LOG.writerConfiguration` for every level it should handle, e.g., in `config/system/additional.php`:

```php
use Digicademy\Typo3SentryLogsWriter\Log\Writer\SentryLogsWriter;
use Psr\Log\LogLevel;

foreach ([
    LogLevel::INFO,
    LogLevel::NOTICE,
    LogLevel::WARNING,
    LogLevel::ERROR,
    LogLevel::CRITICAL,
    LogLevel::ALERT,
    LogLevel::EMERGENCY,
] as $level) {
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'][$level][SentryLogsWriter::class] = [];
}
```

TYPO3 can also configure writers per component (e.g.
`$GLOBALS['TYPO3_CONF_VARS']['LOG']['TYPO3']['CMS']['writerConfiguration']`),
which is the way to include or exclude specific parts of the system. See the
[TYPO3 logging documentation][typo3-logging].

[typo3-logging]: https://docs.typo3.org/permalink/t3coreapi:logging-configuration

The DSN and all SDK options come from networkteam/sentry-client. If no DSN is
configured, the writer does nothing.

### Switching it off

To stop sending without removing the writer, [return `null` from a `before_send_log` callback](https://docs.sentry.io/platforms/php/configuration/options/#before_send_log).

## How it works

The SDK buffers log items. The writer registers a shutdown function on first
use that flushes the buffer in one envelope. This covers frontend and backend
requests as well as CLI commands and scheduler tasks.

## Limitations

- **Sending blocks the PHP process.** The flush is a synchronous HTTP request
  to Sentry at the end of every request that wrote logs. Under PHP-FPM, the
  client waits for it unless the [response is finished earlier with
  `fastcgi_finish_request()`](https://www.php.net/manual/en/function.fastcgi-finish-request.php).
- **Records written by later shutdown functions are lost.** PHP runs shutdown
  functions in the order they were registered, and the flush is registered on
  the first log record.
- **Long-running CLI processes buffer in memory.** Set the [SDK option
  `log_flush_threshold`](https://docs.sentry.io/platforms/php/configuration/options/#log_flush_threshold) to send a batch every n records.

## License

GPL-2.0-or-later.
