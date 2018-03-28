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
        if (file_exists(BASE_PATH . '/bootstrap/compile/http-routes.php')) {
            require BASE_PATH . '/bootstrap/compile/http-routes.php';
        } else {
            require BASE_PATH . '/routes/http.php';
        }
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
    protected $modules = ['Frontend' => ['className' => \App\Kernels\Http\Modules\Frontend\Module::class, 'path' => BASE_PATH . '/app/Kernels/Http/Modules/Frontend/Module.php'], 'Backend' => ['className' => \App\Kernels\Http\Modules\Backend\Module::class, 'path' => BASE_PATH . '/app/Kernels/Http/Modules/Backend/Module.php'], 'Example' => ['className' => \App\Kernels\Http\Modules\Example\Module::class, 'path' => BASE_PATH . '/app/Kernels/Http/Modules/Example/Module.php']];
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
        $this->response->setJsonContent(['controller' => __CLASS__, 'action' => __FUNCTION__]);
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
namespace Neutrino;

class Dotconst
{
    private static $extensions = [\Neutrino\Dotconst\Extensions\PhpDir::class => \Neutrino\Dotconst\Extensions\PhpDir::class, \Neutrino\Dotconst\Extensions\PhpEnv::class => \Neutrino\Dotconst\Extensions\PhpEnv::class, \Neutrino\Dotconst\Extensions\PhpConst::class => \Neutrino\Dotconst\Extensions\PhpConst::class];
    public static function addExtension($extension)
    {
        return self::$extensions[$extension] = $extension;
    }
    public static function getExtensions()
    {
        foreach (self::$extensions as $extension => $class) {
            if (is_string($class)) {
                self::$extensions[$extension] = new $class();
            }
        }
        return self::$extensions;
    }
    public static function load($path, $compilePath = null)
    {
        if (!$compilePath || !\Neutrino\Dotconst\Loader::fromCompile($compilePath)) {
            foreach (\Neutrino\Dotconst\Loader::fromFiles($path) as $const => $value) {
                if (defined($const)) {
                    throw new \Neutrino\Dotconst\Exception\RuntimeException('Constant ' . $const . ' already defined');
                }
                define($const, $value);
            }
        }
    }
}
namespace Neutrino;

class Version extends \Phalcon\Version
{
    protected static function _getVersion()
    {
        return [1, 3, 0, 1, 0];
    }
}
namespace Neutrino\Dotconst;

class Compile
{
    public static function compile($basePath, $compilePath)
    {
        $extensions = \Neutrino\Dotconst::getExtensions();
        $raw = \Neutrino\Dotconst\Loader::loadRaw($basePath);
        $config = \Neutrino\Dotconst\Loader::fromFiles($basePath);
        $r = fopen($compilePath . '/consts.php', 'w');
        if ($r === false) {
            throw new \Neutrino\Dotconst\Exception\InvalidFileException('Can\'t create file : ' . $compilePath);
        }
        fwrite($r, "<?php" . PHP_EOL);
        $nested = [];
        foreach ($raw as $const => $value) {
            foreach ($extensions as $k => $extension) {
                if (is_string($extension)) {
                    $extensions[$k] = $extension = new $extension();
                }
                if ($extension->identify($value)) {
                    fwrite($r, "define('{$const}', " . $extension->compile($value, $basePath, $compilePath) . ");" . PHP_EOL);
                    continue 2;
                }
            }
            if (preg_match('#^@\\{(\\w+)\\}@?#', $value, $match)) {
                $key = strtoupper($match[1]);
                $value = preg_replace('#^@\\{(\\w+)\\}@?#', '', $value);
                $draw = '';
                $require = null;
                if (isset($config[$key])) {
                    $draw .= $key;
                    $require = $key;
                } else {
                    $draw .= $match[1];
                }
                if (!empty($value)) {
                    $draw .= " . '{$value}'";
                }
                $nested[$const] = ['draw' => $draw, 'require' => $require];
                continue;
            }
            fwrite($r, "define('{$const}', " . var_export($value, true) . ");" . PHP_EOL);
        }
        $nested = \Neutrino\Dotconst\Helper::nestedConstSort($nested);
        foreach ($nested as $const => $item) {
            fwrite($r, "define('{$const}', {$item['draw']});" . PHP_EOL);
        }
        fclose($r);
    }
}
namespace Neutrino\Dotconst;

class Helper
{
    public static function loadIniFile($file)
    {
        $config = parse_ini_file($file, true, INI_SCANNER_TYPED);
        if ($config === false) {
            throw new \Neutrino\Dotconst\Exception\InvalidFileException('Failed parse file : ' . $file);
        }
        return array_change_key_case(self::definable($config), CASE_UPPER);
    }
    public static function mergeConfigWithFile($config, $file)
    {
        foreach (self::loadIniFile($file) as $section => $value) {
            if (isset($config[$section]) && is_array($value)) {
                $config[$section] = array_merge($config[$section], $value);
            } else {
                $config[$section] = $value;
            }
        }
        return $config;
    }
    public static function nestedConstSort($nested)
    {
        $stack = 0;
        $sort = function ($a, $b) use($nested, &$stack, &$sort) {
            if ($stack++ >= 128) {
                throw new \Neutrino\Dotconst\Exception\CycleNestedConstException();
            }
            if (is_null($a['require']) && is_null($b['require'])) {
                $return = 0;
            } elseif (is_null($a['require'])) {
                $return = -1;
            } elseif (is_null($b['require'])) {
                $return = 1;
            } elseif (isset($nested[$a['require']]) && isset($nested[$b['require']])) {
                $return = $sort($nested[$a['require']], $nested[$b['require']]);
            } elseif (isset($nested[$a['require']]) && !isset($nested[$b['require']])) {
                $return = 1;
            } elseif (!isset($nested[$a['require']]) && isset($nested[$b['require']])) {
                $return = -1;
            } else {
                $return = 0;
            }
            $stack--;
            return $return;
        };
        if (PHP_VERSION_ID < 70000) {
            $stable_uasort = function (&$array, $sort) use(&$stable_uasort) {
                if (count($array) < 2) {
                    return;
                }
                $halfway = count($array) / 2;
                $array1 = array_slice($array, 0, $halfway, true);
                $array2 = array_slice($array, $halfway, null, true);
                $stable_uasort($array1, $sort);
                $stable_uasort($array2, $sort);
                if (call_user_func($sort, end($array1), reset($array2)) < 1) {
                    $array = $array1 + $array2;
                    return;
                }
                $array = [];
                reset($array1);
                reset($array2);
                while (current($array1) && current($array2)) {
                    if (call_user_func($sort, current($array1), current($array2)) < 1) {
                        $array[key($array1)] = current($array1);
                        next($array1);
                    } else {
                        $array[key($array2)] = current($array2);
                        next($array2);
                    }
                }
                while (current($array1)) {
                    $array[key($array1)] = current($array1);
                    next($array1);
                }
                while (current($array2)) {
                    $array[key($array2)] = current($array2);
                    next($array2);
                }
                return;
            };
            $stable_uasort($nested, $sort);
        } else {
            uasort($nested, $sort);
        }
        return $nested;
    }
    private static function definable($config)
    {
        $flatten = [];
        foreach ($config as $section => $value) {
            if (is_array($value)) {
                $value = self::definable($value);
                foreach ($value as $k => $v) {
                    $flatten["{$section}_{$k}"] = $v;
                }
            } else {
                $flatten[$section] = $value;
            }
        }
        return $flatten;
    }
    public static function normalizePath($path)
    {
        if (empty($path)) {
            return '';
        }
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        $parts = explode('/', $path);
        $safe = [];
        foreach ($parts as $idx => $part) {
            if ($idx == 0 && empty($part)) {
                $safe[] = '';
            } elseif (trim($part) == "" || $part == '.') {
            } elseif ('..' == $part) {
                if (null === array_pop($safe) || empty($safe)) {
                    $safe[] = '';
                }
            } else {
                $safe[] = $part;
            }
        }
        if (count($safe) === 1 && $safe[0] === '') {
            return DIRECTORY_SEPARATOR;
        }
        return implode(DIRECTORY_SEPARATOR, $safe);
    }
}
namespace Neutrino\Interfaces\Auth;

interface Authenticable
{
    public function getAuthIdentifier();
    public function getAuthPassword();
    public function getRememberToken();
    public function setRememberToken($value);
    public static function getAuthIdentifierName();
    public static function getAuthPasswordName();
    public static function getRememberTokenName();
}
namespace Neutrino\Interfaces\Auth;

interface Authorizable
{
    public function getRole();
}
namespace Neutrino\Interfaces\Repositories;

interface RepositoryInterface
{
    public function all();
    public function count(array $params = null);
    public function find(array $params = [], array $order = null, $limit = null, $offset = null);
    public function first(array $params = [], array $order = null);
    public function firstOrNew(array $params = [], $create = false, $withTransaction = false);
    public function firstOrCreate(array $params = [], $withTransaction = false);
    public function create($value, $withTransaction = true);
    public function save($value, $withTransaction = true);
    public function update($value, $withTransaction = true);
    public function delete($value, $withTransaction = true);
    public function each(array $params = [], $start = null, $end = null, $pad = 100, array $order = null);
}
namespace Neutrino\Repositories;

abstract class Repository extends \Phalcon\Di\Injectable implements \Neutrino\Interfaces\Repositories\RepositoryInterface
{
    protected $modelClass;
    protected $messages = [];
    public function __construct($modelClass = null)
    {
        $this->modelClass = is_null($modelClass) ? $this->modelClass : $modelClass;
        if (empty($this->modelClass)) {
            throw new \RuntimeException(static::class . ' must have a $modelClass.');
        }
    }
    public function all()
    {
        $class = $this->modelClass;
        return $class::find();
    }
    public function count(array $params = null)
    {
        $class = $this->modelClass;
        return $class::count($this->paramsToCriteria($params));
    }
    public function find(array $params = [], array $order = null, $limit = null, $offset = null)
    {
        $class = $this->modelClass;
        return $class::find($this->paramsToCriteria($params, $order, $limit, $offset));
    }
    public function first(array $params = [], array $order = null)
    {
        $class = $this->modelClass;
        return $class::findFirst($this->paramsToCriteria($params, $order));
    }
    public function average($column, array $params = [], array $order = null, $limit = null, $offset = null)
    {
        $class = $this->modelClass;
        $parameters = $this->paramsToCriteria($params, $order, $limit, $offset);
        $parameters['column'] = $column;
        return $class::average($parameters);
    }
    public function minimum($column, array $params = [], array $order = null, $limit = null, $offset = null)
    {
        $class = $this->modelClass;
        $parameters = $this->paramsToCriteria($params, $order, $limit, $offset);
        $parameters['column'] = $column;
        return $class::minimum($parameters);
    }
    public function maximum($column, array $params = [], array $order = null, $limit = null, $offset = null)
    {
        $class = $this->modelClass;
        $parameters = $this->paramsToCriteria($params, $order, $limit, $offset);
        $parameters['column'] = $column;
        return $class::maximum($parameters);
    }
    public function firstOrNew(array $params = [], $create = false, $withTransaction = false)
    {
        $model = $this->first($params);
        if ($model === false) {
            $class = $this->modelClass;
            $model = new $class();
            foreach ($params as $key => $param) {
                $model->{$key} = $param;
            }
            if ($create && $this->create($model, $withTransaction) === false) {
                throw new \Neutrino\Repositories\Exceptions\TransactionException(__METHOD__ . ': can\'t create model : ' . get_class($model));
            }
        }
        return $model;
    }
    public function firstOrCreate(array $params = [], $withTransaction = false)
    {
        return $this->firstOrNew($params, true, $withTransaction);
    }
    public function each(array $params = [], $start = null, $end = null, $pad = 100, array $order = null)
    {
        if (is_null($start)) {
            $start = 0;
        }
        if (is_null($end)) {
            $end = INF;
        }
        if ($start >= $end) {
            return;
        }
        $class = $this->modelClass;
        $nb = ceil(($end - $start) / $pad);
        $idx = 0;
        $page = 0;
        do {
            $finish = true;
            $models = $class::find($this->paramsToCriteria($params, $order, $pad, $start + $pad * $page));
            foreach ($models as $model) {
                $finish = false;
                (yield $idx => $model);
                $idx++;
            }
            $page++;
            if ($page >= $nb) {
                $finish = true;
            }
        } while (!$finish);
    }
    public function create($value, $withTransaction = true)
    {
        if ($withTransaction) {
            return $this->transactionCall(is_array($value) ? $value : [$value], __FUNCTION__);
        }
        return $this->basicCall(is_array($value) ? $value : [$value], __FUNCTION__);
    }
    public function save($value, $withTransaction = true)
    {
        if ($withTransaction) {
            return $this->transactionCall(is_array($value) ? $value : [$value], __FUNCTION__);
        }
        return $this->basicCall(is_array($value) ? $value : [$value], __FUNCTION__);
    }
    public function update($value, $withTransaction = true)
    {
        if ($withTransaction) {
            return $this->transactionCall(is_array($value) ? $value : [$value], __FUNCTION__);
        }
        return $this->basicCall(is_array($value) ? $value : [$value], __FUNCTION__);
    }
    public function delete($value, $withTransaction = true)
    {
        if ($withTransaction) {
            return $this->transactionCall(is_array($value) ? $value : [$value], __FUNCTION__);
        }
        return $this->basicCall(is_array($value) ? $value : [$value], __FUNCTION__);
    }
    public function getMessages()
    {
        return $this->messages;
    }
    protected function paramsToCriteria(array $params = null, array $orders = null, $limit = null, $offset = null)
    {
        $criteria = [];
        if (!empty($params)) {
            $clauses = [];
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    if (isset($value['operator']) && array_key_exists('value', $value)) {
                        if (is_array($value['value'])) {
                            $clauses[] = "{$key} {$value['operator']} ({{$key}:array})";
                        } else {
                            $clauses[] = "{$key} {$value['operator']} :{$key}:";
                        }
                        $params[$key] = $value['value'];
                    } else {
                        $clauses[] = "{$key} IN ({{$key}:array})";
                    }
                } elseif (is_string($value)) {
                    $clauses[] = "{$key} LIKE :{$key}:";
                } else {
                    $clauses[] = "{$key} = :{$key}:";
                }
            }
            $criteria = [implode(' AND ', $clauses), 'bind' => $params];
        }
        if (!empty($orders)) {
            $_orders = [];
            foreach ($orders as $key => $order) {
                if (is_int($key)) {
                    $key = $order;
                    $order = 'ASC';
                }
                $_orders[] = "{$key} {$order}";
            }
            $criteria['order'] = implode(', ', $_orders);
        }
        if (isset($limit)) {
            $criteria['limit'] = $limit;
        }
        if (isset($offset)) {
            $criteria['offset'] = $offset;
        }
        return $criteria;
    }
    protected function basicCall(array $values, $method)
    {
        try {
            $this->messages = [];
            foreach ($values as $item) {
                if ($item->{$method}() === false) {
                    $this->messages = array_merge($this->messages, $item->getMessages());
                }
            }
            if (!empty($this->messages)) {
                throw new \Neutrino\Repositories\Exceptions\TransactionException(get_class(\Neutrino\Support\Arr::fetch($values, 0)) . ':' . $method . ': failed. Show ' . static::class . '::getMessages().');
            }
        } catch (\Exception $e) {
            $this->messages[] = $e->getMessage();
            return false;
        }
        return true;
    }
    protected function transactionCall(array $values, $method)
    {
        if (empty($values)) {
            return true;
        }
        $tx = $this->getDI()->getShared(\Phalcon\Mvc\Model\Transaction\Manager::class)->get();
        try {
            $this->messages = [];
            foreach ($values as $item) {
                $item->setTransaction($tx);
                if ($item->{$method}() === false) {
                    $this->messages = array_merge($this->messages, $item->getMessages());
                }
            }
            if (!empty($this->messages)) {
                throw new \Neutrino\Repositories\Exceptions\TransactionException(get_class(\Neutrino\Support\Arr::fetch($values, 0)) . ':' . $method . ': failed. Show ' . static::class . '::getMessages().');
            }
            if ($tx->commit() === false) {
                throw new \Neutrino\Repositories\Exceptions\TransactionException('Commit failed.');
            }
            return true;
        } catch (\Exception $e) {
            $tx->rollback();
            $this->messages[] = $e->getMessage();
            if (!is_null($messages = $tx->getMessages())) {
                $this->messages = array_merge($this->messages, $messages);
            }
            return false;
        }
    }
}
namespace Neutrino\Repositories\Exceptions;

class TransactionException extends \Phalcon\Exception
{
}
namespace Neutrino\Providers;

class Annotations extends \Neutrino\Support\SimpleProvider
{
    protected $class = \Phalcon\Annotations\Adapter\Memory::class;
    protected $name = \Neutrino\Constants\Services::ANNOTATIONS;
    protected $shared = true;
    protected $aliases = [\Phalcon\Annotations\Adapter\Memory::class];
}
namespace Neutrino\Providers;

class Auth extends \Neutrino\Support\SimpleProvider
{
    protected $class = \Neutrino\Auth\Manager::class;
    protected $name = \Neutrino\Constants\Services::AUTH;
    protected $shared = true;
    protected $aliases = [\Neutrino\Auth\Manager::class];
}
namespace Neutrino\Providers;

class Cookies extends \Neutrino\Support\SimpleProvider
{
    protected $class = \Phalcon\Http\Response\Cookies::class;
    protected $name = \Neutrino\Constants\Services::COOKIES;
    protected $shared = true;
    protected $aliases = [\Phalcon\Http\Response\Cookies::class];
}
namespace Neutrino\Providers;

class Crypt extends \Neutrino\Support\SimpleProvider
{
    protected $class = \Phalcon\Crypt::class;
    protected $name = \Neutrino\Constants\Services::CRYPT;
    protected $shared = true;
    protected $aliases = [\Phalcon\Crypt::class];
}
namespace Neutrino\Providers;

class Escaper extends \Neutrino\Support\SimpleProvider
{
    protected $class = \Phalcon\Escaper::class;
    protected $name = \Neutrino\Constants\Services::ESCAPER;
    protected $shared = true;
    protected $aliases = [\Phalcon\Escaper::class];
}
namespace Neutrino\Providers;

class Filter extends \Neutrino\Support\SimpleProvider
{
    protected $class = \Phalcon\Filter::class;
    protected $name = \Neutrino\Constants\Services::FILTER;
    protected $shared = true;
    protected $aliases = [\Phalcon\Filter::class];
}
namespace Neutrino\Providers;

class FlashSession extends \Neutrino\Support\Provider
{
    protected $name = \Neutrino\Constants\Services::FLASH_SESSION;
    protected $shared = true;
    protected $aliases = [\Phalcon\Flash\Session::class];
    protected function register()
    {
        $flash = new \Phalcon\Flash\Session();
        return $flash;
    }
}
namespace Neutrino\Providers;

class Model extends \Phalcon\Di\Injectable implements \Neutrino\Interfaces\Providable
{
    public function registering()
    {
        $di = $this->getDI();
        $modelManagerService = new \Phalcon\Di\Service(\Neutrino\Constants\Services::MODELS_MANAGER, \Phalcon\Mvc\Model\Manager::class, true);
        $di->setRaw(\Neutrino\Constants\Services::MODELS_MANAGER, $modelManagerService);
        $di->setRaw(\Phalcon\Mvc\Model\Manager::class, $modelManagerService);
        $modelMetadataService = new \Phalcon\Di\Service(\Neutrino\Constants\Services::MODELS_METADATA, \Phalcon\Mvc\Model\Metadata\Memory::class, true);
        $di->setRaw(\Neutrino\Constants\Services::MODELS_METADATA, $modelMetadataService);
        $di->setRaw(\Phalcon\Mvc\Model\Metadata\Memory::class, $modelMetadataService);
        $transactionManagerService = new \Phalcon\Di\Service(\Neutrino\Constants\Services::TRANSACTION_MANAGER, \Phalcon\Mvc\Model\Transaction\Manager::class, true);
        $di->setRaw(\Neutrino\Constants\Services::TRANSACTION_MANAGER, $transactionManagerService);
        $di->setRaw(\Phalcon\Mvc\Model\Transaction\Manager::class, $transactionManagerService);
    }
}
namespace Neutrino\Providers;

class ModelManager extends \Neutrino\Support\SimpleProvider
{
    protected $class = \Phalcon\Mvc\Model\Manager::class;
    protected $name = \Neutrino\Constants\Services::MODELS_MANAGER;
    protected $shared = true;
    protected $aliases = [\Phalcon\Mvc\Model\Manager::class];
}
namespace Neutrino\Providers;

class ModelTransactionManager extends \Neutrino\Support\SimpleProvider
{
    protected $class = \Phalcon\Mvc\Model\Transaction\Manager::class;
    protected $name = \Neutrino\Constants\Services::TRANSACTION_MANAGER;
    protected $shared = true;
    protected $aliases = [\Phalcon\Mvc\Model\Transaction\Manager::class];
}
namespace Neutrino\Providers;

class ModelsMetaData extends \Neutrino\Support\SimpleProvider
{
    protected $class = \Phalcon\Mvc\Model\Metadata\Memory::class;
    protected $name = \Neutrino\Constants\Services::MODELS_METADATA;
    protected $shared = true;
    protected $aliases = [\Phalcon\Mvc\Model\Metadata\Memory::class];
}
namespace Neutrino\Providers;

class Security extends \Neutrino\Support\SimpleProvider
{
    protected $class = \Phalcon\Security::class;
    protected $name = \Neutrino\Constants\Services::SECURITY;
    protected $shared = true;
    protected $aliases = [\Phalcon\Security::class];
}
namespace Neutrino\Support\DesignPatterns;

abstract class Singleton extends \Phalcon\Di\Injectable
{
    private static $instance;
    protected function __construct()
    {
    }
    private final function __clone()
    {
        throw new \RuntimeException('Try to clone Singleton instance.');
    }
    public static function instance()
    {
        if (self::$instance == null) {
            self::$instance = new static();
        }
        return self::$instance;
    }
}
namespace Neutrino\Support\DesignPatterns;

abstract class Strategy extends \Phalcon\Di\Injectable implements \Neutrino\Support\DesignPatterns\Strategy\StrategyInterface
{
    use \Neutrino\Support\DesignPatterns\Strategy\StrategyTrait;
}
namespace Neutrino\Support\DesignPatterns\Strategy;

trait MagicCallStrategyTrait
{
    public function __call($name, $arguments)
    {
        $use = $this->uses();
        if (!method_exists($use, $name)) {
            throw new \BadMethodCallException(get_class($use) . ' doesn\\t have ' . $name . ' method.');
        }
        return $use->{$name}(...$arguments);
    }
}
namespace Neutrino\Support\DesignPatterns\Strategy;

interface StrategyInterface
{
    public function uses($use = null);
}
namespace Neutrino\Support\DesignPatterns\Strategy;

trait StrategyTrait
{
    protected $supported;
    protected $default;
    private $adapters = [];
    private $adapter;
    public function uses($use = null)
    {
        if (!empty($use)) {
            if (!in_array($use, $this->supported)) {
                throw new \RuntimeException(static::class . " : {$use} unsupported. ");
            }
            if (!isset($this->adapters[$use])) {
                $this->adapters[$use] = $this->make($use);
            }
            $this->adapter = $this->adapters[$use];
        }
        if (empty($this->adapter)) {
            $this->adapter = $this->adapters[$this->default] = $this->make($this->default);
        }
        return $this->adapter;
    }
    protected function make($use)
    {
        return new $use();
    }
}
namespace Neutrino\Support\Fluent;

interface Fluentable extends \ArrayAccess, \Iterator, \JsonSerializable
{
    public function __construct($attributes);
    public function get($key, $default = null);
    public function getAttributes();
    public function toArray();
    public function jsonSerialize();
    public function toJson($options = 0);
    public function offsetExists($offset);
    public function offsetGet($offset);
    public function offsetSet($offset, $value);
    public function offsetUnset($offset);
    public function __call($method, $parameters);
    public function __get($key);
    public function __set($key, $value);
    public function __isset($key);
    public function __unset($key);
}
namespace Neutrino\Support\Fluent;

trait Fluentize
{
    protected $attributes = [];
    public function __construct($attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }
    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        return \Neutrino\Support\Obj::value($default);
    }
    public function getAttributes()
    {
        return $this->attributes;
    }
    public function toArray()
    {
        return $this->attributes;
    }
    public function jsonSerialize()
    {
        return $this->toArray();
    }
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
    public function offsetExists($offset)
    {
        return isset($this->attributes[$offset]);
    }
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }
    public function offsetSet($offset, $value)
    {
        $this->attributes[$offset] = $value;
    }
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }
    public function current()
    {
        return current($this->attributes);
    }
    public function next()
    {
        next($this->attributes);
    }
    public function key()
    {
        return key($this->attributes);
    }
    public function valid()
    {
        return !is_null(key($this->attributes));
    }
    public function rewind()
    {
        reset($this->attributes);
    }
    public function __call($method, $parameters)
    {
        $this->attributes[$method] = count($parameters) > 0 ? $parameters[0] : true;
        return $this;
    }
    public function __get($key)
    {
        return $this->get($key);
    }
    public function __set($key, $value)
    {
        $this->offsetSet($key, $value);
    }
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }
}
namespace Neutrino\Support\Traits;

trait InjectionAwareTrait
{
    protected $_di;
    public function setDI(\Phalcon\DiInterface $dependencyInjector)
    {
        $this->_di = $dependencyInjector;
    }
    public function getDI()
    {
        if (!isset($this->_di)) {
            $this->setDI(\Phalcon\Di::getDefault());
        }
        return $this->_di;
    }
    public function __get($name)
    {
        if (!isset($this->{$name})) {
            $di = $this->getDI();
            if (!$di->has($name)) {
                throw new \RuntimeException("{$name} not found in dependency injection.");
            }
            $this->{$name} = $di->getShared($name);
        }
        return $this->{$name};
    }
}
namespace Neutrino\Support\Traits;

trait Macroable
{
    protected static $macros = [];
    public static function macro($name, callable $macro)
    {
        static::$macros[$name] = $macro;
    }
    public static function hasMacro($name)
    {
        return isset(static::$macros[$name]);
    }
    public static function __callStatic($method, $parameters)
    {
        if (static::hasMacro($method)) {
            if (static::$macros[$method] instanceof \Closure) {
                $closure = \Closure::bind(static::$macros[$method], null, get_called_class());
                return $closure(...$parameters);
            } else {
                return call_user_func_array(static::$macros[$method], $parameters);
            }
        }
        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            $macro = static::$macros[$method];
            if ($macro instanceof \Closure) {
                $closure = $macro->bindTo($this, get_class($this));
                return $closure(...$parameters);
            } else {
                return call_user_func_array($macro, $parameters);
            }
        }
        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}
namespace Neutrino\Support\Model;

trait Eachable
{
    public static function each(array $criteria = null, $start = null, $end = null, $pad = 100)
    {
        if (is_null($start)) {
            $start = 0;
        }
        if (is_null($end)) {
            $end = INF;
        }
        if ($start >= $end) {
            return;
        }
        if (empty($criteria['limit'])) {
            $criteria['limit'] = $pad;
        } else {
            $pad = $criteria['limit'];
        }
        $nb = ceil(($end - $start) / $pad);
        $idx = 0;
        $page = 0;
        do {
            $finish = true;
            $criteria['offset'] = $start + $pad * $page;
            $models = self::find($criteria);
            foreach ($models as $model) {
                $finish = false;
                (yield $idx => $model);
                $idx++;
            }
            $page++;
            if ($page >= $nb) {
                $finish = true;
            }
        } while (!$finish);
    }
}
namespace Neutrino\Support;

class Arr
{
    public static function accessible($value)
    {
        return is_array($value) || $value instanceof \ArrayAccess;
    }
    public static function add($array, $key, $value)
    {
        if (is_null(self::get($array, $key))) {
            self::set($array, $key, $value);
        }
        return $array;
    }
    public static function collapse($array)
    {
        $results = [];
        foreach ($array as $values) {
            if (!self::accessible($values)) {
                continue;
            }
            $results = array_merge($results, $values);
        }
        return $results;
    }
    public static function divide($array)
    {
        return [array_keys($array), array_values($array)];
    }
    public static function dot($array, $prepend = '')
    {
        $results = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, self::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }
        return $results;
    }
    public static function except($array, $keys)
    {
        self::forget($array, $keys);
        return $array;
    }
    public static function exists($array, $key)
    {
        if (is_array($array)) {
            return isset($array[$key]) || array_key_exists($key, $array);
        }
        return $array->offsetExists($key);
    }
    public static function first($array, $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($array) ? \Neutrino\Support\Obj::value($default) : reset($array);
        }
        foreach ($array as $key => $value) {
            if (call_user_func($callback, $key, $value)) {
                return $value;
            }
        }
        return \Neutrino\Support\Obj::value($default);
    }
    public static function last($array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($array) ? \Neutrino\Support\Obj::value($default) : end($array);
        }
        return self::first(array_reverse($array, true), $callback, $default);
    }
    public static function flatten($array, $depth = INF)
    {
        return array_reduce($array, function ($result, $item) use($depth) {
            if (!is_array($item)) {
                return array_merge($result, [$item]);
            } elseif ($depth === 1) {
                return array_merge($result, array_values($item));
            } else {
                return array_merge($result, self::flatten($item, $depth - 1));
            }
        }, []);
    }
    public static function forget(&$array, $keys)
    {
        $original =& $array;
        $keys = (array) $keys;
        if (count($keys) === 0) {
            return;
        }
        foreach ($keys as $key) {
            if (self::exists($array, $key)) {
                unset($array[$key]);
                continue;
            }
            $parts = explode('.', $key);
            $array =& $original;
            while (count($parts) > 1) {
                $part = array_shift($parts);
                if (isset($array[$part]) && is_array($array[$part])) {
                    $array =& $array[$part];
                } else {
                    continue 2;
                }
            }
            unset($array[array_shift($parts)]);
        }
    }
    public static function read($array, $key, $default = null)
    {
        if (is_null($key)) {
            return \Neutrino\Support\Obj::value($default);
        }
        return self::exists($array, $key) ? $array[$key] : \Neutrino\Support\Obj::value($default);
    }
    public static function fetch($array, $key, $default = null)
    {
        if (is_null($key)) {
            return \Neutrino\Support\Obj::value($default);
        }
        return isset($array[$key]) ? $array[$key] : \Neutrino\Support\Obj::value($default);
    }
    public static function get($array, $key, $default = null)
    {
        if (!self::accessible($array)) {
            return \Neutrino\Support\Obj::value($default);
        }
        if (is_null($key)) {
            return $array;
        }
        if (!is_array($key)) {
            if (self::exists($array, $key)) {
                return $array[$key];
            }
            $keys = explode('.', $key);
        } else {
            $keys = $key;
        }
        foreach ($keys as $segment) {
            if (self::accessible($array) && self::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return \Neutrino\Support\Obj::value($default);
            }
        }
        return $array;
    }
    public static function has($array, $keys)
    {
        if (is_null($keys)) {
            return false;
        }
        $keys = (array) $keys;
        if (!$array) {
            return false;
        }
        if ($keys === []) {
            return false;
        }
        foreach ($keys as $key) {
            $subKeyArray = $array;
            if (self::exists($array, $key)) {
                continue;
            }
            foreach (explode('.', $key) as $segment) {
                if (self::accessible($subKeyArray) && self::exists($subKeyArray, $segment)) {
                    $subKeyArray = $subKeyArray[$segment];
                } else {
                    return false;
                }
            }
        }
        return true;
    }
    public static function isAssoc(array $array)
    {
        $keys = array_keys($array);
        return array_keys($keys) !== $keys;
    }
    public static function only($array, $keys)
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }
    public static function pluck($array, $value, $key = null)
    {
        $results = [];
        list($value, $key) = self::explodePluckParameters($value, $key);
        foreach ($array as $item) {
            $itemValue = self::get($item, $value);
            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = self::get($item, $key);
                $results[$itemKey] = $itemValue;
            }
        }
        return $results;
    }
    public static function prepend($array, $value, $key = null)
    {
        if (is_null($key)) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }
        return $array;
    }
    public static function pull(&$array, $key, $default = null)
    {
        $value = self::get($array, $key, $default);
        self::forget($array, $key);
        return $value;
    }
    public static function set(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }
        $keys = explode('.', $key);
        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array =& $array[$key];
        }
        $array[array_shift($keys)] = $value;
        return $array;
    }
    public static function sortRecursive($array)
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::sortRecursive($value);
            }
        }
        if (self::isAssoc($array)) {
            ksort($array);
        } else {
            sort($array);
        }
        return $array;
    }
    public static function where($array, callable $callback)
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }
    public static function map($callback, array $array, $recursive = false)
    {
        if ($recursive) {
            $func = function ($item) use(&$func, &$callback) {
                return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
            };
            return array_map($func, $array);
        }
        return array_map($callback, $array);
    }
    public static function explodePluckParameters($value, $key)
    {
        $value = is_string($value) ? explode('.', $value) : $value;
        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);
        return [$value, $key];
    }
}
namespace Neutrino\Support;

class Fluent implements \Neutrino\Support\Fluent\Fluentable
{
    use \Neutrino\Support\Fluent\Fluentize;
}
namespace Neutrino\Support;

class Func
{
    public static function tap($value, \Closure $callback)
    {
        $callback($value);
        return $value;
    }
}
namespace Neutrino\Support;

class Obj
{
    public static function fill(&$target, $key, $value)
    {
        return self::set($target, $key, $value, false);
    }
    public static function read($object, $property, $default = null)
    {
        if (is_null($object)) {
            return self::value($default);
        }
        if (isset($object->{$property}) || property_exists($object, $property)) {
            return $object->{$property};
        }
        return self::value($default);
    }
    public static function fetch($object, $property, $default = null)
    {
        if (is_null($object)) {
            return self::value($default);
        }
        return isset($object->{$property}) ? $object->{$property} : self::value($default);
    }
    public static function get($target, $key, $default = null)
    {
        if (is_null($key) || !is_object($target)) {
            return self::value($default);
        }
        if (!is_array($key)) {
            if (isset($target->{$key}) || property_exists($target, $key)) {
                return $target->{$key};
            }
            $keys = explode('.', $key);
        } else {
            $keys = $key;
        }
        foreach ($keys as $segment) {
            if (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return self::value($default);
            }
        }
        return $target;
    }
    public static function set(&$target, $key, $value, $overwrite = true)
    {
        if (is_null($key)) {
            return $target;
        }
        if (!is_array($key)) {
            if (isset($target->{$key}) || property_exists($target, $key)) {
                if ($overwrite) {
                    $target->{$key} = self::value($value);
                }
                return $target;
            }
            $keys = explode('.', $key);
        } else {
            $keys = $key;
        }
        $keep = $target;
        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($target->{$key}) || !is_object($target->{$key}) && $overwrite) {
                $target->{$key} = new \stdClass();
            } elseif (!is_object($target->{$key})) {
                return $target;
            }
            $target =& $target->{$key};
        }
        $key = array_shift($keys);
        if (!isset($target->{$key}) || $overwrite) {
            $target->{$key} = self::value($value);
        }
        return $keep;
    }
    public static function value($value)
    {
        return $value instanceof \Closure ? $value() : $value;
    }
}
namespace Neutrino\Support;

class Path
{
    public static function normalize($path)
    {
        if (empty($path)) {
            return '';
        }
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        $parts = explode('/', $path);
        $safe = [];
        foreach ($parts as $idx => $part) {
            if ($idx == 0 && empty($part)) {
                $safe[] = '';
            } elseif (trim($part) == "" || $part == '.') {
            } elseif ('..' == $part) {
                if (null === array_pop($safe) || empty($safe)) {
                    $safe[] = '';
                }
            } else {
                $safe[] = $part;
            }
        }
        if (count($safe) === 1 && $safe[0] === '') {
            return DIRECTORY_SEPARATOR;
        }
        return implode(DIRECTORY_SEPARATOR, $safe);
    }
    public static function findRelative($frompath, $topath)
    {
        $frompath = str_replace(DIRECTORY_SEPARATOR, '/', $frompath);
        $topath = str_replace(DIRECTORY_SEPARATOR, '/', $topath);
        $from = explode(DIRECTORY_SEPARATOR, self::normalize($frompath));
        $to = explode(DIRECTORY_SEPARATOR, self::normalize($topath));
        $relpath = '';
        $i = 0;
        while (isset($from[$i]) && isset($to[$i])) {
            if ($from[$i] != $to[$i]) {
                break;
            }
            $i++;
        }
        $j = count($from) - 1;
        while ($i <= $j) {
            if (!empty($from[$j])) {
                $relpath .= '..' . '/';
            }
            $j--;
        }
        while (isset($to[$i])) {
            if (!empty($to[$i])) {
                $relpath .= $to[$i] . '/';
            }
            $i++;
        }
        return substr($relpath, 0, -1);
    }
}
namespace Neutrino\Support;

class Str
{
    public static function ascii($value)
    {
        foreach (\Neutrino\Support\Str::charsArray() as $key => $val) {
            $value = str_replace($val, $key, $value);
        }
        return preg_replace('/[^\\x20-\\x7E]/u', '', $value);
    }
    public static function camel($value)
    {
        static $camelCache;
        if (isset($camelCache[$value])) {
            return $camelCache[$value];
        }
        return $camelCache[$value] = lcfirst(\Neutrino\Support\Str::studly($value));
    }
    public static function contains($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ($needle != '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
    public static function endsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ((string) $needle === \Neutrino\Support\Str::substr($haystack, -\Neutrino\Support\Str::length($needle))) {
                return true;
            }
        }
        return false;
    }
    public static function finish($value, $cap)
    {
        $quoted = preg_quote($cap, '/');
        return preg_replace('/(?:' . $quoted . ')+$/u', '', $value) . $cap;
    }
    public static function is($pattern, $value)
    {
        if ($pattern == $value) {
            return true;
        }
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\\*', '.*', $pattern);
        return (bool) preg_match('#^' . $pattern . '\\z#u', $value);
    }
    public static function length($value)
    {
        return mb_strlen($value);
    }
    public static function levenshtein($word, $words, $order = SORT_ASC, $sort_flags = SORT_REGULAR)
    {
        foreach ($words as $w) {
            $result[$w] = levenshtein($word, $w);
        }
        if ($order & SORT_DESC) {
            arsort($result, $sort_flags);
        } else {
            asort($result, $sort_flags);
        }
        return $result;
    }
    public static function limit($value, $limit = 100, $end = '...')
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }
        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }
    public static function lower($value)
    {
        return mb_strtolower($value, 'UTF-8');
    }
    public static function words($value, $words = 100, $end = '...')
    {
        preg_match('/^\\s*+(?:\\S++\\s*+){1,' . $words . '}/u', $value, $matches);
        if (!isset($matches[0]) || \Neutrino\Support\Str::length($value) === \Neutrino\Support\Str::length($matches[0])) {
            return $value;
        }
        return rtrim($matches[0]) . $end;
    }
    public static function parseCallback($callback, $default)
    {
        return \Neutrino\Support\Str::contains($callback, '@') ? explode('@', $callback, 2) : [$callback, $default];
    }
    public static function random($length = 16)
    {
        $string = '';
        while (($len = \Neutrino\Support\Str::length($string)) < $length) {
            $size = $length - $len;
            $bytes = \Neutrino\Support\Str::callRandom($size);
            $string .= \Neutrino\Support\Str::substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }
        return $string;
    }
    public static function quickRandom($length = 16)
    {
        return \Neutrino\Support\Str::substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
    }
    public static function replaceFirst($search, $replace, $subject)
    {
        $position = strpos($subject, $search);
        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }
        return $subject;
    }
    public static function replaceLast($search, $replace, $subject)
    {
        $position = strrpos($subject, $search);
        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }
        return $subject;
    }
    public static function upper($value)
    {
        return mb_strtoupper($value, 'UTF-8');
    }
    public static function title($value)
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
    public static function slug($title, $separator = '-')
    {
        $title = \Neutrino\Support\Str::ascii($title);
        $flip = $separator == '-' ? '_' : '-';
        $title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);
        $title = preg_replace('![^' . preg_quote($separator) . '\\pL\\pN\\s]+!u', '', mb_strtolower($title));
        $title = preg_replace('![' . preg_quote($separator) . '\\s]+!u', $separator, $title);
        return trim($title, $separator);
    }
    public static function snake($value, $delimiter = '_')
    {
        static $snakeCache;
        $key = $value;
        if (isset($snakeCache[$key][$delimiter])) {
            return $snakeCache[$key][$delimiter];
        }
        if (!ctype_lower($value)) {
            $value = preg_replace('/\\s+/u', '', $value);
            $value = \Neutrino\Support\Str::lower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }
        return $snakeCache[$key][$delimiter] = $value;
    }
    public static function startsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ($needle != '' && mb_strpos($haystack, $needle) === 0) {
                return true;
            }
        }
        return false;
    }
    public static function studly($value)
    {
        static $studlyCache;
        $key = $value;
        if (isset($studlyCache[$key])) {
            return $studlyCache[$key];
        }
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return $studlyCache[$key] = str_replace(' ', '', $value);
    }
    public static function capitalize($value)
    {
        static $capitalizeCache;
        $key = $value;
        if (isset($capitalizeCache[$key])) {
            return $capitalizeCache[$key];
        }
        return $capitalizeCache[$key] = ucwords(strtolower($value));
    }
    public static function substr($string, $start, $length = null)
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }
    public static function ucfirst($string)
    {
        return \Neutrino\Support\Str::upper(\Neutrino\Support\Str::substr($string, 0, 1)) . \Neutrino\Support\Str::substr($string, 1);
    }
    public static function normalizePath($path)
    {
        trigger_error('Deprecated: ' . __METHOD__ . '. Use ' . \Neutrino\Support\Path::class . '::normalize instead.', E_USER_DEPRECATED);
        if (empty($path)) {
            return '';
        }
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        $parts = explode('/', $path);
        $safe = [];
        foreach ($parts as $idx => $part) {
            if ($idx == 0 && empty($part)) {
                $safe[] = '';
            } elseif (trim($part) == "" || $part == '.') {
            } elseif ('..' == $part) {
                if (null === array_pop($safe) || empty($safe)) {
                    $safe[] = '';
                }
            } else {
                $safe[] = $part;
            }
        }
        if (count($safe) === 1 && $safe[0] === '') {
            return DIRECTORY_SEPARATOR;
        }
        return implode(DIRECTORY_SEPARATOR, $safe);
    }
    private static function callRandom($size)
    {
        static $randFunc;
        switch ($randFunc) {
            case 'random_bytes':
                return random_bytes($size);
            case 'openssl_random_pseudo_bytes':
                return openssl_random_pseudo_bytes($size);
            case 'mcrypt_create_iv':
                return mcrypt_create_iv($size, MCRYPT_DEV_URANDOM);
            case '\\Phalcon\\Text':
                return \Phalcon\Text::random(\Phalcon\Text::RANDOM_ALNUM, $size);
            default:
                if (function_exists('random_bytes')) {
                    $randFunc = 'random_bytes';
                } elseif (function_exists('openssl_random_pseudo_bytes')) {
                    $randFunc = 'openssl_random_pseudo_bytes';
                } elseif (function_exists('mcrypt_create_iv')) {
                    $randFunc = 'mcrypt_create_iv';
                } else {
                    $randFunc = '\\Phalcon\\Text';
                }
                return \Neutrino\Support\Str::callRandom($size);
        }
    }
    private static function charsArray()
    {
        static $charsArray;
        if (isset($charsArray)) {
            return $charsArray;
        }
        return $charsArray = ['0' => ['°', '₀', '۰'], '1' => ['¹', '₁', '۱'], '2' => ['²', '₂', '۲'], '3' => ['³', '₃', '۳'], '4' => ['⁴', '₄', '۴', '٤'], '5' => ['⁵', '₅', '۵', '٥'], '6' => ['⁶', '₆', '۶', '٦'], '7' => ['⁷', '₇', '۷'], '8' => ['⁸', '₈', '۸'], '9' => ['⁹', '₉', '۹'], 'a' => ['à', 'á', 'ả', 'ã', 'ạ', 'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ', 'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ', 'ā', 'ą', 'å', 'α', 'ά', 'ἀ', 'ἁ', 'ἂ', 'ἃ', 'ἄ', 'ἅ', 'ἆ', 'ἇ', 'ᾀ', 'ᾁ', 'ᾂ', 'ᾃ', 'ᾄ', 'ᾅ', 'ᾆ', 'ᾇ', 'ὰ', 'ά', 'ᾰ', 'ᾱ', 'ᾲ', 'ᾳ', 'ᾴ', 'ᾶ', 'ᾷ', 'а', 'أ', 'အ', 'ာ', 'ါ', 'ǻ', 'ǎ', 'ª', 'ა', 'अ', 'ا'], 'b' => ['б', 'β', 'Ъ', 'Ь', 'ب', 'ဗ', 'ბ'], 'c' => ['ç', 'ć', 'č', 'ĉ', 'ċ'], 'd' => ['ď', 'ð', 'đ', 'ƌ', 'ȡ', 'ɖ', 'ɗ', 'ᵭ', 'ᶁ', 'ᶑ', 'д', 'δ', 'د', 'ض', 'ဍ', 'ဒ', 'დ'], 'e' => ['é', 'è', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ', 'ë', 'ē', 'ę', 'ě', 'ĕ', 'ė', 'ε', 'έ', 'ἐ', 'ἑ', 'ἒ', 'ἓ', 'ἔ', 'ἕ', 'ὲ', 'έ', 'е', 'ё', 'э', 'є', 'ə', 'ဧ', 'ေ', 'ဲ', 'ე', 'ए', 'إ', 'ئ'], 'f' => ['ф', 'φ', 'ف', 'ƒ', 'ფ'], 'g' => ['ĝ', 'ğ', 'ġ', 'ģ', 'г', 'ґ', 'γ', 'ဂ', 'გ', 'گ'], 'h' => ['ĥ', 'ħ', 'η', 'ή', 'ح', 'ه', 'ဟ', 'ှ', 'ჰ'], 'i' => ['í', 'ì', 'ỉ', 'ĩ', 'ị', 'î', 'ï', 'ī', 'ĭ', 'į', 'ı', 'ι', 'ί', 'ϊ', 'ΐ', 'ἰ', 'ἱ', 'ἲ', 'ἳ', 'ἴ', 'ἵ', 'ἶ', 'ἷ', 'ὶ', 'ί', 'ῐ', 'ῑ', 'ῒ', 'ΐ', 'ῖ', 'ῗ', 'і', 'ї', 'и', 'ဣ', 'ိ', 'ီ', 'ည်', 'ǐ', 'ი', 'इ'], 'j' => ['ĵ', 'ј', 'Ј', 'ჯ', 'ج'], 'k' => ['ķ', 'ĸ', 'к', 'κ', 'Ķ', 'ق', 'ك', 'က', 'კ', 'ქ', 'ک'], 'l' => ['ł', 'ľ', 'ĺ', 'ļ', 'ŀ', 'л', 'λ', 'ل', 'လ', 'ლ'], 'm' => ['м', 'μ', 'م', 'မ', 'მ'], 'n' => ['ñ', 'ń', 'ň', 'ņ', 'ŉ', 'ŋ', 'ν', 'н', 'ن', 'န', 'ნ'], 'o' => ['ó', 'ò', 'ỏ', 'õ', 'ọ', 'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ', 'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ', 'ø', 'ō', 'ő', 'ŏ', 'ο', 'ὀ', 'ὁ', 'ὂ', 'ὃ', 'ὄ', 'ὅ', 'ὸ', 'ό', 'о', 'و', 'θ', 'ို', 'ǒ', 'ǿ', 'º', 'ო', 'ओ'], 'p' => ['п', 'π', 'ပ', 'პ', 'پ'], 'q' => ['ყ'], 'r' => ['ŕ', 'ř', 'ŗ', 'р', 'ρ', 'ر', 'რ'], 's' => ['ś', 'š', 'ş', 'с', 'σ', 'ș', 'ς', 'س', 'ص', 'စ', 'ſ', 'ს'], 't' => ['ť', 'ţ', 'т', 'τ', 'ț', 'ت', 'ط', 'ဋ', 'တ', 'ŧ', 'თ', 'ტ'], 'u' => ['ú', 'ù', 'ủ', 'ũ', 'ụ', 'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự', 'û', 'ū', 'ů', 'ű', 'ŭ', 'ų', 'µ', 'у', 'ဉ', 'ု', 'ူ', 'ǔ', 'ǖ', 'ǘ', 'ǚ', 'ǜ', 'უ', 'उ'], 'v' => ['в', 'ვ', 'ϐ'], 'w' => ['ŵ', 'ω', 'ώ', 'ဝ', 'ွ'], 'x' => ['χ', 'ξ'], 'y' => ['ý', 'ỳ', 'ỷ', 'ỹ', 'ỵ', 'ÿ', 'ŷ', 'й', 'ы', 'υ', 'ϋ', 'ύ', 'ΰ', 'ي', 'ယ'], 'z' => ['ź', 'ž', 'ż', 'з', 'ζ', 'ز', 'ဇ', 'ზ'], 'aa' => ['ع', 'आ', 'آ'], 'ae' => ['ä', 'æ', 'ǽ'], 'ai' => ['ऐ'], 'at' => ['@'], 'ch' => ['ч', 'ჩ', 'ჭ', 'چ'], 'dj' => ['ђ', 'đ'], 'dz' => ['џ', 'ძ'], 'ei' => ['ऍ'], 'gh' => ['غ', 'ღ'], 'ii' => ['ई'], 'ij' => ['ĳ'], 'kh' => ['х', 'خ', 'ხ'], 'lj' => ['љ'], 'nj' => ['њ'], 'oe' => ['ö', 'œ', 'ؤ'], 'oi' => ['ऑ'], 'oii' => ['ऒ'], 'ps' => ['ψ'], 'sh' => ['ш', 'შ', 'ش'], 'shch' => ['щ'], 'ss' => ['ß'], 'sx' => ['ŝ'], 'th' => ['þ', 'ϑ', 'ث', 'ذ', 'ظ'], 'ts' => ['ц', 'ც', 'წ'], 'ue' => ['ü'], 'uu' => ['ऊ'], 'ya' => ['я'], 'yu' => ['ю'], 'zh' => ['ж', 'ჟ', 'ژ'], '(c)' => ['©'], 'A' => ['Á', 'À', 'Ả', 'Ã', 'Ạ', 'Ă', 'Ắ', 'Ằ', 'Ẳ', 'Ẵ', 'Ặ', 'Â', 'Ấ', 'Ầ', 'Ẩ', 'Ẫ', 'Ậ', 'Å', 'Ā', 'Ą', 'Α', 'Ά', 'Ἀ', 'Ἁ', 'Ἂ', 'Ἃ', 'Ἄ', 'Ἅ', 'Ἆ', 'Ἇ', 'ᾈ', 'ᾉ', 'ᾊ', 'ᾋ', 'ᾌ', 'ᾍ', 'ᾎ', 'ᾏ', 'Ᾰ', 'Ᾱ', 'Ὰ', 'Ά', 'ᾼ', 'А', 'Ǻ', 'Ǎ'], 'B' => ['Б', 'Β', 'ब'], 'C' => ['Ç', 'Ć', 'Č', 'Ĉ', 'Ċ'], 'D' => ['Ď', 'Ð', 'Đ', 'Ɖ', 'Ɗ', 'Ƌ', 'ᴅ', 'ᴆ', 'Д', 'Δ'], 'E' => ['É', 'È', 'Ẻ', 'Ẽ', 'Ẹ', 'Ê', 'Ế', 'Ề', 'Ể', 'Ễ', 'Ệ', 'Ë', 'Ē', 'Ę', 'Ě', 'Ĕ', 'Ė', 'Ε', 'Έ', 'Ἐ', 'Ἑ', 'Ἒ', 'Ἓ', 'Ἔ', 'Ἕ', 'Έ', 'Ὲ', 'Е', 'Ё', 'Э', 'Є', 'Ə'], 'F' => ['Ф', 'Φ'], 'G' => ['Ğ', 'Ġ', 'Ģ', 'Г', 'Ґ', 'Γ'], 'H' => ['Η', 'Ή', 'Ħ'], 'I' => ['Í', 'Ì', 'Ỉ', 'Ĩ', 'Ị', 'Î', 'Ï', 'Ī', 'Ĭ', 'Į', 'İ', 'Ι', 'Ί', 'Ϊ', 'Ἰ', 'Ἱ', 'Ἳ', 'Ἴ', 'Ἵ', 'Ἶ', 'Ἷ', 'Ῐ', 'Ῑ', 'Ὶ', 'Ί', 'И', 'І', 'Ї', 'Ǐ', 'ϒ'], 'K' => ['К', 'Κ'], 'L' => ['Ĺ', 'Ł', 'Л', 'Λ', 'Ļ', 'Ľ', 'Ŀ', 'ल'], 'M' => ['М', 'Μ'], 'N' => ['Ń', 'Ñ', 'Ň', 'Ņ', 'Ŋ', 'Н', 'Ν'], 'O' => ['Ó', 'Ò', 'Ỏ', 'Õ', 'Ọ', 'Ô', 'Ố', 'Ồ', 'Ổ', 'Ỗ', 'Ộ', 'Ơ', 'Ớ', 'Ờ', 'Ở', 'Ỡ', 'Ợ', 'Ø', 'Ō', 'Ő', 'Ŏ', 'Ο', 'Ό', 'Ὀ', 'Ὁ', 'Ὂ', 'Ὃ', 'Ὄ', 'Ὅ', 'Ὸ', 'Ό', 'О', 'Θ', 'Ө', 'Ǒ', 'Ǿ'], 'P' => ['П', 'Π'], 'R' => ['Ř', 'Ŕ', 'Р', 'Ρ', 'Ŗ'], 'S' => ['Ş', 'Ŝ', 'Ș', 'Š', 'Ś', 'С', 'Σ'], 'T' => ['Ť', 'Ţ', 'Ŧ', 'Ț', 'Т', 'Τ'], 'U' => ['Ú', 'Ù', 'Ủ', 'Ũ', 'Ụ', 'Ư', 'Ứ', 'Ừ', 'Ử', 'Ữ', 'Ự', 'Û', 'Ū', 'Ů', 'Ű', 'Ŭ', 'Ų', 'У', 'Ǔ', 'Ǖ', 'Ǘ', 'Ǚ', 'Ǜ'], 'V' => ['В'], 'W' => ['Ω', 'Ώ', 'Ŵ'], 'X' => ['Χ', 'Ξ'], 'Y' => ['Ý', 'Ỳ', 'Ỷ', 'Ỹ', 'Ỵ', 'Ÿ', 'Ῠ', 'Ῡ', 'Ὺ', 'Ύ', 'Ы', 'Й', 'Υ', 'Ϋ', 'Ŷ'], 'Z' => ['Ź', 'Ž', 'Ż', 'З', 'Ζ'], 'AE' => ['Ä', 'Æ', 'Ǽ'], 'CH' => ['Ч'], 'DJ' => ['Ђ'], 'DZ' => ['Џ'], 'GX' => ['Ĝ'], 'HX' => ['Ĥ'], 'IJ' => ['Ĳ'], 'JX' => ['Ĵ'], 'KH' => ['Х'], 'LJ' => ['Љ'], 'NJ' => ['Њ'], 'OE' => ['Ö', 'Œ'], 'PS' => ['Ψ'], 'SH' => ['Ш'], 'SHCH' => ['Щ'], 'SS' => ['ẞ'], 'TH' => ['Þ'], 'TS' => ['Ц'], 'UE' => ['Ü'], 'YA' => ['Я'], 'YU' => ['Ю'], 'ZH' => ['Ж'], ' ' => [" ", " ", " ", " ", " ", " ", " ", " ", " ", " ", " ", " ", " ", " ", "　"]];
    }
}
namespace Neutrino\View\Engines;

abstract class EngineRegister
{
    public static final function getRegisterClosure()
    {
        return function ($view, $di) {
            return (new static())->register($view, $di);
        };
    }
    public abstract function register($view, $di);
}
namespace Neutrino\View\Engines\Volt;

class VoltEngineRegister extends \Neutrino\View\Engines\EngineRegister
{
    public function register($view, $di)
    {
        $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);
        $config = $di->getShared(\Neutrino\Constants\Services::CONFIG)->view;
        $options = array_merge(['compiledPath' => $config->compiled_path, 'compiledSeparator' => '_', 'compileAlways' => APP_ENV === \Neutrino\Constants\Env::DEVELOPMENT || APP_DEBUG], isset($config->options) ? (array) $config->options : []);
        $volt->setOptions($options);
        $compiler = $volt->getCompiler();
        $extensions = isset($config->extensions) ? $config->extensions : [];
        foreach ($extensions as $extension) {
            $compiler->addExtension(new $extension($compiler));
        }
        $filters = isset($config->filters) ? $config->filters : [];
        foreach ($filters as $name => $filter) {
            $filter = new $filter($compiler);
            $compiler->addFilter($name, function ($resolvedArgs, $exprArgs) use($filter) {
                return $filter->compileFilter($resolvedArgs, $exprArgs);
            });
        }
        $compiler->addFunction('dump', 'Neutrino\\Debug\\VarDump::dump');
        $functions = isset($config->functions) ? $config->functions : [];
        foreach ($functions as $name => $function) {
            $function = new $function($compiler);
            $compiler->addFunction($name, function ($resolvedArgs, $exprArgs) use($function) {
                return $function->compileFunction($resolvedArgs, $exprArgs);
            });
        }
        return $volt;
    }
}
namespace Neutrino\View\Engines\Volt\Compiler;

abstract class Extending
{
    protected $compiler;
    public function __construct(\Phalcon\Mvc\View\Engine\Volt\Compiler $compiler)
    {
        $this->compiler = $compiler;
    }
}
namespace Neutrino\View\Engines\Volt\Compiler;

abstract class ExtensionExtend extends \Neutrino\View\Engines\Volt\Compiler\Extending
{
    public abstract function compileFunction($name, $arguments, $funcArguments);
    public abstract function compileFilter($name, $arguments, $funcArguments);
    public abstract function resolveExpression($expr);
    public abstract function compileStatement($statement);
}
namespace Neutrino\View\Engines\Volt\Compiler;

abstract class FilterExtend extends \Neutrino\View\Engines\Volt\Compiler\Extending
{
    public abstract function compileFilter($resolvedArgs, $exprArgs);
}
namespace Neutrino\View\Engines\Volt\Compiler;

abstract class FunctionExtend extends \Neutrino\View\Engines\Volt\Compiler\Extending
{
    public abstract function compileFunction($resolvedArgs, $exprArgs);
}
namespace Neutrino\View\Engines\Volt\Compiler\Extensions;

class PhpFunctionExtension extends \Neutrino\View\Engines\Volt\Compiler\ExtensionExtend
{
    public function compileFunction($name, $arguments, $funcArguments)
    {
        if (function_exists($name)) {
            return $name . '(' . $arguments . ')';
        }
        return null;
    }
    public function compileFilter($name, $arguments, $funcArguments)
    {
    }
    public function resolveExpression($expr)
    {
    }
    public function compileStatement($statement)
    {
    }
}
namespace Neutrino\View\Engines\Volt\Compiler\Extensions;

class StrExtension extends \Neutrino\View\Engines\Volt\Compiler\ExtensionExtend
{
    public function compileFunction($name, $arguments, $funcArguments)
    {
        if (!\Neutrino\Support\Str::startsWith($name, 'str_') || function_exists($name)) {
            return null;
        }
        $name = substr($name, 4);
        if (method_exists(\Neutrino\Support\Str::class, $name) && \Neutrino\Debug\Reflexion::getReflectionMethod(\Neutrino\Support\Str::class, $name)->isPublic()) {
            return \Neutrino\Support\Str::class . '::' . $name . '(' . $arguments . ')';
        }
        return null;
    }
    public function compileFilter($name, $arguments, $funcArguments)
    {
        switch ($name) {
            case 'slug':
                return \Neutrino\Support\Str::class . '::slug(' . $arguments . ')';
            case 'limit':
                return \Neutrino\Support\Str::class . '::limit(' . $arguments . ')';
            case 'words':
                return \Neutrino\Support\Str::class . '::words(' . $arguments . ')';
        }
        return null;
    }
    public function resolveExpression($expr)
    {
    }
    public function compileStatement($statement)
    {
    }
}
namespace Neutrino\View\Engines\Volt\Compiler\Filters;

class MergeFilter extends \Neutrino\View\Engines\Volt\Compiler\FilterExtend
{
    public function compileFilter($resolvedArgs, $exprArgs)
    {
        return 'array_merge(' . $resolvedArgs . ')';
    }
}
namespace Neutrino\View\Engines\Volt\Compiler\Filters;

class RoundFilter extends \Neutrino\View\Engines\Volt\Compiler\FilterExtend
{
    public function compileFilter($resolvedArgs, $exprArgs)
    {
        $value = isset($exprArgs[0]['expr']['value']) ? $exprArgs[0]['expr']['value'] : $resolvedArgs;
        switch (isset($exprArgs[1]['expr']['type']) ? $exprArgs[1]['expr']['type'] : null) {
            case 260:
                switch (isset($exprArgs[1]['expr']['value']) ? $exprArgs[1]['expr']['value'] : null) {
                    case 'floor':
                        return "floor({$value})";
                    case 'ceil':
                        return "ceil({$value})";
                }
        }
        $precision = isset($exprArgs[1]['expr']['value']) ? $exprArgs[1]['expr']['value'] : 0;
        switch (isset($exprArgs[2]['expr']['value']) ? $exprArgs[2]['expr']['value'] : null) {
            case 'floor':
                return "floor({$value}*(10**{$precision}))/(10**{$precision})";
            case 'ceil':
                return "ceil({$value}*(10**{$precision}))/(10**{$precision})";
        }
        return "round({$value}, {$precision})";
    }
}
namespace Neutrino\View\Engines\Volt\Compiler\Filters;

class SliceFilter extends \Neutrino\View\Engines\Volt\Compiler\FilterExtend
{
    public function compileFilter($resolvedArgs, $exprArgs)
    {
        return 'array_slice(' . $resolvedArgs . ')';
    }
}
namespace Neutrino\View\Engines\Volt\Compiler\Filters;

class SplitFilter extends \Neutrino\View\Engines\Volt\Compiler\FilterExtend
{
    public function compileFilter($resolvedArgs, $exprArgs)
    {
        $value = isset($exprArgs[0]['expr']['value']) ? $exprArgs[0]['expr']['value'] : $resolvedArgs;
        $separator = isset($exprArgs[1]['expr']['value']) ? $exprArgs[1]['expr']['value'] : '';
        if (empty($separator)) {
            $length = isset($exprArgs[2]['expr']['value']) ? $exprArgs[2]['expr']['value'] : '1';
            return 'str_split(' . $value . ', ' . intval($length) . ')';
        }
        if (isset($exprArgs[2]['expr']['value'])) {
            return 'explode(' . var_export($separator, true) . ', ' . $value . ', ' . intval($exprArgs[2]['expr']['value']) . ')';
        }
        return 'explode(' . var_export($separator, true) . ', ' . $value . ')';
    }
}
namespace Neutrino\Foundation\Auth;

class User extends \Neutrino\Model implements \Neutrino\Interfaces\Auth\Authenticable
{
    use \Neutrino\Auth\Authenticable;
}
namespace Neutrino\Auth;

trait Authenticable
{
    public function getAuthIdentifier()
    {
        return $this->{static::getAuthIdentifierName()};
    }
    public function getAuthPassword()
    {
        return $this->{static::getAuthPasswordName()};
    }
    public function getRememberToken()
    {
        return $this->{static::getRememberTokenName()};
    }
    public function setRememberToken($value)
    {
        $this->{static::getRememberTokenName()} = $value;
    }
    public static function getAuthIdentifierName()
    {
        return 'email';
    }
    public static function getAuthPasswordName()
    {
        return 'password';
    }
    public static function getRememberTokenName()
    {
        return 'remember_token';
    }
}
namespace Neutrino\Auth;

class Manager extends \Phalcon\Di\Injectable
{
    protected $user;
    protected $loggedOut = false;
    protected $model;
    public function user()
    {
        if ($this->loggedOut) {
            return null;
        }
        if (!is_null($this->user)) {
            return $this->user;
        }
        $user = null;
        if (!is_null($id = $this->retrieveIdentifier())) {
            $user = $this->retrieveUserByIdentifier($id);
        }
        $cookies = $this->{\Neutrino\Constants\Services::COOKIES};
        if (empty($user) && $cookies->has('remember_me')) {
            $recaller = $cookies->get('remember_me');
            list($identifier, $token) = explode('|', $recaller);
            if ($identifier && $token) {
                $user = $this->retrieveUserByToken($identifier, $token);
                if ($user) {
                    $this->{\Neutrino\Constants\Services::SESSION}->set($this->sessionKey(), $user->getAuthIdentifier());
                }
            }
        }
        $this->user = $user;
        return $this->user;
    }
    public function guest()
    {
        return !$this->check();
    }
    public function attempt(array $credentials = [], $remember = false)
    {
        $user = $this->retrieveUserByCredentials($credentials);
        if (!empty($user)) {
            $this->login($user, $remember);
            return $user;
        }
        return null;
    }
    public function check()
    {
        return !is_null($this->user());
    }
    public function logout()
    {
        $this->user = null;
        $this->loggedOut = true;
        $this->{\Neutrino\Constants\Services::SESSION}->destroy();
    }
    public function retrieveIdentifier()
    {
        return $this->{\Neutrino\Constants\Services::SESSION}->get($this->sessionKey());
    }
    public function login(\Neutrino\Foundation\Auth\User $user, $remember = false)
    {
        if (!$user) {
            return false;
        }
        $this->regenerateSessionId();
        $this->{\Neutrino\Constants\Services::SESSION}->set($this->sessionKey(), $user->getAuthIdentifier());
        if ($remember) {
            $rememberToken = \Neutrino\Support\Str::random(60);
            $cookies = $this->{\Neutrino\Constants\Services::COOKIES};
            $cookies->set('remember_me', $user->getAuthIdentifier() . '|' . $rememberToken);
            $user->setRememberToken($rememberToken);
            $user->save();
        }
        $this->user = $user;
        return true;
    }
    public function loginUsingId($id)
    {
        $this->login($user = $this->retrieveUserById($id));
        return $user;
    }
    protected function retrieveUserById($id)
    {
        $class = $this->modelClass();
        return $class::findFirst($id);
    }
    protected function retrieveUserByIdentifier($id)
    {
        $class = $this->modelClass();
        return $class::findFirst(['conditions' => $class::getAuthIdentifierName() . ' = :auth_identifier:', 'bind' => ['auth_identifier' => $id]]);
    }
    protected function retrieveUserByToken($identifier, $token)
    {
        $user = $this->retrieveUserByIdentifier($identifier);
        if (!empty($user) && $user->getRememberToken() === $token) {
            return $user;
        }
        return null;
    }
    protected function retrieveUserByCredentials(array $credentials)
    {
        $class = $this->modelClass();
        $identifier = $class::getAuthIdentifierName();
        $password = $class::getAuthPasswordName();
        $user = $this->retrieveUserByIdentifier(\Neutrino\Support\Arr::fetch($credentials, $identifier));
        if ($user) {
            $security = $this->{\Neutrino\Constants\Services::SECURITY};
            if ($security->checkHash(\Neutrino\Support\Arr::fetch($credentials, $password), $user->getAuthPassword())) {
                return $user;
            }
        }
        return null;
    }
    protected function regenerateSessionId()
    {
        $this->{\Neutrino\Constants\Services::SESSION}->regenerateId();
    }
    private function sessionKey()
    {
        return $this->{\Neutrino\Constants\Services::CONFIG}->session->id;
    }
    private function modelClass()
    {
        if (!isset($this->model)) {
            $this->model = '\\' . $this->{\Neutrino\Constants\Services::CONFIG}->auth->model;
        }
        return $this->model;
    }
}
namespace App\Core\Constants;

final class Services
{
    const EXAMPLE = 'example';
}
namespace App\Core\Facades;

class Example extends \Neutrino\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Core\Constants\Services::EXAMPLE;
    }
}
namespace App\Core\Models;

class User extends \Neutrino\Foundation\Auth\User
{
    use \App\Core\Models\Viewable;
    public $id;
    public $name;
    public $email;
    public $password;
    public $remember_token;
    public function initialize()
    {
        parent::initialize();
        $this->setSource("users");
        $this->primary('id', \Phalcon\Db\Column::TYPE_INTEGER);
        $this->column('name', \Phalcon\Db\Column::TYPE_VARCHAR);
        $this->column('email', \Phalcon\Db\Column::TYPE_VARCHAR);
        $this->column('password', \Phalcon\Db\Column::TYPE_VARCHAR);
        $this->column('remember_token', \Phalcon\Db\Column::TYPE_VARCHAR, ['nullable' => true]);
    }
}
namespace App\Core\Models;

trait Viewable
{
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
        $func = str_studly($name);
        if (method_exists($this, 'get' . $func)) {
            return $this->{'get' . $func};
        }
        if (method_exists($this, 'is' . $func)) {
            return $this->{'is' . $func};
        }
        return null;
    }
}
namespace App\Core\Providers;

class Example extends \Neutrino\Support\SimpleProvider
{
    protected $name = \App\Core\Constants\Services::EXAMPLE;
    protected $shared = false;
    protected $class = \App\Core\Services\Example::class;
    protected $aliases = [\App\Core\Services\Example::class];
}
namespace App\Core\Services;

class Example extends \Phalcon\Di\Injectable implements \Phalcon\Di\InjectionAwareInterface
{
    public function doSomething()
    {
        $logger = $this->getDI()->get(\Neutrino\Constants\Services::LOGGER);
        $logger->debug('Something Appends !');
        return 'abc';
    }
    public function doAnotherThing()
    {
        \Neutrino\Support\Facades\Log::debug('Another thing Appends !');
        return 'abc';
    }
}
