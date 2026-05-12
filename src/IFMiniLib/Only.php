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
 *         ActiveUser::one(); // вернет единственный экземпляр ActiveUser
 */

class Only
{
    /**
     * @var array<class-string, static> массив для хранения уникальных экземпляров
     */
    private static $_instances = [];

    private function __construct($params = null)
    {
        $className = get_called_class();

        if (method_exists($className, 'afterConstruct')) {
            $this->afterConstruct($params);
        }
    }

    // блокируем доступ к функции
    public function __clone()
    {
        throw new \Exception("__clone not available");
    }

    // блокируем доступ к функции
    public function __wakeup()
    {
        throw new \Exception("__wakeup not available");
    } // блокируем доступ к функции

    /**
     * возврщает один и тот же экземпляр этого класса
     * @example Only::one()
     * @return static
     **/
    public static function one($params = null)
    {
        $className = static::class;

        if (empty(self::$_instances[$className]) ) {
            self::$_instances[$className] = empty($params)
                ? new static()
                : new static($params);
        }

        return self::$_instances[$className];
    }
}
/* end class Only */
