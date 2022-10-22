<?php
/**
 * Реализация инерфейса cache для варианта с xcached
 * User: Сергей
 * Date: 10.06.13
 * Time: 10:52
 * To change this template use File | Settings | File Templates.
 */
/*  --- point::ENGINE_namespace --- */
namespace Ksnk\core;

class ENGINE_nocache implements engine_cache
{
    /**
     *  доступ к xcached
     * @static
     * @param $key
     * @param null $value
     * @param int $time в секундах 28800 - 8 часов
     * @return bool|null
     */
    static function cache($key, $value = null, $time = null, $tags=null)
    {
        return false;
    }
}