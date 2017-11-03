<?php

namespace LiquidBox\Tests\Silex\Provider;

use Silex\WebTestCase;

/**
 * @author Jonathan-Paul Marois <jonathanpaul.marois@gmail.com>
 */
class PdoServiceProviderTest extends WebTestCase
{
    public function createApplication()
    {
        $app = new \Silex\Application();

        return $app;
    }

    public function parameterProvider()
    {
        return [
            '(string) SQLite in memory' => [
                ['pdo.dsn' => 'sqlite::memory:'],
                'sqlite',
            ],
            '(array) SQLite in memory' => [
                [
                    'pdo.driver' => 'pdo_sqlite',
                    'pdo.connection' => ':memory:',
                ],
                'sqlite',
            ],
            '(string) MySQL localhost and test database' => [
                [
                    'pdo.dsn' => 'mysql:host=localhost;dbname=test',
                    'pdo.username' => 'root',
                ],
                'mysql',
            ],
            '(string) MySQL localhost, test database, and options' => [
                [
                    'pdo.dsn' => 'mysql:host=localhost;dbname=test',
                    'pdo.username' => 'root',
                    'pdo.options' => [
                        \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                    ],
                ],
                'mysql',
            ],
            '(string) MySQL localhost, test database, and attributes' => [
                [
                    'pdo.dsn' => 'mysql:host=localhost;dbname=test',
                    'pdo.username' => 'root',
                    'pdo.attributes' => [
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    ],
                ],
                'mysql',
            ],
            '(array) MySQL localhost and test database' => [
                [
                    'pdo.connection' => [
                        'host' => 'localhost',
                        'dbname' => 'test',
                    ],
                    'pdo.username' => 'root',
                ],
                'mysql',
            ],
            '(array) MySQL localhost, test database, and UTF8 character set' => [
                [
                    'pdo.connection' => [
                        'host' => 'localhost',
                        'dbname' => 'test',
                        'charset' => 'UTF8',
                    ],
                    'pdo.username' => 'root',
                ],
                'mysql',
            ],
            '(array) Multiple MySQL connections' => [
                [
                    'pdo.dsn' => [
                        'read' => [
                            'connection' => [
                                'host' => 'localhost',
                                'dbname' => 'test',
                            ],
                            'username' => 'root',
                        ],
                        'write' => [
                            'connection' => [
                                'host' => 'localhost',
                                'dbname' => 'test',
                            ],
                            'username' => 'root',
                        ],
                    ],
                ],
                'mysql',
            ],
            '(string) PostgreSQL localhost and test database' => [
                [
                    'pdo.dsn' => 'pgsql:host=localhost;dbname=test',
                    'pdo.username' => 'liquidbox',
                    'pdo.password' => 'liquidbox',
                ],
                'pgsql',
            ],
            '(array) PostgreSQL driver, localhost, and test database' => [
                [
                    'pdo.driver' => 'pdo_pgsql',
                    'pdo.connection' => [
                        'host' => 'localhost',
                        'dbname' => 'test',
                    ],
                    'pdo.username' => 'liquidbox',
                    'pdo.password' => 'liquidbox',
                ],
                'pgsql',
            ],
            '(array) Multiple PostgreSQL connections' => [
                [
                    'pdo.driver' => 'pdo_pgsql',
                    'pdo.dsn' => [
                        'read' => [
                            'connection' => [
                                'host' => 'localhost',
                                'dbname' => 'test',
                            ],
                            'username' => 'liquidbox',
                            'password' => 'liquidbox',
                        ],
                        'write' => [
                            'connection' => [
                                'host' => 'localhost',
                                'dbname' => 'test',
                            ],
                            'username' => 'liquidbox',
                            'password' => 'liquidbox',
                        ],
                    ],
                ],
                'pgsql',
            ],
        ];
    }

    /**
     * @dataProvider parameterProvider
     */
    public function testRegister(array $properties, $expected)
    {
        $this->app->register(new \LiquidBox\Silex\Provider\PdoServiceProvider(), $properties);

        $this->assertInstanceOf('\\PDO', $this->app['pdo']);
        $this->assertEquals($expected, $this->app['pdo']->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    public function testRegisterAndPdoConnect()
    {
        $this->app->register(new \LiquidBox\Silex\Provider\PdoServiceProvider());

        $pdo = $this->app['pdo.connect']('mysql:host=localhost;dbname=test', 'root');

        $this->assertInstanceOf('\\PDO', $pdo);
        $this->assertEquals('mysql', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    public function testRegisterAsDoctrineService()
    {
        $this->app->register(new \LiquidBox\Silex\Provider\PdoServiceProvider('db', 'dbs'), [
            'pdo.dsn' => 'mysql:host=localhost;dbname=test',
            'pdo.username' => 'root',
        ]);

        $this->assertInstanceOf('\\PDO', $this->app['db']);
        $this->assertEquals('mysql', $this->app['db']->getAttribute(\PDO::ATTR_DRIVER_NAME));

        $this->assertInstanceOf('\\PDO', $this->app['dbs'][0]);
        $this->assertEquals('mysql', $this->app['dbs'][0]->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    public function testRegisterWithMultipleConnectionDrivers()
    {
        $this->app->register(new \LiquidBox\Silex\Provider\PdoServiceProvider(), [
            'pdo.dsn' => [
                'default_mysql' => [
                    'connection' => [
                        'host' => 'localhost',
                        'dbname' => 'test',
                        'charset' => 'utf8',
                    ],
                    'username' => 'root',
                ],
                'master' => [
                    'driver' => 'pdo_pgsql',
                    'connection' => [
                        'host' => 'localhost',
                        'dbname' => 'test',
                        'connect_timeout' => 2,
                    ],
                    'username' => 'liquidbox',
                    'password' => 'liquidbox',
                ],
                'swap' => [
                    'driver' => 'pdo_sqlite',
                    'connection' => [
                        ':memory:',
                    ],
                ],
            ],
            'pdo.attributes' => [
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
        ]);

        $this->assertInstanceOf('\\PDO', $this->app['pdo']);
        $this->assertInstanceOf('\\PDO', $this->app['pdo.connections']['default_mysql']);
        $this->assertEquals(
            $this->app['pdo']->getAttribute(\PDO::ATTR_CLIENT_VERSION),
            $this->app['pdo.connections']['default_mysql']->getAttribute(\PDO::ATTR_CLIENT_VERSION)
        );

        $this->assertInstanceOf('\\PDO', $this->app['pdo.connections']['master']);

        $this->assertInstanceOf('\\PDO', $this->app['pdo.connections']['swap']);
        $this->assertEquals('sqlite', $this->app['pdo.connections']['swap']->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }
}
