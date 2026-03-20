<?php

declare(strict_types=1);

namespace Stolpersteine\Config;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;

class Logger
{
    private static ?MonologLogger $instance = null;

    public static function get(): MonologLogger
    {
        if (self::$instance === null) {
            self::$instance = self::createLogger();
        }

        return self::$instance;
    }

    private static function createLogger(): MonologLogger
    {
        $appConfig = Config::get('app');
        $level     = self::resolveLevel($appConfig);

        $logDir  = rtrim($appConfig['log_dir'] ?? __DIR__ . '/../../../storage/logs', '/');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/app.log';

        $handler = new RotatingFileHandler($logFile, 7, $level);
        $handler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            false,
            true    // ignoreEmptyContextAndExtra → keine leeren [] [] am Zeilenende
        ));

        $logger = new MonologLogger('stolpersteine');
        $logger->pushHandler($handler);

        return $logger;
    }

    private static function resolveLevel(array $appConfig): Level
    {
        if (isset($appConfig['log_level'])) {
            return Level::fromName(strtoupper($appConfig['log_level']));
        }

        // Fallback: debug=true → DEBUG, sonst WARNING
        return ($appConfig['debug'] ?? false) ? Level::Debug : Level::Warning;
    }

    // Nur für Tests: Singleton zurücksetzen
    public static function reset(): void
    {
        self::$instance = null;
    }
}
