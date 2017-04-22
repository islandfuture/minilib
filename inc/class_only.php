<?php
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
 *         class ActiveUser extends One { ... }
 *         ActiveUser::one(); // вернет единственный экземпляр ActiveUser
 */

class Only
{
    /**
     * @var Array массив для хранения уникальных экземпляров
     */
    private static $_arInstances=array();
    
    private function __construct($arParams = null)
    {
        $classname = get_called_class();

        if(method_exists($classname, 'afterConstruct') ) {
            $this->afterConstruct($arParams);
        }
    } // блокируем доступ к функции
    private function __clone()
    {
    } // блокируем доступ к функции
    private function __wakeup()
    {
    } // блокируем доступ к функции
    
    /**
     * возврщает один и тот же экземпляр этого класса
     * @example Only::one()
     * @return Only
     **/
    public static function one() 
    {
        $classname = get_called_class();
        strtolower($classname);
        
        if (empty(self::$_arInstances[$classname]) ) {           
            self::$_arInstances[$classname] = new $classname();
        }

        return self::$_arInstances[$classname];
    }    


}
/* end class Only */
