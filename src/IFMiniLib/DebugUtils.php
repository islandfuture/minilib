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
 * @example Application::one()->init(); // считываем данные из конфига и подготавливаем все для работы
 */
class DebugUtils extends Only
{
    public $arTimes = [];
    public $isActivate = false;

    public function setDebugMode($on)
    {
        if ($on) {
            $this->isActivate = true;
            $this->start("All proccess");
        } else {
            $this->isActivate = false;
        }
    }

    public function start($name)
    {
        if ($this->isActivate) {
            $this->arTimes[$name] = ['start' => microtime(true), 'end' => ''];
        }
        return $this;
    }

    public function end($name)
    {
        if ($this->isActivate) {
            if (! isset($this->arTimes[$name]['start'])) {
                $this->arTimes[$name] = ['start' => strtotime(Application::one()->startScript), 'end' => ''];
            }

            $this->arTimes[$name]['end'] = microtime(true);
        }
        return $this;
    }

    public function getTime($name)
    {
        if ($this->isActivate) {
            if (! isset($this->arTimes[$name])) {
                $this->arTimes[$name] = [];
            }

            if (! isset($this->arTimes[$name]['start'])) {
                $this->arTimes[$name]['start'] = strtotime(Application::one()->startScript);
            }

            if (! isset($this->arTimes[$name]['end'])) {
                $this->arTimes[$name]['end'] = microtime(true);
            }

            return number_format($this->arTimes[$name]['end'] - $this->arTimes[$name]['start'],5,',',' ');
        }

        return '';
    }

    public function saveTimers($file)
    {
        if ($this->isActivate) {
            $this->end("All proccess");

            $str = [];
            foreach ($this->arTimes as $name => $arTime) {
                $str[] = $name.': '.$this->getTime($name)." sec ";
            }

            Application::one()->log("-------".(empty($_SERVER['REQUEST_URI'])?'':$_SERVER['REQUEST_URI'])."\n".implode("\n", $str)."\n-------------", 'debug-'.$file);
        }
        return $this;
    }

}