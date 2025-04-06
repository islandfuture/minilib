<?php
namespace IFMiniLib;

require_once __DIR__.DIRECTORY_SEPARATOR.'Only.php';
/**
 * Класс "Приложение", отвечающий за инициализацию, определения страницы отображения,
 * первичной обработки входных данных, контролем текущих процессов и отображением выходных данных
 *
 * @link    https://github.com/islandfuture/minilib
 * @author  Michael Akimov <michael@island-future.ru>
 * @version GIT: $Id$
 *
 * @example App::I()->init(); // считываем данные из конфига и подготавливаем все для работы
 */


class Core extends Only
{
    // объект приложения
    public static $app = null;

    //@var Array массив параметров конфигурации
    private $arConfig=array();

    //@var string название текущей страницы (точнее запрашиваемой)
    public $sCurPage = 'portal/index';

    //@var string name of templates directory
    public $sLayout = 'main';

    //@var string основное содержание страницы
    public $sPageContent = '';

    //@var array массив для обмена переменными между блоками
    public $arProperties = array();

    public $sUserLang = '';

    protected $modules = array();

    /**
     * Возвращает данные, сохраненные в App::$arConfig в индексе $name
     * @param string $name имя переменных
     *
     * @example
     *  $superData = App::I()->superData;
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (! isset($this->arConfig[$name])) {
            return null;
        }

        return $this->arConfig[$name];
    }

    /**
     * Сохраняем данные, в App::$arConfig в индексе $name
     * @param string $name имя индекса в arConfig, где сохраняются данные $val
     * @param mixed $val
     *
     * @example
     *  App::I()->superData = array('example' => true);
     *
     * @return mixed
     */
    public function __set($name, $val)
    {
        $this->arConfig[$name] = $val;
        return $this;
    }

    public function __isset($name)
    {
        return isset($this->arConfig[$name]);
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

        $sAppCorePath = __DIR__.DIRECTORY_SEPARATOR;

        /* DOCUMENT_ROOT must set to public directory */
        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $reflection = new \ReflectionClass(get_class($this));
            $sAppPath = dirname($reflection->getFileName());
            $sRootPath = dirname($sAppPath);
            $sPublicPath = $_SERVER['DOCUMENT_ROOT'] = $sRootPath . DIRECTORY_SEPARATOR . 'public' ;
        } else {
            $sPublicPath = $_SERVER['DOCUMENT_ROOT'] = realpath($_SERVER['DOCUMENT_ROOT']);
            $sRootPath = dirname($sPublicPath);
            $sAppPath  = $sRootPath . 'app';
        }

        $sConfig = $sRootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

        /* если нет конфига, то баста - ругаемся */
        if (! file_exists($sConfig)) {
            throw new \Exception('Cannot load config file ['.$sConfig.']');
        }

        /**
         * Включаем буферизацию и подключаем файл с конфигом и возможно файл прав доступа.
         * Если в конфиге есть какие-то ошибки или что-то выводится на экран, то буферизация этого не позволит.
         * Также все данные из конфига помещается на $this->arConfig
         **/
        ob_start();
        $this->arConfig = (include_once $sConfig);
        ob_end_clean();

        if (empty($this->arConfig['debug']) || $this->arConfig['debug'] != 'Y') {
            $this->arConfig['debug'] = 'N';
        }

        $this->arConfig['PATH_ROOT'] = $sRootPath;
        $this->arConfig['PATH_CORE'] = $sAppCorePath;
        $this->arConfig['PATH_PUBLIC'] = $sPublicPath;

        $sVendorPath = $sRootPath.'vendor'.DIRECTORY_SEPARATOR;
        if (empty($this->arConfig['PATH_VENDOR'])) {
            $this->arConfig['PATH_VENDOR'] = $sVendorPath;
        }

        if (empty($this->arConfig['PATH_APP'])) {
            $this->arConfig['PATH_APP'] = $sAppPath;
        }

        $this->arConfig['PATH_TEMPLATE'] = $this->PATH_ROOT.'tpl'.DIRECTORY_SEPARATOR;
        $this->arConfig['PATH_LAYOUR'] = $this->PATH_ROOT.'layout'.DIRECTORY_SEPARATOR;

        // регистрируем автозагрузчик классов
        spl_autoload_register(array($this, 'appAutoload' ));

        $this->startScript = date('Y-m-d H:i:s');


        /* глобальные переменные для хранения последних ошибок или сообщений */
        $this->arProperties['lasterror'] = '';
        $this->arProperties['lastmessage'] = '';
        $this->arProperties['js_bottom'] = array();

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
            $session = ActiveUser::I();
            if ($session->hasError()) {
                throw new \Exception('Cannot start user session');
            }
        } elseif($this->session == 'auto') {
            ActiveUser::$sUserClassName = 'none';
            session_start();
        }

        return $this;
    }

    /**
     * функция для автозагрузки классов
     * @param sting $className - название класса, который нужно загрузить
     **/
    public function appAutoload($className)
    {
        if (! isset( $this->arConfig['include'])) {
            $this->arConfig['include'] = array();
        }

        /* Проверяем, есть ли класс в массиве для автозагрузки классов */
        if (file_exists( $this->PATH_APP.$className.'.php') ) {
            require_once $this->PATH_APP.$className.'.php';
            return true;
        } elseif (file_exists( $this->PATH_CORE.$className.'.php') ) {
            require_once $this->PATH_CORE.$className.'.php';
            return true;
        } elseif (strpos($className,'\\') !== false && file_exists($this->PATH_APP . str_replace('\\', '/', $className).'.php')) {
            require_once $this->PATH_APP . str_replace('\\', '/', $className) . '.php';
            return true;
        } elseif (strpos($className,'/') !== false && file_exists($this->PATH_APP.$className.'.php')) {
            require_once $this->PATH_APP.$className.'.php';
            return true;
        } elseif (array_key_exists($className, $this->arConfig['include'])) {
            if (substr($this->arConfig['include'][ $className ], 0, 1) == '/') {
                $sPath = $this->arConfig['include'][ $className ];
            } else {
                $sPath = $this->PATH_VENDOR.$this->arConfig['include'][ $className ];
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
        $pos2 = strpos($debugstr, ')', $pos1+5);
        if ($pos1 > 0 && $pos2 > 0) {
            $debugstr = str_replace(substr($debugstr,$pos1+17,$pos2-$pos1-17),'***', $debugstr);
        }
        $err  = $e->getMessage() . " = " . $e->getFile(). " = " . $e->getLine() . "\r\n" . $debugstr . "\r\n";
        $this->log($err, 'error');
        if (!empty(static::I()->buglowers['to'])) {
            $headers = 'From: no-reply';
            @mail(static::I()->buglowers['to'], 'Error Handler', $err, $headers);
        }

        if ($e instanceof Http404) {
            header('HTTP/1.0 404 Not found');
            if( static::I()->output == 'json') {
                die(json_encode(array('error' => 'Document not found')));
            } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/404.php')) {
                include $_SERVER['DOCUMENT_ROOT'] . '/404.php';
                exit;
            } else {
                echo "Not found.";
                exit;
            }
        }

        if ($e instanceof Http403) {
            header('HTTP/1.0 403 Forbidden');
            if( static::I()->output == 'json') {
                die(json_encode(array('error' => 'Access denied')));
                return;
            } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/403.php')) {
                include $_SERVER['DOCUMENT_ROOT'] . '/403.php';
                //static::I()->showPage('login', 'main');
                return;
            } else {
                static::I()->showPage('login', 'main');
            }
        }

        if (static::I()->debug == 'Y') {
            try {
                ob_end_clean();
            } catch(\Exception $ee){
            }

            if( static::I()->output == 'json') {
                die(json_encode(array('error' => "Error code " . $e->getCode() . ": ".$e->getMessage().' in line ['.$e->getLine().'] in file ['.$e->getFile().']'."\n", 'errortrace' => $debugstr)));
                return;
            }
            echo "<pre>-----------------------------\n";
            echo "Error code " . $e->getCode() . ": ".$e->getMessage().' in line ['.$e->getLine().'] in file ['.$e->getFile().']'."\n";

            echo $debugstr;
            echo "\n-----------------------------\n";
            die();
        }

        if( static::I()->output == 'json') {
            header('HTTP/1.0 500 Internal server error', true);
            die(json_encode(array('error' => 'Unknown error')));
            return;
        }

        if (static::I()->errorpage5xx > '' && file_exists(static::I()->PATH_PUBLIC.static::I()->errorpage5xx)) {
            header('HTTP/1.0 500 Internal server error', true);
            echo file_get_contents(static::I()->PATH_PUBLIC.static::I()->errorpage5xx);
            exit;
        }
        die('<html><head><title>Error in '.static::I()->web['name'].'</title><meta charset="utf-8" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head><body><table style="width:100%; height:100%;"><tr><td style="vertical-align: middle; text-align: center;"><h1>Temporary error</h1><p>Please reload page later.</p></td></tr></table></body></html>');
    }

    public static function appShutdown()
    {
        $error = error_get_last();
        /*
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_PARSE:
        */
        // Checking if last error is a fatal error
        if (! $error) {
            return ;
        }

        if (
            ($error['type'] === E_ERROR) 
            || ($error['type'] === E_USER_ERROR)
            || ($error['type'] === E_USER_NOTICE)
        ) {
            $errstr = "ERROR: " . $error['type']. " |Msg : ".$error['message']." |File : ".$error['file']. " |Line : " . $error['line'];
            static::I()->log($errstr, 'error');

            if( static::I()->output == 'json') {
                header('HTTP/1.0 500 Internal server error');
                if (static::I()->debug == 'Y') {
                    die(json_encode(array('error' => $errstr)));
                } else {
                    die(json_encode(array('error' => 'Unknown error')));
                }
                return;
            }

            if (static::I()->debug == 'Y') {
                echo "<pre>-----------------------------\n";
                echo $errstr."\n";
                echo "\n-----------------------------\n";
            }

            

            die('<html><head><title>Unknown error in '.static::I()->web['name'].'</title><meta charset="utf-8" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head><body><table style="width:100%; height:100%;"><tr><td style="vertical-align: middle; text-align: center;"><h1>Temporary error</h1><p>Please reload page later.</p></td></tr></table></body></html>');


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
                if (file_exists($this->PATH_APP.'configs'.DIRECTORY_SEPARATOR.$fname)) {
                    $this->arConfig = $this->arConfig + include $this->PATH_APP.'configs'.DIRECTORY_SEPARATOR.$fname;
                     //= array_merge_recursive($this->arConfig, $arTmp);
                }
            }
        }
    }


    /**
     * функция подключает внешний модуль, если он не был подключен до этого
     * @return boolean возвращает true если модуль подключен и false в противном случае
     */
    public function externalModule($module,$path)
    {
        if (! empty($this->modules[$module])) {
            return true;
        }

        if (file_exists($this->PATH_APP.'externals'.DIRECTORY_SEPARATOR.$path)) {
            $this->modules[$module] = $path;
            include_once $this->PATH_APP.'externals'.DIRECTORY_SEPARATOR.$path;
            return true;
        }

        return false;
    }//end function

    /**
     * Метод записывает сообщения в лог
     * @param string $message - сообщение для записи (логирования)
     */
    public function log($message, $file='log')
    {
        $logname = '';
        if (isset($this->web['shortcode'])) {
            $file = $this->web['shortcode'].'-'.$file;
        }
        if ($this->logdir > '' && file_exists($this->PATH_APP.$this->logdir)) {
            $logname = realpath($this->PATH_APP.$this->logdir) . DIRECTORY_SEPARATOR . $file;
        } else {
            if (! file_exists($this->PATH_PUBLIC . 'uploads' . DIRECTORY_SEPARATOR)) {
                mkdir($this->PATH_PUBLIC . 'uploads' . DIRECTORY_SEPARATOR);
            }
            $logname = $this->PATH_PUBLIC . 'uploads' . DIRECTORY_SEPARATOR . $file;
        }
        $f = fopen($logname . '.txt', 'a');
        if ($f) {
            $user = '';
            //if (! $this->session || $this->sesion != 'none') {
            //    $user = 'userid=' . ActiveUser::I()->id . ', ';
            //}
            $ip = '-';
            if (! empty($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            fwrite($f, '[' . $this->startScript . '], '.$ip.', ' . $user . $message . "\n");
            fclose($f);
        } else {
            throw new \Exception('Cannot open log file');
        }
    }


    public function xorCrypt($String, $Password)
    {
        $Salt='jkdhf483ythk2b';
        $StrLen = strlen($String);
        $Seq = $Password;
        $Gamma = '';
        while (strlen($Gamma)<$StrLen)
        {
            $Seq = sha1($Gamma.$Seq.$Salt, true);
            $Gamma.=substr($Seq,0,8);
        }
       /*
        if (mb_strlen($Gamma, 'utf8')>$StrLen) {
            $Gamma=mb_substr($Gamma,0,$StrLen,'utf8');
        }*/
        return $String^$Gamma;
    }

    public function cleanString($string)
    {
        $string = strip_tags($string);
        $string = preg_replace('/[^a-zA-Z0-9а-яА-ЯёЁ+\-_]/ui','', $string);
        return $string;
    }

    public function getUserLang() {
        if ($this->sUserLang == '') {
            /* определяем язык, чтобы знать на каком сообщить об успешной отправки */
            $ru=array('ru','be','uk','ky','ab','mo','et','lv');

            $this->sUserLang="EN";
            if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
                $htal=$_SERVER['HTTP_ACCEPT_LANGUAGE'];
                if (($list = strtolower($htal))) {
                    if (preg_match_all('/([a-z]{1,8}(?:-[a-z]{1,8})?)(?:;q=([0-9.]+))?/', $list, $list)) {
                        $language = array_combine($list[1], $list[2]);
                        foreach ($language as $n => $v)
                            $language[$n] = $v ? $v : 1;
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
                    $language[$s]=$v;
                }

                foreach ($language as $l => $v) {
                    if (in_array($l, $ru)) {
                        $this->sUserLang="RU";
                    }
                }

            }
        }

        return $this->sUserLang;
    }

    // проверяем запуск скрипта на разовый запуск (используется в кронах,
    // где важно чтобы один и тот же скрипт работал только в одном экземпляре)
    public function checkOneRun($script='')
    {
        $sSystemName    = php_uname();
        if ( !empty($sSystemName) && !preg_match("/Windows/iu", $sSystemName)) {
            if (empty($script)) {
                $script = $_SERVER['SCRIPT_NAME'];
            }
            $command = "ps aux | grep -v -E 'grep|bash|\/sh|ps aux' | grep ".$script;
            $output = '';
            if (exec($command, $output)) {
                if (is_array($output) && sizeof($output) > 1) {
                    die("Cannot run ".$script.", because proccess already RUN\n");
                }
            }
        }

    }

}
