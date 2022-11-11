<?php
/**
 * статический класс - преставитель CMS в космосе.
 * ----------------------------------------------------------------------------------
 * $Id: X-Site cms (2.0, LapsiTV build), written by Ksnk (sergekoriakin@gmail.com),
 * ver: xxx, Last build: 1806091520
 * status : draft build.
 * GIT: origin    https://github.com/Ksnk/ENGINE.git (push)$
 * ----------------------------------------------------------------------------------
 * License MIT - Serge Koriakin - 2012-2018
 * ----------------------------------------------------------------------------------
 */

/*  --- point::ENGINE_namespace --- */

namespace Ksnk\core;
use Ksnk\core\UTILS;

/*  --- point::ENGINE_top --- */


/**
 * Class xData -data-holder,
 * базовый класс для хранителя данных для шаблонов
 */
class xData implements \Iterator
{

    /**
     * @var array
     */
    static $items = array();

    /**
     * система кэширования однотипных данных. Все данные различаются полем ID.
     * @static
     * @param $class
     * @param $id
     * @param array $data
     * @return mixed
     */
    static function get($class, $id, $data = array())
    {
        if (!isset(self::$items[$class]))
            self::$items[$class] = array();
        if (is_array($id)) {
            if (!isset(self::$items[$class][$id['id']]))
                self::$items[$id['id']] = new $class($id, $data);
            return self::$items[$class][$id['id']];
        } else if (!isset(self::$items[$class][$id])) {
            self::$items[$class][$id] = new $class($id, $data);
        }
        return self::$items[$class][$id];
    }

    protected $data = array();
    private $def = '';

    function getData()
    {
        return $this->data;
    }

    function __construct($data, $def = '')
    {
        foreach ($data as $k => $v) {
            if (is_array($v))
                $this->data[$k] = new self($v, $def);
            else
                $this->data[$k] = $v;
        }
        $this->def = $def;
    }

    protected function  &resolve($name)
    {
        if (!array_key_exists($name, $this->data))
            $this->data[$name] = $this->def;
        return $this->data[$name];
    }

    function &__get($name)
    {
        if (array_key_exists($name, $this->data))
            return $this->data[$name];
        else {
            $x = $this->resolve($name);
            return $x;
        }
    }

    public function __set($name, $value)
    {
        // echo "Setting '$name' to '$value'\n";
        $this->data[$name] = $value;
    }

    // итератор
    function rewind()
    {
        // $this->eoa=true;//count($this->data==0);
        reset($this->data);
    }

    function current()
    {
        return current($this->data);
    }

    function key()
    {
        return key($this->data);
    }

    function next()
    {
        return next($this->data);
    }

    function valid()
    {
        $key = key($this->data);
        return ($key !== NULL && $key !== FALSE);
    }

}

interface engine_cache
{
    static function cache($key, $value = null, $time = null, $tags = null);
}

interface engine_options
{
    function get($name);

    function set($name, $value = null);
}

/**
 * @method static bool has_rights
 * @method static \Ksnk\model\database\databaseMaket NS
 * @method static mixed cache($key, $value = null, $time = null, $tags = null);
 * @method static run
 */
class ENGINE
{

// константы системы прав
    const right_C = 1, right_R = 2, right_U = 4, right_D = 8, right_A = 16;
    const rgrp_A = 1, rgrp_B = 2, rgrp_C = 4, rgrp_D = 8, rgrp_E = 16, rgrp_F = 32, rgrp_G = 64;


    private static $class_list = array();
    private static $class_alias = array();
    private static $SUBDIR = '';

    /*  --- point::ENGINE_header --- */

    private static $events = array();


    static private $options = array(); // пары: имя-значение
    static private $transport = array(); // пары: имя - механизм сохранения
    static public $default_transport = '';
    static private $transports = array(); // строка -> объект


    static public $session_started = false;


    static $cookie_name = 'testing';


    static private $interface = array();


    /** @var \Ksnk\model\database\xDatabaseLapsii */
    static private $db = null;


    static $url_par = null;
    static $url_path = null;


    static $start_time;

    /**
     * простейший строковый шаблон для вывода минимально параметризованных строк.
     * a-la YII
     * @static
     * @param $msg
     * @param $arg
     * @return mixed
     */
    static public function _t($msg, $arg)
    {
        if (is_array($arg) && count($arg) > 0) {
            foreach ($arg as $k => $v) {
                if (is_object($v)) {
                    $v = get_class($v);
                }
                $msg = str_replace($k, $v, $msg);
            }
        }
        return $msg;
    }

    /**
     * функция вывода сообщения об ошибке. Управляется функция с помошью параметров
     * --error.handler и error.format
     * стандартный handler - printf
     * для ajax
     *
     * @param string $msg - собственно собщение
     * @param array $par - параметры для функции _t
     * @param array $backtrace - параметры для вывода backtrace
     * @return mixed
     */
    static function error($msg, $par = array(), $backtrace = array())
    {
        if (!error_reporting()) return false;
        $error_handler = self::option('error.handler', 'echo');
        $error_format = self::option('error.format'
            , "<!--xxx-error-{backtrace}\n{msg}\n-->");
        $msg = self::_t($error_format, array(
            '{backtrace}' => self::backtrace($backtrace),
            '{msg}' => self::_t($msg, $par)
        ));
        if ($error_handler == 'echo' || $error_handler == 'printf') {
            echo $msg;
        } else
            call_user_func($error_handler, self::_t($error_format, array(
                '{backtrace}' => self::backtrace($backtrace),
                '{msg}' => self::_t($msg, $par)
            )));
        return false;
    }

    /*
    if (array_key_exists('error', self::$interface)) {
            return call_user_func(self::$interface['error'], $msg, $args, $rule);
        }

        $error = ENGINE::option('page.error');
        if (!empty($error)) $error .= "<hr>\n";
        ENGINE::set_option('page.error', $error . self::_t($msg, $args));
        //echo self::_t($msg, $args) . " <br>\n";
        return null;
    }
*/

    /**
     * выдать имя алиаса по имени класса
     * @static
     * @param $object
     * @return mixed|string
     */
    static function get_class($object)
    {
        if (method_exists($object, 'get_alias')) {
            $classname = $object->get_alias();
        } else {
            $classname = get_class($object);
            $key = array_search($classname, self::$class_alias);
            if ($key !== false) {
                $classname = $key;
            }
        }
        return $classname;
    }

    /**
     * place object in factory storage with selected name
     * @param $name
     * @param $obj
     */
    static function setObj($name, &$obj)
    {
        self::$class_list[$name] = $obj;
    }

    static function callFirst($method, $par)
    {
        foreach (self::$class_list as &$v)
            if (method_exists($v, $method)) {
                if (!is_null($x = call_user_func_array(array(&$v, $method), $par)))
                    return $x;
            }
        return null;
    }

    static function callAll($method, $par = '')
    {
        $result = false;
        foreach (self::$class_list as &$v)
            if (method_exists($v, $method)) {
                $result = $result || call_user_func(array($v, $method), $par);
            }
        return $result;
    }

    /**
     * @param $name
     * @return string - aliaced name of class
     */
    static function getAliace($name)
    {
        if (empty(self::$class_alias)) {
            self::$class_alias = ENGINE::option('engine.aliaces');
        }
        if (isset(self::$class_alias[$name]))
            return self::$class_alias[$name];
        return $name;
    }

    /**
     * get an object from alias record
     * @static
     * @param $name
     * @param null $par
     * @return null
     */
    static function getObj($name, $par = null)
    {
        $dir = '';
        if (($pos = strrpos($name, '/')) !== false) {
            $dir = substr($name, 0, $pos + 1);
            $name = substr($name, $pos + 1);
        }
        self::$SUBDIR = $dir;
        if (empty(self::$class_list[$name])) {
            $class = self::getAliace($name);
            if (class_exists($class)) {
                self::$class_list[$name] = new $class($par);
            } else {
                if (!error_reporting()) return null;
                else if ($name == $class) {
                    self::error(
                        'class `{class}` not found in (cwd:{dir})',
                        array('{class}' => $class, '{dir}' => getcwd())
                    );
                } else {
                    self::error(
                        'class `{class}({name})` not found in (cwd:{dir})',
                        array(
                            '{name}' => $name,
                            '{class}' => $class,
                            '{dir}' => getcwd()
                        )
                    );
                }
                return null;
            }
        }
        return self::$class_list[$name];
    }

    /**
     * вызвать класс-метод.
     * Если класс из массива - вызывать объект из фабрики.
     *
     * @param callable|array $func
     * @param null|array $args
     * @param string $error_rule
     * @return mixed
     */
    static function exec($func, $args = array(), $error_rule = '')
    {
        if (is_array($func) && is_string($func[0])) {
            $func[0] = self::getObj($class = $func[0]);
        }
        if (is_callable($func)) {
            if (is_array($args))
                return call_user_func_array($func, $args);
            else
                return call_user_func($func);
        }

        if (empty($func)) {
            // попытка выковырять параметры со стека вызовов
            $db = debug_backtrace();
            switch ($db[1]['function']) {
                case '__callStatic':
                    $func = $db[1]['args'][0];
                    break;
                default:
                    $func = "I dont know how to dig for function name.";
            }
        }

        if (is_array($func)) {
            if (empty($class)) {
                $class = get_class($func[0]);
            }
            ENGINE::error('unresolved callable {{class}}({{real}})->{{method}}', array('{{class}}' => $class, '{{method}}' => $func[1], '{{real}}' => $class), $error_rule);
        } else
            ENGINE::error('unresolved callable {{function}}', array('{{function}}' => $func), $error_rule);
        return '';
    }

    static function _(&$val, $def = '')
    {
        return empty($val) ? $def : $val;
    }

    static function evaltpl($template, $par){
        $ns=\Ksnk\templater\template_compiler::options('namespace').'\\';
        do {
            $tmp_class = 'tmp_' . rand(10000, 99999);
        } while (class_exists($ns.$tmp_class));

        eval('?>'.\Ksnk\templater\template_compiler::compile_tpl($template, $tmp_class));
        $tmp_class=$ns.$tmp_class;
        $t=new $tmp_class();
        return  $t->_($par);
    }

    static function template($name, $method, $par = array())
    {
        static $cache;
        if (is_array($name)) {
            if ($method == '_') {
                $method = '_' . $name[1];
                $name = $name[0];
            } else {
                $name = $name[0];
            }
        }
//$par = array_merge($this->par, $par);
        if (empty($cache[$name])) {
            $ns=self::option('template_namespace','').$name;
            if (class_exists($ns)) {
                $class = new $ns();
            } else if (class_exists($name)) {
                $class= new $name();
            } else {
                ENGINE::error('method {{method}} not found',
                    array('{{method}}' => $method . '::' . $name),
                    array('function' => 'template', 'count' => 4)
                );
                return '';
            }
            $cache[$name] = $class;
        }
//debug($name,$par);
        if (!is_string($name)) {
            self::debug('wtf?', '~count|15');
        }
        $x = $cache[$name]->$method($par);
        return $x;
    }

    /*  --- point::ENGINE_body --- */

    /**
     * Регистрация обработчика событий
     *
     * @static
     * @param string|array $event
     * @param null|callable $handler
     * @param string $phase - значения pre||post
     */
    static public function register_event_handler($event, $handler = null, $phase = '')
    {
        if (is_array($event)) {
            foreach ($event as $ev) {
                self::register_event_handler($ev, $handler, $phase);
            }
            return;
        }
        if (!empty($phase)) {
            if (!preg_match('/^post|pre$/', $phase))
                self::error(sprintf('ENGINE: Wrong $phase($s) value awhile registerring event handler `$s`'
                    , $phase, $handler));
            else {
                $event .= '/' . $phase;
            }
        }
        if (!isset(self::$events[$event]))
            self::$events[$event] = array();
        array_push(self::$events[$event], $handler);
    }

    /**
     * убрать обработчик событий
     *
     * @static
     * @param $event
     * @param null|callable $handler - либо функция, либо символическое имя. при отсутствии параметра чистится вся очередь события
     */
    static public function unregister_event_handler($event, $handler = null)
    {
        if (!isset(self::$events[$event]))
            return;
        if (is_null($handler))
            self::$events[$event] = array();
        else if (is_callable($handler)) {
            foreach (array($event . '/pre', $event, $event . '/post') as $ev)
                if (isset(self::$events[$ev])) {
                    $key = array_search($handler, self::$events[$ev]);
                    if ($key !== false)
                        unset(self::$events[$ev][$key]);
                }
        }
    }

    /**
     * вызвать все обработчики события
     *
     * @static
     * @param $event
     * @param null $args
     */
    static public function trigger_event($event, $args = null)
    {
        foreach (array($event . '/pre', $event, $event . '/post') as $ev)
            if (isset(self::$events[$ev]))
                foreach (self::$events[$ev] as &$handle) {
                    self::exec($handle, array($event, &$args));
                }
    }


    /**
     * Выдать параметр по имени
     * @param string|array $name
     * @param mixed $default
     * @return mixed
     */
    static public function option($name = '', $default = '')
    {
        if (array_key_exists($name, self::$transport)) {
            $x = call_user_func(array(self::$transport[$name], 'get'), $name);
            if (is_null($x)) return $default;
            return $x;
        } else if (array_key_exists($name, self::$options)) {
            return self::$options[$name];
        } else {
            return $default;
        }
    }

    /**
     * @static
     * @param string|array $name
     * @param null $value
     * @param string|\object $transport
     * @return bool|mixed
     */
    static public function set_option($name = '', $value = null, $transport = '')
    {
        if (is_array($name))
            $transport = $value;

        if (!empty($transport) && is_string($transport) && !isset(self::$transports[$transport])) {
            self::read_options($transport);
            $transport = self::$transports[$transport];
        }

        if (empty($name)) {
            foreach (self::$transports as $v) {
                if (is_callable(array($v, 'save')))
                    call_user_func(array($v, 'save'));
            }
            return true;
        } else if (is_array($name)) {
            foreach ($name as $k => $v) {
                if (!is_numeric($k))
                    self::set_option($k, $v, $transport);
                else
                    self::$transport[$v] = $transport;
            }
            return true;
        } else if (!empty($transport)) {
            self::$transport[$name] = $transport;
        }
        if ($name == 'engine.aliaces')
            self::$class_alias = false;

        if (array_key_exists($name, self::$transport)) {
            $transport = self::$transport[$name];
        } else if (!empty(self::$default_transport)) {
            $transport = self::$default_transport;
        } else {
            if (!is_null($value)) {
                if (!is_array($value)
                    || !isset(self::$options[$name])
                    || !is_array(self::$options[$name])
                    || !array_key_exists($name, self::$options)
                ) {
                    self::$options[$name] = $value;
                } else {
                    UTILS::array_merge_deep(self::$options[$name], $value);
                }
                return true;
            } else if (array_key_exists($name, self::$options))
                return self::$options[$name];
            else
                return null;
        }
        if (is_string($transport))
            class_exists($transport);
        if (!is_null($value)) {
            call_user_func(array($transport, 'set'), $name, $value);
            return true;
        } else {
            return call_user_func(array($transport, 'get'), $name);
        }
    }

    /**
     * Создать пакет параметров
     * @param string $transport
     */
    static public function read_options($transport = '')
    {
        if (!empty($transport)
            && !array_key_exists($transport, self::$transports)
        ) {
            // отделяем имя от параметра
            $x = explode('|', $transport . '|');
            $y = explode('~', $x[0] . '~');
            self::$transports[$transport] = 'engine_options_' . $y[0];
            foreach (array('', 'engine_options_', '\\' . __NAMESPACE__ . '\\engine_options_') as $pref) {
                if (is_callable(array($pref . $y[0], 'init'))) {
                    self::$transports[$transport] =
                        call_user_func(array($pref . $y[0], 'init'), $y[1], $x[1]);
                    break;
                } else if (class_exists($pref . $y[0])) {
                    self::$transports[$transport] = $pref . $y[0];
                    break;
                }
            }
        }
    }

    static function slice_option($start)
    {
        $res = array();
        $reg = '#^' . preg_quote($start) . "(.*)$#";
        foreach (self::$transport as $k => $v) {
            if (preg_match($reg, $k, $m)) {
                $v = self::option($k);
                $res[$m[1]] = $v;
            }
        }
        foreach (self::$options as $k => $v) {
            if (preg_match($reg, $k, $m)) {
                $res[$m[1]] = $v;
            }
        }
        return $res;
    }


    static function start_session($log = true)
    {
        if (!self::$session_started) {
            $session_name = self::option('engine.sessionname');
            if (!empty($session_name)) {
                session_name($session_name);
            }
            session_set_cookie_params(ENGINE::option('engine.session_lifetime', 600), ENGINE::option('engine.session_path', '/'), ENGINE::option('engine.session_domain', null));
            session_start();
            setcookie(session_name(), session_id(), time() + ENGINE::option('engine.session_lifetime', 600), ENGINE::option('engine.session_path', '/'), ENGINE::option('engine.session_domain', null));

            /*
        if($log){
        $log = array();
        foreach (array('REMOTE_ADDR', 'X-Forwarded-For', 'X-Real-IP') as $name) {
            if (isset($_SERVER[$name])) {
                $log[$_SERVER[$name]] = $name;
            }
        }
            /*
        ENGIN 'Старт сессии.
IP:{{IP}}
REF:"{{HTTP_REFERER}}"
UA:"{{HTTP_USER_AGENT}}"', array('type' => 'session',
                '{{IP}}' => implode(',', array_keys($log)),
                '{{HTTP_REFERER}}' => ENGINE::_($_SERVER['HTTP_REFERER'], '-'),
                '{{HTTP_USER_AGENT}}' => ENGINE::_($_SERVER['HTTP_USER_AGENT'], '-')
            )
        );

            }*/
            self::$session_started = true;
        }
    }

    static function startSessionIfExists()
    {
        if (self::$session_started) {
            return;
        }

        $session_name = ENGINE::option('engine.sessionname', session_name());
        if (array_key_exists($session_name, $_GET)
            && array_key_exists($session_name, $_COOKIE)
        ) {
            ENGINE::set_option('action', 'reload');
        }
        if (array_key_exists($session_name, $_GET)
            || array_key_exists($session_name, $_COOKIE)
        ) {
            ENGINE::start_session(false); //session_start();
        }
    }

    static function close_session()
    {
        if (self::$session_started) {
            session_write_close();
            self::$session_started = false;
        }
    }


    /**
     * инициализация системы и прописка основных обработчиков
     * @static
     * @param string|array $options
     */
    static function init($options)
    {
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_cookies', '1');
        ini_set('session.bug_compat_42', '0');
        ini_set('allow_call_time_pass_reference', '1');

        if (defined('ROOT_URI')) ENGINE::set_option('page.root', ROOT_URI);

        if (is_array($options)) {
            ENGINE::set_option($options);
        } else if (is_readable($options)) {
            ENGINE::set_option(include($options));
        } else {
            ENGINE::error('Init: parameter failed');
        }
        //////////////////////////////////////////////////////////////////////////////////
// register all default interfaces
        if (method_exists('ENGINE', 'register_interface'))
            foreach (ENGINE::option('engine.interfaces', array()) as $k => $v)
                ENGINE::register_interface($k, $v);

//////////////////////////////////////////////////////////////////////////////////
// include all classes from `engine.include_files`
        foreach (ENGINE::option('engine.include_files', array()) as $f)
            include_once($f);

        self::$class_alias = ENGINE::option('engine.aliaces', array());

        foreach (ENGINE::option('external.options', array()) as $k => $v)
            ENGINE::set_option($k, null, $v);

        if (method_exists('ENGINE', 'register_event_handler'))
            foreach (ENGINE::option('engine.event_handler', array()) as $k => $v) {
                if (is_array($v) && count($v) > 0) {
                    foreach ($v as $vv) {
                        ENGINE::register_event_handler($k, $vv);
                    }
                }
            }
    }


    /**
     * функция получения информации о стеке вызовов.
     * @param array $opt - массив с ключами для вывода
     * @param int $count - количество позиций
     * @return string
     */
    static function backtrace($opt = array(), $count = 1)
    {
        $x = debug_backtrace();
        if (empty($opt)) $opt = array('function' => 'debug');
        if (isset($opt['count'])) $count = $opt['count'];
        $result = array();
        while (count($x) > 0) {
            foreach (array('function', 'class') as $xx) {
                if (isset($opt[$xx]) && isset($x[0][$xx]) && (0 === strpos($x[0][$xx], $opt[$xx]))) {
                    //array_shift($x);
                    break 2;
                } elseif (isset($opt['!' . $xx]) && isset($x[0][$xx]) && (false === strpos($x[0][$xx], $opt['!' . $xx])))
                    break 2;
            }
            array_shift($x);
        }
        if (empty($x)) {
            $x = debug_backtrace();
            $count = max(3, $count);
            array_shift($x);
        } else if (!empty($opt['shift'])) {
            array_shift($x);
        }
        while ($count-- && (count($x) > 0)) {
            $xx = array();
            $y = array_shift($x);
            foreach (array('function' => 'func', 'class' => 'cls', 'file' => 'file', 'line' => 'line') as $k => $v) {
                if (isset($y[$k]))
                    if ($k == 'file')
                        $xx[] = $v . ':' . str_replace($_SERVER['DOCUMENT_ROOT'], '', $y[$k]);
                    else
                        $xx[] = $v . ':' . $y[$k];
            }
            if (!empty($xx))
                $result[] = implode(',', $xx);
        }
        return implode("\n|", $result);
    }

    static function debug()
    {
        $backtrace_options = array('function' => 'debug');
        $backtrace_count = 1;
        $na = func_num_args();
        $out = '';
        for ($i = 0; $i < $na; $i++) {
            $msg = func_get_arg($i);

            if(is_string($msg) && strlen($msg)>1 && $msg[0]=='~'){
                $x = explode('|', substr($msg, 1) . '||');
                $backtrace_options[$x[0]] = $x[1];
            } else {
                $out .= self::varlog($msg) . "\r\n";
            }
        }
        if (empty($backtrace_options)) $backtrace_count = 4;
        echo '<!--xxx-debug-' . self::backtrace($backtrace_options, $backtrace_count) . ' ' . str_replace('-->', '--&gt;', trim(substr($out, 0, 16000))) . '-->';
    }

    /**
     * лог объекта в печатную форму
     *
     * @param $varInput
     * @param string $var_name
     * @param string $reference
     * @param string $method
     * @param bool $sub
     *
     * @return string
     *
     * http://us2.php.net/manual/en/function.var-dump.php#76072
     */
    static function varlog(
        &$varInput, $var_name = '', $reference = '', $method = '=', $sub = false
    )
    {

        static $output;
        static $depth;

        if ($sub == false) {
            $output = '';
            $depth = 0;
            $reference = $var_name;
            $var = serialize($varInput);
            $var = unserialize($var);
        } else {
            ++$depth;
            $var =& $varInput;
        }

        // constants
        $nl = "\n";
        $block = 'a_big_recursion_protection_block';

        $c = $depth;
        $indent = '';
        while ($c-- > 0) {
            $indent .= '|  ';
        }
        if ($depth > 5) return '...';
        // if this has been parsed before
        if (is_array($var) && isset($var[$block])) {

            $real =& $var[$block];
            $name =& $var['name'];
            $type = gettype($real);
            $output .= $indent . $var_name . ' ' . $method . '& ' . ($type == 'array' ? 'Array' : get_class($real)) . ' ' . $name . $nl;

            // havent parsed this before
        } else {

            // insert recursion blocker
            $var = array($block => $var, 'name' => $reference);
            $theVar =& $var[$block];

            // print it out
            $type = gettype($theVar);
            switch ($type) {

                case 'array' :
                    $output .= $indent . $var_name . ' ' . $method . ' Array (' . $nl;
                    $keys = array_keys($theVar);
                    foreach ($keys as $name) {
                        $value =& $theVar[$name];
                        self::varlog($value, $name, $reference . '["' . $name . '"]', '=', true);
                    }
                    $output .= $indent . ')' . $nl;
                    break;

                case 'object' :
                    $output .= $indent;
                    if (!empty($var_name)) {
                        $output .= $var_name . ' = ';
                    }
                    //$output .= '{'.var_export ($theVar,true).$indent.'}'.$nl;
                    //break;
                    //if( !class_exists('ReflectionClass')){
                    $output .= get_class($theVar) . ' {' . $nl;
                    foreach ((array)$theVar as $name => $value) {
                        self::varlog($value, $name, $reference . '->' . $name, '->', true);
                    }
                    /*} else {
                        $reflect = new ReflectionClass($theVar);
                        $output .= $reflect->getName().' {'.$nl;
                        $props   = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);

                        foreach ($props as $prop) {
                           // if()
                            self::varlog($theVar->{$prop}, $prop, $reference.'->'.$prop, '->', true);
                            print $prop->getName() . "\n";
                        }
                    }*/
                    $output .= $indent . '}' . $nl;
                    break;

                case 'string' :
                    if ($var_name != '' && strlen($theVar) > 1000) {
                        $output .= $indent . $var_name . ' ' . $method . ' "' . mb_substr($theVar, 0, 500, "UTF-8") . '..."' . $nl;
                    } else {
                        $output .= $indent . $var_name . ' ' . $method . ' "' . $theVar . '"' . $nl;
                    }
                    break;

                default :
                    $output .= $indent . $var_name . ' ' . $method . ' (' . $type . ') ' . $theVar . $nl;
                    break;

            }

            // $var=$var[$block];

        }

        --$depth;

        if ($sub == false)
            return $output;
        return '';
    }


    /**
     * @param $flag
     * @return int
     * @sample if(DBG::hasflag('test1')){ ...
     */
    static function hasflag($flag)
    {
        return isset($_COOKIE) &&
        isset($_COOKIE[self::$cookie_name]) &&
        preg_match('/\b' . preg_quote($flag) . '\b/', $_COOKIE[self::$cookie_name]);
    }

    /**
     * setting up cookie in case user want to start debuging
     * @return bool
     * @sample if(DBG::initflags()) header('location: '.#clear URL from testing parameter#)
     */
    static function initflags()
    {
        if (isset($_GET) && isset($_GET[self::$cookie_name]) && !preg_match('/[^\w,\+\-\.\s]/', $_GET[self::$cookie_name])) {
            if (preg_match('/[\-\s\+]/', $_GET[self::$cookie_name])) {
                if (isset($_COOKIE[self::$cookie_name])) {
                    $cookie = preg_split('/[,]+/', $_COOKIE[self::$cookie_name]);
                } else {
                    $cookie = array();
                }
                if (preg_match_all('/([\s\-\+])([\w]*)/', $_GET[self::$cookie_name], $m))
                    foreach ($m[0] as $k => $v) {
                        if ($m[1][$k] != '-') $cookie[] = $m[2][$k];
                        else {
                            $cookie = array_diff($cookie, array($m[2][$k]));
                        }
                    }
                $cookie = implode(',', array_unique($cookie));
            } else {
                $cookie = trim($_GET[self::$cookie_name]);
            }
            if (!empty($cookie)) {
                self::set_option(self::$cookie_name, '');
                setcookie(self::$cookie_name, $cookie);
            } else {
                self::set_option(self::$cookie_name, '');
                setcookie(self::$cookie_name, "", time() - 3600);
            }
        }
        if (isset($_COOKIE[self::$cookie_name])) {
            self::set_option(self::$cookie_name, $_COOKIE[self::$cookie_name]);
        }
    }


    /**
     * Регистрация нового интерфейса или сброс, если второго параметра нет
     *
     * @static
     * @param $method - имя метода
     * @param null|callable $callable - параметры регистрации
     * @return null - последнее значение хандлера для возможности вернуть предыдущее
     */
    static public function register_interface($method, $callable = null)
    {
        $result = null;
        if (!is_string($method)) {
            ENGINE::error('wrong interface definition');
            return $result;
        }
        if (isset(self::$interface[$method])) {
            // so return the past one.
            $result = self::$interface[$method];
        }
        if (is_null($callable))
            unset(self::$interface[$method]);
        else
            self::$interface[$method] = $callable;
        return $result;
    }

    /**
     * Just a little magic
     * Простой механизм для монтажа расширений
     *
     * @param $method
     * @param $args
     * @return mixed
     */

    static public function __callStatic($method, $args)
    {
        if (isset(self::$interface[$method])) {
            return self::exec(self::$interface[$method], $args);
        } else {
            $class = self::option('interface.' . $method, false);
            if (false !== $class && is_callable(array($class, $method))) {
                self::register_interface($method, array($class, $method));
                return self::exec(self::$interface[$method], $args);
            }
            return null;
        }
    }


    /**
     * хяндлер ENGINE::DB
     *
     * @param string $option строка параметров
     *
     * @return \Ksnk\model\database\xDatabaseLapsii
     */
    static public function &db($option = '')
    {
        if (empty(self::$db)) {
            self::$db = self::getObj('Database', $option);
        }
        if (!empty(self::$db)) self::$db->set_option($option);
        return self::$db;
    }


    /**
     * организация массива - регулярка, имена захваченных
     * Выставляет options - ajax, Class, method
     *
     * @param null $rules
     * @param bool $single - не запускать роутинг от Main
     */
    static function route($rules = null, $single=false)
    {

        /** @var array $rules */
        if (empty($rules)) {
            /**
             * дефолтное правило - если нет роутинга в конфиге
             * - показываем стартовую страницу
             */
            $rules = ENGINE::option(
                'router.rules',
                array(array('', array('class' => 'Main', 'method' => 'do_Default')))
            );
        }

        /**
         * @var string $query_string - очищенная от стартового
         * каталога строка запроса
         */
        $query_string = preg_replace(
            '#^' . ENGINE::option('page.rootsite') . '#i',
            '',
            $_SERVER['REQUEST_URI']
        );

        /** аварийное правило, если никакое правило роутинга не подойдет  */
        ENGINE::set_option(
            array('class' => 'Main', 'method' => 'do_404', 'query_string'=>$query_string)
        );

        $code = UTILS::val($_SERVER,'REDIRECT_STATUS');
        $codes = array(
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error'
        );
        $source_host = 'http'.((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 's' : '').'://'.$_SERVER['HTTP_HOST'];
//.$_SERVER['REQUEST_URI'];
        if (array_key_exists($code, $codes) && is_numeric($code)) {
            if ($code==403){
                // forbiden?
                header('location:'.$source_host);
                die("Error $code: {$codes[$code]}");
            }
        } else {
            //die('Unknown error');
            self::set_option('page.host',$source_host);
            foreach ($rules as $rule) if (!empty($rule)) {
                if (empty($rule[0]) || preg_match($rule[0], $query_string, $m)) {
                    if (is_callable($rule[1])) {
                        if (true === call_user_func($rule[1], $m))
                            return;
                    } else
                        foreach ($rule[1] as $k => $v) {
                            if (is_int($k)) {
                                if (!empty($m[$k])) {
                                    if ($v == 'method') {
                                        $m[$k] = 'do_' . $m[$k];
                                    }
                                    ENGINE::set_option($v, $m[$k]);
                                }
                            } else {
                                ENGINE::set_option($k, $v);
                            }
                        }
                    break;
                }
            }
            if(!$single)
                self::exec(['Main','route']);
        }

    }

    /**
     * строим ссылку, по полученным параметрам
     * @param string $z - адреc для перехода
     * @param string|array $act - действие
     * @param null $par - дополнительные параметры
     * @return string
     */
    static function link($z = '', $act = '', $par = null)
    {
        if (is_null(self::$url_par)) {
            self::$url_par = $_GET;
        }
        if (is_null(self::$url_path)) {
            self::$url_path = preg_replace("/\?.*$/", "", $_SERVER['REQUEST_URI']);
        }
        //$uri = ENGINE::option('page.rootsite');
        $z = str_replace('\\', '/', $z);
        $host = 'http://'
            . $_SERVER["SERVER_NAME"]
            . (80 == $_SERVER["SERVER_PORT"] ? '' : ':' . $_SERVER["SERVER_PORT"]);
        if (!is_array($act)) {
            $act = array(array($act, $par));
        }
        foreach ($act as $x) {
            $action = $x[0];
            $param = self::_($x[1], '');
            switch ($action) {
                case '+':
                    if (empty($param))
                        self::$url_par = array();
                    self::$url_par = array_merge(self::$url_par, $param);
                    break;
                case '-':
                    if (empty($param)) {
                        self::$url_par = array();
                    } else {
                        self::$url_par = array_diff_key(self::$url_par, array_flip($param));
                    }
                    break;
                case 'file2url':
                    $z=\Autoload::find($z);
                    if (empty($par)) {
                        $query = '';
                    } else {
                        $query = $par;
                    }
                    if (!empty($query))
                        $query = '?' . $query;
                   // if (!defined('INDEX_DIR')) {
                        if (isset($_SERVER['SCRIPT_FILENAME']) && isset($_SERVER['SCRIPT_NAME'])) {
                            $root = str_replace($_SERVER['SCRIPT_NAME'], '', str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']));
                        } else {
                            $root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
                        }
                        $root=rtrim($root,'/');
                        $z = str_replace(' ','%20',str_replace($root, '', str_replace('\\','/',$z)));
                   // } else
                    //    $z = str_replace(str_replace('\\', '/', INDEX_DIR), '', $z);
                    return $z . $query;
                case 'root':
                    self::$url_path = ENGINE::option('page.rootsite', self::$url_path) . $z;
                    break;
                case 'replace':
                    self::$url_par = $param;
                    break;
            }
        }
        if (!empty(self::$url_par))
            $query = http_build_query(self::$url_par);
        else
            $query = '';
        if (!empty($query))
            $query = '?' . $query;
        return self::$url_path . $query;

    }


    static public function _report()
    {
        echo '<!--';
        /*  --- point::ENGINE_final_report --- */

        if (!empty(self::$db)) {
            echo self::$db->report();
        }

        printf("%.03f sec spent (%s)"
            , microtime(true) - self::$start_time, date("Y-m-d H:i:s"));
        echo '-->';
    }

    static public function _shutdown()
    {
        /*  --- point::ENGINE_shutdown --- */

        if (ENGINE::option('noreport')) return;
        ENGINE::_report();
    }


    static function relocate($link)
    {
        if (ENGINE::hasflag('norelock') || ENGINE::option('debug', false)) {
            echo '<a href="' . $link . '">Press link to redirect</a>';
        } else {
            header('location:' . $link);
        }
        exit;
    }

    static function ajax_action()
    {
        //ENGINE::set_option('ajax', true);
        if ('iframe' == self::option('ajax'))
            header('Content-type: text/html; charset=UTF-8');
        else
            header('Content-type: application/json; charset=UTF-8');
        if ('POST' == $_SERVER['REQUEST_METHOD'] &&
            (array_key_exists('handler', $_POST) || !ENGINE::option('skip_post', false))
        ) {
            if (array_key_exists('handler', $_POST)) {
                preg_match(
                    '/^([^:]*)::([^:]+)(?::([^:]+))?(?::([^:]+))?(?::([^:]+))?$/',
                    str_replace('%3A', ':', $_POST['handler']), $m
                );
                if (empty($m[1])) {
                    $m[1] = 'Main';
                }
                if (empty($m[2])) {
                    ENGINE::error('Wrong handler.');
                }
                for ($i = 3; $i < 6; $i++) {
                    if (!array_key_exists($i, $m)) {
                        $m[$i] = '';
                    }
                }
                $act = array($m[1], 'do_' . $m[2]);
                $data = ENGINE::exec($act, array($m[3], $m[4], $m[5]));
            } else {
                //ENGINE::error('Wrong usage of POST method.');
                $data = self::getData();
            }
        } else {
            /*  --- point::BEFORE_GETDATA --- */

            ENGINE::trigger_event('BEFORE_GETDATA');

            $data = self::getData();
        }

        $result = array('data' => $data);
        $x = ob_get_contents();
        $x .= trim(ENGINE::option('page.debug'));
        if (!empty($x)) {
            $result['debug'] = utf8_encode($x);
        }
        ob_end_clean();
        self::_finish_ajax($result);

    }

    static function printgzip($contents)
    {
        //echo utf8_encode(json_encode($result));
        $HTTP_ACCEPT_ENCODING = $_SERVER["HTTP_ACCEPT_ENCODING"];
        if (headers_sent())
            $encoding = false;
        else if (strpos($HTTP_ACCEPT_ENCODING, 'x-gzip') !== false)
            $encoding = 'x-gzip';
        else if (strpos($HTTP_ACCEPT_ENCODING, 'gzip') !== false)
            $encoding = 'gzip';
        else
            $encoding = false;

        if ($encoding) {
            $_temp1 = strlen($contents);
            if ($_temp1 < 2048)    // no need to waste resources in compressing very little data
                print($contents);
            else {
                header('Content-Encoding: ' . $encoding);
                print("\x1f\x8b\x08\x00\x00\x00\x00\x00");
                $contents = gzcompress($contents, 9);
                $contents = substr($contents, 0, $_temp1);
                print($contents);
            }
        } else
            print $contents;

    }

    static function _finish_ajax($result = array())
    {
        $data = self::slice_option('ajax.');
        if (!empty($data)) {
            $result = array_merge($result, $data);
        }
        $error = self::option('page.error');
        if (!empty($error)) {
            if (!isset($result['error'])) $result['error'] = '';
            $result['error'] .= utf8_encode($error);
        }
        $x = ob_get_contents();
        $x .= trim(self::option('page.debug'));
        if (!empty($x)) {
            $result['debug'] = utf8_encode($x);
        }
        ob_end_clean();
        if (session_id() != "") {
            $result['session'] = array('name' => session_name(), 'value' => session_id());
        }

        // $q=ENGINE::db()->exec('Database',get_request_count
        ob_start();
        self::_report();
        $result['stat'] = ob_get_contents();
        ob_end_clean();
        self::set_option('noreport', 1);
        //echo json_encode_cyr($result);
        $contents = utf8_encode(json_encode($result));
        if ('iframe' === self::option('ajax')) {
            $contents = '<script type="text/javascript"> top.' . self::option('iframe_callback', 'ajax_handle') . '(' . $contents . ')</script>';
        }
        self::printgzip($contents);

    }

    static function getData()
    {
        $x = array(ENGINE::option('class', 'Main'), ENGINE::option('method', 'do_Default'));
        //if(class_exists())
        if (!ENGINE::getObj($x[0])) {
            $x = array('Main', 'do_404');
        }
        return ENGINE::exec($x,ENGINE::option('idx1'));
    }

    static function headers()
    {
        static $headers = array();
        if (empty($headers)) {
            if (is_callable('apache_request_headers')) {
                $headers = apache_request_headers();
            } else {
                $headers = array();
                foreach ($_SERVER as $key => $value) {
                    if (substr($key, 0, 5) == "HTTP_") {
                        $key = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($key, 5)))));
                        $headers[$key] = $value;
                    } else {
                        $headers[$key] = $value;
                    }
                }
            }
        }
        return $headers;
    }

    static function action()
    {
        $headers = ENGINE::headers();
        if ((isset($headers['X-Requested-With']) && $headers['X-Requested-With'] == 'XMLHttpRequest')
            || (isset($headers['x-requested-with']) && $headers['x-requested-with'] == 'XMLHttpRequest')
            || isset($_GET['ajax'])
            || (isset($_POST) && isset($_POST['ajax']))
        ) {
            if(isset($_POST['ajax']) && $_POST['ajax']=='iframe')
                self::set_option('ajax', 'iframe');
            else
                self::set_option('ajax', 'json');
        }
        try {
            ob_start();
            $error = ENGINE::option('session.page.error');
            if (!empty($error)) {
                ENGINE::set_option('session.page.error', '');
                ENGINE::error($error);
            }

            if ('' != self::option('ajax')) {

                self::ajax_action();
                return;
            }
            header('Content-Type:text/html; charset=' . ENGINE::option('page.code', 'UTF-8'));
            header('X-UA-Compatible: IE=edge,chrome=1');

            if (isset($_SESSION['SAVE_POST'])) {
                if ('POST' != $_SERVER['REQUEST_METHOD']) {
                    $_SERVER['REQUEST_METHOD'] = 'POST';
                    $_POST = $_SESSION['SAVE_POST'];
                    $_FILES = $_SESSION['SAVE_FILES'];
                }
                unset($_SESSION['SAVE_POST'], $_SESSION['SAVE_FILES']);
            }

            if ('POST' == self::_($_SERVER['REQUEST_METHOD']) &&
                (array_key_exists('handler', $_POST) || ENGINE::option('skip_post', false))
            ) {
                if (array_key_exists('handler', $_POST)) {
                    preg_match('/^([^:]*)::([^:]+)(?::([^:]+))?(?::([^:]+))?(?::([^:]+))?$/'
                        , $_POST['handler'], $m);
                    if (empty($m[1])) $m[1] = 'Main';
                    if (empty($m[2])) ENGINE::error('Wrong handler.');
                    for ($i = 3; $i < 6; $i++)
                        if (!array_key_exists($i, $m)) $m[$i] = '';
                    $act = array($m[1], 'do_' . $m[2]);
                    ENGINE::exec($act, array($m[3], $m[4], $m[5]));
                } else
                    ENGINE::error('Wrong usage of POST method.');
                $error = ENGINE::option('page.error');
                if (!empty($error)) {
                    ENGINE::set_option('session.page.error', $error);
                }
                ENGINE::relocate(ENGINE::link());
            }
            /*  --- point::BEFORE_GETDATA --- */

            ENGINE::trigger_event('BEFORE_GETDATA');

            $data = self::getData();

            $x = ENGINE::template(
                ENGINE::option('page_tpl', 'main')
                , ENGINE::option('page_macro', '_')
                , array_merge(array('data' => $data), ENGINE::slice_option('page.'))
            );
            if (''===$x) {
                ENGINE::error($x = ENGINE::_t('template `{{tpl}}::{{macro}}` not defined',
                    array('{{tpl}}' => ENGINE::option('page_tpl', 'main'),
                        '{{macro}}' => ENGINE::option('page_macro', '_'))));
                $x = '<html><head><title>Oops</title></head><body>' . $x . '</body></html>';
            }
            echo UTILS::ob_html_compress($x);
        } catch (\Exception $e) {
            ENGINE::error($x = ENGINE::_t('Exception pending `{{msg}}`',
                array('{{msg}}' => $e->getMessage())));
            if (!UTILS::detectUTF8($x)) $x = iconv('cp1251', 'utf-8', $x);
            echo '<html><head><title>Oops</title></head><body>' . $x . '</body></html>';
        }
    }

}

/*  --- point::ENGINE_bottom --- */

register_shutdown_function(__NAMESPACE__.'\ENGINE::_shutdown');
ENGINE::$start_time = microtime(true);
