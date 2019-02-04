<?php
/**
 * PDO service provider for the Silex micro-framework.
 *
 * @see http://php.net/manual/book.pdo.php
 */

namespace LiquidBox\Silex\Provider;

use PDO;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class PDOExt extends PDO
{
    protected $_table_prefix;
    public function __construct($dsn, $user = null, $password = null, $driver_options = array(), $prefix = null)
    {
        $this->_table_prefix = $prefix ?? '';
        parent::__construct($dsn, $user, $password, $driver_options);
    }

    public function exec($statement)
    {
        $statement = $this->_tablePrefix($statement);
        return parent::exec($statement);
    }
    public function prepare($statement, $driver_options = array())
    {
        $statement = $this->_tablePrefix($statement);
        return parent::prepare($statement, $driver_options);
    }
    public function query($statement)
    {
        $statement = $this->_tablePrefix($statement);
        $args      = func_get_args();
        if (count($args) > 1) {
            return call_user_func_array(array($this, 'parent::query'), $args);
        } else {
            return parent::query($statement);
        }
    }
    protected function _tablePrefix($statement)
    {
      $statement  = str_replace("prfx_", $this->_table_prefix, $statement);
  		return $statement;
    }
}

/**
 * PDO Provider.
 *
 * @author Jonathan-Paul Marois <jonathanpaul.marois@gmail.com>
 */
class PdoServiceProvider implements ServiceProviderInterface
{
    /**
     * @var array Buffer.
     */
    private $args = array();

    /**
     * @var array
     */
    private $defaultArgs = array(
        'username' => '',
        'password' => ''
    );

    /**
     * @var int|string
     */
    private $defaultConnection = 0;

    /**
     * @var string
     */
    private $id = 'pdo';

    /**
     * @var string
     */
    private $instances = 'pdo.connections';

    /**
     * @var bool
     */
    private $isLoaded = false;

    /**
     * @var array
     */
    private $parameters = array();

    /**
     * @param string $id
     * @param string $instances
     */
    public function __construct($id = null, $instances = null)
    {
        if (strlen($id)) {
            $this->id = $id;
        }
        if (strlen($instances)) {
            $this->instances = $instances;
        }
    }

    /**
     * @return string
     */
    private function buildConnectionString(array $connection)
    {
        return implode(';', array_map(function ($key, $value) {
            return is_int($key) ? $value : $key . '=' . $value;
        }, array_keys($connection), $connection));
    }

    /**
     * @param string $driver
     * @param array  $connection
     *
     * @return string
     */
    private function buildDsn($driver, array $connection)
    {
        return $this->sanitizeDriver($driver) . ':' . $this->buildConnectionString($connection);
    }

    /**
     * @param \Pimple\Container $app
     * @param string            $name
     *
     * @return string
     */
    private function getArg(Container $app, $name)
    {
        return isset($this->args[$name]) ? $this->args[$name] : $this->getDefaultArg($app, $name);
    }

    private function getArgDsn(Container $app)
    {
        if (!empty($this->args['dsn'])) {
            return $this->args['dsn'];
        }

        return $this->buildDsn(
            !empty($this->args['driver']) ? $this->args['driver'] : $this->getDefaultArg($app, 'driver'),
            !is_array($this->args['connection']) ? array($this->args['connection']) : $this->args['connection']
        );
    }

    /**
     * @param \Pimple\Container $app
     * @param string            $name
     *
     * @return string|array
     */
    private function getDefaultArg(Container $app, $name)
    {
        if (!isset($this->defaultArgs[$name])) {
            $this->setDefaultArg($app, $name);
        }

        return $this->defaultArgs[$name];
    }

    private function loadParameters(Container $app)
    {
        if ($this->isLoaded) {
            return;
        }

        $this->isLoaded = true;

        if (empty($app['pdo.dsn']) || !is_array($app['pdo.dsn'])) {
            $this->loadSingletonParameters($app);
        } else {
            $this->parameters = $app['pdo.dsn'];
            $this->defaultConnection = array_keys($this->parameters)[0];
        }
    }

    private function loadSingletonParameters(Container $app)
    {
        $this->parameters[0] = array();

        if (!empty($app['pdo.dsn'])) {
            $this->parameters[0]['dsn'] = $app['pdo.dsn'];
        } elseif (!empty($app['pdo.connection'])) {
            $this->parameters[0]['connection'] = $app['pdo.connection'];
        }

        if (!empty($app['pdo.username'])) {
            $this->parameters[0]['username'] = $app['pdo.username'];
        }
        if (!empty($app['pdo.password'])) {
            $this->parameters[0]['password'] = $app['pdo.password'];
        }
        if (!empty($app['pdo.prefix'])) {
            $this->parameters[0]['prefix'] = $app['pdo.prefix'];
        }
    }

    /**
     * @param string $driver
     *
     * @return string
     */
    private function sanitizeDriver($driver)
    {
        return ('pdo_' == substr($driver, 0, 4)) ? substr($driver, 4) : $driver;
    }

    private function setAttributes(PDO $pdo, array $attributes)
    {
        if (count($attributes)) {
            foreach ($attributes as $attr => $value) {
                $pdo->setAttribute($attr, $value);
            }
        }
    }

    /**
     * @param \Pimple\Container $app
     * @param string            $name
     */
    private function setDefaultArg(Container $app, $name)
    {
        $this->defaultArgs[$name] = empty($app['pdo.' . $name]) ?
            array('driver' => 'mysql', 'attributes' => array(), 'options' => array())[$name] :
            $app['pdo.' . $name];
    }

    public function register(Container $app)
    {
        $app[$this->id] = function () use ($app) {
            $this->loadParameters($app);

            return $app[$this->instances][$this->defaultConnection];
        };
        $app[$this->instances] = function () use ($app) {
            $this->loadParameters($app);

            $instances = new Container();
            foreach ($this->parameters as $connection => $args) {
                $instances[$connection] = function () use ($app, $args) {
                    $this->args = $args;
                    return $app['pdo.connect'](
                        $this->getArgDsn($app),
                        $this->getArg($app, 'username'),
                        $this->getArg($app, 'password'),
                        $this->getArg($app, 'options'),
                        $this->getArg($app, 'attributes'),
                        $this->getArg($app, 'prefix')
                    );
                };
            }

            return $instances;
        };

        $app['pdo.connect'] = $app->protect(function (
            $dsn,
            $username = '',
            $password = '',
            array $options = array(),
            array $attributes = array(),
            $prefix = ''
        ) use ($app) {
            $pdo = new PDOExt(
                is_array($dsn) ? $this->buildDsn($this->getDefaultArg($app, 'driver'), $dsn) : $dsn,
                $username,
                $password,
                $options,
                $prefix
            );
            $this->setAttributes($pdo, $attributes);

            return $pdo;
        });
    }
}
