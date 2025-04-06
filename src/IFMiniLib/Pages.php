<?php
namespace IFMiniLib;

class Pages
{
    // @var Core
    public $app;

    public $wepPath = '';

    public $properties = [];

    /**
     * Превращает текущий веб-путь в дисковый путь
     *
     * @return string
     */
    public function getCurDir()
    {
        $str = str_replace('/', DIRECTORY_SEPARATOR, $this->webPath);
        return realpath(dirname($this->app->PATH_PUBLIC.$str)).DIRECTORY_SEPARATOR;
    }

    /* функция отправляет заголовок для перенаправления на другую страницу */
    public function redirect($sLocalUrl='')
    {
        if ($sLocalUrl > '') {
            $this->webPath = $sLocalUrl;
            header('Location: '.$this->webPath);
            return;
        }

        if (! empty($_REQUEST['page'])) {
            $this->webPath = $_REQUEST['page'];
            if (substr($this->webPath, 0, 1) == '/') {
                $this->webPath = substr($this->webPath, 1);
            }

            if (substr($this->webPath, -1, 1) == '/') {
                $this->webPath = substr($this->webPath, 0, -1);
            }

        }//end if

        header('Location: /'.$this->webPath.'/');
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
        if (empty($this->properties['js'])) {
            $this->properties['js'] = array();
        }
        if (empty($this->properties['js'][$sPosition]) || ! is_array($this->properties['js'][$sPosition])) {
            $this->properties['js'][$sPosition] = array();
        }

        $this->properties['js'][$sPosition][$sName] = $sScript;
    }

    /**
     * Возвращает JS-код для вставки в html-страницу
     */
    public function getClientJs($sPosition='')
    {
        if ($sPosition == '') {
            $arTmp = array();
            foreach ($this->properties['js'] as $arScript) {
                $arTmp += $arScript;
            }
        } elseif (isset($this->properties['js'][$sPosition])) {
            $arTmp = $this->properties['js'][$sPosition];
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
        if (empty($this->properties['css'])) {
            $this->properties['css'] = array();
        }
        if (empty($this->properties['css'][$sPosition]) || ! is_array($this->properties['css'][$sPosition])) {
            $this->properties['css'][$sPosition] = array();
        }

        $this->properties['css'][$sPosition][$sName] = $sScript;
    }

    /**
     * Возвращает CSS-код для вставки в html-страницу
     */
    public function getClientCss($sPosition = '')
    {
        if ($sPosition == '') {
            $arTmp = array();
            foreach ($this->properties['css'] as $arScript) {
                $arTmp += $arScript;
            }
        }
        elseif (isset($this->properties['css'][$sPosition])) {
            $arTmp = $this->properties['css'][$sPosition];
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
            return 'Страница: ' . $this->webPath;
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
        $this->properties[$name] = $value;
    }//end function

    public function getProperty($name)
    {
        if (isset($this->properties[$name])) {
            return $this->properties[$name];
        } else {
            return '';
        }
    }

    /**
     * Метод соединяет шапку, футер и страницу и показывает это.
     *
     * @param string $page страница
     * @param string $template название папки, где лежат шапка и футер
     * @param array $arVars массив с переменными, которые можно использовать на странице
     */
    public function show($page, $template, $arVars=array())
    {
        $sHtml = '';
        if (file_exists($this->app->PATH_APP.'tpl'.DIRECTORY_SEPARATOR.$page.'.tpl.php')) {
            extract($arVars);
            ob_start();
            require $this->app->PATH_APP.'tpl'.DIRECTORY_SEPARATOR.$page.'.tpl.php';
            $sHtml = ob_get_contents();
            ob_end_clean();
        } else {
            throw new Exception('Страница: '.$page.' не найдена');
        }

        require $this->app->PATH_APP.'layout'.DIRECTORY_SEPARATOR.$template.DIRECTORY_SEPARATOR.'header.php';
        echo $sHtml;
        require $this->app->PATH_APP.'layout'.DIRECTORY_SEPARATOR.$template.DIRECTORY_SEPARATOR.'footer.php';
    }
}
