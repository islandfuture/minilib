<?php
namespace IFMiniLib;

/**
 * Подключение конфигов, когда нужно
 * Пример использования: Config::get('redis', 'port')
 * вернет значение параметра port из конфига config.redis.php
 **/
class Config
{
    public static $app = null;
    public static function get($config, $name='')
    {
        static $configs = [];
        if (! isset($configs[$config])) {
            $fileconfig = App::I()->PATH_APP . '/configs/config.'.$config.'.php';
            if (! file_exists($fileconfig)) {
                return null;
            }
            $configs[$config] = include_once $fileconfig;
        }

        if ($name=='') {
            return $configs[$config];
        }

        return isset($configs[$config][$name]) ? $configs[$config][$name] : null;
    }
}
