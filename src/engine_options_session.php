/**
 * класс для хранения параметров в сессии
 */
/*  --- point::ENGINE_namespace --- */
namespace Ksnk\core;

class engine_options_session implements engine_options
{

    function get($name)
    {
        if (ENGINE::$session_started)
            return $_SESSION[$name];
        else
            return null;
    }


    function set($name, $value = null)
    {
        ENGINE::start_session();
        if (empty($value))
            unset($_SESSION[$name]);
        else
            $_SESSION[$name] = $value;
    }

}