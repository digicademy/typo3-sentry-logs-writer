# TYPO3 Sentry Logs Writer

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-23

### Added

- TYPO3 log writer that sends log records to the Sentry Logs API, with TYPO3 component, level, request ID and record data as attributes
- Mapping of `Throwable`s in record data to OpenTelemetry `exception.*` attributes
- Flush of buffered log items on PHP shutdown, for web requests and CLI alike
- Compatibility with TYPO3 12.4, 13, and 14
