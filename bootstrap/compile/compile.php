<?php
namespace Neutrino\Error;

class Error implements \ArrayAccess, \JsonSerializable
{
    protected $attributes;
    public function __construct(array $options = [])
    {
        $defaults = ['type' => -1, 'code' => 0, 'message' => 'No error message', 'file' => '', 'line' => '', 'exception' => null, 'isException' => false, 'isError' => false];
        $options = array_merge($defaults, $options);
        foreach ($options as $option => $value) {
            $this->attributes[$option] = $value;
        }
        $this->attributes['typeStr'] = \Neutrino\Error\Helper::verboseErrorType($this->attributes['type']);
        $this->attributes['logLvl'] = \Neutrino\Error\Helper::getLogType($this->attributes['type']);
    }
    public static function fromException($e)
    {
        return new static(['type' => -1, 'code' => $e->getCode(), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'isException' => true, 'exception' => $e]);
    }
    public static function fromError($errno, $errstr, $errfile, $errline)
    {
        return new static(['type' => $errno, 'code' => $errno, 'message' => $errstr, 'file' => $errfile, 'line' => $errline, 'isError' => true]);
    }
    public function isFateful()
    {
        $type = $this->type;
        return $type == -1 || $type == E_ERROR || $type == E_PARSE || $type == E_CORE_ERROR || $type == E_COMPILE_ERROR || $type == E_RECOVERABLE_ERROR;
    }
    public function __get($name)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }
    public function __isset($name)
    {
        return isset($this->attributes[$name]);
    }
    public function offsetExists($offset)
    {
        return \Neutrino\Support\Arr::has($this->attributes, $offset);
    }
    public function offsetGet($offset)
    {
        return \Neutrino\Support\Arr::get($this->attributes, $offset);
    }
    public function offsetSet($offset, $value)
    {
        $this->attributes[$offset] = $value;
    }
    public function offsetUnset($offset)
    {
        if (isset($this->attributes[$offset])) {
            unset($this->attributes[$offset]);
        }
    }
    function jsonSerialize()
    {
        $json = $this->attributes;
        if ($this->attributes['isException']) {
            $exception = $this->attributes['exception'];
            $json['exception'] = ['class' => get_class($exception), 'code' => $exception->getCode(), 'message' => $exception->getMessage(), 'traces' => \Neutrino\Error\Helper::formatExceptionTrace($exception)];
        }
        return $json;
    }
}
namespace Neutrino\Error;

class Helper
{
    public static function format(\Neutrino\Error\Error $error)
    {
        return implode("\n", self::formatLines($error));
    }
    private static function formatLines(\Neutrino\Error\Error $error, $pass = 0)
    {
        $pass++;
        $lines[] = self::getErrorType($error->type);
        if ($error->isException) {
            $lines[] = '  Class : ' . get_class($error->exception);
            $lines[] = '  Code : ' . $error->code;
        }
        $lines[] = '  Message : ' . $error->message;
        $lines[] = ' in : ' . str_replace(DIRECTORY_SEPARATOR, '/', $error->file) . '(' . $error->line . ')';
        if ($error->isException) {
            $lines[] = '';
            foreach (self::formatExceptionTrace($error->exception) as $trace) {
                $lines[] = '#' . $trace['id'] . ' ' . $trace['func'];
                $row = str_repeat(' ', strlen($trace['id']) + 2) . 'in : ';
                if (isset($trace['file'])) {
                    $row .= str_replace(DIRECTORY_SEPARATOR, '/', $trace['file']);
                    if (isset($trace['line'])) {
                        $row .= '(' . $trace['line'] . ')';
                    }
                } else {
                    $row .= '[internal function]';
                }
                $lines[] = $row;
            }
            $previous = $error->exception->getPrevious();
            if (!is_null($previous)) {
                $lines[] = '';
                $lines[] = '# Previous exception : ' . $pass;
                $lines[] = '';
                $lines = array_merge($lines, self::formatLines(\Neutrino\Error\Error::fromException($previous), $pass));
            }
        }
        return $lines;
    }
    public static function formatExceptionTrace($exception)
    {
        $traces = [];
        foreach ($exception->getTrace() as $idx => $trace) {
            $_trace = [];
            $_trace['id'] = $idx;
            $_trace['func'] = '';
            if (isset($trace['class'])) {
                $_trace['func'] = $trace['class'] . '->';
            }
            if (isset($trace['function'])) {
                $_trace['func'] .= $trace['function'];
            }
            $args = [];
            if (isset($trace['args'])) {
                $args = self::verboseArgs((array) $trace['args']);
            }
            $_trace['func'] .= '(' . implode(', ', $args) . ')';
            if (isset($trace['file'])) {
                $_trace['file'] = str_replace(DIRECTORY_SEPARATOR, '/', $trace['file']);
                if (isset($trace['line'])) {
                    $_trace['file'] .= '(' . $trace['line'] . ')';
                }
            } else {
                $_trace['file'] = '[internal function]';
            }
            $traces[] = $_trace;
        }
        return $traces;
    }
    public static function verboseArgs(array $args)
    {
        $arguments = [];
        foreach ($args as $key => $arg) {
            $arguments[$key] = self::verboseType($arg);
        }
        return $arguments;
    }
    public static function verboseType($value, $lvl = 0)
    {
        switch ($type = gettype($value)) {
            case 'array':
                if (!empty($value) && $lvl === 0) {
                    $found = [];
                    foreach ($value as $item) {
                        $type = gettype($item);
                        if ($type == 'object') {
                            $type = get_class($item);
                        }
                        $found[$type] = true;
                    }
                    if (count($value) < 4) {
                        $str = [];
                        foreach ($value as $item) {
                            $str[] = self::verboseType($item, $lvl + 1);
                        }
                        return 'array(' . implode(', ', $str) . ')';
                    } elseif (count($found) === 1) {
                        return 'arrayOf(' . $type . ')[' . count($value) . ']';
                    }
                    return 'array[' . count($value) . ']';
                }
                return 'array';
            case 'object':
                $class = explode('\\', get_class($value));
                return 'object(' . array_pop($class) . ')';
            case 'NULL':
                return 'null';
            case 'unknown type':
                return '?';
            case 'resource':
            case 'resource (closed)':
                return $type;
            case 'string':
                if (strlen($value) > 20) {
                    return "'" . substr($value, 0, 8) . '...\'[' . strlen($value) . ']';
                }
            case 'boolean':
            case 'integer':
            case 'double':
            default:
                return var_export($value, true);
        }
    }
    public static function getErrorType($code)
    {
        switch ($code) {
            case -1:
                return 'Uncaught exception';
            case E_ERROR:
                return 'E_ERROR';
            case E_WARNING:
                return 'E_WARNING';
            case E_PARSE:
                return 'E_PARSE';
            case E_NOTICE:
                return 'E_NOTICE';
            case E_CORE_ERROR:
                return 'E_CORE_ERROR';
            case E_CORE_WARNING:
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR:
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING:
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR:
                return 'E_USER_ERROR';
            case E_USER_WARNING:
                return 'E_USER_WARNING';
            case E_USER_NOTICE:
                return 'E_USER_NOTICE';
            case E_STRICT:
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR:
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED:
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED:
                return 'E_USER_DEPRECATED';
        }
        return "(unknown error bit {$code})";
    }
    public static function verboseErrorType($code)
    {
        switch ($code) {
            case -1:
                return 'Uncaught exception';
            case E_COMPILE_ERROR:
            case E_CORE_ERROR:
            case E_ERROR:
            case E_PARSE:
            case E_RECOVERABLE_ERROR:
            case E_USER_ERROR:
                return 'Fatal error [' . self::getErrorType($code) . ']';
            case E_WARNING:
            case E_USER_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
                return 'Warning [' . self::getErrorType($code) . ']';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'Notice [' . self::getErrorType($code) . ']';
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'Deprecated [' . self::getErrorType($code) . ']';
        }
        return "(unknown error bit {$code})";
    }
    public static function getLogType($code)
    {
        switch ($code) {
            case E_PARSE:
                return \Phalcon\Logger::CRITICAL;
            case E_COMPILE_ERROR:
            case E_CORE_ERROR:
            case E_ERROR:
                return \Phalcon\Logger::EMERGENCY;
            case -1:
            case E_RECOVERABLE_ERROR:
            case E_USER_ERROR:
                return \Phalcon\Logger::ERROR;
            case E_WARNING:
            case E_USER_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
                return \Phalcon\Logger::WARNING;
            case E_NOTICE:
            case E_USER_NOTICE:
                return \Phalcon\Logger::NOTICE;
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return \Phalcon\Logger::INFO;
        }
        return \Phalcon\Logger::ERROR;
    }
}
namespace Neutrino\Error;

class Handler
{
    private static $writers = [\Neutrino\Error\Writer\Phplog::class => null];
    public static function addWriter($writer)
    {
        self::$writers[$writer] = null;
    }
    public static function setWriters(array $writers)
    {
        self::$writers = array_fill_keys($writers, null);
    }
    public static function register()
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            self::handleError($errno, $errstr, $errfile, $errline);
        });
        set_exception_handler(function ($e) {
            self::handleException($e);
        });
        register_shutdown_function(function () {
            $error = error_get_last();
            if (isset($error['type']) && $error['type'] === E_ERROR) {
                self::handleError($error['type'], $error['message'], $error['file'], $error['line']);
            }
        });
    }
    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        if (!($errno & error_reporting())) {
            return;
        }
        static::handle(\Neutrino\Error\Error::fromError($errno, $errstr, $errfile, $errline));
    }
    public static function handleException($e)
    {
        static::handle(\Neutrino\Error\Error::fromException($e));
    }
    public static function handle(\Neutrino\Error\Error $error)
    {
        foreach (self::$writers as $class => $writer) {
            if (is_null($writer)) {
                self::$writers[$class] = $writer = new $class();
            }
            $writer->handle($error);
        }
    }
}
namespace Neutrino\Constants;

final class Services
{
    const ACL = 'acl';
    const ANNOTATIONS = 'annotations';
    const APP = 'application';
    const ASSETS = 'assets';
    const AUTH = 'auth';
    const CACHE = 'cache';
    const CONFIG = 'config';
    const COOKIES = 'cookies';
    const CRYPT = 'crypt';
    const DB = 'db';
    const DISPATCHER = 'dispatcher';
    const ESCAPER = 'escaper';
    const EVENTS_MANAGER = 'eventsManager';
    const FILTER = 'filter';
    const FLASH = 'flash';
    const FLASH_SESSION = 'flashSession';
    const HTTP_CLIENT = 'httpClient';
    const LOGGER = 'logger';
    const MICRO_ROUTER = 'micro.router';
    const MODELS_MANAGER = 'modelsManager';
    const MODELS_METADATA = 'modelsMetadata';
    const TRANSACTION_MANAGER = 'transactionManager';
    const ROUTER = 'router';
    const RESPONSE = 'response';
    const REQUEST = 'request';
    const SESSION = 'session';
    const SESSION_BAG = 'sessionBag';
    const SECURITY = 'security';
    const TAG = 'tag';
    const URL = 'url';
    const VIEW = 'view';
}
namespace Neutrino\Constants;

final class Env
{
    const PRODUCTION = 'production';
    const STAGING = 'staging';
    const TEST = 'test';
    const DEVELOPMENT = 'development';
}
namespace Neutrino\Constants;

final class Events
{
    const DISPATCH = 'dispatch';
    const LOADER = 'loader';
    const ACL = 'acl';
    const CONSOLE = 'console';
    const DB = 'db';
    const APPLICATION = 'application';
    const COLLECTION = 'collection';
    const MICRO = 'micro';
    const MODEL = 'model';
    const VIEW = 'view';
    const COLLECTION_MANAGER = 'collectionManager';
    const MODELS_MANAGER = 'modelsManager';
    const VOLT = 'volt';
}
namespace Neutrino\Constants\Events\Http;

final class Application
{
    const BOOT = 'application:boot';
    const BEFORE_START_MODULE = 'application:beforeStartModule';
    const AFTER_START_MODULE = 'application:afterStartModule';
    const BEFORE_HANDLE = 'application:beforeHandleRequest';
    const AFTER_HANDLE = 'application:afterHandleRequest';
    const VIEW_RENDER = 'application:viewRender';
    const BEFORE_SEND_RESPONSE = 'application:beforeSendResponse';
}
namespace Neutrino\Constants\Events;

final class Dispatch
{
    const BEFORE_DISPATCH_LOOP = 'dispatch:beforeDispatchLoop';
    const BEFORE_DISPATCH = 'dispatch:beforeDispatch';
    const BEFORE_NOT_FOUND_ACTION = 'dispatch:beforeNotFoundAction';
    const BEFORE_EXECUTE_ROUTE = 'dispatch:beforeExecuteRoute';
    const AFTER_INITIALIZE = 'dispatch:afterInitialize';
    const AFTER_EXECUTE_ROUTE = 'dispatch:afterExecuteRoute';
    const AFTER_DISPATCH = 'dispatch:afterDispatch';
    const AFTER_DISPATCH_LOOP = 'dispatch:afterDispatchLoop';
    const BEFORE_EXCEPTION = 'dispatch:beforeException';
}
namespace Neutrino\Constants\Events;

final class Acl
{
    const BEFORE_CHECK_ACCESS = 'acl:beforeCheckAccess';
    const AFTER_CHECK_ACCESS = 'acl:afterCheckAccess';
}
namespace Neutrino\Constants\Events;

final class Collection
{
    const BEFORE_VALIDATION = 'collection:beforeValidation';
    const BEFORE_VALIDATION_ON_CREATE = 'collection:beforeValidationOnCreate';
    const BEFORE_VALIDATION_ON_UPDATE = 'collection:beforeValidationOnUpdate';
    const VALIDATION = 'collection:validation';
    const ON_VALIDATION_FAILS = 'collection:onValidationFails';
    const AFTER_VALIDATION_ON_CREATE = 'collection:afterValidationOnCreate';
    const AFTER_VALIDATION_ON_UPDATE = 'collection:afterValidationOnUpdate';
    const AFTER_VALIDATION = 'collection:afterValidation';
    const BEFORE_SAVE = 'collection:beforeSave';
    const BEFORE_UPDATE = 'collection:beforeUpdate';
    const BEFORE_CREATE = 'collection:beforeCreate';
    const AFTER_UPDATE = 'collection:afterUpdate';
    const AFTER_CREATE = 'collection:afterCreate';
    const AFTER_SAVE = 'collection:afterSave';
    const NOT_SAVE = 'collection:notSave';
    const NOT_DELETED = 'collection:notDeleted';
    const NOT_SAVED = 'collection:notSaved';
}
namespace Neutrino\Constants\Events;

final class CollectionManager
{
    const AFTER_INITIALIZE = 'collectionManager:afterInitialize';
}
namespace Neutrino\Constants\Events;

final class Db
{
    const BEFORE_QUERY = 'db:beforeQuery';
    const AFTER_QUERY = 'db:afterQuery';
    const BEGIN_TRANSACTION = 'db:beginTransaction';
    const CREATE_SAVEPOINT = 'db:createSavepoint';
    const ROLLBACK_TRANSACTION = 'db:rollbackTransaction';
    const ROLLBACK_SAVEPOINT = 'db:rollbackSavepoint';
    const COMMIT_TRANSACTION = 'db:commitTransaction';
    const RELEASE_SAVEPOINT = 'db:releaseSavepoint';
}
namespace Neutrino\Constants\Events;

final class Loader
{
    const BEFORE_CHECK_CLASS = 'loader:beforeCheckClass';
    const PATH_FOUND = 'loader:pathFound';
    const BEFORE_CHECK_PATH = 'loader:beforeCheckPath';
    const AFTER_CHECK_CLASS = 'loader:afterCheckClass';
}
namespace Neutrino\Constants\Events;

final class Micro
{
    const BEFORE_HANDLE_ROUTE = 'micro:beforeHandleRoute';
    const BEFORE_EXECUTE_ROUTE = 'micro:beforeExecuteRoute';
    const AFTER_EXECUTE_ROUTE = 'micro:afterExecuteRoute';
    const BEFORE_NOT_FOUND = 'micro:beforeNotFound';
    const AFTER_HANDLE_ROUTE = 'micro:afterHandleRoute';
}
namespace Neutrino\Constants\Events;

final class ModelsManager
{
    const AFTER_INITIALIZE = 'modelsManager:afterInitialize';
}
namespace Neutrino\Constants\Events;

final class Model
{
    const NOT_DELETED = 'model:notDeleted';
    const NOT_SAVED = 'model:notSaved';
    const ON_VALIDATION_FAILS = 'model:onValidationFails';
    const BEFORE_VALIDATION = 'model:beforeValidation';
    const BEFORE_VALIDATION_ON_CREATE = 'model:beforeValidationOnCreate';
    const BEFORE_VALIDATION_ON_UPDATE = 'model:beforeValidationOnUpdate';
    const AFTER_VALIDATION_ON_CREATE = 'model:afterValidationOnCreate';
    const AFTER_VALIDATION_ON_UPDATE = 'model:afterValidationOnUpdate';
    const AFTER_VALIDATION = 'model:afterValidation';
    const BEFORE_SAVE = 'model:beforeSave';
    const BEFORE_UPDATE = 'model:beforeUpdate';
    const BEFORE_CREATE = 'model:beforeCreate';
    const AFTER_UPDATE = 'model:afterUpdate';
    const AFTER_CREATE = 'model:afterCreate';
    const AFTER_SAVE = 'model:afterSave';
    const NOT_SAVE = 'model:notSave';
    const BEFORE_DELETE = 'model:beforeDelete';
    const AFTER_DELETE = 'model:afterDelete';
}
namespace Neutrino\Constants\Events;

final class View
{
    const BEFORE_RENDER_VIEW = 'view:beforeRenderView';
    const AFTER_RENDER_VIEW = 'view:afterRenderView';
    const NOT_FOUND_VIEW = 'view:notFoundView';
    const BEFORE_RENDER = 'view:beforeRender';
    const AFTER_RENDER = 'view:afterRender';
}
namespace Neutrino\Constants\Events;

final class Volt
{
    const COMPILE_FUNCTION = 'volt:compileFunction';
    const COMPILE_FILTER = 'volt:compileFilter';
    const RESOLVE_EXPRESSION = 'volt:resolveExpression';
    const COMPILE_STATEMENT = 'volt:compileStatement';
}
namespace Neutrino\Constants\Events;

final class Console
{
    const BEFORE_START_MODULE = 'console:beforeStartModule';
    const AFTER_START_MODULE = 'console:afterStartModule';
    const BEFORE_HANDLE_TASK = 'console:beforeHandleTask';
    const AFTER_HANDLE_TASK = 'console:afterHandleTask';
}
namespace Neutrino\Constants\Events;

final class Kernel
{
    const BOOT = 'kernel:boot';
    const TERMINATE = 'kernel:terminate';
}
namespace Neutrino\Interfaces;

interface Kernelable
{
    public function bootstrap(\Phalcon\Config $config);
    public function registerServices();
    public function registerRoutes();
    public function registerMiddlewares();
    public function registerListeners();
    public function registerModules(array $modules, $merge = false);
    public function boot();
    public function terminate();
}
namespace Neutrino\Interfaces;

interface Providable
{
    public function registering();
}
namespace Neutrino\Interfaces\Middleware;

interface InitInterface
{
    public function init(\Phalcon\Events\Event $event, $source, $data = null);
}
namespace Neutrino\Interfaces\Middleware;

interface AfterInterface
{
    public function after(\Phalcon\Events\Event $event, $source, $data = null);
}
namespace Neutrino\Interfaces\Middleware;

interface BeforeInterface
{
    public function before(\Phalcon\Events\Event $event, $source, $data = null);
}
namespace Neutrino\Interfaces\Middleware;

interface FinishInterface
{
    public function finish(\Phalcon\Events\Event $event, $source, $data = null);
}
namespace Neutrino\Dotconst;

class Loader
{
    public static function fromCompile($path)
    {
        if (file_exists($compilePath = $path . '/consts.php')) {
            require $compilePath;
            return true;
        }
        return false;
    }
    public static function fromFiles($path)
    {
        $pathEnv = $path . DIRECTORY_SEPARATOR . '.const';
        if (!file_exists($pathEnv . '.ini')) {
            return [];
        }
        $raw = self::loadRaw($path);
        $config = self::parse($raw, $pathEnv . '.ini');
        return $config;
    }
    public static function loadRaw($path)
    {
        $basePath = $path . DIRECTORY_SEPARATOR . '.const';
        $path = $basePath . '.ini';
        if (!file_exists($path)) {
            return [];
        }
        $raw = \Neutrino\Dotconst\Helper::loadIniFile($path);
        $config = self::parse($raw, $path);
        if (!empty($config['APP_ENV']) && file_exists($pathEnv = $basePath . '.' . $config['APP_ENV'] . '.ini')) {
            $raw = \Neutrino\Dotconst\Helper::mergeConfigWithFile($raw, $pathEnv);
        }
        return $raw;
    }
    private static function parse($config, $file)
    {
        return self::dynamize($config, dirname($file));
    }
    private static function dynamize($config, $dir)
    {
        foreach (\Neutrino\Dotconst::getExtensions() as $extension) {
            foreach ($config as $const => $value) {
                if ($extension->identify($value)) {
                    $config[$const] = $extension->parse($value, $dir);
                }
            }
        }
        $nested = [];
        foreach ($config as $const => $value) {
            if (preg_match('#^@\\{(\\w+)\\}@?#', $value, $match)) {
                $key = strtoupper($match[1]);
                $value = preg_replace('#^@\\{(\\w+)\\}@?#', '', $value);
                $draw = '';
                $require = null;
                if (isset($config[$key])) {
                    $require = $key;
                } else {
                    $draw .= $match[1];
                }
                $value = $draw . $value;
                $nested[$const] = ['require' => $require, 'value' => $value];
            }
        }
        $nested = \Neutrino\Dotconst\Helper::nestedConstSort($nested);
        foreach ($nested as $const => $value) {
            $v = null;
            if (isset($config[$value['require']])) {
                $v = $config[$value['require']];
            }
            if (!empty($value['value'])) {
                $v .= $value['value'];
            }
            $config[$const] = $v;
        }
        return $config;
    }
}
namespace Neutrino\Config;

class Loader
{
    public static function load($basePath, array $excludes = [])
    {
        if (!is_null($config = self::fromCompile($basePath))) {
            return $config;
        } else {
            return self::fromFiles($basePath, $excludes);
        }
    }
    public static function raw($basePath, array $excludes = [])
    {
        $config = [];
        $excludes = array_flip($excludes);
        foreach (glob($basePath . '/config/*.php') as $file) {
            if (!isset($excludes[$fileName = basename($file, '.php')])) {
                $config[$fileName] = (require $file);
            }
        }
        return $config;
    }
    public static function fromFiles($basePath, array $excludes = [])
    {
        return new \Phalcon\Config(self::raw($basePath, $excludes));
    }
    public static function fromCompile($basePath)
    {
        if (file_exists($compilePath = $basePath . '/bootstrap/compile/config.php')) {
            return new \Phalcon\Config(require $compilePath);
        }
        return null;
    }
}
namespace Neutrino\Events;

abstract class Listener extends \Phalcon\Di\Injectable
{
    protected $listen;
    protected $space;
    private $closures = [];
    public function attach()
    {
        $em = $this->getEventsManager();
        if (!empty($this->space)) {
            foreach ($this->space as $space) {
                $em->attach($space, $this);
            }
        }
        if (!empty($this->listen)) {
            foreach ($this->listen as $event => $callback) {
                if (!method_exists($this, $callback)) {
                    throw new \RuntimeException("Method '{$callback}' not exist in " . get_class($this));
                }
                $this->closures[$event] = $closure = function (\Phalcon\Events\Event $event, $handler, $data = null) use($callback) {
                    return $this->{$callback}($event, $handler, $data);
                };
                $em->attach($event, $closure);
            }
        }
    }
    public function detach()
    {
        $em = $this->getEventsManager();
        if (!empty($this->space)) {
            foreach ($this->space as $space) {
                $em->detach($space, $this);
            }
        }
        foreach ($this->closures as $event => $closure) {
            $em->detach($event, $closure);
        }
        $this->closures = [];
    }
}
namespace Neutrino\Support;

abstract class SimpleProvider extends \Phalcon\Di\Injectable implements \Neutrino\Interfaces\Providable
{
    protected $class;
    protected $name;
    protected $aliases;
    protected $shared = false;
    protected $options;
    public final function __construct()
    {
        if (empty($this->name) || !is_string($this->name)) {
            throw new \RuntimeException('Provider "' . static::class . '::$name" isn\'t valid.');
        }
        if (empty($this->class) || !is_string($this->class)) {
            throw new \RuntimeException('Provider "' . static::class . '::$class" isn\'t valid.');
        }
    }
    public final function registering()
    {
        if (empty($this->options)) {
            $definition = $this->class;
        } else {
            $definition = array_merge(['className' => $this->class], $this->options);
        }
        $service = new \Phalcon\Di\Service($this->name, $definition, $this->shared);
        $this->getDI()->setRaw($this->name, $service);
        if (!empty($this->aliases)) {
            foreach ($this->aliases as $alias) {
                $this->getDI()->setRaw($alias, $service);
            }
        }
    }
}
namespace Neutrino\Support;

abstract class Provider extends \Phalcon\Di\Injectable implements \Neutrino\Interfaces\Providable
{
    protected $name;
    protected $aliases;
    protected $shared = false;
    public final function __construct()
    {
        if (empty($this->name) || !is_string($this->name)) {
            throw new \RuntimeException('Provider "' . static::class . '::$name" isn\'t valid.');
        }
    }
    public final function registering()
    {
        $self = $this;
        $service = new \Phalcon\Di\Service($this->name, function () use($self) {
            return $self->register();
        }, $this->shared);
        $this->getDI()->setRaw($this->name, $service);
        if (!empty($this->aliases)) {
            foreach ($this->aliases as $alias) {
                $this->getDI()->setRaw($alias, $service);
            }
        }
    }
    protected abstract function register();
}
namespace Neutrino\Providers\Http;

class Dispatcher extends \Neutrino\Support\Provider
{
    protected $name = \Neutrino\Constants\Services::DISPATCHER;
    protected $shared = true;
    protected $aliases = [\Phalcon\Mvc\Dispatcher::class];
    protected function register()
    {
        $dispatcher = new \Phalcon\Mvc\Dispatcher();
        $dispatcher->setEventsManager($this->getDI()->getShared(\Neutrino\Constants\Services::EVENTS_MANAGER));
        return $dispatcher;
    }
}
namespace Neutrino\Providers\Http;

class Router extends \Neutrino\Support\Provider
{
    protected $name = \Neutrino\Constants\Services::ROUTER;
    protected $shared = true;
    protected $aliases = [\Phalcon\Mvc\Router::class];
    protected function register()
    {
        $router = new \Phalcon\Mvc\Router(false);
        $router->setUriSource(\Phalcon\Mvc\Router::URI_SOURCE_SERVER_REQUEST_URI);
        return $router;
    }
}
namespace Neutrino\Providers\Micro;

class Router extends \Neutrino\Support\SimpleProvider
{
    protected $class = \Neutrino\Micro\Router::class;
    protected $name = \Neutrino\Constants\Services::MICRO_ROUTER;
    protected $shared = true;
}
namespace Neutrino\Providers;

class Database extends \Phalcon\Di\Injectable implements \Neutrino\Interfaces\Providable
{
    public function registering()
    {
        $di = $this->getDI();
        $database = (array) $di->getShared(\Neutrino\Constants\Services::CONFIG)->database;
        $connections = (array) $database['connections'];
        if (count($connections) > 1) {
            $di->setShared(\Neutrino\Constants\Services::DB, \Neutrino\Database\DatabaseStrategy::class);
            foreach ($connections as $name => $connection) {
                $di->setShared(\Neutrino\Constants\Services::DB . '.' . $name, function () use($connection) {
                    return new $connection['adapter']((array) $connection['config']);
                });
            }
        } else {
            $connection = array_shift($connections);
            $serviceName = \Neutrino\Constants\Services::DB . '.' . $database['default'];
            $service = new \Phalcon\Di\Service($serviceName, function () use($connection) {
                return new $connection['adapter']((array) $connection['config']);
            }, true);
            $di->setRaw($serviceName, $service);
            $di->setRaw(\Neutrino\Constants\Services::DB, $service);
        }
    }
}
namespace Neutrino\Providers;

class Cache extends \Phalcon\Di\Injectable implements \Neutrino\Interfaces\Providable
{
    public function registering()
    {
        $di = $this->getDI();
        $di->setShared(\Neutrino\Constants\Services::CACHE, \Neutrino\Cache\CacheStrategy::class);
        $cache = $di->getShared(\Neutrino\Constants\Services::CONFIG)->cache;
        foreach ($cache->stores as $name => $cache) {
            $di->setShared(\Neutrino\Constants\Services::CACHE . '.' . $name, function () use($cache) {
                $driver = $cache->driver;
                if (empty($driver)) {
                    $driver = $cache->backend;
                }
                switch ($driver) {
                    case 'Aerospike':
                    case 'Apc':
                    case 'Database':
                    case 'Libmemcached':
                    case 'File':
                    case 'Memcache':
                    case 'Memory':
                    case 'Mongo':
                    case 'Redis':
                    case 'Wincache':
                    case 'Xcache':
                        $driverClass = '\\Phalcon\\Cache\\Backend\\' . ucfirst($driver);
                        break;
                    default:
                        $driverClass = $driver;
                        if (!class_exists($driverClass)) {
                            $msg = empty($driver) ? 'Cache driver not set.' : "Cache driver {$driver} not implemented.";
                            throw new \RuntimeException($msg);
                        }
                }
                $adapter = $cache->adapter;
                if (empty($adapter)) {
                    $adapter = $cache->frontend;
                }
                switch ($adapter) {
                    case 'Data':
                    case 'Json':
                    case 'File':
                    case 'Base64':
                    case 'Output':
                    case 'Igbinary':
                    case 'None':
                        $adapterClass = '\\Phalcon\\Cache\\Frontend\\' . ucfirst($adapter);
                        break;
                    case null:
                        $adapterClass = \Phalcon\Cache\Frontend\None::class;
                        break;
                    default:
                        $adapterClass = $adapter;
                        if (!class_exists($adapterClass)) {
                            throw new \RuntimeException("Cache adapter {$adapter} not implemented.");
                        }
                }
                $options = isset($cache->options) ? $cache->options->toArray() : [];
                $adapterInstance = new $adapterClass($options);
                if (!$adapterInstance instanceof \Phalcon\Cache\FrontendInterface) {
                    throw new \RuntimeException("Cache adapter {$adapter} not implement FrontendInterface.");
                }
                $driverInstance = new $driverClass($adapterInstance, $options);
                if (!$driverInstance instanceof \Phalcon\Cache\BackendInterface) {
                    throw new \RuntimeException("Cache driver {$adapter} not implement BackendInterface.");
                }
                return $driverInstance;
            });
        }
    }
}
namespace Neutrino\Providers;

class Logger extends \Neutrino\Support\Provider
{
    protected $name = \Neutrino\Constants\Services::LOGGER;
    protected $shared = true;
    protected function register()
    {
        $config = $this->getDI()->getShared(\Neutrino\Constants\Services::CONFIG)->log;
        $adapter = isset($config->adapter) ? $config->adapter : null;
        switch ($adapter) {
            case null:
            case \Phalcon\Logger\Adapter\File::class:
            case 'File':
                $adapter = \Phalcon\Logger\Adapter\File::class;
                $name = isset($config->path) ? $config->path : null;
                break;
            case 'Firelogger':
            case 'Stream':
            case 'Syslog':
            case 'Udplogger':
                $adapter = '\\Phalcon\\Logger\\Adapter\\' . ucfirst($adapter);
                $name = isset($config->name) ? $config->name : 'phalcon';
                break;
            default:
                if (!class_exists($adapter)) {
                    throw new \RuntimeException("Logger adapter {$adapter} not implemented.");
                }
                $name = isset($config->name) ? $config->name : (isset($config->path) ? $config->path : 'phalcon');
        }
        if (empty($name)) {
            throw new \RuntimeException('Required parameter {name|path} missing.');
        }
        if (empty($config->options)) {
            throw new \RuntimeException('Required parameter {options} missing.');
        }
        return new $adapter($name, (array) $config->options);
    }
}
namespace Neutrino\Providers;

class Flash extends \Neutrino\Support\Provider
{
    protected $name = \Neutrino\Constants\Services::FLASH;
    protected $shared = false;
    protected $aliases = [\Phalcon\Flash\Direct::class];
    protected function register()
    {
        $flash = new \Phalcon\Flash\Direct();
        $flash->setImplicitFlush(false);
        return $flash;
    }
}
namespace Neutrino\Providers;

class Session extends \Phalcon\Di\Injectable implements \Neutrino\Interfaces\Providable
{
    public function registering()
    {
        $di = $this->getDI();
        $di->set(\Neutrino\Constants\Services::SESSION_BAG, \Phalcon\Session\Bag::class);
        $di->setShared(\Neutrino\Constants\Services::SESSION, function () {
            $sessionConfig = $this->getShared(\Neutrino\Constants\Services::CONFIG)->session;
            $adapter = $sessionConfig->adapter;
            switch ($adapter) {
                case 'Aerospike':
                case 'Database':
                case 'HandlerSocket':
                case 'Mongo':
                case 'Files':
                case 'Libmemcached':
                case 'Memcache':
                case 'Redis':
                    $class = 'Phalcon\\Session\\Adapter\\' . $adapter;
                    break;
                case \Phalcon\Session\Adapter\Files::class:
                case \Phalcon\Session\Adapter\Libmemcached::class:
                case \Phalcon\Session\Adapter\Memcache::class:
                case \Phalcon\Session\Adapter\Redis::class:
                    $class = $adapter;
                    break;
                default:
                    $class = $adapter;
                    if (!class_exists($adapter)) {
                        throw new \RuntimeException("Session Adapter {$class} not found.");
                    }
            }
            try {
                $options = [];
                if (!empty($sessionConfig->options)) {
                    $options = $sessionConfig->options->toArray();
                }
                $session = new $class($options);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Session Adapter {$class} construction fail.", $e);
            }
            $session->start();
            return $session;
        });
    }
}
namespace Neutrino\Providers;

class View extends \Phalcon\Di\Injectable implements \Neutrino\Interfaces\Providable
{
    public function registering()
    {
        $di = $this->getDI();
        $di->setShared(\Neutrino\Constants\Services::TAG, \Phalcon\Tag::class);
        $di->setShared(\Phalcon\Tag::class, \Phalcon\Tag::class);
        $di->setShared(\Neutrino\Constants\Services::ASSETS, \Phalcon\Assets\Manager::class);
        $di->setShared(\Phalcon\Assets\Manager::class, \Phalcon\Assets\Manager::class);
        $di->setShared(\Neutrino\Constants\Services::VIEW, function () {
            $view = new \Phalcon\Mvc\View();
            $configView = $this->getShared(\Neutrino\Constants\Services::CONFIG)->view;
            if (isset($configView->views_dir)) {
                $view->setViewsDir($configView->views_dir);
            }
            if (isset($configView->partials_dir)) {
                $view->setPartialsDir($configView->partials_dir);
            }
            if (isset($configView->layouts_dir)) {
                $view->setLayoutsDir($configView->layouts_dir);
            }
            $engines = $configView->engines;
            $registerEngines = [];
            foreach ($engines as $type => $engine) {
                if (method_exists($engine, 'getRegisterClosure')) {
                    $registerEngines[$type] = $engine::getRegisterClosure();
                } else {
                    $registerEngines[$type] = $engine;
                }
            }
            $view->registerEngines($registerEngines);
            return $view;
        });
    }
}
namespace Neutrino\Providers;

class Url extends \Neutrino\Support\Provider
{
    protected $name = \Neutrino\Constants\Services::URL;
    protected $shared = true;
    protected $aliases = [\Phalcon\Mvc\Url::class];
    protected function register()
    {
        $url = new \Phalcon\Mvc\Url();
        $url->setBaseUri($this->getDI()->getShared(\Neutrino\Constants\Services::CONFIG)->app->base_uri);
        return $url;
    }
}
namespace Neutrino\Micro;

abstract class Middleware implements \Phalcon\Mvc\Micro\MiddlewareInterface
{
    const ON_BEFORE = 'before';
    const ON_AFTER = 'after';
    const ON_FINISH = 'finish';
    public abstract function bindOn();
}
namespace Neutrino\Micro;

interface RouterInterface
{
    public function setDefaultModule($moduleName);
    public function setDefaultController($controllerName);
    public function setDefaultAction($actionName);
    public function setDefaults(array $defaults);
    public function handle($uri = null);
    public function add($pattern, $paths = null, $httpMethods = null);
    public function addGet($pattern, $paths = null);
    public function addPost($pattern, $paths = null);
    public function addPut($pattern, $paths = null);
    public function addPatch($pattern, $paths = null);
    public function addDelete($pattern, $paths = null);
    public function addOptions($pattern, $paths = null);
    public function addHead($pattern, $paths = null);
    public function addPurge($pattern, $paths = null);
    public function addTrace($pattern, $paths = null);
    public function addConnect($pattern, $paths = null);
    public function mount(\Phalcon\Mvc\Micro\Collection $collection);
    public function clear();
    public function getModuleName();
    public function getNamespaceName();
    public function getControllerName();
    public function getActionName();
    public function getParams();
    public function getMatchedRoute();
    public function getMatches();
    public function wasMatched();
    public function getRoutes();
    public function getRouteById($id);
    public function getRouteByName($name);
}
namespace Neutrino\Micro;

class Router extends \Phalcon\Di\Injectable implements \Neutrino\Micro\RouterInterface
{
    public function setDefaultModule($moduleName)
    {
        throw new \RuntimeException(__CLASS__ . ' doesn\'t support modules.');
    }
    public function setDefaultController($controllerName)
    {
        throw new \RuntimeException(__CLASS__ . ' doesn\'t support default controller.');
    }
    public function setDefaultAction($actionName)
    {
        throw new \RuntimeException(__CLASS__ . ' doesn\'t support default action.');
    }
    public function setDefaults(array $defaults)
    {
        throw new \RuntimeException(__CLASS__ . ' doesn\'t support defaults paths.');
    }
    public function handle($uri = null)
    {
        $this->router->handle($uri);
    }
    public function add($pattern, $paths = null, $httpMethods = null)
    {
        foreach ($httpMethods as $httpMethod) {
            $this->application->{strtolower($httpMethod)}($pattern, $this->pathToHandler($paths));
        }
        return null;
    }
    public function addGet($pattern, $paths = null)
    {
        return $this->application->get($pattern, $this->pathToHandler($paths));
    }
    public function addPost($pattern, $paths = null)
    {
        return $this->application->post($pattern, $this->pathToHandler($paths));
    }
    public function addPut($pattern, $paths = null)
    {
        return $this->application->put($pattern, $this->pathToHandler($paths));
    }
    public function addPatch($pattern, $paths = null)
    {
        return $this->application->patch($pattern, $this->pathToHandler($paths));
    }
    public function addDelete($pattern, $paths = null)
    {
        return $this->application->delete($pattern, $this->pathToHandler($paths));
    }
    public function addOptions($pattern, $paths = null)
    {
        return $this->application->options($pattern, $this->pathToHandler($paths));
    }
    public function addHead($pattern, $paths = null)
    {
        return $this->application->head($pattern, $this->pathToHandler($paths));
    }
    public function addPurge($pattern, $paths = null)
    {
        throw new \RuntimeException(__METHOD__ . ': Micro Application doesn\'t support HTTP PURGE method.');
    }
    public function addTrace($pattern, $paths = null)
    {
        throw new \RuntimeException(__METHOD__ . ': Micro Application doesn\'t support HTTP TRACE method.');
    }
    public function addConnect($pattern, $paths = null)
    {
        throw new \RuntimeException(__METHOD__ . ': Micro Application doesn\'t support HTTP CONNECT method.');
    }
    public function notFound($handler)
    {
        $this->application->notFound($handler);
    }
    public function mount(\Phalcon\Mvc\Micro\Collection $collection)
    {
        $this->application->mount($collection);
        return $this;
    }
    public function clear()
    {
        throw new \RuntimeException(__METHOD__ . ': you can\'t clear the router in micro application.');
    }
    public function getModuleName()
    {
        throw new \RuntimeException(__METHOD__ . ' doesn\'t support modules.');
    }
    public function getNamespaceName()
    {
        return $this->router->getNamespaceName();
    }
    public function getControllerName()
    {
        return $this->router->getControllerName();
    }
    public function getActionName()
    {
        return $this->router->getActionName();
    }
    public function getParams()
    {
        return $this->router->getParams();
    }
    public function getMatchedRoute()
    {
        return $this->router->getMatchedRoute();
    }
    public function getMatches()
    {
        return $this->router->getMatches();
    }
    public function wasMatched()
    {
        return $this->router->wasMatched();
    }
    public function getRoutes()
    {
        return $this->router->getRoutes();
    }
    public function getRouteById($id)
    {
        return $this->router->getRouteById($id);
    }
    public function getRouteByName($name)
    {
        return $this->router->getRouteByName($name);
    }
    protected function pathToHandler($path)
    {
        if ($path instanceof \Closure) {
            return $path;
        }
        if (is_array($path)) {
            return function (...$args) use($path) {
                $controller = isset($path['controller']) ? $path['controller'] : null;
                $action = isset($path['action']) ? $path['action'] : null;
                $handler = $this->getDI()->get($controller);
                if (!method_exists($handler, $action)) {
                    throw new \RuntimeException('Method : "' . $action . '" doesn\'t exist on "' . $controller . '"');
                }
                if (isset($path['middlewares'])) {
                    foreach ($path['middlewares'] as $middleware => $params) {
                        if (is_string($params)) {
                            $middleware = $params;
                            $params = [];
                        }
                        $middlewares[] = $middleware = new $middleware($controller, ...$params);
                        if ($middleware instanceof \Neutrino\Interfaces\Middleware\BeforeInterface) {
                            if (!isset($event)) {
                                $event = new \Phalcon\Events\Event(\Neutrino\Constants\Events\Micro::BEFORE_EXECUTE_ROUTE, $this);
                            }
                            $result = $middleware->before($event, $this, null);
                            if ($result === false) {
                                return $this->response;
                            }
                        }
                    }
                }
                $value = $handler->{$action}(...$args);
                if (isset($middlewares)) {
                    $event = null;
                    foreach ($middlewares as $middleware) {
                        if ($middleware instanceof \Neutrino\Interfaces\Middleware\AfterInterface) {
                            if (!isset($event)) {
                                $event = new \Phalcon\Events\Event(\Neutrino\Constants\Events\Micro::AFTER_EXECUTE_ROUTE, $this);
                            }
                            $middleware->after($event, $this, null);
                        }
                    }
                }
                return $value;
            };
        }
        throw new \RuntimeException("invalid route paths");
    }
}
namespace Neutrino\Http;

abstract class Controller extends \Phalcon\Mvc\Controller
{
    protected function onConstruct()
    {
        $this->routeMiddleware();
    }
    protected function routeMiddleware()
    {
        $router = $this->router;
        $dispatcher = $this->dispatcher;
        if (!$dispatcher->wasForwarded() && $router->wasMatched()) {
            $actionMethod = $dispatcher->getActionName();
            $route = $router->getMatchedRoute();
            $paths = $route->getPaths();
            if (!empty($paths['middleware'])) {
                $middlewares = $paths['middleware'];
                if (!is_array($middlewares)) {
                    $middlewares = [$middlewares];
                }
                foreach ($middlewares as $key => $middleware) {
                    if (is_int($key)) {
                        $middlewareClass = $middleware;
                        $middlewareParams = [];
                    } else {
                        $middlewareClass = $key;
                        $middlewareParams = !is_array($middlewares) ? [$middleware] : $middleware;
                    }
                    $this->middleware($middlewareClass, ...$middlewareParams)->only([$actionMethod]);
                }
            }
        }
    }
    protected function middleware($middlewareClass, ...$params)
    {
        $middleware = new $middlewareClass(static::class, ...$params);
        $this->{\Neutrino\Constants\Services::APP}->attach($middleware);
        return $middleware;
    }
}
namespace Neutrino\Support\Facades;

abstract class Facade
{
    protected static $di;
    protected static $resolvedInstance;
    public static function clearResolvedInstances()
    {
        self::$resolvedInstance = [];
    }
    public static function setDependencyInjection(\Phalcon\DiInterface $di)
    {
        static::$di = $di;
    }
    public static function swap($instance)
    {
        self::$resolvedInstance[static::getFacadeAccessor()] = $instance;
        static::$di->setShared(static::getFacadeAccessor(), $instance);
    }
    public static function shouldReceive(...$params)
    {
        $name = static::getFacadeAccessor();
        if (static::isMock()) {
            $mock = self::$resolvedInstance[$name];
        } else {
            $mock = static::createFreshMockInstance();
        }
        return $mock->shouldReceive(...$params);
    }
    public static function getFacadeRoot()
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }
    public static function __callStatic($method, $args)
    {
        $instance = static::getFacadeRoot();
        if (!$instance) {
            throw new \RuntimeException('A facade root has not been set.');
        }
        if (empty($args)) {
            return $instance->{$method}();
        } else {
            return $instance->{$method}(...$args);
        }
    }
    protected static function getFacadeAccessor()
    {
        throw new \RuntimeException('Facade does not implement getFacadeAccessor method.');
    }
    protected static function createFreshMockInstance()
    {
        $name = static::getFacadeAccessor();
        self::$resolvedInstance[$name] = $mock = static::createMockInstance();
        $mock->shouldAllowMockingProtectedMethods();
        if (isset(static::$di)) {
            static::$di->setShared($name, $mock);
        }
        return $mock;
    }
    protected static function createMockInstance()
    {
        $class = static::getMockableClass();
        return $class ? \Mockery::mock($class) : \Mockery::mock();
    }
    protected static function isMock()
    {
        $name = static::getFacadeAccessor();
        return isset(self::$resolvedInstance[$name]) && self::$resolvedInstance[$name] instanceof \Mockery\MockInterface;
    }
    protected static function getMockableClass()
    {
        if ($root = static::getFacadeRoot()) {
            return get_class($root);
        }
        return null;
    }
    protected static function resolveFacadeInstance($name)
    {
        if (is_object($name)) {
            return $name;
        }
        if (isset(self::$resolvedInstance[$name])) {
            return self::$resolvedInstance[$name];
        }
        return self::$resolvedInstance[$name] = static::$di->getShared($name);
    }
}
namespace Neutrino\Support\Facades;

class Auth extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Neutrino\Constants\Services::AUTH;
    }
}
namespace Neutrino\Support\Facades;

class Cache extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Neutrino\Constants\Services::CACHE;
    }
}
namespace Neutrino\Support\Facades;

class Flash extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Neutrino\Constants\Services::FLASH;
    }
}
namespace Neutrino\Support\Facades;

class Log extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Neutrino\Constants\Services::LOGGER;
    }
}
namespace Neutrino\Support\Facades;

class Request extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Neutrino\Constants\Services::REQUEST;
    }
}
namespace Neutrino\Support\Facades;

class Response extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Neutrino\Constants\Services::RESPONSE;
    }
}
namespace Neutrino\Support\Facades;

class Router extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Neutrino\Constants\Services::ROUTER;
    }
}
namespace Neutrino\Support\Facades;

class Session extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Neutrino\Constants\Services::SESSION;
    }
}
namespace Neutrino\Support\Facades;

class Url extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Neutrino\Constants\Services::URL;
    }
}
namespace Neutrino\Support\Facades;

class View extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Neutrino\Constants\Services::VIEW;
    }
}
namespace Neutrino\Support\Facades\Micro;

class Router extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Neutrino\Constants\Services::MICRO_ROUTER;
    }
}
namespace Neutrino\Foundation;

class Bootstrap
{
    private $config;
    public function __construct(\Phalcon\Config $config)
    {
        $this->config = $config;
    }
    public function make($kernelClass)
    {
        $kernel = new $kernelClass();
        $kernel->bootstrap($this->config);
        if (APP_DEBUG && APP_ENV !== \Neutrino\Constants\Env::TEST && php_sapi_name() !== 'cli') {
            \Neutrino\Debug\Debugger::register();
        }
        $kernel->registerServices();
        $kernel->registerMiddlewares();
        $kernel->registerListeners();
        $kernel->registerRoutes();
        $kernel->registerModules([]);
        return $kernel;
    }
    public function run(\Neutrino\Interfaces\Kernelable $kernel)
    {
        $kernel->boot();
        if (($response = $kernel->handle()) instanceof \Phalcon\Http\Response) {
            if (!$response->isSent()) {
                $response->send();
            }
        }
        $kernel->terminate();
    }
}
namespace Neutrino\Foundation;

trait Kernelize
{
    public function registerServices()
    {
        $di = $this->getDI();
        foreach ($this->providers as $name => $provider) {
            if (is_string($name)) {
                $service = new \Phalcon\Di\Service($name, $provider, true);
                $di->setRaw($name, $service);
                $di->setRaw($provider, $service);
                continue;
            }
            $prv = new $provider();
            $prv->registering();
        }
    }
    public function registerMiddlewares()
    {
        foreach ($this->middlewares as $middleware) {
            $this->attach(new $middleware());
        }
    }
    public function registerListeners()
    {
        foreach ($this->listeners as $listener) {
            $this->attach(new $listener());
        }
    }
    public function registerModules(array $modules, $merge = false)
    {
        if (!empty($this->modules) || !empty($modules)) {
            parent::registerModules(array_merge($this->modules, $modules), $merge);
        }
    }
    public function attach(\Neutrino\Events\Listener $listener)
    {
        $listener->setDI($this->getDI());
        $listener->setEventsManager($this->getEventsManager());
        $listener->attach();
    }
    public function bootstrap(\Phalcon\Config $config)
    {
        \Neutrino\Error\Handler::setWriters($this->errorHandlerLvl);
        $diClass = $this->dependencyInjection;
        if (empty($diClass)) {
            $di = \Phalcon\Di::getDefault();
        } else {
            \Phalcon\Di::reset();
            $di = new $diClass();
            \Phalcon\Di::setDefault($di);
        }
        $this->setDI($di);
        $di->setShared(\Neutrino\Constants\Services::APP, $this);
        $di->setShared(\Neutrino\Constants\Services::CONFIG, $config);
        $emClass = $this->eventsManagerClass;
        if (!empty($emClass)) {
            $em = new $emClass();
            $this->setEventsManager($em);
            $di->setInternalEventsManager($em);
            $di->setShared(\Neutrino\Constants\Services::EVENTS_MANAGER, $em);
        }
        \Neutrino\Support\Facades\Facade::setDependencyInjection($di);
    }
    public function boot()
    {
        if (!is_null($em = $this->getEventsManager())) {
            $em->fire(\Neutrino\Constants\Events\Kernel::BOOT, $this);
        }
    }
    public function terminate()
    {
        if (!is_null($em = $this->getEventsManager())) {
            $em->fire(\Neutrino\Constants\Events\Kernel::TERMINATE, $this);
        }
    }
}
namespace Neutrino\Foundation\Http;

abstract class Kernel extends \Phalcon\Mvc\Application implements \Neutrino\Interfaces\Kernelable
{
    use \Neutrino\Foundation\Kernelize {
        boot as _boot;
    }
    protected $providers = [];
    protected $middlewares = [];
    protected $listeners = [];
    protected $modules = [];
    protected $dependencyInjection = \Phalcon\Di\FactoryDefault::class;
    protected $eventsManagerClass = \Phalcon\Events\Manager::class;
    protected $errorHandlerLvl = [\Neutrino\Error\Writer\Phplog::class, \Neutrino\Error\Writer\Logger::class, \Neutrino\Error\Writer\Flash::class, \Neutrino\Error\Writer\View::class];
    public function registerRoutes()
    {
        require BASE_PATH . '/routes/http.php';
    }
    public function boot()
    {
        $this->_boot();
        $this->useImplicitView(isset($this->config->view->implicit) ? $this->config->view->implicit : false);
    }
}
namespace Neutrino\Foundation\Micro;

abstract class Kernel extends \Phalcon\Mvc\Micro implements \Neutrino\Interfaces\Kernelable
{
    use \Neutrino\Foundation\Kernelize;
    protected $providers = [];
    protected $middlewares = [];
    protected $listeners = [];
    protected $dependencyInjection = \Phalcon\Di\FactoryDefault::class;
    protected $eventsManagerClass = null;
    protected $errorHandlerLvl = [\Neutrino\Error\Writer\Phplog::class, \Neutrino\Error\Writer\Logger::class, \Neutrino\Error\Writer\Json::class];
    public function registerMiddlewares()
    {
        foreach ($this->middlewares as $middleware) {
            $this->registerMiddleware(new $middleware());
        }
    }
    protected function registerMiddleware(\Neutrino\Micro\Middleware $middleware)
    {
        $on = $middleware->bindOn();
        if ($on == 'before') {
            $this->before($middleware);
        } elseif ($on == 'after') {
            $this->after($middleware);
        } elseif ($on == 'finish') {
            $this->finish($middleware);
        } else {
            throw new \RuntimeException(__METHOD__ . ': ' . get_class($middleware) . ' can\'t bind on "' . $on . '"');
        }
    }
    public final function registerModules(array $modules = [], $merge = false)
    {
    }
    public function registerRoutes()
    {
        require BASE_PATH . '/routes/micro.php';
    }
}
namespace Neutrino\Foundation\Middleware;

abstract class Controller extends \Neutrino\Events\Listener
{
    private $filter = [];
    private $controllerClass;
    public function __construct($controllerClass)
    {
        $this->controllerClass = $controllerClass;
        if ($this instanceof \Neutrino\Interfaces\Middleware\BeforeInterface) {
            $this->listen[\Neutrino\Constants\Events\Dispatch::BEFORE_EXECUTE_ROUTE] = 'checkBefore';
        }
        if ($this instanceof \Neutrino\Interfaces\Middleware\AfterInterface) {
            $this->listen[\Neutrino\Constants\Events\Dispatch::AFTER_EXECUTE_ROUTE] = 'checkAfter';
        }
        if ($this instanceof \Neutrino\Interfaces\Middleware\FinishInterface) {
            $this->listen[\Neutrino\Constants\Events\Dispatch::AFTER_DISPATCH] = 'checkFinish';
        }
    }
    public final function check()
    {
        $dispatcher = $this->dispatcher;
        if ($dispatcher->wasForwarded() && !$dispatcher->isFinished()) {
            return false;
        }
        if ($this->controllerClass !== $dispatcher->getHandlerClass()) {
            return false;
        }
        $action = $dispatcher->getActionName();
        $enable = true;
        if (isset($this->filter['only'])) {
            $enable = isset($this->filter['only'][$action]);
        }
        if ($enable && isset($this->filter['except'])) {
            $enable = !isset($this->filter['except'][$action]);
        }
        return $enable;
    }
    public final function only(array $filters = null)
    {
        return $this->filters('only', $filters);
    }
    public final function except(array $filters = null)
    {
        return $this->filters('except', $filters);
    }
    public final function checkBefore($event, $source, $data)
    {
        if ($this->check()) {
            return $this->before($event, $source, $data);
        }
        return true;
    }
    public final function checkAfter($event, $source, $data)
    {
        if ($this->check()) {
            return $this->after($event, $source, $data);
        }
        return true;
    }
    public final function checkFinish($event, $source, $data)
    {
        if ($this->check()) {
            return $this->finish($event, $source, $data);
        }
        return true;
    }
    private function filters($type, array $filters = null)
    {
        if ($filters === null) {
            return $this;
        }
        if (empty($filters)) {
            $this->filter[$type] = [];
            return $this;
        }
        foreach ($filters as $item) {
            $this->filter[$type][$item] = true;
        }
        return $this;
    }
}
namespace Neutrino;

class Module extends \Phalcon\Mvc\User\Module implements \Phalcon\Mvc\ModuleDefinitionInterface
{
    protected $providers = [];
    public function registerAutoloaders(\Phalcon\DiInterface $dependencyInjector = null)
    {
    }
    public function registerServices(\Phalcon\DiInterface $di)
    {
        foreach ($this->providers as $name => $provider) {
            if (is_string($name)) {
                $service = new \Phalcon\Di\Service($name, $provider, true);
                $di->setRaw($name, $service);
                $di->setRaw($provider, $service);
                continue;
            }
            $prv = new $provider();
            $prv->registering();
        }
        $this->initialise($di);
    }
    public function initialise(\Phalcon\DiInterface $di)
    {
    }
}
namespace Neutrino;

abstract class Model extends \Phalcon\Mvc\Model
{
    protected static $metaDatasClass = [];
    protected static $columnsMapClass = [];
    public function initialize()
    {
        static::$metaDatasClass[static::class] = [\Phalcon\Mvc\Model\MetaData::MODELS_ATTRIBUTES => [], \Phalcon\Mvc\Model\MetaData::MODELS_PRIMARY_KEY => [], \Phalcon\Mvc\Model\MetaData::MODELS_NON_PRIMARY_KEY => [], \Phalcon\Mvc\Model\MetaData::MODELS_NOT_NULL => [], \Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES => [], \Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES_NUMERIC => [], \Phalcon\Mvc\Model\MetaData::MODELS_DATE_AT => [], \Phalcon\Mvc\Model\MetaData::MODELS_DATE_IN => [], \Phalcon\Mvc\Model\MetaData::MODELS_IDENTITY_COLUMN => false, \Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES_BIND => [], \Phalcon\Mvc\Model\MetaData::MODELS_AUTOMATIC_DEFAULT_INSERT => [], \Phalcon\Mvc\Model\MetaData::MODELS_AUTOMATIC_DEFAULT_UPDATE => [], \Phalcon\Mvc\Model\MetaData::MODELS_DEFAULT_VALUES => [], \Phalcon\Mvc\Model\MetaData::MODELS_EMPTY_STRING_VALUES => []];
    }
    public function metaData()
    {
        return static::$metaDatasClass[static::class];
    }
    public function columnMap()
    {
        return static::$columnsMapClass[static::class];
    }
    protected function primary($name, $type, array $options = [])
    {
        static::addColumn($name, $type, isset($options['map']) ? $options['map'] : $name);
        static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_PRIMARY_KEY][] = $name;
        static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_NOT_NULL][] = $name;
        if ((!isset($options['identity']) || $options['identity']) && (!isset($options['multiple']) || !$options['multiple'])) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_IDENTITY_COLUMN] = $name;
        }
        if ((!isset($options['autoIncrement']) || $options['autoIncrement']) && (!isset($options['multiple']) || !$options['multiple'])) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_AUTOMATIC_DEFAULT_INSERT][$name] = true;
        }
    }
    protected function column($name, $type, array $options = [])
    {
        static::addColumn($name, $type, isset($options['map']) ? $options['map'] : $name);
        static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_NON_PRIMARY_KEY][] = $name;
        if (isset($options['nullable']) && $options['nullable']) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_EMPTY_STRING_VALUES][$name] = true;
        } else {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_NOT_NULL][] = $name;
        }
        if (isset($options['default'])) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_DEFAULT_VALUES][$name] = $options['default'];
        }
        if (isset($options['autoInsert']) && $options['autoInsert']) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_AUTOMATIC_DEFAULT_INSERT][$name] = true;
        }
        if (isset($options['autoUpdate']) && $options['autoUpdate']) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_AUTOMATIC_DEFAULT_UPDATE][$name] = true;
        }
    }
    protected function timestampable($name, array $options = [])
    {
        if (isset($options['autoInsert']) && $options['autoInsert'] || isset($options['autoUpdate']) && $options['autoUpdate']) {
            throw new \RuntimeException('Model: A timestampable field can\'t have autoInsert or autoUpdate.');
        }
        self::column($name, isset($options['type']) ? $options['type'] : \Phalcon\Db\Column::TYPE_DATETIME, $options);
        $params = [];
        if (!isset($options['default']) && isset($options['insert']) && $options['insert']) {
            $params['beforeValidationOnCreate'] = ['field' => $name, 'format' => isset($options['format']) ? $options['format'] : DATE_ATOM];
        }
        if (isset($options['update']) && $options['update']) {
            $params['beforeValidationOnUpdate'] = ['field' => $name, 'format' => isset($options['format']) ? $options['format'] : DATE_ATOM];
        }
        if (empty($params)) {
            throw new \RuntimeException('Model: A timestampable field needs to have at least insert or update.');
        }
        $this->addBehavior(new \Phalcon\Mvc\Model\Behavior\Timestampable($params));
    }
    protected function timestamps()
    {
        $this->timestampable('created_at', ['insert' => true]);
        $this->timestampable('updated_at', ['update' => true]);
    }
    protected function softDeletable($name, array $options = [])
    {
        if (isset($options['autoInsert']) && $options['autoInsert'] || isset($options['autoUpdate']) && $options['autoUpdate']) {
            throw new \RuntimeException('Model: A timestampable field can\'t have autoInsert or autoUpdate.');
        }
        self::column($name, isset($options['type']) ? $options['type'] : \Phalcon\Db\Column::TYPE_BOOLEAN, $options);
        $this->addBehavior(new \Phalcon\Mvc\Model\Behavior\SoftDelete(['field' => $name, 'value' => isset($options['value']) ? $options['value'] : true]));
    }
    protected function softDelete()
    {
        $this->softDeletable('deleted');
    }
    private static function addColumn($name, $type, $map)
    {
        static::$columnsMapClass[static::class][$name] = $map;
        static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_ATTRIBUTES][] = $name;
        static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES][$name] = $type;
        static::describeColumnType($name, $type);
    }
    private static function describeColumnType($name, $type)
    {
        if ($type === null) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES_BIND][$name] = \Phalcon\Db\Column::BIND_PARAM_NULL;
        } elseif ($type === \Phalcon\Db\Column::TYPE_BIGINTEGER || $type === \Phalcon\Db\Column::TYPE_INTEGER || $type === \Phalcon\Db\Column::TYPE_TIMESTAMP) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES_BIND][$name] = \Phalcon\Db\Column::BIND_PARAM_INT;
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES_NUMERIC][$name] = true;
        } elseif ($type === \Phalcon\Db\Column::TYPE_DECIMAL || $type === \Phalcon\Db\Column::TYPE_FLOAT || $type === \Phalcon\Db\Column::TYPE_DOUBLE) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES_BIND][$name] = \Phalcon\Db\Column::BIND_PARAM_DECIMAL;
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES_NUMERIC][$name] = true;
        } elseif ($type === \Phalcon\Db\Column::TYPE_JSON || $type === \Phalcon\Db\Column::TYPE_TEXT || $type === \Phalcon\Db\Column::TYPE_CHAR || $type === \Phalcon\Db\Column::TYPE_VARCHAR || $type === \Phalcon\Db\Column::TYPE_DATE || $type === \Phalcon\Db\Column::TYPE_DATETIME) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES_BIND][$name] = \Phalcon\Db\Column::BIND_PARAM_STR;
        } elseif ($type === \Phalcon\Db\Column::TYPE_BLOB || $type === \Phalcon\Db\Column::TYPE_JSONB || $type === \Phalcon\Db\Column::TYPE_MEDIUMBLOB || $type === \Phalcon\Db\Column::TYPE_TINYBLOB || $type === \Phalcon\Db\Column::TYPE_LONGBLOB) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES_BIND][$name] = \Phalcon\Db\Column::BIND_PARAM_BLOB;
        } elseif ($type === \Phalcon\Db\Column::TYPE_BOOLEAN) {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES_BIND][$name] = \Phalcon\Db\Column::BIND_PARAM_BOOL;
        } else {
            static::$metaDatasClass[static::class][\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES_BIND][$name] = \Phalcon\Db\Column::BIND_SKIP;
        }
    }
}
namespace App\Kernels\Http;

class Kernel extends \Neutrino\Foundation\Http\Kernel
{
    protected $providers = [\Neutrino\Providers\Logger::class, \Neutrino\Providers\Url::class, \Neutrino\Providers\Flash::class, \Neutrino\Providers\Session::class, \Neutrino\Providers\Http\Router::class, \Neutrino\Providers\View::class, \Neutrino\Providers\Http\Dispatcher::class, \Neutrino\Providers\Database::class, \Neutrino\Providers\Cache::class, \Neutrino\Providers\Auth::class, \App\Core\Providers\Example::class];
    protected $middlewares = [];
    protected $listeners = [];
    protected $modules = ['Frontend' => ['className' => \App\Kernels\Http\Modules\Frontend\Module::class, 'path' => BASE_PATH . '/app/Kernels/Http/Modules/Frontend/Module.php'], 'Backend' => ['className' => \App\Kernels\Http\Modules\Backend\Module::class, 'path' => BASE_PATH . '/app/Kernels/Http/Modules/Backend/Module.php']];
}
namespace App\Kernels\Http\Controllers;

class ControllerBase extends \Neutrino\Http\Controller
{
    public function initialize()
    {
        $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
    }
    protected function onConstruct()
    {
    }
}
namespace App\Kernels\Http\Controllers;

class ControllerJson extends \Neutrino\Http\Controller
{
    public function initialize()
    {
        $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_NO_RENDER);
    }
    protected function onConstruct()
    {
    }
}
namespace App\Kernels\Micro;

class Kernel extends \Neutrino\Foundation\Micro\Kernel implements \Neutrino\Interfaces\Kernelable
{
    protected $providers = [\Neutrino\Providers\Logger::class, \Neutrino\Providers\Url::class, \Neutrino\Providers\Session::class, \Neutrino\Providers\Http\Router::class, \Neutrino\Providers\Http\Dispatcher::class, \Neutrino\Providers\Database::class, \Neutrino\Providers\Cache::class, \Neutrino\Providers\Micro\Router::class, \App\Core\Providers\Example::class];
    protected $middlewares = [];
    protected $listeners = [];
}
namespace App\Kernels\Micro\Controllers;

class MicroController extends \Phalcon\Mvc\Controller
{
    public function indexAction()
    {
        $this->response->setStatusCode(200);
        $this->response->setJsonContent(['action' => 'index']);
        return $this->response;
    }
}
namespace App\Kernels\Http\Controllers;

class HomeController extends \App\Kernels\Http\Controllers\ControllerBase
{
    public function indexAction()
    {
        $this->view->render('home', 'index');
    }
}
namespace App\Kernels\Http\Modules\Frontend;

class Module extends \Neutrino\Module
{
    protected $providers = [];
    public function initialise(\Phalcon\DiInterface $di)
    {
    }
}
namespace App\Kernels\Http\Modules\Frontend\Controllers;

class ControllerBase extends \Neutrino\Http\Controller
{
    public function initialize()
    {
        $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
    }
}
namespace App\Kernels\Http\Modules\Frontend\Controllers;

class IndexController extends \App\Kernels\Http\Modules\Frontend\Controllers\ControllerBase
{
    public function indexAction()
    {
        $this->view->render('front/index', 'index');
    }
}
