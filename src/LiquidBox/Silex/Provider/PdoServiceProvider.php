<?php
/**
 * PDO service provider for the Silex micro-framework.
 *
 * @see http://php.net/manual/en/book.pdo.php
 */

namespace LiquidBox\Silex\Provider;

use PDO;
use Silex\Application;
use Silex\ServiceProviderInterface;

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
     * @param \Silex\Application $app
     * @param string             $name
     *
     * @return string
     */
    private function getArg(Application $app, $name)
    {
        return isset($this->args[$name]) ? $this->args[$name] : $this->getDefaultArg($app, $name);
    }

    private function getArgDsn(Application $app)
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
     * @param \Silex\Application $app
     * @param string             $name
     *
     * @return string|array
     */
    private function getDefaultArg(Application $app, $name)
    {
        if (!isset($this->defaultArgs[$name])) {
            $this->setDefaultArg($app, $name);
        }

        return $this->defaultArgs[$name];
    }

    private function loadParameters(Application $app)
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

    private function loadSingletonParameters(Application $app)
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
     * @param \Silex\Application $app
     * @param string             $name
     */
    private function setDefaultArg(Application $app, $name)
    {
        static $args = array('driver' => 'mysql', 'attributes' => array(), 'options' => array());

        $this->defaultArgs[$name] = empty($app['pdo.' . $name]) ? $args[$name] : $app['pdo.' . $name];
    }

    /**
     * @codeCoverageIgnore
     */
    public function boot(Application $app)
    {
    }

    public function register(Application $app)
    {
        $app[$this->id] = $app->share(function () use ($app) {
            $this->loadParameters($app);

            return $app[$this->instances][$this->defaultConnection];
        });
        $app[$this->instances] = $app->share(function () use ($app) {
            $this->loadParameters($app);

            $instances = new \Pimple();
            foreach ($this->parameters as $connection => $this->args) {
                $instances[$connection] = $instances->share(function () use ($app) {
                    return $app['pdo.connect'](
                        $this->getArgDsn($app),
                        $this->getArg($app, 'username'),
                        $this->getArg($app, 'password'),
                        $this->getArg($app, 'options'),
                        $this->getArg($app, 'attributes')
                    );
                });
            }

            return $instances;
        });

        $app['pdo.connect'] = $app->protect(function (
            $dsn,
            $username = '',
            $password = '',
            array $options = array(),
            array $attributes = array()
        ) use ($app) {
            $pdo = new PDO(
                is_array($dsn) ? $this->buildDsn($this->getDefaultArg($app, 'driver'), $dsn) : $dsn,
                $username,
                $password,
                $options
            );
            $this->setAttributes($pdo, $attributes);

            return $pdo;
        });
    }
}
