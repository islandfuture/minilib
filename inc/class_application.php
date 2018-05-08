<?php
require_once __DIR__.DIRECTORY_SEPARATOR.'class_only.php';
/**
 * Класс "Приложение", отвечающий за инициализацию, определения страницы отображения,
 * первичной обработки входных данных, контролем текущих процессов и отображением выходных данных
 *
 * @link    https://github.com/islandfuture/minilib
 * @author  Michael Akimov <michael@island-future.ru>
 * @version GIT: $Id$
 *
 * @example Application::one()->init(); // считываем данные из конфига и подготавливаем все для работы
 */
class Application extends Only
{
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
     * Возвращает данные, сохраненные в Application::$arConfig в индексе $name
     * @param string $name имя переменных
     *
     * @example
     *  $superData = Application::one()->superData;
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (empty($this->arConfig[$name])) {
            return null;
        }

        return $this->arConfig[$name];
    }

    /**
     * Сохраняем данные, в Application::$arConfig в индексе $name
     * @param string $name имя индекса в arConfig, где сохраняются данные $val
     * @param mixed $val
     *
     * @example
     *  Application::one()->superData = array('example' => true);
     *
     * @return mixed
     */
    public function __set($name, $val)
    {
        $this->arConfig[$name] = $val;
        return $this;
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

        /* DOCUMENT_ROOT must set to public directory */
        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
        } else {
            $_SERVER['DOCUMENT_ROOT'] = realpath($_SERVER['DOCUMENT_ROOT']);
        }

        $sPublicPath = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR;
        $sAppPath = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR;
        $sVendorPath = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR;
        $sConfig = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR.'config.php';

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

        // регистрируем автозагрузчик классов
        spl_autoload_register(array($this, 'appAutoload' ), false);

        $this->startScript = date('Y-m-d H:i:s');

        $this->arConfig['PATH_ROOT'] = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR;
        $this->arConfig['PATH_APP'] = $sAppPath;
        $this->arConfig['PATH_PAGES'] = $sPublicPath.'pages'.DIRECTORY_SEPARATOR;
        $this->arConfig['PATH_PUBLIC'] = $sPublicPath;
        $this->arConfig['PATH_VENDOR'] = $sVendorPath;

        /* глобальные переменные для хранения последних ошибок или сообщений */
        $this->arProperties['lasterror'] = '';
        $this->arProperties['lastmessage'] = '';
        $this->arProperties['js_bottom'] = array();

        error_reporting(E_ALL);
        if ($this->debug == 'Y') {
            ini_set('display_errors', 1);
        }

        /* регистрируем обработчики исключений и ошибок */
        set_error_handler(array($this,'appError'), E_ALL | E_STRICT | E_USER_ERROR);
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
        } elseif($this->session == 'auto') {
            session_start();
        }

        return $this;
    }

    /**
     * функция для автозагрузки классов
     * @param sting $sClassName - название класса, который нужно загрузить
     **/
    public function appAutoload($sClassName)
    {
        if (! isset( $this->arConfig['include'])) {
            $this->arConfig['include'] = array();
        }
        /* Проверяем, есть ли класс в массиве для автозагрузки классов */
        if( file_exists( $this->PATH_APP.'class_'.strtolower($sClassName).'.php') ) {
            include_once $this->PATH_APP.'class_'.strtolower($sClassName).'.php';
            return true;
        } elseif (array_key_exists($sClassName, $this->arConfig['include'])) {
            if (substr($this->arConfig['include'][ $sClassName ], 0, 1) == '/') {
                $sPath = $this->arConfig['include'][ $sClassName ];
            } else {
                $sPath = $this->PATH_APP.$this->arConfig['include'][ $sClassName ];
            }

            if (file_exists($sPath)) {
                include_once $sPath;
                return true;
            } else {
                return;
                // throw new \Exception('Class ['.$sClassName.'] not found in path ['.$sPath.']');
            }
        } elseif (strpos($sClassName,'\\') !== false && file_exists($this->PATH_APP . str_replace('\\', '/', $sClassName).'.php')) {
            include_once $this->PATH_APP . str_replace('\\', '/', $sClassName) . '.php';
        } elseif (strpos($sClassName,'/') !== false && file_exists($this->PATH_APP.$sClassName.'.php')) {
            include_once $this->PATH_APP.$sClassName.'.php';
        } else {
            return ;
            //throw new \Exception('Class ['.$sClassName.'] not exists in include settings');
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
        throw new ErrorException($errstr, $errno, 1, $errfile, $errline);
    }

    /**
     * Функция вешается на исключения
     */
    public function appException($e)
    {
        $err  = $e->getMessage() . " = " . $e->getFile(). " = " . $e->getLine() . "\r\n" . $e->getTraceAsString() . "\r\n";
        if (!empty(Application::one()->buglowers['to'])) {
            $headers = 'From: no-reply';
            @mail(Application::one()->buglowers['to'], 'Error Handler', $err, $headers);
        }

        if ($e instanceof Http404) {
            header('HTTP/1.0 404 Not found');
            if( Application::one()->output == 'json') {
                die(json_encode(array('error' => 'Document not found')));
                return;
            } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/404.php')) {
                include $_SERVER['DOCUMENT_ROOT'] . '/404.php';
                return;
            }
        }

        if ($e instanceof Http403) {
            header('HTTP/1.0 403 Forbidden');
            if( Application::one()->output == 'json') {
                die(json_encode(array('error' => 'Access denied')));
                return;
            } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/403.php')) {
                include $_SERVER['DOCUMENT_ROOT'] . '/403.php';
                //Application::one()->showPage('login', 'main');
                return;
            } else {
                Application::one()->showPage('login', 'main');
            }
        }

        if (Application::one()->debug == 'Y') {
            if( Application::one()->output == 'json') {
                die(json_encode(array('error' => "Error code " . $e->getCode() . ": ".$e->getMessage().' in line ['.$e->getLine().'] in file ['.$e->getFile().']'."\n", 'errortrace' => $e->getTraceAsString())));
                return;
            }
            echo "<pre>-----------------------------\n";
            echo "Error code " . $e->getCode() . ": ".$e->getMessage().' in line ['.$e->getLine().'] in file ['.$e->getFile().']'."\n";
            echo $e->getTraceAsString();
            echo "\n-----------------------------\n";
        }

        if( Application::one()->output == 'json') {
            header('HTTP/1.0 500 Internal server error');
            die(json_encode(array('error' => 'Unknown error')));
            return;
        }

        die('<html><head><title>Error in '.Application::one()->web['name'].'</title><meta charset="utf-8" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head><body><table style="width:100%; height:100%;"><tr><td style="vertical-align: middle; text-align: center;"><h1>Произошло что-то страшное.</h1><p>Разработчикам уже сообщили и они трудятся боясь получить по шее.</p></td></tr></table></body></html>');
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
        if (($error['type'] === E_ERROR) || ($error['type'] === E_USER_ERROR)|| ($error['type'] === E_USER_NOTICE)) {
            $errstr = "ERROR: " . $error['type']. " |Msg : ".$error['message']." |File : ".$error['file']. " |Line : " . $error['line'];

            if (Application::one()->debug == 'Y') {
                if( Application::one()->output == 'json') {
                    die(json_encode(array('error' => $errstr)));
                    return;
                }
                echo "<pre>-----------------------------\n";
                echo $errstr."\n";
                echo "\n-----------------------------\n";
            }

            if( Application::one()->output == 'json') {
                header('HTTP/1.0 500 Internal server error');
                die(json_encode(array('error' => 'Unknown error')));
                return;
            }

            die('<html><head><title>Unknown Error</title><meta charset="utf-8" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head><body><table style="width:100%; height:100%;"><tr><td style="vertical-align: middle; text-align: center;"><h1>Произошло что-то страшное.</h1><p>Разработчикам уже сообщили и они трудятся боясь получить по шее.</p></td></tr></table></body></html>');

        } else {
            //echo "no error where found " ;

        }
    }
    /**
     * метод нужен для избежания не правильной инициализции класса в качестве синглтона.
     */
    protected function afterConstruct()
    {
        if ($this->additionalConfigs > '' && is_array($this->additionalConfigs)) {
            foreach ($this->additionalConfigs as $fname) {
                if (file_exists($this->PATH_ROOT.'configs'.DIRECTORY_SEPARATOR.$fname)) {
                    $this->arConfig = $this->arConfig + include $this->PATH_ROOT.'configs'.DIRECTORY_SEPARATOR.$fname;
                     //= array_merge_recursive($this->arConfig, $arTmp);
                }
            }
        }
    }

    /**
     * Превращает текущий веб-путь в дисковый путь
     *
     * @return string
     */
    public function getCurDir()
    {
        $str = str_replace('/', DIRECTORY_SEPARATOR, $this->sCurPage);
        return realpath(dirname($this->PATH_PUBLIC.$str)).DIRECTORY_SEPARATOR;
    }

    /* функция отправляет заголовок для перенаправления на другую страницу */
    public function redirect($sLocalUrl='')
    {
        if ($sLocalUrl > '') {
            $this->sCurPage = $sLocalUrl;
            return header('Location: '.$this->sCurPage);
        }

        if (! empty($_REQUEST['page'])) {
            $this->sCurPage = $_REQUEST['page'];
            if (substr($this->sCurPage, 0, 1) == '/') {
                $this->sCurPage = substr($this->sCurPage, 1);
            }

            if (substr($this->sCurPage, -1, 1) == '/') {
                $this->sCurPage = substr($this->sCurPage, 0, -1);
            }

        }//end if

        return header('Location: /'.$this->sCurPage.'/');
    }

    /**
     * Метод ведет учет клиентских JS скриптов, для дальнейшей вставки в конец документа
     *
     * @param string $sName уникальное название скрипта
     * @param string $sScript путь до JS скрипта или Javascript код
     * @param string $sPosition (top|bottom) - где должен выводится скрипт, вверху или внизу страницы
     */
    public function addClientJs($sName, $sScript, $sPosition='top')
    {
        if (empty($this->arProperties['js'])) {
            $this->arProperties['js'] = array();
        }
        if (empty($this->arProperties['js'][$sPosition]) || ! is_array($this->arProperties['js'][$sPosition])) {
            $this->arProperties['js'][$sPosition] = array();
        }

        $this->arProperties['js'][$sPosition][$sName] = $sScript;
    }

    /**
     * Возвращает JS-код для вставки в html-страницу
     */
    public function getClientJs($sPosition='')
    {
        if ($sPosition == '') {
            $arTmp = array();
            foreach ($this->arProperties['js'] as $arScript) {
                $arTmp += $arScript;
            }
        } elseif (isset($this->arProperties['js'][$sPosition])) {
            $arTmp = $this->arProperties['js'][$sPosition];
        } else {
            $arTmp = array();
        }

        $sHtml = '';
        foreach ($arTmp as $sScript) {
            if(substr($sScript, 0, 4) == 'http' || substr($sScript, 0, 1) == '/') {
                $sHtml .= '<script type="text/javascript" src="'.$sScript.'"></script>'."\n";
            } else {
                $sHtml .= '<script type="text/javascript">'."\n".$sScript."\n".'</script>'."\n";
            }
        }

        return "\n<!-- BEGIN: all external script $sPosition -->\n".$sHtml."\n<!-- END: all external script $sPosition -->\n";
    }

    /**
     * Функция ведет учет клиентских CSS скриптов, для дальнейшей вставки в конец документа
     *
     * @param string $sName уникальное название скрипта
     * @param string $sScript путь до CSS скрипта или CSS-код
     * @param string $sPosition (top|bottom) - где должен выводится скрипт, вверху или внизу страницы
     */
    public function addClientCss($sName, $sScript, $sPosition='top')
    {
        if (empty($this->arProperties['css'])) {
            $this->arProperties['css'] = array();
        }
        if (empty($this->arProperties['css'][$sPosition]) || ! is_array($this->arProperties['css'][$sPosition])) {
            $this->arProperties['css'][$sPosition] = array();
        }

        $this->arProperties['css'][$sPosition][$sName] = $sScript;
    }

    /**
     * Возвращает CSS-код для вставки в html-страницу
     */
    public function getClientCss($sPosition = '')
    {
        if ($sPosition == '') {
            $arTmp = array();
            foreach ($this->arProperties['css'] as $arScript) {
                $arTmp += $arScript;
            }
        }
        elseif (isset($this->arProperties['css'][$sPosition])) {
            $arTmp = $this->arProperties['css'][$sPosition];
        }
        else {
            $arTmp = array();
        }

        $sHtml = '';
        foreach ($arTmp as $sScript) {
            if(substr($sScript, 0, 4) == 'http' || substr($sScript, 0, 1) == '/') {
                $sHtml .= '<link rel="stylesheet" href="'.$sScript.'" />' . "\n";
            } else {
                $sHtml .= '<style type="text/css">' . "\n" . $sScript . "\n" . '</style>' . "\n";
            }
        }

        return "\n<!-- BEGIN: all external css $sPosition -->\n" . $sHtml . "\n<!-- END: all external css $sPosition -->\n";
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setH1($title)
    {
        $this->h1 = $title;
    }

    public function getTitle()
    {
        if($this->title > '') {
            return $this->title;
        } elseif ($this->h1 > '') {
            return $this->h1;
        } else {
            return 'Страница: ' . $this->sCurPage;
        }
    }//end function

    public function getH1()
    {
        if($this->h1 > '') {
            return $this->h1;
        } elseif ($this->title > '') {
            return $this->title;
        } else {
            return '';
        }
    }//end function

    public function setProperty($name, $value)
    {
        $this->arProperties[$name] = $value;
    }//end function

    public function getProperty($name)
    {
        if (isset($this->arProperties[$name])) {
            return $this->arProperties[$name];
        } else {
            return '';
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
        $f = fopen($this->PATH_PUBLIC . 'uploads' . DIRECTORY_SEPARATOR . $file.'.txt', 'a');
        if ($f) {
            $user = '';
            //if (! $this->session || $this->sesion != 'none') {
            //    $user = 'userid=' . ActiveUser::one()->id . ', ';
            //}
            $ip = '-';
            if (! empty($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            fwrite($f, '[' . $this->startScript . '], '.$ip.', ' . $user . $message . "\n");
            fclose($f);
        } else {
            throw new Exception('Cannot open log file');
        }
    }

    /**
     * Метод соединяет шапку, футер и страницу и показывает это.
     *
     * @param string $page страница
     * @param string $template название папки, где лежат шапка и футер
     * @param array $arVars массив с переменными, которые можно использовать на странице
     */
    public function showPage($page, $template, $arVars=array())
    {
        $sHtml = '';
        if (file_exists($this->PATH_APP.'tpl'.DIRECTORY_SEPARATOR.$page.'.tpl.php')) {
            extract($arVars);
            ob_start();
            include $this->PATH_APP.'tpl'.DIRECTORY_SEPARATOR.$page.'.tpl.php';
            $sHtml = ob_get_contents();
            ob_end_clean();
        } else {
            throw new Exception('Страница: '.$page.' не найдена');
        }

        include $this->PATH_APP.'layout'.DIRECTORY_SEPARATOR.$template.DIRECTORY_SEPARATOR.'header.php';
        echo $sHtml;
        include $this->PATH_APP.'layout'.DIRECTORY_SEPARATOR.$template.DIRECTORY_SEPARATOR.'footer.php';
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
        $string = preg_replace('/[^a-zA-Z0-9а-яА-ЯёЁ+-_]/ui','', $string);
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
