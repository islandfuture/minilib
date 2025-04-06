<?php
namespace IFMiniLib;

/**
 * Класс отвечающий за ссесию текущего юзера и за связь с данными юзера в БД
 *
 * @link    https://github.com/islandfuture/minilib
 * @author  Michael Akimov <michael@island-future.ru>
 * @version GIT: $Id$
 *
 * @example в рамках выполнения скрипта сессия всегда одна.
 *      ActiveUser::one()->iRoleId = 1
 **/

class ActiveUser extends Only
{
    // @var string в свойстве хранится название класса, которое отвечает за хранение данных юзера в БД
    public static $sUserClassName = 'ModelUsers';

    // @var \Data\Model клас отвечающий за данные о пользователе
    protected $oCurrentUser = null;

    // @var boolean равен true, если загружали данные из БД в сессию
    protected $isSynchronized = false;

    protected $hasError = false;

    public function hasError()
    {
        return $this->hasError;
    }

    public function __get($sName)
    {
        if (empty($_SESSION['MINILIB_USER'][$sName])) {
            $_SESSION['MINILIB_USER'][$sName] = '';
        }

        return $_SESSION['MINILIB_USER'][$sName];
    }

    public function __set($sName, $sVal)
    {
        if (empty($_SESSION['MINILIB_USER'])) {
            $_SESSION['MINILIB_USER'] = array();
        }
        $_SESSION['MINILIB_USER'][$sName] = $sVal;
    }

    protected function afterConstruct($arParams)
    {
        if ($arParams && ! empty($arParams['sModel'])) {
            static::$sUserClassName = $arParams['sModel'];
        }

        if (session_status()!=PHP_SESSION_ACTIVE) {
            $this->hasError = (! @session_start());
            if ($this->hasError) {
                session_regenerate_id(true); // replace the Session ID
                $this->hasError = session_start();
            }
        }

        if (empty($_SESSION['MINILIB_USER'])) {
            $_SESSION['MINILIB_USER'] = array();
        }

        if (static::$sUserClassName != 'none') {
            $className = static::$sUserClassName;

            if (empty($_SESSION['MINILIB_USER']['id'])) {
                $this->oCurrentUser = $className::checkAuthCookie();
            }

            if (! $this->oCurrentUser) {
                $this->oCurrentUser = new static::$sUserClassName;
                $this->oCurrentUser->attributes($_SESSION['MINILIB_USER']);
            } else {
                $arFields = $this->oCurrentUser->__getFields();
                foreach ($arFields as $sKey => $sVal) {
                    $_SESSION['MINILIB_USER'][$sKey] = $sVal;
                }
                $this->isSynchronized = true;
            }

        } else {
            $this->oCurrentUser = $_SESSION['MINILIB_USER'];
        }
        return true;
    }

    /**
     * Функция возвращает объект с данными текущего юзера, при необходимости берет из БД
     */
    public function getModel($sKey = 'id', $isNeedSynchro = true)
    {
        if (! $this->isSynchronized && $isNeedSynchro && ($this->__get($sKey) > '')) {
            $this->oCurrentUser = DB::getOne(
                array(
                    'sModel' => static::$sUserClassName,
                    'arFilter' => array(
                        $sKey => array('=' => $this->__get($sKey) )
                    )
                )
            );

            if ($this->oCurrentUser) {
                $arFields = $this->oCurrentUser->__getFields();
                foreach ($arFields as $sKey => $sVal) {
                    $_SESSION['MINILIB_USER'][$sKey] = $sVal;
                }
                $this->isSynchronized = true;
            }
        }

        return $this->oCurrentUser;
    }

    public function getName()
    {
        if (static::$sUserClassName != 'none') {
            return $this->oCurrentUser->getName();
        } else {
            return 'none';
        }
    }

    public function login($arParams = array(), $rememberDay=0)
    {
        foreach ($arParams as $key => $val) {
            $this->__set($key, $val);
        }

        if (static::$sUserClassName != 'none') {
            $this->oCurrentUser->attributes($_SESSION['MINILIB_USER']);
            if ($rememberDay > 0 ) {
                $this->oCurrentUser->setAuthCookie($rememberDay);
            }
        } else {
            $this->oCurrentUser = $_SESSION['MINILIB_USER'];
        }
    }

    public function logout()
    {
        $this->id = null;
        if (static::$sUserClassName != 'none') {
            $className = static::$sUserClassName;
            $this->oCurrentUser = new $className;
            $className::clearAuthCookie();
        } else {
            $this->oCurrentUser = new \stdClass();
        }
        $_SESSION = array();

        //уничтожаем сессию
        setcookie(session_name(), session_id(), time()-60*60*24, "/", "", true, true);
        session_unset();
        session_destroy();
    }

    /**
     * Проверяем доступ на просмотр страницы $sPage
     * @return boolean true - если доступ есть
     * @throws \Exceptions\Http403
     */
    public function validateAccess($sPage, &$arAccess)
    {
        /* arAccess defined in \Application::init() method */
        if (empty($arAccess)) {
            return true;
        }
        if (substr($sPage,0,1) != '/') {
            $sPage = '/'.$sPage;
        }

        /* если в классе юзера есть класс проверки доступа, то передаем управление туда */
        if (static::$sUserClassName != 'none' && method_exists($this->oCurrentUser,'validateAccess')) {
            return $this->oCurrentUser->validateAccess($sPage, $arAccess);
        }

        $iRoleId = $this->iRoleId;
        $iStop = 30;
        $isResult = false;
        do {
            if (! empty($arAccess[$sPage]))
            {
                foreach ($arAccess[$sPage] as $iGroup => $sGrant) {
                    if ($iGroup == 0 && $sGrant == 'allow') {
                        $isResult = true;
                        break 2;
                    }

                    if (($iRoleId && $iGroup) == $iGroup) {
                        $isResult = ($sGrant == 'allow' ? true : false);
                        break 2;
                    }
                }
            }
            if( substr($sPage,-1) == '/') {
                $sPage = substr($sPage,0,-1);
            } else {
                $iPos = strrpos($sPage,'/');
                if ($iPos === false) {
                    $sPage = '';
                } else {
                    $sPage = substr($sPage,0,$iPos+1);
                }

            }
            $iStop--;
        } while($sPage > '' && $iStop > 0);

        if ($isResult === false) {
            throw new \Exceptions\Http403();
        }

        return $isResult;
    }

    public function hasMessage()
    {
        return ($this->globalMessage > '');
    }

    public function showMessage()
    {
        echo $this->globalMessage;
        $this->globalMessage = '';
    }

    /**
     * добавляем в сессию данные о событии которое нужно отправить в гугланалитик
     *
     */
    public function addUgaEvent($arParams)
    {
        $arUga = $this->uga;
        if (! $arUga || ! is_array($arUga)) {
            $arUga = array();
        }
        $arUga[] = $arParams;
        ActiveUser::one()->uga = $arUga;

    }
    public function getGA_Events()
    {
        $result = '';
        $arUga = $this->uga;
        if($arUga && is_array($arUga)) {
            foreach($arUga as $arEvent) {
                if ($arEvent['hitType'] == 'pageview') {
                    $result .= "\n\tga('send',".json_encode($arEvent).");\n";
                }
            }
            ActiveUser::one()->uga = array();
        } else {
            $result = "";
        }

        return $result;
    }
}
//end class ActiveUser
