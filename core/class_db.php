<?php
use \PDO as PDO;
use \Only as Only;

/**
 * Класс для работы с хранилищами данных
 *
 * @link    https://github.com/islandfuture/minilib
 * @author  Michael Akimov <michael@island-future.ru>
 * @version GIT: $Id$
 **/

class DB extends Only
{
    public static $debugQuery = '';
    /**
     * @var array кеш для хранения запросов во время выполнения скрипта
     **/
    protected static $arCaches=array();

    /**
     * @var array кеш для хранения запросов к конкретным таблицам (позволяет не выполнять одинаковые запросы к БД)
     **/
    protected static $arCacheTables = array();

    /**
     * @var boolean параметр на будущее, для работы с мемкешем
     **/
    protected $bEnableCache = false;

    /**
     * @var Array of PDO statement
     **/
    private $arPools = array();

    /**
     * @var string название текущей БД
     */
    private static $sCurKey = 'default';

    /**
     * ошибки, которые  произошли просто так или с каким-то объектом
     * массив состоит из моделей/полей или общей модели/блоков
     * Общая модель называется '_' (1-й уровень массива), у каждой модели есть счетчик ошибок ierr
     */
    protected static $arErrors = array();

    /**
     *  Создает модель данных (класс, который является проекцией какой-то таблицы)
     *
     *  @return Model
     */
    public static function model($sClassName,$arParams=null)
    {
        if ($arParams) {
            return new $sClassName($arParams);
        } else {
            return new $sClassName;
        }

    }

    /**
     * Метод генерит блок WHERE для запроса
     *
     * @param array $arParams
     * @return string условие для WHERE
     */
    public static function generateWhereSQL($arParams )
    {
        /* если название модели не указано, то ругаемся */
        if (empty($arParams['sModel'])) {
            throw new \Exception('Class of model not defined');
        }

        $sClassName = $arParams['sModel'];
        $sTableName = $sClassName::getTable(); // название таблицы
        $arFields    = $sClassName::getClearFields(); // название полей таблицы

        $sWhere        = '1=1';
        $arRelations    = null;

        /*
         * Выставляем базу и таблицу для запроса
         */
        if (!empty($arParams['sDatabase'] )) {
            $table = '`' . $arParams['sDatabase'] . '`.`' . $sTableName . '`';
        }
        elseif ($sClassName::getDatabase() > '') {
            $table = '`' . $sClassName::getDatabase() . '`.`' . $sTableName . '`';
        }
        else
        {
            $table = '`' . $sTableName . '`';
        }

        if (empty($arParams['arFilter'])) {
            $arParams['arFilter'] = array();
        }
        /* перебираем массив с условиями фильтрации */
        foreach($arParams['arFilter'] as $key => $value ) {
            /* если название ключа является названием поля из таблицы */
            if (key_exists($key, $arFields)) {

                if (is_array($value)) {
                    /**
                     * перибираем условия, чтобы сформировать правильный запрос
                     * @example пример фильтра обрабатываемого в этом блоке
                     *      // Выбрать все записи, чей код больше 100 и меньше 1000
                     *      $arParams['arFilter'] = array(
                                'id' => array(
                                    '>' => 100,
                                    '<' => 1000
                                )
                            );
                     */
                    foreach($value as $op => $val ) {

                        $op = strtolower($op);
                        if (!empty($arParams['securesecret']) && !empty($arParams['securefields']) && in_array($key, $arParams['securefields']) ) {
                            $sKey = "AES_DECRYPT($table.`$key`,UNHEX('".$arParams['securesecret']."'))";
                        } else {
                            if (! empty($arParams['collate'])) {
                                $sKey = $table.".`$key` collate ".$arParams['collate'];
                            } else {
                                $sKey = $table.".`$key`";
                            }
                        }

                        switch($op ) {
                        case 'not like':
                        case 'like':
                        case '>=':
                        case '>':
                        case '<':
                        case '<=':
                        case '=':
                        case '!=':
                            $sWhere .= " AND $sKey " . $op . " '" . addslashes($val) . "'";

                            break;
                        case 'between':
                            if (is_array($val)) {
                                $sWhere .= " AND $sKey $op '" . addslashes($val[0]) . "' and '" . addslashes($val[1]) . "'";
                            } else {
                                $sWhere .= " AND ($sKey $op " . addslashes($val) . ")";
                            }
                            break;
                        case '!in':
                            if (is_array($val)) {
                                foreach($val AS &$value )
                                {
                                    $value = addslashes($value);
                                }
                                $val = "'" . implode("','", $val) . "'";
                                $sWhere .= " AND $sKey not in (" . $val . ")";
                            }
                            else
                            {
                                $sWhere .= " AND $sKey not in (" . addslashes($val) . ")";
                            }
                            break;
                        case 'not in':
                        case 'in':
                            /*
                              if ( !is_array($val) && strpos($val,',')>0 ){
                              $val = explode(',',$val);
                              }
                             */
                            if (is_array($val)) {
                                foreach($val AS &$value )
                                {
                                    $value = addslashes($value);
                                }
                                $val = "'" . implode("','", $val) . "'";
                                $sWhere .= " AND $sKey $op (" . $val . ")";
                            }
                            else
                            {
                                $sWhere .= " AND $sKey $op (" . addslashes($val) . ")";
                            }
                            break;
                        default:
                            if ($op == 0) {
                                foreach($value AS &$val )
                                {
                                    $val = addslashes($val);
                                }
                                $sWhere .= " AND $sKey IN ('" . implode("','", $value) . "')";
                                break 2;
                            }
                            else
                            {
                                foreach($val AS &$value )
                                {
                                    $value = addslashes($value);
                                }
                                $sWhere .= " AND $sKey " . $op . " ('" . implode("','", $val) . "')";
                            }
                        } /* end switch*/
                    }
                } else {
                    if ($value == '[:null:]') {
                        $sWhere .= " AND $table.`" . $key . "` is null";
                    } elseif ($value == '[:!null:]') {
                        $sWhere .= " AND $table.`" . $key . "` is not null";
                    } elseif ($value == '[:ignore:]') {
                        /* по данному полю сортировать нельзя */
                    } else {
                        if (!empty($arParams['securesecret']) && !empty($arParams['securefields']) && in_array($key, $arParams['securefields']) ) {
                            $sKey = "AES_DECRYPT($table.`$key`,UNHEX('".$arParams['securesecret']."'))";
                        } else {
                            $sKey = $table.".`$key`";
                        }

                        $sWhere .= " AND $sKey='" . addslashes($value) . "'";
                    }
                }
            }
            else
            {
                if (!$arRelations) {
                    $arRelations = $sClassName::getRelations();
                }

                if (isset($arRelations[$key] )) {
                    $rel         = $arRelations[$key];
                    $classname     = $rel[3];

                    if ($rel[0] == '::table::') {

                        $sWhere .= ' AND '. $rel[2] .' IN ('.static::generateSelectSQL(
                            array(
                                'sModel' => $classname,
                                'sDatabase' => $classname::getDatabase(),
                                'fields' => $rel[4],
                                'arFilter'=> $value
                            )
                        ).')';

                    }

                }
                elseif ($key == ':sql:') {
                    /**
                     * перибираем условия, чтобы сформировать правильный запрос
                     * @example пример фильтра обрабатываемого в этом блоке
                     *      // Выбрать все записи, чей код больше 100 и меньше 1000
                     *      $arParams['arFilter'] = array(
                                ':sql:' => ' AND id > 100 AND id < 1000'
                            );
                     */
                    $sWhere .= ' AND ' . $value;
                }
            }
        }
        return $sWhere;
    }

    /**
     * Возвращает SQL запрос для подсчета количества записей
     * @return string
     */
    public static function generateCountSQL($arParams)
    {
        $from_add = '';
        if (empty($arParams['arFilter'])) {
            $arParams['arFilter'] = array();
        }

        $sClassName = $arParams['sModel'];

        /*
         * Выставляем базу и таблицу для запроса
         */
        if (!empty($arParams['sDatabase'] )) {
            $table = '`' . $arParams['sDatabase'] . '`.`' . $sClassName::getTable() . '`';
        } elseif ($sClassName::getDatabase() > '') {
            $table = '`' . $sClassName::getDatabase() . '`.`' . $sClassName::getTable() . '`';
        } else {
            $table = '`' . $sClassName::getTable() . '`';
        }

        $sWhere = static::generateWhereSQL($arParams);

        if (!empty($arParams['joins'] )) {
            if (is_string($arParams['joins'])) {
                $arParams['joins'] = array($arParams['joins'] );
            }

            foreach ($arParams['joins'] as $i => $joinClass ) {

                if (is_array($joinClass)) {
                    if (isset($joinClass['typeJoin'] )) {
                        $from_add .= ' ' . $joinClass['typeJoin'] . ' ';
                    } else {
                        $from_add .= ' INNER JOIN ';
                    }

                    $joinClassname     = $joinClass[0];
                    $t                 = $joinClassname::getTable();

                    if (isset($joinClass[1] )) {
                        $from_add .= '(SELECT * FROM `' . $t . '` WHERE ' . $joinClass[1] . ') as t' . $i . ' ';
                    } else {
                        $from_add .= ' ' . $t . ' as t' . $i . ' ';
                    }

                    $from_add .= ' ON ' . $joinClass['on'];
                } else {
                    $t = $joinClass::getTable();
                    $from_add .= ', `' . $t . '` as t' . $i . ' ';
                }
            }
        } elseif (!empty($arParams['ignoreindex']) && is_string($arParams['ignoreindex'])) {
            $from_add .= ' IGNORE INDEX (' . $arParams['ignoreindex'] . ') ';
        } elseif (!empty($arParams['useindex']) && is_string($arParams['useindex'])) {
            $from_add .= ' USE INDEX (' . $arParams['useindex'] . ') ';
        }

        return 'SELECT COUNT(*) as cnt FROM ' . $table . ' ' . $from_add . ' WHERE ' . $sWhere;
    }

    /**
     * Метод генерит тело SELECT запроса
     * @param array $arParams
     * @return string
     */
    public static function generateSelectSQL($arParams)
    {
        if (empty($arParams['sModel'])) {
            throw new \Exception('Class of model not defined');
        }

        $sClassName = $arParams['sModel'];
        $sTableName = $sClassName::getTable();
        $arFields     = $sClassName::getClearFields();

        /*
         * Выставляем базу и таблицу для запроса
         */
        if (!empty($arParams['sDatabase'] )) {
            $table = '`' . $arParams['sDatabase'] . '`.`' . $sTableName . '`';
        } elseif ($sClassName::getDatabase() > '') {
            $table = '`' . $sClassName::getDatabase() . '`.`' . $sTableName . '`';
        } else {
            $table = '`' . $sTableName . '`';
        }

        $from_add = '';

        $limit = '';
        if (!empty($arParams['iPageSize'] )) {

            if (empty($arParams['iPage'] )) {
                $offset = 0;
            } else {
                $offset = ($arParams['iPage'] - 1) * $arParams['iPageSize'];
            }

            $limit = " LIMIT " . $offset . "," . $arParams['iPageSize'];
        }/* emd if */


        if (!isset($arParams['arFilter'] )) {
            $arParams['arFilter']     = array();
        }

        if (empty($arParams['fields'] )) {
            if (!empty($arParams['securesecret']) && !empty($arParams['securefields']) ) {
                $ar = array();
                foreach($arFields as $key => $v) {
                    if (in_array($key, $arParams['securefields'])) {
                        $ar[] = "AES_DECRYPT(".$table . ".`".$key."`,UNHEX('".$arParams['securesecret']."')) as `$key`";
                    } else {
                        $ar[] = $table . '.`'.$key.'`';
                    }
                }
                $select = implode(', ', $ar);
            } else {
                $arKeys     = array_keys($arFields);
                $select     = $table . '.`' . implode('`,' . $table . '.`', $arKeys) . '`';
            }
        } else {
            if (is_array($arParams['fields'])) {
                $select = implode(',', $arParams['fields']);
            } else {
                $select = $arParams['fields'];
            }

            /* @todo добавить анализ полей и если есть групповые функции SUM, AVG, COUNT то остальные поля добавить в GROUP BY */
        }/* end if else */


        $sWhere = static::generateWhereSQL($arParams);

        if (!empty($arParams['joins'] )) {
            if (is_string($arParams['joins'])) {
                $arParams['joins'] = array($arParams['joins'] );
            }

            foreach($arParams['joins'] as $i => $joinClass ) {

                if (is_array($joinClass)) {
                    if (isset($joinClass['typeJoin'] )) {
                        $from_add .= ' ' . $joinClass['typeJoin'] . ' ';
                    } else {
                        $from_add .= ' INNER JOIN ';
                    }

                    $joinClassname     = $joinClass[0];
                    $t                 = $joinClassname::getTable();

                    if (isset($joinClass[1] )) {
                        $from_add .= '(SELECT * FROM `' . $t . '` WHERE ' . $joinClass[1] . ') as t' . $i . ' ';
                    } else {
                        $from_add .= ' ' . $t . ' as t' . $i . ' ';
                    }

                    $from_add .= ' ON ' . $joinClass['on'];
                } else {
                    $t = $joinClass::getTable();

                    $from_add .= ', `' . $t . '` as t' . $i;
                }
            }
        } elseif (!empty($arParams['ignoreindex']) && is_string($arParams['ignoreindex'])) {
            $from_add .= ' IGNORE INDEX (' . $arParams['ignoreindex'] . ') ';
        } elseif (!empty($arParams['useindex']) && is_string($arParams['useindex'])) {
            $from_add .= ' USE INDEX (' . $arParams['useindex'] . ') ';
        }

        $orders = '';
        if (!empty($arParams['arSort'] )) {
            $orders = array();
            foreach($arParams['arSort'] as $by => $order )
            {
                $orders[] = '`' . $by . '` ' . $order;
            }
            if (sizeof($orders) > 0) {
                $orders = 'ORDER BY ' . implode(',', $orders);
            }
            else
            {
                $orders = '';
            }
        }

        return 'SELECT ' . $select . ' FROM ' . $table . $from_add . ' WHERE ' . $sWhere . ' ' . $orders . $limit;
    }


    /**
     * Функция возвращает массив объектов определнного класса
     * Если присутсвует параметр $arSysOptions[index] - отдаем индексированный массив
     *
     * @param  Array $arParametrs  массив с данными класса и параметров фильтрации для выбора нужных объектов
     * @param  Array $arSysOptions массив с системными опциями (такие как отключить кеширование: nocache=>true)
     * @return Array
     *
     * @example возвращает первые 20 записей сделанные в блоге после 1 января 2015 года по убыванию
     * DB::I()->getAll(array
     *      'sModel'=>'Blogs',
     *      'arFilter' => array(
     *          'tCreated' => array('>' => '2015-01-01')
     *      ),
     *      'arSort' => array(
     *          'tCreated' => 'desc'
     *      )
     *      'iPageSize' => 30,
     *      'iPage' => 1
     *
     * ));
     */
    public static function getAll($arParametrs = array(), $arSysOptions = array())
    {
        $sKeyCache = md5(serialize($arParametrs));

        if (empty($arParametrs['sModel'])) {
            throw new \Exception('Cannot define class for sModel');
        }

        if (! empty($arSysOptions['index'])) {
            $sKeyCache .= 'idx';
        }

        /* узнаем название класса модели */
        $sClassName = $arParametrs['sModel'];
        $arResult = array();

        if (empty( static::$arCacheTables[$sClassName][$sKeyCache] )
            || (isset($arSysOptions['nocache'] ) && $arSysOptions['nocache'])
        ) {
            if (empty($arParametrs["iPageSize"])) {
                $arParametrs['iPageSize'] = 100;
            }

            if (empty($arParametrs['iPage'])) {
                $arParametrs['iPage'] = 1;
            }

            if (empty($arParametrs['arFilter'])) {
                $arParametrs['arFilter'] = Array();
            }

            /* Собираем SQL */
            $sSql = static::generateSelectSQL($arParametrs);

            /* Отправляем запрос к базе */
            if (! empty($arSysOptions['debug'])) {
                echo '[[[' . $sSql . ']]]';
            }
            $st = DB::I()->getStorage()->query($sSql, PDO::FETCH_CLASS, $sClassName, array(DB::I()));

            if ($st) {
                $arResult = $st->fetchAll();

                if (!empty($arSysOptions['index'] )) {
                    $idName     = $sClassName::getIdName();
                    $arTmp = array();
                    foreach($arResult as $obTmp )
                    {
                        $arTmp[ $obTmp->{$idName} ] = $obTmp;
                    }
                    $arResult = array();
                    $arResult = & $arTmp;
                }
            }

            if (empty(static::$arCacheTables[$sClassName])) {
                static::$arCacheTables[$sClassName] = array();
            }

            static::$arCacheTables[$sClassName][$sKeyCache] = & $arResult;
        }//end if self::$_cache

        return static::$arCacheTables[$sClassName][$sKeyCache];
    }

    /**
     * Возвращает количество найденных записей
     *
     * @param array $arParametrs  данные для запроса
     * @param array $arSysOptions Дополнительные условия по отбору объекта
     *
     * @return integer
     **/
    public static function getCountAll($arParametrs = array(), $arSysOptions = array() )
    {
        $arParametrs['fileds'] = array('count(*) as cnt');

        if (empty($arParametrs['sModel'])) {
            throw new \Exception('Cannot define class for sModel');
        }

        if (isset($arParametrs['iPage'])) {
            unset($arParametrs['iPage']);
        }
        if (isset($arParametrs['iPageSize'])) {
            unset($arParametrs['iPageSize']);
        }
        if (isset($arParametrs['arSort'])) {
            unset($arParametrs['arSort']);
        }
        if (empty($arParametrs['arFilter'] )) {
            $arParametrs['arFilter'] = Array();
        }

        $sKeyCache = md5(serialize($arParametrs));

        /* узнаем название класса модели */
        $sClassName = $arParametrs['sModel'];
        $iResult = 0;

        if
        (empty( static::$arCacheTables[$sClassName][$sKeyCache] )
            || (isset($arSysOptions['nocache'] ) && $arSysOptions['nocache'])
        ) {

            $arSelect = array();

            /* Собираем SQL */
            $sSql = static::generateCountSQL($arParametrs);

            /* Отправляем запрос к базе */
            if (!empty($arSysOptions['debug'] )) {
                static::$debugQuery = $sSql;
            }
            $st = DB::I()->getStorage()->query($sSql);

            if ($st) {
                $arTmp = $st->fetch(PDO::FETCH_ASSOC);
                if (isset($arTmp['cnt'])) {
                    $iResult=$arTmp['cnt'];
                }

            }

            if (empty(static::$arCacheTables[$sClassName])) {
                static::$arCacheTables[$sClassName] = array();
            }

            static::$arCacheTables[$sClassName][$sKeyCache] = $iResult;
        }//end if self::$_cache

        return static::$arCacheTables[$sClassName][$sKeyCache];
    }

    /**
     * Возвращает объект связанный с таблицей
     *
     * @param array $arParametrs  данные для запроса
     * @param array $arSysOptions Дополнительные условия по отбору объекта
     *
     * @return Model
     **/
    public static function getOne($arParametrs = array(), $arSysOptions = array() )
    {
        $arParametrs['iPage'] = 1;
        $arParametrs['iPageSize'] = 1;

        $rows = static::getAll($arParametrs, $arSysOptions);

        $obj = null;
        foreach($rows as $obj) {
            break;
        }

        return $obj;
    }

    /**
     * Удаляет записи
     *
     * @param array $arParametrs  данные для запроса
     **/
    public static function deleteAll($arParametrs=array() )
    {
        if (empty($arParametrs['sModel'])) {
            throw new \Exception('Cannot define class for Model');
        }

        /* узнаем название класса модели */
        $sClassName = $arParametrs['sModel'];

        /*
         * Выставляем базу и таблицу для запроса
         */
        if (! empty($arParametrs['sDatabase'] )) {
            $sTable = '`'.$arParametrs['sDatabase'].'`.`'.$sClassName::getTable().'`';
        } elseif ($sClassName::getDatabase() > '') {
            $sTable = '`'.$sClassName::getDatabase().'`.`'.$sClassName::getTable().'`';
        } else {
            $sTable = '`'.$sClassName::getTable().'`';
        }

        $sWhere     = static::generateWhereSQL($arParametrs);
        $sql     = "DELETE FROM " . $sTable . " WHERE " . $sWhere;
        $result     = static::I()->execute($sql);

        if (false !== $result) {
            if (0 === $result) {
                $err = static::I()->errorInfo();
                if ('00000' != $err[0]) {
                    echo "<div class='error'>" . $sql;
                    echo ($err);
                    echo '</div>';
                }//end if
            }//end if
        }//end if

        static::clearInnerCache($sClassName);

        return $result;
    }


    /**
     * Добавляем ошибку
     * @param string $sError      текст ошибки
     * @param string $sClassNamel название класса модели в котором произошла ошибка ("_" - означает общая ошибка)
     * @param string $sField      название поля в котором обнаружена ошибка
     */
    public static function addError($sError, $sClassName = '_', $sField = '_')
    {
        if (empty(static::$arErrors[$sClassName])) {
            static::$arErrors[$sClassName] = array();
        }//end if

        if (empty(static::$arErrors[$sClassName][$sField])) {
            static::$arErrors[$sClassName][$sField] = array();
        }
        static::$arErrors[$sClassName][$sField][] = $sError;
    }//end function

    // возвращает TRUE если в модели и поля есть ошибки
    public static function isError($sClassName = '_', $sField = '_')
    {
        if (isset(static::$arErrors) && isset(static::$arErrors[$sClassName])) {
            if (isset(static::$arErrors[$sClassName][$sField]) && sizeof(static::$arErrors[$sClassName][$sField]) > 0) {
                return true;
            } elseif ($sField == '*' && sizeof(static::$arErrors[$sClassName]) > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Функция проверяет есть ли ошибки в модели с названием $sClassName, или вообще, есть ли ошибки ($sClassName == '')
     *
     * @param string $sClassName название класса модели в которой проверяем наличие ошибок
     *
     * @return boolean
     */
    public static function isErrors($sClassName = '')
    {
        if ($sClassName == '') {
            return (sizeof(static::$arErrors) > 0);
        } else {
            if (isset(static::$arErrors[$sClassName]) && sizeof(static::$arErrors[$sClassName]) > 0) {
                return true;
            } else {
                return false;
            }
        }
    }//end function

    /**
     * Функция возвращает массив из текстов ошибок для поля указанного класса
     *
     * @return array
     */
    public static function getError($sClassName = '_', $sField = '_', $isClear = true)
    {
        if (isset(static::$arErrors[$sClassName])
            && isset(static::$arErrors[$sClassName][$sField])
        ) {
            $e = static::$arErrors[$sClassName][$sField];

            if ($isClear) {
                static::clearError($sClassName, $sField);
            }

            return $e;
        } else {
            return false;
        }
    }//end function

    // @todo доделать
    public static function getErrors($sClassName='_', $isClear=false)
    {
        if ($sClassName == '') {
            $e = static::$arErrors;
        } elseif (isset(static::$arErrors[$sClassName])) {
            $e = static::$arErrors[$sClassName];
        } else {
            $e = false;
            $isClear = false;
        }

        if ($isClear) {
            static::clearErrors($sClassName);
        }
        return $e;
    }//end function

    // стираем ошибку
    public function clearError($sClassName = '_', $sField = '_')
    {
        if (isset(static::$arErrors[$sClassName]) && isset(static::$arErrors[$sClassName][$sField])) {
            static::$arErrors[$sClassName][$sField] = null;
            unset(static::$arErrors[$sClassName][$sField]);

            if (sizeof(static::$arErrors[$sClassName]) == 0) {
                unset(static::$arErrors[$sClassName]);
            }
        }

        if (sizeof(static::$arErrors) == 0) {
            static::$arErrors = array();
        }
    }//end function

    // стираем ошибки, если указана модель, то стираем ошибки, только указанной модели
    public function clearErrors($sClassName = '_')
    {
        if ($sClassName == '') {
            static::$arErrors = array();
        } elseif (isset(static::$arErrors[$sClassName])) {
            unset(static::$arErrors[$sClassName]);

            if (sizeof(static::$arErrors) == 0) {
                static::$arErrors = array();
            }
        }

    }//end function

    /**
     * Проверка подключения
     * @return boolean возвращает TRUE, если объект подключения создан
     */
    public function isConnected()
    {
        return is_object(DB::I()->getStorage());
    }

    /**
     * Функция начинает транзакцию
     * @return bool возвращет TRUE если успех и FALSE если не успех
     */
    public function begin()
    {
        return DB::I()->getStorage()->beginTransaction();
    }

    /**
     * Функция коммитит все изменения в рамках ранее начатой транзакции
     * @return bool возвращет TRUE если успех и FALSE если не успех
     */
    public function commit()
    {
        return DB::I()->getStorage()->commit();
    }

    /**
     * Функция отменяет все изменения в рамках ранее начатой транзакции
     * @return bool возвращет TRUE если успех и FALSE если не успех
     */
    public function rollback()
    {
        return DB::I()->getStorage()->rollBack();
    }

    /**
     * Так как все результаты запросов кешируются на время выполнения скрипта иногда кеш нужно очищать
     * (особенно если идет обработка большого объема данных)
     * @param string $sClassName название модели, чтобы стереть только кеш касающийся этой модели
     */
    public static function clearInnerCache($sClassName = '')
    {
        if ($sClassName == '') {
            static::$arCaches = array();
        }
        elseif (isset(static::$arCacheTables[$sClassName])) {
            static::$arCacheTables[$sClassName] = array();
        }
        elseif ($sClassName == ':all:') {
            static::$arCacheTables = array();
            static::$arCaches = array();
        }
    }


    /**
     * @throws WrongArgumentException
     * @return DB
    **/
    public function addStorage($key, $arDBConfig)
    {
        if (isset($this->arPools[$key])) {
            throw new \Exception("already have '{$key}' link db");
        }

        $arDBPoolConfig = MiniLibCore::$app->dbpool;
        if ($arDBPoolConfig == null) {
            $arDBPoolConfig = array();
        }
        $arDBPoolConfig[ $key ] = $arDBConfig;
        MiniLibCore::$app->dbpool = $arDBPoolConfig;

        static::$sCurKey = $key;
        return $this;
    }

    public function disconnect($sKey='')
    {
        if ($sKey > '') {
            static::$sCurKey = $sKey;
        }

        if (empty($this->arPools[static::$sCurKey])) {
            throw new \Exception("already disconnected '{static::$sCurKey}' link db");
        }

        $this->arPools[static::$sCurKey] = null;
    }

    /**
     * Функция возвращает класс для работы с хранилищем
     * @return class
     */
    private function getStorage($sKey = '')
    {
        if ($sKey > '') {
            static::$sCurKey = $sKey;
        }

        if (empty($this->arPools[static::$sCurKey])) {
            $arDBPoolConfig = MiniLibCore::$app->dbpool;
            if (empty($arDBPoolConfig[static::$sCurKey])) {
                throw new \Exception('Cannot read config for initialize DB {' . static::$sCurKey . '}');
            }

            $arPdoOptions = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                //PDO::MYSQL_ATTR_MAX_BUFFER_SIZE => 4*1024*1024
            );

            $this->arPools[static::$sCurKey] =  new PDO(
                $arDBPoolConfig[static::$sCurKey]['dsn'],
                $arDBPoolConfig[static::$sCurKey]['user'],
                $arDBPoolConfig[static::$sCurKey]['password'],
                $arPdoOptions
            );

            $this->arPools[static::$sCurKey]->exec("SET NAMES 'utf8'");
        }

        return $this->arPools[static::$sCurKey];
    }

    public function setKey($sKey='')
    {
        if ($sKey > '') {
            static::$sCurKey = $sKey;
        } else {
            static::$sCurKey = 'default';
        }
        return $this;
    }

    /**
     * создется объект, для работы с БД. Данные для соединения берутся из класса SFW_Config->dbpool
     */
    public function __construct()
    {
        $arCacheConfig = MiniLibCore::$app->cache;
        if ($arCacheConfig && isset($arCacheConfig['enable']) && $arCacheConfig['enable'] == 'on') {
            $this->bEnableCache = true;
            /* @todo add code for init cache classes */
        }

        static::$arErrors = array(
            'commons'=>array()
        );
    }//end function


    /**
     * Функция выполняет SQL-запрос к БД и
     * @param string $sSql
     * @param array  $arSysOptions
     * @return class pdo_statement
     */
    public function query($sSql, $arSysOptions=array())
    {
        /* формируем ключ для кеширования запроса */
        $sCacheKey = hash('md5', $sSql);

        if (!empty($arSysOptions['nocache']) || empty(static::$arCaches[$sCacheKey] )) {
            if (isset($arSysOptions['type']) && $arSysOptions['type'] == 'class' && isset($arSysOptions['classname'])) {
                $rows = $this->getStorage()->query($sSql, PDO::FETCH_CLASS, $arSysOptions['classname'], array($this));
            } else {
                $rows = $this->getStorage()->query($sSql);
            }

            if (empty($arSysOptions['nocache'])) {
                static::$arCaches[$sCacheKey] = $rows;
            }
        } else {
            $rows = static::$arCaches[$sCacheKey];

        }//end if else

        return $rows;
    }

    /**
     * Делает запрос к БД и возращает массив результатов ввиде массива или класса
     * @param string $sSql
     * @param array  $arSysOptions
     *
     * @return array of classes
     */
    public function queryAll($sSql, $arSysOptions=array() )
    {
        $sCacheKey = hash('md5', $sSql);
        $rows = null;

        if (! empty($arSysOptions['nocache']) || empty(static::$arCaches[$sCacheKey] )) {
            if (isset($arSysOptions['type']) && $arSysOptions['type'] == 'class' && isset($arSysOptions['classname'])) {
                $st = $this->getStorage()->query($sSql, PDO::FETCH_CLASS, $arSysOptions['classname'], array($this));
            } else {
                $st = $this->getStorage()->query($sSql, PDO::FETCH_ASSOC);
            }

            $rows = $st ? $st->fetchAll() : array() ;

            if (empty($arSysOptions['nocache'])) {
                static::$arCaches[$sCacheKey] = $rows;
            }
        } else {
            $rows = static::$arCaches[$sCacheKey];

        }//end if else

        return $rows;
    }

    /* экранирование */
    public function quote($s)
    {
        return $this->getStorage()->quote($s);
    }

    /**
     * запуск запросов к БД вида Alter table, insert, update
     * @param string $sql
     * @return integer
     */
    public function execute($sql)
    {
        $rows = $this->getStorage()->exec($sql);
        return $rows;
    }

    /**
     * Возвращает ID последней вставленной записи с полем автоинкремента
     * @param string $name
     * @return integer
     */
    public function getLastID($name=null)
    {
        $val = $this->getStorage()->lastInsertId($name);
        return $val;
    }

    /**
     * возвращает информацию о последней ошибке
     */
    public function errorInfo()
    {
        return $this->getStorage()->errorInfo();
    }

}
