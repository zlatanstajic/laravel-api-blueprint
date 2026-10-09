<?php

declare(strict_types=1);

namespace Tests\Unit;

use ErrorException;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Verify database configuration without connecting to a database.
 */
class DatabaseConfigurationTest extends TestCase
{
    /**
     * Preserve SSL options without using deprecated driver constants.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_ssl_options_load_without_deprecation(): void
    {
        new Application(dirname(__DIR__, 2));

        putenv('MYSQL_ATTR_SSL_CA=test-ca.pem');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $database = require dirname(__DIR__, 2).'/config/database.php';
        } finally {
            restore_error_handler();
        }

        $expected = extension_loaded('pdo_mysql') ? ['test-ca.pem'] : [];

        $this->assertSame($expected, array_values($database['connections']['mysql']['options']));
        $this->assertSame($expected, array_values($database['connections']['mariadb']['options']));
    }
}
