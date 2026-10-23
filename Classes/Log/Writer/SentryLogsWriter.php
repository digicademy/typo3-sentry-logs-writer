<?php

declare(strict_types=1);

namespace Digicademy\Typo3SentryLogsWriter\Log\Writer;

use Networkteam\SentryClient\Service\SentryService;
use Psr\Log\LogLevel;

use function Sentry\logger;

use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Log\Writer\{
    AbstractWriter,
    WriterInterface
};

/**
 * Sends TYPO3 log records to the Sentry Logs API.
 *
 * Unlike networkteam/sentry-client's SentryLogWriter, which turns every log
 * record into an error event (an "issue"), this writer emits structured log
 * items. Sentry and GlitchTip show these in their "Logs" view, not as issues.
 *
 * The Sentry SDK is initialised by networkteam/sentry-client from its DSN and
 * `EXTCONF.sentry_client.options` configuration. Without a DSN the writer
 * does nothing.
 *
 * Register the writer per log level, e.g. in config/system/additional.php:
 *
 *     $GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'][LogLevel::INFO][SentryLogsWriter::class] = [];
 */
final class SentryLogsWriter extends AbstractWriter
{
    /**
     * Whether the end-of-request flush has been registered already. Static,
     * because TYPO3 creates one writer instance per logger and level.
     */
    private static bool $flushRegistered = false;

    /**
     * @param array<string, mixed> $options writer options from LOG.writerConfiguration; none are defined
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        SentryService::inititalize();
    }

    /**
     * Buffers the record in the Sentry SDK's logs aggregator. The buffer is
     * sent to Sentry in one envelope when PHP shuts down.
     *
     * The SDK attaches the trace ID of the currently active span, so records
     * written during a request instrumented by sentry_transaction_handler are
     * linked to that request's transaction.
     */
    public function writeLog(LogRecord $record): WriterInterface
    {
        if (SentryService::isEnabled() !== true) {
            return $this;
        }

        self::registerFlush();

        // The SDK only runs the message through vsprintf() when values are
        // passed. None are passed here, so percent signs need no escaping.
        $message = $this->interpolate((string)$record->getMessage(), $record->getData());
        $attributes = $this->buildAttributes($record);
        $logs = logger();

        // Sentry has no notice, critical, alert or emergency levels. The
        // original PSR-3 level is kept in the `typo3.level` attribute.
        match ($record->getLevel()) {
            LogLevel::DEBUG => $logs->debug($message, [], $attributes),
            LogLevel::INFO, LogLevel::NOTICE => $logs->info($message, [], $attributes),
            LogLevel::WARNING => $logs->warn($message, [], $attributes),
            LogLevel::ERROR => $logs->error($message, [], $attributes),
            LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY => $logs->fatal($message, [], $attributes),
            default => $logs->info($message, [], $attributes),
        };

        return $this;
    }

    /**
     * Builds the searchable attributes of a log item from a TYPO3 log record.
     *
     * Throwables in the record data are mapped to the OpenTelemetry
     * `exception.*` attributes, because the SDK would otherwise serialise
     * them to an empty JSON object. All other data is added below
     * `typo3.data.`; the SDK flattens nested associative arrays into dotted
     * keys and drops values it cannot serialise.
     *
     * @return array<string, mixed>
     */
    private function buildAttributes(LogRecord $record): array
    {
        $attributes = [
            'typo3.component' => $record->getComponent(),
            'typo3.level' => $record->getLevel(),
            'typo3.request_id' => $record->getRequestId(),
        ];

        $data = [];
        foreach ($record->getData() as $key => $value) {
            if ($value instanceof \Throwable) {
                $attributes['exception.type'] = $value::class;
                $attributes['exception.message'] = $value->getMessage();
                $attributes['exception.stacktrace'] = $value->getTraceAsString();
                continue;
            }
            $data[$key] = $value;
        }

        if ($data !== []) {
            $attributes['typo3.data'] = $data;
        }

        return $attributes;
    }

    /**
     * Makes sure the buffered log items are sent when PHP shuts down.
     *
     * The Sentry PHP SDK does not flush its logs buffer on its own. A
     * shutdown function covers frontend and backend requests as well as CLI
     * commands and scheduler tasks, and also runs after fatal errors.
     */
    private static function registerFlush(): void
    {
        if (self::$flushRegistered) {
            return;
        }

        register_shutdown_function(static function (): void {
            logger()->flush();
        });
        self::$flushRegistered = true;
    }
}
