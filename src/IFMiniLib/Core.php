<?php
namespace IFMiniLib;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'Only.php';
/**
 * Класс "Приложение", отвечающий за инициализацию, определения страницы отображения,
 * первичной обработки входных данных, контролем текущих процессов и отображением выходных данных
 *
 * @link    https://github.com/islandfuture/minilib
 * @author  Michael Akimov <michael@island-future.ru>
 * @version 2.01.00
 *
 * @example App::one()->init(); // считываем данные из конфига и подготавливаем все для работы
 */

use IFMiniLib\Only;
use IFMiniLib\ActiveUser;

class Core extends Only
{
    // объект приложения
    public static $app = null;

    public $nameSpace;

    //@var Array массив параметров конфигурации
    private $configs = [];

    //@var array массив для обмена переменными между блоками
    public $properties = [];

    public $userLang = '';

    protected $modules = [];

    /**
     * Возвращает данные, сохраненные в App::$configs в индексе $name
     * @param string $name имя переменных
     *
     * @example
     *  $superData = App::one()->superData;
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (! isset($this->configs[$name])) {
            return null;
        }

        return $this->configs[$name];
    }

    /**
     * Сохраняем данные, в App::$configs в индексе $name
     * @param string $name имя индекса в configs, где сохраняются данные $val
     * @param mixed $val
     *
     * @example
     *  App::one()->superData = array('example' => true);
     *
     * @return mixed
     */
    public function __set($name, $val)
    {
        $this->configs[$name] = $val;
        return $this;
    }

    public function __isset($name)
    {
        return isset($this->configs[$name]);
    }

    /**
     * Инициализируем все первоначальные данные. Этот метод должен вызываться на каждой странице сайта.
     */
    public function init()
    {
        /* если скрипт вызвали в консоли, то создадим переменную $_SERVER */
        if (empty($_SERVER)) {
            $_SERVER = array();
        }

        $sAppCorePath = dirname(__DIR__) . '/';

        /* DOCUMENT_ROOT must set to public directory */
        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $reflection = new \ReflectionClass(get_class($this));
            $sAppPath = dirname($reflection->getFileName()) . '/';
            $sRootPath = dirname($sAppPath) . '/';
            $sPublicPath = $_SERVER['DOCUMENT_ROOT'] = $sRootPath . '/' . 'public'  . '/';
        } else {
            $sPublicPath = $_SERVER['DOCUMENT_ROOT'] = realpath($_SERVER['DOCUMENT_ROOT']);
            $sRootPath = dirname($sPublicPath) . '/';
            $sAppPath  = $sRootPath . 'app' . '/';
        }

        $this->nameSpace = static::class;
        if (strpos($this->nameSpace, '\\') > 0) {
            $this->nameSpace = substr($this->nameSpace, 0, strrpos($this->nameSpace, '\\'));
        } else {
            $this->nameSpace = '';
        }
        $sConfig = $sRootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

        /* если нет конфига, то баста - ругаемся */
        if (! file_exists($sConfig)) {
            throw new \Exception('Cannot load config file [' . $sConfig . ']');
        }

        /**
         * Включаем буферизацию и подключаем файл с конфигом и возможно файл прав доступа.
         * Если в конфиге есть какие-то ошибки или что-то выводится на экран, то буферизация этого не позволит.
         * Также все данные из конфига помещается на $this->configs
         **/
        ob_start();
        $this->configs = (include_once $sConfig);
        ob_end_clean();

        if (empty($this->configs['debug']) || $this->configs['debug'] != 'Y') {
            $this->configs['debug'] = 'N';
        }

        $this->configs['PATH_ROOT'] = $sRootPath;
        $this->configs['PATH_CORE'] = $sAppCorePath;
        $this->configs['PATH_PUBLIC'] = $sPublicPath;

        $sVendorPath = $sRootPath . 'vendor' . '/';
        if (empty($this->configs['PATH_VENDOR'])) {
            $this->configs['PATH_VENDOR'] = $sVendorPath;
        }

        if (empty($this->configs['PATH_APP'])) {
            $this->configs['PATH_APP'] = $sAppPath;
        }

        $this->configs['PATH_TEMPLATE'] = $this->PATH_ROOT . 'tpl' . '/';
        $this->configs['PATH_LAYOUT'] = $this->PATH_ROOT . 'layout' . '/';
        $this->configs['PATH_RESOURCES'] = $this->PATH_ROOT . 'resources' . '/';

        // регистрируем автозагрузчик классов
        spl_autoload_register([$this, 'autoloadClasses'], true, true);

        $this->startScript = date('Y-m-d H:i:s');


        /* глобальные переменные для хранения последних ошибок или сообщений */
        $this->properties['lasterror'] = '';
        $this->properties['lastmessage'] = '';
        $this->properties['js_bottom'] = array();

        error_reporting(E_ALL);
        if ($this->debug == 'Y') {
            ini_set('display_errors', 1);
        }

        /* регистрируем обработчики исключений и ошибок */
        set_error_handler(array($this,'appError'), E_ALL | E_USER_ERROR);
        set_exception_handler(array($this, 'appException'));
        register_shutdown_function(array($this,'appShutdown'));
        ignore_user_abort(true);
        /* если в приложении нужны сессии, то подклбчаем их */
        if ($this->session && $this->session != 'none' && $this->session != 'auto') {
            /* Сессию можно соединить с классом отвечающим за юзеров */
            ActiveUser::$sUserClassName = $this->user > '' ? $this->user : 'none';
            $session = ActiveUser::one();
            if ($session->hasError()) {
                throw new \Exception('Cannot start user session');
            }
        } elseif ($this->session == 'auto') {
            ActiveUser::$sUserClassName = 'none';
            session_start();
        }

        return $this;
    }

    /**
     * функция для автозагрузки классов
     * @param string $className - название класса, который нужно загрузить
     **/
    public function autoloadClasses($className)
    {
        if (! isset($this->configs['include'])) {
            $this->configs['include'] = array();
        }
        $originalClassName = $className;
        $className = ltrim(str_replace('\\', '/', $className), '/');
        if ($this->nameSpace > '' && $this->nameSpace == substr($className, 0, strlen($this->nameSpace))) {
            $className = substr($className, strlen($this->nameSpace) + 1);
        }

        /* Проверяем, есть ли класс в массиве для автозагрузки классов */
        if (file_exists($this->PATH_APP . $className . '.php') ) {
            require_once $this->PATH_APP . $className . '.php';
            return true;
        } elseif (file_exists($this->PATH_CORE . $className . '.php') ) {
            require_once $this->PATH_CORE . $className . '.php';
            return true;
        } elseif (array_key_exists($className, $this->configs['include'])) {
            if (substr($this->configs['include'][ $className ], 0, 1) == '/') {
                $sPath = $this->configs['include'][ $className ];
            } else {
                $sPath = $this->PATH_VENDOR . $this->configs['include'][ $className ];
            }

            if (file_exists($sPath)) {
                require_once $sPath;
                return true;
            } else {
                return;
                // throw new \Exception('Class ['.$className.'] not found in path ['.$sPath.']');
            }
            return;
        } else {
            return ;
            //throw new \Exception('Class ['.$className.'] not exists in include settings');
        }
    }

    /**
     * Функция вешается как обработчик ошибок
     *
     * @param int $errno номер ошибки
     * @param string $errstr текст ошибки
     * @param string $errfile название файла в котором произошла ошибка
     *
     * @return bool
     */
    public function appError($errno, $errstr, $errfile = __FILE__, $errline = __LINE__, $errcontext  =array())
    {
        throw new \ErrorException($errstr, $errno, 1, $errfile, $errline);
    }

    /**
     * Функция вешается на исключения
     */
    public function appException($e)
    {
        $debugstr = $e->getTraceAsString();
        $pos1 = strpos($debugstr, 'PDO->__construct(');
        $pos2 = strpos($debugstr, ')', $pos1 + 5);
        if ($pos1 > 0 && $pos2 > 0) {
            $debugstr = str_replace(substr($debugstr,$pos1+17,$pos2-$pos1-17),'***', $debugstr);
        }
        $err  = $e->getMessage() . " = " . $e->getFile(). " = " . $e->getLine() . "\r\n" . $debugstr . "\r\n";
        $this->log($err, [], 'error');
        if (!empty(static::one()->buglowers['to'])) {
            $headers = 'From: no-reply';
            @mail(static::one()->buglowers['to'], 'Error Handler', $err, $headers);
        }

        if (static::one()->debug == 'Y') {
            try {
                ob_end_clean();
            } catch(\Exception $ee){
            }

            if(static::one()->output == 'json') {
                die(json_encode(array('error' => "Error code " . $e->getCode() . ": ".$e->getMessage().' in line ['.$e->getLine().'] in file ['.$e->getFile().']'."\n", 'errortrace' => $debugstr)));
                return;
            }
            echo "<pre>-----------------------------\n";
            echo "Error code " . $e->getCode() . ": ".$e->getMessage().' in line ['.$e->getLine().'] in file ['.$e->getFile().']'."\n";

            echo $debugstr;
            echo "\n-----------------------------\n";
            die();
        }

        if(static::one()->output == 'json') {
            header('HTTP/1.0 500 Internal server error', true);
            die(json_encode(array('error' => 'Unknown error')));
            return;
        }

        if (static::one()->errorpage5xx > '' && file_exists(static::one()->PATH_PUBLIC.static::one()->errorpage5xx)) {
            header('HTTP/1.0 500 Internal server error', true);
            echo file_get_contents(static::one()->PATH_PUBLIC.static::one()->errorpage5xx);
            exit;
        }
        die('<html><head><title>Error in '.static::one()->web['name'].'</title><meta charset="utf-8" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head><body><table style="width:100%; height:100%;"><tr><td style="vertical-align: middle; text-align: center;"><h1>Temporary error</h1><p>Please reload page later.</p></td></tr></table></body></html>');
    }

    public static function appShutdown()
    {
        $error = error_get_last();
        if (! $error) {
            return ;
        }

        if (
            ($error['type'] === E_ERROR) 
            || ($error['type'] === E_USER_ERROR)
            || ($error['type'] === E_USER_NOTICE)
        ) {
            $errstr = "ERROR: " . $error['type'] . " |Msg : " . $error['message'] . " |File : " . $error['file'] . " |Line : " . $error['line'];
            static::one()->log($errstr, [], 'error');

            if (static::one()->output == 'json') {
                header('HTTP/1.0 500 Internal server error');
                if (static::one()->debug == 'Y') {
                    die(json_encode(array('error' => $errstr)));
                } else {
                    die(json_encode(array('error' => 'Unknown error')));
                }
                return;
            }

            if (static::one()->debug == 'Y') {
                echo "<pre>-----------------------------\n";
                echo $errstr . "\n";
                echo "\n-----------------------------\n";
            }

            die('<html><head><title>Unknown error in ' . static::one()->web['name'] . '</title><meta charset="utf-8" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head><body><table style="width:100%; height:100%;"><tr><td style="vertical-align: middle; text-align: center;"><h1>Temporary error</h1><p>Please reload page later.</p></td></tr></table></body></html>');
        } else {
            //echo "no error where found " ;
        }
    }

    /**
     * метод нужен для избежания не правильной инициализции класса в качестве синглтона.
     */
    protected function afterConstruct()
    {
        \IFMiniLib\Core::$app = $this;

        if ($this->additionalConfigs > '' && is_array($this->additionalConfigs)) {
            foreach ($this->additionalConfigs as $fname) {
                if (file_exists($this->PATH_APP . 'config' . DIRECTORY_SEPARATOR . $fname)) {
                    $this->configs = $this->configs + include $this->PATH_APP . 'config' . DIRECTORY_SEPARATOR . $fname;
                     //= array_merge_recursive($this->configs, $arTmp);
                }
            }
        }
    }

    /**
     * функция подключает внешний модуль, если он не был подключен до этого
     * @return boolean возвращает true если модуль подключен и false в противном случае
     */
    public function externalModule($module, $path)
    {
        if (! empty($this->modules[$module])) {
            return true;
        }

        if (file_exists($this->PATH_APP . 'externals' . DIRECTORY_SEPARATOR . $path)) {
            $this->modules[$module] = $path;
            include_once $this->PATH_APP . 'externals' . DIRECTORY_SEPARATOR . $path;
            return true;
        }

        return false;
    } //end function

    /**
     * Метод записывает сообщения в лог
     * @param string $message - сообщение для записи (логирования)
     */
    public function log($message, $params = [], $file = 'log')
    {
        $logname = '';
        if (isset($this->web['shortcode'])) {
            $file = $this->web['shortcode'] . '-' . $file;
        }
        if (! $this->logdir) {
            $this->logdir = 'logs';
        }

        if (! file_exists($this->PATH_APP . $this->logdir)) {
            mkdir($this->PATH_APP . $this->logdir, 0777, true);
        }

        $logname = realpath($this->PATH_APP . $this->logdir) . DIRECTORY_SEPARATOR . $file;

        $f = fopen($logname . '.txt', 'a');
        if ($f) {
            $ip = '-';
            if (! empty($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            fwrite($f, '[' . date('Y-m-d H:i:s.u') . '], ' . $ip . ', ' . $message . (empty($params) ? '' : "\nPARAMS: " . json_encode($params)) . "\n");
            fclose($f);
        } else {
            throw new \Exception('Cannot open log file');
        }
    }

    public function getUserLang()
    {
        if ($this->userLang == '') {
            $ru = ['ru', 'be', 'uk', 'ky', 'ab', 'mo', 'et', 'lv'];

            $this->userLang = "EN";
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $htal = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
                if (($list = strtolower($htal))) {
                    if (preg_match_all('/([a-z]{1,8}(?:-[a-z]{1,8})?)(?:;q=([0-9.]+))?/', $list, $list)) {
                        $language = array_combine($list[1], $list[2]);
                        foreach ($language as $n => $v) {
                            $language[$n] = $v ? $v : 1;
                        }
                        arsort($language, SORT_NUMERIC);
                    } else {
                        $language = array();
                    }
                } else {
                    $language = array();
                }

                foreach ($language as $l => $v) {
                    unset($language[$l]);
                    $s = strtok($l, '-');
                    $language[$s] = $v;
                }

                foreach ($language as $l => $v) {
                    if (in_array($l, $ru)) {
                        $this->userLang = "RU";
                    }
                }
            }
        }

        return $this->userLang;
    }

    // проверяем запуск скрипта на разовый запуск (используется в кронах,
    // где важно чтобы один и тот же скрипт работал только в одном экземпляре)
    public function checkOneRun($script = '')
    {
        $sSystemName    = php_uname();
        if (!empty($sSystemName) && !preg_match("/Windows/iu", $sSystemName)) {
            if (empty($script)) {
                $script = $_SERVER['SCRIPT_NAME'];
            }
            $command = "ps aux | grep -v -E 'grep|bash|\/sh|ps aux' | grep " . $script;
            $output = '';
            if (exec($command, $output)) {
                if (is_array($output) && sizeof($output) > 1) {
                    die("Cannot run " . $script . ", because proccess already RUN\n");
                }
            }
        }
    }

    /**
     * Запускает обработку текущего запроса, беря за основу путь из $_SERVER['REQUEST_URI']
     * @return mixed
     */
    public function run()
    {
        if ($this->_mode == 'cli') {
            $origPageName = $_SERVER['argv'][0];
            if (substr($origPageName, -4) == '.php') {
                $origPageName = substr($origPageName, 0, -4);
            }
            $pageName = explode('/', $origPageName);
            $pageName = array_pop($pageName);
            $pageName = preg_replace('/[^a-z\/\-]/', '', $pageName);
            $pageName = preg_replace_callback(
                '|([\-\\\])([a-z])|iu',
                function ($matches) {
                    return ($matches[1] == '\\' ? $matches[1] : '') . mb_strtoupper($matches[2], 'UTF-8');
                },
                $pageName,
                -1
            );

            $pageName = mb_ucfirst($pageName, 'UTF-8') ;
            if ($this->nameSpace > '') {
                $actionClass = $this->nameSpace . '\\CliActions\\' . $pageName . 'Action';
            } else {
                $actionClass = 'CliActions\\' . $pageName . 'Action';
            }

            if (! class_exists($actionClass)) {
                static::log('action not found', ['action' => $actionClass, 'script' => $origPageName], 'error');
                throw new \Exception('Script not found: ' . $origPageName);
            }
        } else {
            $pageName = explode('?', $_SERVER['REQUEST_URI']);

            $pageName = explode('#', $pageName[0]);
            $pageName = preg_replace('/[^a-z\/\-.]/iu', '', $pageName[0]);
            if (substr($pageName[0], 0, 1) == '.') {
                Response::one()->set('File not found [' . $pageName . ']', 404)->sendAndExit();
            }

            $this->curPage = $origPageName = $pageName = str_replace('/../', '', $pageName);
            if (file_exists($this->PATH_PUBLIC . $pageName) && is_file($this->PATH_PUBLIC . $pageName)) {
                Response::one()->setFile($this->PATH_PUBLIC . $pageName)->sendFileAndExit();
            }
            //$pageName = ($pageName, 'UTF-8');

            if (substr($pageName, -1) == '/') {
                $pageName = substr($pageName, 1, -1);
            } else {
                $pageName = substr($pageName, 1);
            }

            if (substr($pageName, -4) == '.php') {
                $pageName = substr($pageName, 0, -4);
            }

            if ($pageName == 'index' || $pageName == '/' || $pageName == '') {
                $pageName = 'default';
            }
            $pageName = preg_replace('/[^a-z\/\-]/iu', '', $pageName);
            $pageName = preg_replace('/\//', '\\', $pageName);
            $pageName = preg_replace_callback(
                '|([\-\\\])([a-z])|iu',
                function ($matches) {
                    return ($matches[1] == '\\' ? $matches[1] : '') . mb_strtoupper($matches[2], 'UTF-8');
                },
                $pageName,
                -1
            );

            $pageName = mb_ucfirst($pageName, 'UTF-8') ;
            if ($this->nameSpace > '') {
                $actionClass = $this->nameSpace . '\\WebActions\\' . $pageName . 'Action';
            } else {
                $actionClass = 'WebActions\\' . $pageName . 'Action';
            }

            if (! class_exists($actionClass)) {
                $actionClass2 = 'WebActions\\' . $pageName . '\\DefaultAction';

                if (! class_exists($actionClass2)) {
                    static::log('action not found', ['action' => $actionClass, 'page' => $origPageName], 'error');
                    throw new \Exception('Page not found: ' . $origPageName);
                } else {
                    $actionClass = $actionClass2;
                }
            }
        }

        $action = new $actionClass();

        if (method_exists($action, '_beforeRun')) {
            $action->_beforeRun();
        }

        $response = $action->run();

        if (method_exists($action, '_afterRun')) {
            $action->_afterRun($response);
        }

        return $response;
    }
}
