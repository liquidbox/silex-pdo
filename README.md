[![GitHub release](https://img.shields.io/github/release/liquidbox/silex-pdo.svg)](https://github.com/liquidbox/silex-pdo/releases)
[![license](https://img.shields.io/github/license/liquidbox/silex-pdo.svg)](LICENSE)
[![Build Status](https://travis-ci.org/liquidbox/silex-pdo.svg?branch=master)](https://travis-ci.org/liquidbox/silex-pdo)
[![Code Coverage](https://scrutinizer-ci.com/g/liquidbox/silex-pdo/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/liquidbox/silex-pdo/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/liquidbox/silex-pdo/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/liquidbox/silex-pdo/?branch=master)
[![Packagist](https://img.shields.io/packagist/dt/liquidbox/silex-pdo.svg)](https://packagist.org/packages/liquidbox/silex-pdo)

You are reading the documentation for Silex 1.x.

# PHP Data Objects

The <em>PdoServiceProvider</em> provides integration with the [PHP Data Objects (PDO)](http://php.net/manual/en/intro.pdo.php) extension.

## Parameters

* <strong>pdo.dsn</strong> (optional): The Data Source Name, or DSN, contains the information required to connect to the database.
* <strong>pdo.driver</strong> (optional): The [PDO driver](http://php.net/manual/pdo.drivers.php) implementation to use.
* <strong>pdo.connection</strong> (optional): A collection of driver-specific connection parameters for specifying the connection string.
* <strong>pdo.username</strong> (optional): The user name for the DSN string.
* <strong>pdo.password</strong> (optional): The password for the DSN string.
* <strong>pdo.options</strong> (optional): A collection of driver-specific connection options.
* <strong>pdo.attributes</strong> (optional): A collection of attributes to set.

The parameters <code>pdo.driver</code> and <code>pdo.connection</code> are ignored if <code>pdo.dsn</code> is set.

## Services

* <strong>pdo</strong>: The [<code>PDO</code>](http://php.net/manual/en/class.pdo.php) connection instance. The main way of interacting with PDO.
* <strong>pdo.connections</strong>: The collection of PDO connection instances. See section on [using multiple databases](#using-multiple-databases) for details.
* <strong>pdo.connect</strong>: Factory for <code>PDO</code> connection instances.

## Registering

<strong>Example #1 Connecting to MySQL</strong>

```php
$app->register(new \LiquidBox\Silex\Provider\PdoServiceProvider(), array(
    'pdo.dsn' => 'mysql:host=localhost;dbname=test',
    'pdo.username' => $user,
    'pdo.password' => $passwd,
));

// or

$app->register(new \LiquidBox\Silex\Provider\PdoServiceProvider(), array(
    'pdo.connection' => array(
        'host'   => 'localhost',
        'dbname' => 'test'
    ),
    'pdo.username' => $user,
    'pdo.password' => $passwd,
));

```

The two registered connections are equivalent.

<strong>Example #2 Connecting to SQLite</strong>

```php
$app->register(new \LiquidBox\Silex\Provider\PdoServiceProvider(), array(
    'pdo.dsn' => 'sqlite::memory:',
));

// or

$app->register(new \LiquidBox\Silex\Provider\PdoServiceProvider(), array(
    'pdo.driver' => 'sqlite',
    'pdo.connection' => ':memory:',
));
```

<strong>Example #3 Using Doctrine service names</strong>

```php
$app->register(new \LiquidBox\Silex\Provider\PdoServiceProvider('db', 'dbs'), array(
    'pdo.driver' => 'pdo_pgsql',
    'pdo.connection' => array(
        'host'   => 'localhost',
        'dbname' => 'test',
    ),
    'pdo.username' => $user,
    'pdo.password' => $passwd,
));
```

The services <code>pdo</code> and <code>pdo.connections</code> will be renamed <code>db</code> and <code>dbs</code> respectively.

Add PDO as a dependency:

```shell
composer require liquidbox/silex-pdo:^1.0
```

## Usage

<strong>Example #1 Demonstrate query</strong>

```php
$sql = 'SELECT name, color, calories FROM fruit ORDER BY name';

foreach ($app['pdo']->query($sql) as $row) {
    echo implode("\t", $row) . PHP_EOL;
}
```

<strong>Example #2 Prepare an SQL statement with named parameters</strong>

```php
/* Execute a prepared statement by passing an array of values */
$sql = <<<heredoc
SELECT name, color, calories
    FROM fruit
    WHERE calories < :calories AND color = :color
heredoc;

$pdoStatement = $app['pdo']->prepare($sql, array(
    \PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY,
));

$pdoStatement->execute(array(':calories' => 150, ':color' => 'red'));

$red = $pdoStatement->fetchAll();

$pdoStatement->execute(array(':calories' => 175, ':color' => 'yellow'));

$yellow = $pdoStatement->fetchAll();
```

## Using multiple databases

The PDO provider can allow access to multiple databases. In order to configure the data sources, use <strong>pdo.dsn</strong> as an array of configurations where keys are connection names and values are parameters:

```php
$app->register(new LiquidBox\Silex\Provider\PdoServiceProvider(), array(
    'pdo.dsn' => array(
        'mysql_read' => array(
            'connection' => array(
                'host'    => 'mysql_read.someplace.tld',
                'dbname'  => 'my_database',
                'charset' => 'utf8mb4',
            ),
            'username' => 'my_username',
            'password' => 'my_password',
        ),
        'mysql_write' => array(
            'connection' => array(
                'host'    => 'mysql_write.someplace.tld',
                'dbname'  => 'my_database',
                'charset' => 'utf8mb4',
            ),
            'username' => 'my_username',
            'password' => 'my_password',
        ),
    ),
));
```

The first registered connection is the default and can simply be accessed as you would if there was only one connection. Given the above configuration, these two lines are equivalent:

```php
$app['pdo']->query('SELECT * FROM table')->fetchAll();

$app['pdo.connections']['mysql_read']->query('SELECT * FROM table')->fetchAll();
```

You can use different drivers for each connection.

```php
$app->register(
    new LiquidBox\Silex\Provider\PdoServiceProvider(null, 'pdo.dbs'),
    array(
        'pdo.dsn' => array(
            'member_db' => array(
                'driver'     => 'pdo_pgsql',
                'connection' => array(
                    'host'    => 'member_data.someplace.tld',
                    'dbname'  => 'membership',
                ),
                'username' => $pgsql_user,
                'password' => $pgsql_passwd,
            ),
            'content_db' => array(
                'connection' => array(
                    'host'    => 'content_data.someplace.tld',
                    'dbname'  => 'media_info',
                    'charset' => 'utf8',
                ),
                'username' => $mysql_user,
                'password' => $mysql_passwd,
            ),
            'session_storage' => array('dsn' => 'sqlite::memory:'),
        ),
    )
);
```

This registers <code>$app['pdo.dbs']['member_db']</code>, <code>$app['pdo.dbs']['content_db']</code>, and <code>$app['pdo.dbs']['session_storage']</code> using PostgreSQL, MySQL, and SQLite drivers respectively.

## Traits

<code>LiquidBox\Silex\Application\PdoTrait</code> adds the following shortcut:

* <strong>prepare</strong>: Prepares a statement for execution and returns a statement object.

```php
/* Execute a prepared statement by passing an array of values */
$pdoStatement = $app->prepare('SELECT name, colour, calories
    FROM fruit
    WHERE calories < ? AND colour = ?');

$pdoStatement->execute(array(150, 'red'));

$red = $pdoStatement->fetchAll();

$pdoStatement->execute(array(175, 'yellow'));

$yellow = $pdoStatement->fetchAll();
```

For more information, check out the [official PDO documentation](http://php.net/manual/en/book.pdo.php).
