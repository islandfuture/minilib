<?php
namespace IFMiniLib;

/**
 * Реализация паттерна "Одиночный/Singleton" + "Registry",
 * Все синглтоны должны наследоваться от этого класса.
 *
 * @link    https://github.com/islandfuture/minilib
 * @author  Michael Akimov <michael@island-future.ru>
 * @version GIT: $Id$
 *
 * @example
 *        Only::one() //вернет один экземпляр себя
 *
 * @example
 *         class ActiveUser extends Only { ... }
 *         ActiveUser::I(); // вернет единственный экземпляр ActiveUser
 */

class Only
{
    /**
     * @var Array массив для хранения уникальных экземпляров
     */
    private static $_instances=array();

    private function __construct($params = null)
    {
        $className = get_called_class();

        if (method_exists($className, 'afterConstruct') ) {
            $this->afterConstruct($params);
        }
    }

    // блокируем доступ к функции
    public function __clone()
    {
        throw new Exception("__clone not available");
    }

    // блокируем доступ к функции
    public function __wakeup()
    {
        throw new Exception("__wakeup not available");
    } // блокируем доступ к функции

    /**
     * возврщает один и тот же экземпляр этого класса
     * @example Only::I()
     * @return Only
     **/
    public static function I($params = null)
    {
        $className = get_called_class();
        $className = strtolower($className);

        if (empty(self::$_instances[$className]) ) {
            if (empty($params)) {
                self::$_instances[$className] = new $className();
            } else {
                self::$_instances[$className] = new $className($params);
            }
        }

        return self::$_instances[$className];
    }


}
/* end class Only */
