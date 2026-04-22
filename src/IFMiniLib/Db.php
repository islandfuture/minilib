<?php
namespace IFMiniLib;

use \PDO as PDO;
use \IFMiniLib\Only as Only;

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
    protected static $caches = [];

    /**
     * @var array кеш для хранения запросов к конкретным таблицам (позволяет не выполнять одинаковые запросы к БД)
     **/
    protected static $cacheTables = [];

    /**
     * @var boolean параметр на будущее, для работы с мемкешем
     **/
    protected $enableCache = false;

    /**
     * @var Array of PDO statement
     **/
    private $pools = [];

    /**
     * @var string название текущей БД
     */
    private static $curKey = 'default';

    /**
     * ошибки, которые  произошли просто так или с каким-то объектом
     * массив состоит из моделей/полей или общей модели/блоков
     * Общая модель называется '_' (1-й уровень массива), у каждой модели есть счетчик ошибок ierr
     */
    protected static $errors = [];

    /**
     *  Создает модель данных (класс, который является проекцией какой-то таблицы)
     *
     *  @return Model
     */
    public static function model($className, $params = null)
    {
        if ($params) {
            return new $className($params);
        } else {
            return new $className;
        }
    }

    /**
     * Метод генерит блок WHERE для запроса
     *
     * @param array $params
     * @return string условие для WHERE
     */
    public static function generateWhereSQL(array $params, array &$values)
    {
        /* если название модели не указано, то ругаемся */
        if (empty($params['model'])) {
            throw new \Exception('Class of model not defined');
        }

        $className = $params['model'];
        $tableName = $className::getTable(); // название таблицы
        $fields    = $className::getClearFields(); // название полей таблицы

        $where        = '1=1';
        $relations    = null;

        /*
         * Выставляем базу и таблицу для запроса
         */
        if (!empty($params['database'])) {
            $table = '`' . $params['database'] . '`.`' . $tableName . '`';
        } elseif ($className::getDatabase() > '') {
            $table = '`' . $className::getDatabase() . '`.`' . $tableName . '`';
        } else {
            $table = '`' . $tableName . '`';
        }

        if (empty($params['filter'])) {
            $params['filter'] = array();
        }
        /* перебираем массив с условиями фильтрации */
        foreach ($params['filter'] as $key => $value) {
            /* если название ключа является названием поля из таблицы */
            if (key_exists($key, $fields)) {
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
                    foreach ($value as $op => $val) {
                        $op = strtolower($op);
                        if (
                            !empty($params['securesecret'])
                            && !empty($params['securefields'])
                            && in_array($key, $params['securefields'])
                        ) {
                            $fullKey = "AES_DECRYPT($table.`$key`,UNHEX('" . $params['securesecret'] . "'))";
                        } else {
                            $fullKey = $table . ".`$key`";
                        }

                        switch ($op) {
                            case 'not like':
                            case 'like':
                            case '>=':
                            case '>':
                            case '<':
                            case '<=':
                            case '=':
                            case '!=':
                                $where .= " AND $fullKey " . $op . " :" . $key;
                                $values[":$key"] = $val;
                                break;
                            case 'between':
                                if (is_array($val)) {
                                    $where .= " AND $fullKey $op :{$key}_0 and :{$key}_1";
                                    $values[":{$key}_0"] = $val[0];
                                    $values[":{$key}_1"] = $val[1];
                                } else {
                                    throw new \Exception("Value for BETWEEN must be array");
                                }
                                break;
                            case '!in':
                            case 'not in':
                            case 'in':
                                if ($op == '!in') {
                                    $op = 'NOT IN';
                                }

                                if (! is_array($val)) {
                                    $val = explode(',', $val);
                                    $cnt = count($val);
                                } else {
                                    $cnt = count($val);
                                }

                                if ($cnt == 0) {
                                    throw new \Exception("Value for IN must be non empty array");
                                }

                                $valueKeys = '';
                                for ($iKey = 0; $iKey < $cnt; $iKey++) {
                                    if ($iKey == 0) {
                                        $valueKeys = ":{$key}_$iKey";
                                    } else {
                                        $valueKeys .= ",:{$key}_$iKey";
                                    }
                                    $values[":{$key}_$iKey"] = $val[$iKey];
                                }
                                $where .= " AND $fullKey $op ($valueKeys)";
                                break;
                            default:
                                if ($op == 0) {
                                    $op = 'IN';
                                    $cnt = count($value);
                                } else {
                                    $cnt = count($val);
                                }

                                $valueKeys = '';
                                for ($iKey = 0; $iKey < $cnt; $iKey++) {
                                    if ($iKey == 0) {
                                        $valueKeys = ":{$key}_$iKey";
                                    } else {
                                        $valueKeys .= ",:{$key}_$iKey";
                                    }
                                    $values[":{$key}_$iKey"] = ($op == 0 ? $value[$iKey] : $val[$iKey]);
                                }
                                $where .= " AND $fullKey IN ($valueKeys)";
                                if ($op == 0) {
                                    break 2;
                                }
                                break;
                        } /* end switch*/
                    }
                } elseif ($value == '[:null:]') {
                    $where .= " AND $table.`" . $key . "` is null";
                } elseif ($value == '[:!null:]') {
                    $where .= " AND $table.`" . $key . "` is not null";
                } elseif ($value == '[:ignore:]') {
                    /* по данному полю сортировать нельзя */
                } else {
                    if (
                        !empty($params['securesecret'])
                        && !empty($params['securefields'])
                        && in_array($key, $params['securefields'])
                    ) {
                        $fullKey = "AES_DECRYPT($table.`$key`,UNHEX('" . $params['securesecret'] . "'))";
                    } else {
                        $fullKey = $table . ".`$key`";
                    }

                    $where .= " AND $fullKey=:$key";
                    $values[":$key"] = $value;
                }
            } else {
                if (!$relations) {
                    $relations = $className::getRelations();
                }

                if (isset($relations[$key])) {
                    $rel         = $relations[$key];
                    $classname     = $rel[3];

                    if ($rel[0] == '::table::') {
                        $keyValues2 = [];
                        $where .= ' AND ' . $rel[2] . ' IN (' . static::generateSelectSQL(
                            [
                                'model' => $classname,
                                'database' => $classname::getDatabase(),
                                'fields' => $rel[4],
                                'filter' => $value
                            ],
                            $keyValues2
                        ) . ')';

                        foreach ($keyValues2 as $k => $v) {
                            $keyValues[$k] = $v;
                        }
                    }
                } elseif ($key == ':sql:') {
                    /**
                     * перибираем условия, чтобы сформировать правильный запрос
                     * @example пример фильтра обрабатываемого в этом блоке
                     *      // Выбрать все записи, чей код больше 100 и меньше 1000
                     *      $params['filter'] = array(
                                ':sql:' => ' AND id > 100 AND id < 1000'
                            );
                     */
                    $where .= ' AND ' . $value;
                } else {
                    if (strpos($key, '(') !== false && strpos($key, ' as ') !== false) {
                        /** можно проверить что за функция */
                    } else {
                        throw new \Exception("Unknown key [" . $key . "] in Model");
                    }
                }
            }
        }
        return $where;
    }

    /**
     * Возвращает SQL запрос для подсчета количества записей
     * @return string
     */
    public static function generateCountSQL(array $params, array &$values)
    {
        $from_add = '';
        if (empty($params['filter'])) {
            $params['filter'] = array();
        }

        $className = $params['model'];

        /*
         * Выставляем базу и таблицу для запроса
         */
        if (!empty($params['database'])) {
            $table = '`' . $params['database'] . '`.`' . $className::getTable() . '`';
        } elseif ($className::getDatabase() > '') {
            $table = '`' . $className::getDatabase() . '`.`' . $className::getTable() . '`';
        } else {
            $table = '`' . $className::getTable() . '`';
        }

        $where = static::generateWhereSQL($params, $values);

        if (!empty($params['joins'])) {
            if (is_string($params['joins'])) {
                $params['joins'] = array($params['joins'] );
            }

            foreach ($params['joins'] as $i => $joinClass) {
                if (is_array($joinClass)) {
                    if (isset($joinClass['typeJoin'])) {
                        $from_add .= ' ' . $joinClass['typeJoin'] . ' ';
                    } else {
                        $from_add .= ' INNER JOIN ';
                    }

                    $joinClassname     = $joinClass[0];
                    $t                 = $joinClassname::getTable();

                    if (isset($joinClass[1])) {
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
        } elseif (!empty($params['ignoreindex']) && is_string($params['ignoreindex'])) {
            $from_add .= ' IGNORE INDEX (' . $params['ignoreindex'] . ') ';
        } elseif (!empty($params['useindex']) && is_string($params['useindex'])) {
            $from_add .= ' USE INDEX (' . $params['useindex'] . ') ';
        }

        return 'SELECT COUNT(*) as cnt FROM ' . $table . ' ' . $from_add . ' WHERE ' . $where;
    }

    /**
     * Метод генерит тело SELECT запроса
     * @param array $params
     * @return string
     */
    public static function generateSelectSQL(array $params, array &$values)
    {
        if (empty($params['model'])) {
            throw new \Exception('Class of model not defined');
        }

        $className = $params['model'];
        $tableName = $className::getTable();
        $fields     = $className::getClearFields();

        /*
         * Выставляем базу и таблицу для запроса
         */
        if (!empty($params['database'])) {
            $table = '`' . $params['database'] . '`.`' . $tableName . '`';
        } elseif ($className::getDatabase() > '') {
            $table = '`' . $className::getDatabase() . '`.`' . $tableName . '`';
        } else {
            $table = '`' . $tableName . '`';
        }

        $from_add = '';

        $limit = '';
        if (!empty($params['pageSize'])) {
            $offset = empty($params['page']) ? 0 : ($params['page'] - 1) * $params['pageSize'];
            $limit = " LIMIT " . $offset . "," . $params['pageSize'];
        } /* emd if */

        if (!isset($params['filter'])) {
            $params['filter']     = [];
        }

        if (empty($params['fields'])) {
            if (!empty($params['securesecret']) && !empty($params['securefields'])) {
                $ar = array();
                foreach ($fields as $key => $v) {
                    if (in_array($key, $params['securefields'])) {
                        $ar[] = "AES_DECRYPT(".$table . ".`".$key."`,UNHEX('".$params['securesecret']."')) as `$key`";
                    } else {
                        $ar[] = $table . '.`' . $key . '`';
                    }
                }
                $select = implode(', ', $ar);
            } else {
                $keys     = array_keys($fields);
                $select     = $table . '.`' . implode('`,' . $table . '.`', $keys) . '`';
            }
        } else {
            if (is_array($params['fields'])) {
                $select = implode(',', $params['fields']);
            } else {
                $select = $params['fields'];
            }

            /* @todo добавить анализ полей и если есть групповые функции SUM, AVG, COUNT то остальные поля добавить в GROUP BY */
        }/* end if else */

        $where = static::generateWhereSQL($params, $values);

        if (!empty($params['joins'])) {
            if (is_string($params['joins'])) {
                $params['joins'] = array($params['joins'] );
            }

            foreach ($params['joins'] as $i => $joinClass) {

                if (is_array($joinClass)) {
                    if (isset($joinClass['typeJoin'])) {
                        $from_add .= ' ' . $joinClass['typeJoin'] . ' ';
                    } else {
                        $from_add .= ' INNER JOIN ';
                    }

                    $joinClassname     = $joinClass[0];
                    $t                 = $joinClassname::getTable();

                    if (isset($joinClass[1])) {
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
        } elseif (!empty($params['ignoreindex']) && is_string($params['ignoreindex'])) {
            $from_add .= ' IGNORE INDEX (' . $params['ignoreindex'] . ') ';
        } elseif (!empty($params['useindex']) && is_string($params['useindex'])) {
            $from_add .= ' USE INDEX (' . $params['useindex'] . ') ';
        }

        $orders = '';
        if (!empty($params['sort'])) {
            $orders = array();
            foreach ($params['sort'] as $by => $order) {
                $orders[] = '`' . $by . '` ' . $order;
            }
            if (sizeof($orders) > 0) {
                $orders = 'ORDER BY ' . implode(',', $orders);
            } else {
                $orders = '';
            }
        }

        return 'SELECT ' . $select . ' FROM ' . $table . $from_add . ' WHERE ' . $where . ' ' . $orders . $limit;
    }


    /**
     * Функция возвращает массив объектов определнного класса
     * Если присутсвует параметр $sysOptions[index] - отдаем индексированный массив
     *
     * @param  Array $parametrs  массив с данными класса и параметров фильтрации для выбора нужных объектов
     * @param  Array $sysOptions массив с системными опциями (такие как отключить кеширование: nocache=>true)
     * @return Array
     *
     * @example возвращает первые 20 записей сделанные в блоге после 1 января 2015 года по убыванию
     * DB::one()->getAll(array
     *      'model'=>'Blogs',
     *      'filter' => array(
     *          'tCreated' => array('>' => '2015-01-01')
     *      ),
     *      'sort' => array(
     *          'tCreated' => 'desc'
     *      )
     *      'pageSize' => 30,
     *      'page' => 1
     *
     * ));
     */
    public static function getAll($parametrs = [], $sysOptions = [])
    {
        $keyCache = md5(serialize($parametrs));

        if (empty($parametrs['model'])) {
            throw new \Exception('Cannot define class for model');
        }

        if (! empty($sysOptions['index'])) {
            $keyCache .= 'idx';
        }

        /* узнаем название класса модели */
        $className = $parametrs['model'];
        $result = [];

        if (
            empty(static::$cacheTables[$className][$keyCache])
            || (isset($sysOptions['nocache']) && $sysOptions['nocache'])
        ) {
            if (empty($parametrs["pageSize"])) {
                $parametrs['pageSize'] = 100;
            }

            if (empty($parametrs['page'])) {
                $parametrs['page'] = 1;
            }

            if (empty($parametrs['filter'])) {
                $parametrs['filter'] = [];
            }

            $values = [];

            /* Собираем SQL */
            $sql = static::generateSelectSQL($parametrs, $values);

            /* Отправляем запрос к базе */
            if (! empty($sysOptions['debug'])) {
                App::one()->log('getAll: ' . $sql, ['params' => $parametrs], 'debug');
            }
            $st = DB::one()->getStorage()->prepare($sql);

            if ($st->execute($values)) {
                $st->setFetchMode(PDO::FETCH_CLASS, $className, [DB::one()]);
                $result = $st->fetchAll();

                if (! empty($sysOptions['index'])) {
                    if (
                        $sysOptions['index'] !== true
                        && $className::is($sysOptions['index'])
                    ) {
                        $idName = $sysOptions['index'];
                    } else {
                        $idName = $className::getIdName();
                    }

                    $arTmp = [];
                    foreach ($result as $obTmp) {
                        if (! empty($parametrs['fields'])) {
                            $obTmp->readOnly = true;
                        }
                        $arTmp[ $obTmp->{$idName} ] = $obTmp;
                    }
                    $result = [];
                    $result = & $arTmp;
                } elseif (! empty($parametrs['fields'])) {
                    foreach ($result as $idx => $obTmp) {
                        $result[$idx]->readOnly = true;
                    }
                }
            }
            unset($values);

            if (empty(static::$cacheTables[$className])) {
                static::$cacheTables[$className] = array();
            }

            static::$cacheTables[$className][$keyCache] = & $result;
        }//end if self::$_cache

        return static::$cacheTables[$className][$keyCache];
    }

    /**
     * Возвращает количество найденных записей
     *
     * @param array $parametrs  данные для запроса
     * @param array $sysOptions Дополнительные условия по отбору объекта
     *
     * @return integer
     **/
    public static function getCountAll($parametrs = [], $sysOptions = [])
    {
        $parametrs['fileds'] = ['count(*) as cnt'];

        if (empty($parametrs['model'])) {
            throw new \Exception('Cannot define class for model');
        }

        if (isset($parametrs['page'])) {
            unset($parametrs['page']);
        }
        if (isset($parametrs['pageSize'])) {
            unset($parametrs['pageSize']);
        }
        if (isset($parametrs['sort'])) {
            unset($parametrs['sort']);
        }
        if (empty($parametrs['filter'])) {
            $parametrs['filter'] = [];
        }

        $keyCache = md5(serialize($parametrs));

        /* узнаем название класса модели */
        $className = $parametrs['model'];
        $iResult = 0;
        $values = [];

        if (
            empty(static::$cacheTables[$className][$keyCache])
            || (
                isset($sysOptions['nocache'])
                && $sysOptions['nocache']
            )
        ) {
            $select = [];

            /* Собираем SQL */
            $sql = static::generateCountSQL($parametrs, $values);

            /* Отправляем запрос к базе */
            if (!empty($sysOptions['debug'])) {
                App::one()->log('Get count: ' . $sql, ['params' => $parameters], 'debug');
                static::$debugQuery = $sql;
            }
            $st = DB::one()->getStorage()->prepare($sql);

            if ($st->execute($values)) {
                $arTmp = $st->fetch(PDO::FETCH_ASSOC);
                if (isset($arTmp['cnt'])) {
                    $iResult = $arTmp['cnt'];
                }
            }

            if (empty(static::$cacheTables[$className])) {
                static::$cacheTables[$className] = array();
            }

            static::$cacheTables[$className][$keyCache] = $iResult;
        }//end if self::$_cache

        return static::$cacheTables[$className][$keyCache];
    }

    /**
     * Возвращает объект связанный с таблицей
     *
     * @param array $parametrs  данные для запроса
     * @param array $sysOptions Дополнительные условия по отбору объекта
     *
     * @return Model
     **/
    public static function getOne($parametrs = [], $sysOptions = [])
    {
        $parametrs['page'] = 1;
        $parametrs['pageSize'] = 1;

        $rows = static::getAll($parametrs, $sysOptions);

        $obj = null;
        foreach ($rows as $obj) {
            break;
        }
        return $obj;
    }

    /**
     * Удаляет записи
     *
     * @param array $parametrs  данные для запроса
     **/
    public static function deleteAll($parametrs = [])
    {
        if (empty($parametrs['model'])) {
            throw new \Exception('Cannot define class for Model');
        }

        /* узнаем название класса модели */
        $className = $parametrs['model'];

        /*
         * Выставляем базу и таблицу для запроса
         */
        if (! empty($parametrs['database'])) {
            $table = '`' . $parametrs['database'] . '`.`' . $className::getTable() . '`';
        } elseif ($className::getDatabase() > '') {
            $table = '`' . $className::getDatabase() . '`.`' . $className::getTable() . '`';
        } else {
            $table = '`' . $className::getTable() . '`';
        }

        $limit = '';
        if (!empty($parametrs['iPageSize'])) {
            $limit = " LIMIT " . intval($parametrs['iPageSize']);
        }

        $values = [];
        $where     = static::generateWhereSQL($parametrs, $values);
        $sql     = "DELETE FROM " . $table . " WHERE " . $where . $limit;
        $st = static::one()->getStorage()->prepare($sql);
        $result = $st->execute($values);

        if (false !== $result) {
            if (0 === $result) {
                $err = static::one()->errorInfo();
                if ('00000' != $err[0]) {
                    App::one()->log('Error in DB::deleteAll[' . $sql . ']; ' . print_r($err, true), [], 'error');
                }//end if
            }//end if
        }//end if

        static::clearInnerCache($className);

        return $result;
    }


    /**
     * Добавляем ошибку
     * @param string $sError      текст ошибки
     * @param string $classNamel название класса модели в котором произошла ошибка ("_" - означает общая ошибка)
     * @param string $sField      название поля в котором обнаружена ошибка
     */
    public static function addError($sError, $className = '_', $sField = '_')
    {
        if (empty(static::$errors[$className])) {
            static::$errors[$className] = array();
        }//end if

        if (empty(static::$errors[$className][$sField])) {
            static::$errors[$className][$sField] = array();
        }
        static::$errors[$className][$sField][] = $sError;
    }//end function

    // возвращает TRUE если в модели и поля есть ошибки
    public static function isError($className = '_', $sField = '_')
    {
        if (isset(static::$errors) && isset(static::$errors[$className])) {
            if (isset(static::$errors[$className][$sField]) && sizeof(static::$errors[$className][$sField]) > 0) {
                return true;
            } elseif ($sField == '*' && sizeof(static::$errors[$className]) > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Функция проверяет есть ли ошибки в модели с названием $className, или вообще, есть ли ошибки ($className == '')
     *
     * @param string $className название класса модели в которой проверяем наличие ошибок
     *
     * @return boolean
     */
    public static function isErrors($className = '')
    {
        if ($className == '') {
            return (sizeof(static::$errors) > 0);
        } elseif (isset(static::$errors[$className]) && sizeof(static::$errors[$className]) > 0) {
                return true;
        }
        return false;
    }//end function

    /**
     * Функция возвращает массив из текстов ошибок для поля указанного класса
     *
     * @return array
     */
    public static function getError($className = '_', $sField = '_', $isClear = true)
    {
        if (isset(static::$errors[$className])
            && isset(static::$errors[$className][$sField])
        ) {
            $e = static::$errors[$className][$sField];

            if ($isClear) {
                static::clearError($className, $sField);
            }

            return $e;
        } else {
            return false;
        }
    }//end function

    // @todo доделать
    public static function getErrors($className='_', $isClear=false)
    {
        if ($className == '') {
            $e = static::$errors;
        } elseif (isset(static::$errors[$className])) {
            $e = static::$errors[$className];
        } else {
            $e = false;
            $isClear = false;
        }

        if ($isClear) {
            static::clearErrors($className);
        }
        return $e;
    }//end function

    // стираем ошибку
    public function clearError($className = '_', $sField = '_')
    {
        if (isset(static::$errors[$className]) && isset(static::$errors[$className][$sField])) {
            static::$errors[$className][$sField] = null;
            unset(static::$errors[$className][$sField]);

            if (sizeof(static::$errors[$className]) == 0) {
                unset(static::$errors[$className]);
            }
        }

        if (sizeof(static::$errors) == 0) {
            static::$errors = array();
        }
    }//end function

    // стираем ошибки, если указана модель, то стираем ошибки, только указанной модели
    public function clearErrors($className = '_')
    {
        if ($className == '') {
            static::$errors = array();
        } elseif (isset(static::$errors[$className])) {
            unset(static::$errors[$className]);

            if (sizeof(static::$errors) == 0) {
                static::$errors = array();
            }
        }

    }//end function

    /**
     * Проверка подключения
     * @return boolean возвращает TRUE, если объект подключения создан
     */
    public function isConnected()
    {
        return is_object(DB::one()->getStorage());
    }

    /**
     * Функция начинает транзакцию
     * @return bool возвращет TRUE если успех и FALSE если не успех
     */
    public function begin()
    {
        return DB::one()->getStorage()->beginTransaction();
    }

    /**
     * Функция коммитит все изменения в рамках ранее начатой транзакции
     * @return bool возвращет TRUE если успех и FALSE если не успех
     */
    public function commit()
    {
        return DB::one()->getStorage()->commit();
    }

    /**
     * Функция отменяет все изменения в рамках ранее начатой транзакции
     * @return bool возвращет TRUE если успех и FALSE если не успех
     */
    public function rollback()
    {
        return DB::one()->getStorage()->rollBack();
    }

    /**
     * Так как все результаты запросов кешируются на время выполнения скрипта иногда кеш нужно очищать
     * (особенно если идет обработка большого объема данных)
     * @param string $className название модели, чтобы стереть только кеш касающийся этой модели
     */
    public static function clearInnerCache($className = '')
    {
        if ($className == '' || $className == ':all:') {
            static::$caches = [];
            static::$cacheTables = [];
        } elseif ($className == ':class:' || $className == ':table:') {
            static::$cacheTables = [];
        } elseif ($className == ':query:') {
            static::$caches = [];
        } elseif (isset(static::$cacheTables[$className])) {
            static::$cacheTables[$className] = [];
        }
    }


    /**
     * @throws WrongArgumentException
     * @return DB
    **/
    public function addStorage($key, $dbConfig)
    {
        if (isset($this->pools[$key])) {
            throw new \Exception("already have '{$key}' link db");
        }

        $dbPoolConfig = Core::$app->dbpool;
        if ($dbPoolConfig == null) {
            $dbPoolConfig = array();
        }
        $dbPoolConfig[ $key ] = $dbConfig;
        Core::$app->dbpool = $dbPoolConfig;

        static::$curKey = $key;
        return $this;
    }

    public function disconnect($key = '')
    {
        if ($key > '') {
            static::$curKey = $key;
        }

        $this->pools[static::$curKey] = null;
    }

    /**
     * Функция возвращает класс для работы с хранилищем
     * @return class
     */
    private function getStorage($key = '')
    {
        if ($key > '') {
            static::$curKey = $key;
        }

        if (empty($this->pools[static::$curKey])) {
            $dbPoolConfig = Core::$app->dbpool;
            if (empty($dbPoolConfig[static::$curKey])) {
                throw new \Exception('Cannot read config for initialize DB {' . static::$curKey . '}');
            }

            $pdoOptions = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                //PDO::MYSQL_ATTR_MAX_BUFFER_SIZE => 4*1024*1024
            );

            $this->pools[static::$curKey] =  new PDO(
                $dbPoolConfig[static::$curKey]['dsn'],
                $dbPoolConfig[static::$curKey]['user'],
                $dbPoolConfig[static::$curKey]['password'],
                $pdoOptions
            );

            if (
                ! empty($dbPoolConfig[static::$curKey]['afterConnect'])
                && is_array($dbPoolConfig[static::$curKey]['afterConnect'])
            ) {
                foreach ($dbPoolConfig[static::$curKey]['afterConnect'] as $command) {
                    $this->pools[static::$curKey]->exec($command);
                }
            } else {
                $this->pools[static::$curKey]->exec("SET NAMES 'utf8mb4'");
            }
        }

        return $this->pools[static::$curKey];
    }

    public function setKey($key = '')
    {
        if ($key > '') {
            static::$curKey = $key;
        } else {
            static::$curKey = 'default';
        }
        return $this;
    }

    /**
     * создется объект, для работы с БД. Данные для соединения берутся из класса Core::$app->dbpool
     */
    public function __construct()
    {
        $cacheConfig = Core::$app->cache;
        if (
            $cacheConfig
            && isset($cacheConfig['enable'])
            && $cacheConfig['enable'] == 'on'
        ) {
            $this->enableCache = true;
            /* @todo add code for init cache classes */
        }

        static::$errors = [
            'commons' => []
        ];
    }//end function

    /**
     * Функция выполняет SQL-запрос к БД и
     * @param string $sql
     * @param array  $sysOptions
     * @return class pdo_statement
     */
    public function query($sql, $sysOptions = [])
    {
        /* формируем ключ для кеширования запроса */
        $sCacheKey = hash('md5', $sql);

        if (!empty($sysOptions['nocache']) || empty(static::$caches[$sCacheKey])) {
            if (isset($sysOptions['type']) && $sysOptions['type'] == 'class' && isset($sysOptions['classname'])) {
                $rows = $this->getStorage()->query($sql, PDO::FETCH_CLASS, $sysOptions['classname'], array($this));
            } else {
                $rows = $this->getStorage()->query($sql);
            }

            if (empty($sysOptions['nocache'])) {
                static::$caches[$sCacheKey] = $rows;
            }
        } else {
            $rows = static::$caches[$sCacheKey];
        }//end if else

        return $rows;
    }

    /**
     * Делает запрос к БД и возращает массив результатов ввиде массива или класса
     * @param string $sql
     * @param array  $sysOptions
     *
     * @return array of classes
     */
    public function queryAll($sql, $sysOptions = [])
    {
        $cacheKey = hash('md5', $sql);
        $rows = null;

        if (
            ! empty($sysOptions['nocache'])
            || empty(static::$caches[$cacheKey])
        ) {
            if (
                isset($sysOptions['type'])
                && $sysOptions['type'] == 'class'
                && isset($sysOptions['classname'])
            ) {
                $st = $this->getStorage()->query($sql, PDO::FETCH_CLASS, $sysOptions['classname'], [$this]);
            } else {
                $st = $this->getStorage()->query($sql, PDO::FETCH_ASSOC);
            }

            $rows = $st ? $st->fetchAll() : [];

            if (empty($sysOptions['nocache'])) {
                static::$caches[$cacheKey] = $rows;
            }
        } else {
            $rows = static::$caches[$cacheKey];
        }//end if else

        return $rows;
    }

    /* экранирование */
    public function quote($s)
    {
        return $s === null ? '' : $this->getStorage()->quote($s);
    }

    public function prepare($sql)
    {
        $st = $this->getStorage()->prepare($sql);
        return $st;
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
    public function getLastID($name = null)
    {
        return $this->getStorage()->lastInsertId($name);
    }

    /**
     * возвращает информацию о последней ошибке
     */
    public function errorInfo()
    {
        return $this->getStorage()->errorInfo();
    }

}
