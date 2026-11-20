<?php
declare(strict_types=1);

namespace Cleaners4u;

/**
 * Writes one line per event to <log_dir>/forms-YYYY-MM.log, falling back to PHP's error log.
 * Never log passwords; keep personal details out of log context where possible (use the lead id).
 */
final class Logger
{
    public function __construct(private readonly string $dir)
    {
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARN', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "[%s] %s %s%s\n",
            date('Y-m-d H:i:s T'),
            $level,
            str_replace(["\r", "\n"], ' ', $message),
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : ''
        );

        if ($this->dir !== '') {
            if (!is_dir($this->dir)) {
                @mkdir($this->dir, 0750, true);
            }
            if (is_dir($this->dir) && is_writable($this->dir)) {
                $file = rtrim($this->dir, '/\\') . '/forms-' . date('Y-m') . '.log';
                if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false) {
                    return;
                }
            }
        }
        error_log('[cleaners4u-forms] ' . trim($line));
    }
}
