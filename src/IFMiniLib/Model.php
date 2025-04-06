<?php
namespace IFMiniLib;

/**
 * Класс для работы с объектами в базе. Нужен для описания типов объектов и облегечения
 * работы с рутинными операциями: save, delete
 *
 * Если у поля проставить тип UUID то нужно задать uidMin и uidMax
 * между которыми будет создаваться случайное значение
 * Если нужно чтобы UUID/GUID - были различны на разных серверах, то нужно
 * в конфиге нужно велючить сдвиг App::I()->needUidOffset == 'yes' и
 * задать величину сдвига, например 2:  App::I()->uidOffset=2
 * это будет означать что все случайные числа будут заканчиваться на 2
 */
class Model implements JsonSerializable
{
    /**
     * @var string $lastError последнее сообщение об ошибке
     */
    public static $lastError='';

    /**
     * @var boolean если запрос был с частью полей - то сохранять это нельзя
     **/
    protected $readOnly = false;

    /**
     * @var boolean $isNewRecord признак новой записи
     * (используется для выбра способа сохранения записи Insert или Update)
     */
    public $isNewRecord     = false;

    /**
     * @var array $arFields - массив название и значений полей
     */
    protected $arFields     = array();

    public function __destruct()
    {
        $this->arFields = null;
        return true;
    }

    /**
     * @param array $arFields поля для инициализации объекта
     */
    public function __construct($arFields = array())
    {
        /* если начальных данных нет, то создадим массив с пустыми данными */
        if (sizeof($this->arFields) == 0) {
            $this->arFields = static::getClearFields();
        }

        if (is_array($arFields) && sizeof($arFields) > 0) {
            foreach ($arFields as $key => $value) {
                if (key_exists($key, $this->arFields)) {
                    $this->arFields[$key] = $value;
                }
            }//end foreach*/
        }
    }

    /**
     * Форматирует строку название полей и значений в строку (используется в генераторе кода)
     * @return string
     */
    public function __getFormatString()
    {
        $str = "array(\n";
        foreach ($this->arFields as $key => $value) {
            $str .= "\t'$key' => " . DB::I()->quote($value) . ",\n";
        }//end foreach
        $str .= ")\n";

        return $str;
    }//end function

    /**
     * Возвращает массив созначениями полей
     * @return array
     */
    public function __getFields()
    {
        return $this->arFields;
    }

    /**
     * Эта функция должна быть перегружена в дочерних классах
     */
    public static function getTable()
    {
        throw new \Exception('not found table name in model ' . get_called_class());
    }

    /**
     * Возвращает массив с правилами валидации вида:
     *     array(
     *        'название поля' => array(array(правило валидации),array(правила валидации)),
     *        'name' => array(
     *            'isreq' => array('error'=>'поле оьязательное'),
     *            'islength' => array('min'=>10,'max'=>'30','errorMin' => 'Поле слишком короткое', 'errorMax' => 'Поле слишком длинное')
     *        ),
     *        'email' => array(
     *            'isreq' => array('error'=>'поле оьязательное'),
     *            'islength' => array('min'=>4,'max'=>'250','error' => 'Email может быть от 4 до 250 символов')
     *            'isemail' => array('error' => 'Некорректный формат email')
     *        )
     *     )
     */
    public static function getRules()
    {
        return array();
    }

    /**
     * @return string название базы данных
     */
    public static function getDatabase()
    {
        return '';
    }

    public static function getRelations()
    {
        return array();
    }

    /* сделана на будущее */
    public static function getTypes()
    {
        return array();
    }

    /* @return возвращает название первичного ключа */
    public static function getIdName()
    {
        return 'id';
    }

    /* значение ключа по умолчанию */
    public static function getIdDefault()
    {
        return 'AUTOINC'; // UUID, VALUE
    }

    /**
     * Возвращает TRUE если запрашиваемое поле с именем $name есть в модели/таблице
     * @param string $name
     * @return boolean
     **/
    public static function is($name)
    {
        $result = false;
        $arFields = static::getClearFields();
        if (key_exists($name, $arFields)) {
            $result = true;
        } else {
            $relations = static::getRelations();
            if (isset($relations[$name])) {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * Возвращает значение поля или объект или массив
     * @param string $name название поля или связи
     * @return mixed
     */
    public function __get($name)
    {
        if (! $name) {
            return null;
        }

        if (key_exists($name, $this->arFields)) {
            return $this->arFields[$name];
        }

        $arTrace = debug_backtrace();
        $e = new ErrorException('Unknown fields ' . get_class($this) . '::$' . $name, E_USER_ERROR, 1, $arTrace[0]['file'], $arTrace[0]['line']);
        throw $e;
    }

    /**
     * Устанавливает значение поля или объект или массив
     * @param string $name название поля или связи
     * @param mixed $val значение
     */
    public function __set($name, $val)
    {
        if (sizeof($this->arFields) == 0) {
            $this->arFields = $this->getClearFields();
        }

        if (key_exists($name, $this->arFields)) {
            $this->arFields[$name] = $val;
        } else {
            $arBackTrace = debug_backtrace();
            $arLast = $arBackTrace[0];
            throw new ErrorException('Cannot set value to unknown fields ' . get_class($this) . '::$' . $name, E_USER_ERROR, 1, $arLast['file'], $arLast['line']);
        }
    }

    public function __isset($name)
    {
        return isset($this->arFields[$name]);
    }


    public function __unset($name)
    {
        if (isset($this->arFields[$name])) {
            $this->arFields[$name] = null;
        }
    }

    /**
     * возвращает список OPTION для вставки в тег SELECT
     * @param string $sRelname название свзяи значения, которой нужно вывести
     * @param string $selected выбранное значение
     */
    public function getOptionsList($sRelname, $selected = '', $where = null, $sViewField = 'sName', $glue = ' / ')
    {
        $arRelations = $this->$sRelname(false); // $this->getRelations();
        $arResult = array();

        if ($arRelations) {

            foreach ($arRelations as $idx => $mRelation) {
                if (is_array($mRelation)) {
                    $arResult[] = '<option value="' . $idx . '" ' . ($idx == $selected ? 'selected="selected"' : '') . '>' . $mRelation[0] . '</option>';
                } else {
                    $sKey = $mRelation::getIdName();
                    $arResult[] = '<option value="' . $mRelation->{$sKey} . '" ' . ($mRelation->{$sKey} == $selected ? 'selected="selected"' : '') . '>' . $mRelation->{$sViewField} . '</option>';
                }
            }/* end foreach */

        }

        return implode("\n", $arResult);
    }
    //end function

    /**
     * устанавливает значения текущему объекту
     * @param array $params - массив: ключ->значение
     * @param boolean $isClearEmpty - очищать переменные, если передано пустое значение
     * @param boolean $isSetNull - устанавливать ли в нуль значение или игнорировать его
     **/
    public function attributes($params, $isClearEmpty = true, $isSetNull = false )
    {
        $fields = $this->getClearFields();

        foreach ($fields as $key => $val) {
            if (isset($params[$key])
                && ($params[$key] != ''
                || $isClearEmpty)
            ) {
                if ($isSetNull
                    && $params[$key] == ''
                ) {
                    $this->arFields[$key] = null;
                } else {
                    $this->arFields[$key] = $params[$key];
                }
            }
        }//end foreach
        return $this;
    }

    /**
     * Удалить текущий объект из БД
     * Если определена функции beforeDelete, то она будет вызвана перед удалением, а afterDelete - после удаления записи в БД
     */
    public function delete($arParams = array())
    {
        $bTransaction = false;

        /*
         * Проверяем параметры для передачи в методы before_delete и after_delete
         */
        if (! isset($arParams['before'])) {
            $arParams['before'] = false;
        }

        if (! isset($arParams['after'])) {
            $arParams['after'] = false;
        }

        /*
         * Выставляем базу и таблицу для запроса
         */
        if (! empty($arParams['database'])) {
            $sTable = '`' . $arParams['database'] . '`.`' . static::getTable() . '`';
        } elseif (static::getDatabase() > '') {
            $sTable = '`' . static::getDatabase() . '`.`' . static::getTable() . '`';
        } else {
            $sTable = '`'.static::getTable().'`';
        }

        /*
         * Проверяем необходимость использования транзакции
         */
        if (isset($arParams['transaction'] ) && $arParams['transaction'] == true) {
            $bTransaction = true;
            DB::I()->begin();
        }

        /* если есть функция, которую нужно вызвать до удаления - вызываем ее */
        if (! $this->beforeDelete($arParams['before'])) {
            if ($bTransaction) {
                DB::I()->rollback();
            }
            return false;
        }

        /* удаляем запись */
        $idKey = static::getIdName();
        $sql     = "DELETE FROM " . $sTable . " WHERE ".$idKey." = '" . $this->arFields[$idKey] . "' LIMIT 1";
        $result     = DB::I()->execute($sql);

        /* очищаем кеш связанный с этим классом/таблицей */
        $className = get_called_class();
        DB::clearInnerCache($className);

        if (! $result) {
                $err = DB::I()->errorInfo();
            if ($err[0] != '00000') {
                echo "<div class='error'>" . $sql;
                echo ($err);
                echo '</div>';
            }//end if
        }

        /* если удаление прошло успешно */
        if ($result > 0) {
            /* если определен метод, который нужно вызывать после удаления - вызываем его */
            if ($this->afterDelete($arParams['after']) === false) {
                /* если что-то пошло не так и у нас запущена транзакция, то откатываем удаление */
                if ($bTransaction) {
                    DB::I()->rollback();
                }
                $result = false;
            } else {
                /* если запущена транзакция, фиксируем ее */
                if ($bTransaction) {
                    DB::I()->commit();
                }
                $this->onChange(['action' => 'delete']);
            }
        } elseif ($result === false && $bTransaction) {
            /* если произошла ошибка и определена транзакция - откатываемся */
            DB::I()->rollback();
        }

        return $result;
    } //end function

    /* тригеры событий, которые можно вызывать перед и после удаления и сохранения */
    protected function beforeDelete($mParams=array())
    {
        return true;
    }

    protected function afterDelete($mParams=array() )
    {
        return true;
    }

    protected function beforeSave($mParams=array() )
    {
        return true;
    }

    protected function afterSave($mParams=array() )
    {
        return true;
    }

    protected function onChange($mParams=array())
    {
        return true;
    }

    /**
     * Сохраняем объект в БД. Если объект новый и поле не автоинкрементное, то перед вызовом этого метода
     * нужно установить флаг новой записи в TRUE: $obj->isNewRecord = true;
     * @return mixed больше 0, если сохраненно успешно, false если ошибка (ошибка сохраняется в DB::$lastError)
     */
    public function save($arParams = array() )
    {

        if ($this->readOnly) {
            return false;
        }

        $values          = array();
        $names           = array();
        $types           = static::getTypes(); /* for future */
        $values_upd      = array();
        $def             = static::getDefault();
        $idname          = static::getIdName();
        $bTransaction    = false;
        $className = get_called_class();

        if ($idname && !$this->__get($idname)) {
            $this->isNewRecord = true;
        }

        /*
         * Выставляем базу и таблицу для запроса
         */
        if (!empty($arParams['sDatabase'])) {
            $sTable = '`'.$arParams['sDatabase'].'`.`'.static::getTable().'`';
        }
        elseif (static::getDatabase() > '') {
            $sTable = '`'.static::getDatabase().'`.`'.static::getTable().'`';
        }
        else
        {
            $sTable = '`'.static::getTable().'`';
        }


        /*
         * Проверяем параметры для передачи в методы before_save и after_save
         */
        if (!isset($arParams['before'] )) {
            $arParams['before'] = false;
        }
        if (!isset($arParams['after'] )) {
            $arParams['after'] = false;
        }

        /*
         * Проверяем необходимость использования транзакции
         */
        if (isset($arParams['transaction'] ) && $arParams['transaction'] == true) {
            $bTransaction = true;
            DB::I()->begin();
        }

        if (! $this->beforeSave($arParams['before'])) {
            if ($bTransaction) {
                DB::I()->rollback();
            }
            return false;
        }

        foreach ($this->arFields as $key => $value) {
            $names[$key] = $key;

            if ($value === null && $key != $idname) {
                if (isset($def[$key] )) {
                    if (in_array($def[$key], array( 'CURRENT_TIMESTAMP', 'now()', 'NOW()', 'NULL' ))) {
                        $values[$key]         = $def[$key];
                        $values_upd[$key]     = "`" . $key . "`=" . $def[$key];
                    } elseif ($def[$key] == 'UUID') {
                        do
                        {
                            $uid = mt_rand($this->uidMin, $this->uidMax);
                            if (App::I()->needUidOffset == 'yes' && App::I()->uidOffset > 0) {
                                $uid = substr(''.$uid, -1).App::I()->uidOffset;
                            }
                            $isExists = DB::getCountAll(
                                array(
                                    'sDatabase' => $this->getDatabase(),
                                    'sModel' => get_called_class(),
                                    'arFilter' => array(
                                        $key => array('=' => $uid)
                                    )
                                )
                            );
                        }
                        while($isExists);
                        $values[$key]         = "'" . $uid . "'"; //$value;
                        $values_upd[$key]     = "`" . $key . "`=" . $values[$key];
                        $this->__set($key, $uid);
                    } else {
                        $values[$key]         = DB::I()->quote($def[$key]); //$value;
                        $values_upd[$key]     = "`" . $key . "`=" . $values[$key];
                    }
                    //echo $key.'-';
                } else {
                    //$values[$key] = 'NULL';
                    unset($names[$key]);
                }
            } else {
                if (is_array($value)) {
                    $value = base64_encode(json_encode($value));
                }

                if (!empty($arParams['securesecret']) && !empty($arParams['securefields']) && in_array($key, $arParams['securefields'])) {
                    $value = "AES_ENCRYPT(".DB::I()->quote($value).",UNHEX('".$arParams['securesecret']."'))";
                    $values[$key] = $value;
                } else {
                    $values[$key] = DB::I()->quote($value);
                }

                if ($key != $idname || ($def != 'UUID' && $def != 'AUTOINC' )) {
                    $values_upd[$key] = "`" . $key . "`=" . $values[$key];
                }// end if
            }
        }//end foreach

        if ($this->isNewRecord) {

            if (empty($values[$idname] ) || $values[$idname] == "''") {
                switch ($this->getIdDefault()) {
                case 'UUID':
                    do
                    {
                        $uid = mt_rand($this->uidMin, $this->uidMax);
                        if (App::I()->needUidOffset == 'yes' && App::I()->uidOffset > 0) {
                            $uid = substr(''.$uid, -1).App::I()->uidOffset;
                        }
                        $isExists = DB::getCountAll(
                            array(
                                'sDatabase' => $this->getDatabase(),
                                'sModel' => get_called_class(),
                                'arFilter' => array(
                                    $idname => array('=' => $uid)
                                )
                            )
                        );
                    }
                    while($isExists);

                    $values[$idname] = "'" . $uid . "'";
                    $this->$idname = $uid;
                    break;

                case 'GUID':
                    do {
                        $uid = str_replace('.', '', uniqid('', true));
                        if (App::I()->needUidOffset == 'yes' && App::I()->uidOffset > 0) {
                            $uid = substr(''.$uid, -1).App::I()->uidOffset;
                        }
                        $isExists = DB::getCountAll(
                            array(
                                'sDatabase' => $this->getDatabase(),
                                'sModel' => get_called_class(),
                                'arFilter' => array(
                                    $idname => array('=' => $uid)
                                )
                            )
                        );
                    } while($isExists);

                    $values[$idname] = "'" . $uid . "'";
                    $this->$idname = $uid;
                    break;
                case 'AUTOINC':
                    $values[$idname] = 'NULL';
                    break;
                default:
                    break;
                }//end switch

                if ($idname > '') {
                    $names[$idname] = $idname;
                }
            }
            $sql = "INSERT INTO " . $sTable . ' (`' . implode('`,`', $names) . "`) VALUES(" . implode(',', $values) . ")";
        }
        else
        {

            $sql = "UPDATE " . $sTable . " SET " .
                implode(',', $values_upd) .
                " WHERE $idname = '" . $this->$idname . "' LIMIT 1";
        }//end if else

        //App::I()->log($sql, 'debug');

        $result = DB::I()->execute($sql);
        DB::clearInnerCache($className);

        if($result !== false) {

            if ($result === 0) {
                $err = DB::I()->errorInfo();
                if ($err[0] != '00000') {
                    echo "<div class='error'>" . $sql;
                    var_dump($err);
                    echo '</div>';
                }
            }

            if (static::getIdDefault() == 'AUTOINC' && $this->isNewRecord) {
                $this->__set($idname, DB::I()->getLastID());
            }

            $result = true;
        } else {
            $result = false;
        }

        if (! $result) {
            //@todo вставить добавление ошибок возникших при сохранении
        }

        if ($result) {
            if (false === $this->afterSave($arParams['after'])) {
                if ($bTransaction) {
                    DB::I()->rollback();
                }
                return false;
            } else {
                if ($bTransaction) {
                    DB::I()->commit();
                }

                // if ($this->isNewRecord) {
                //     $sql = str_replace('VALUES(NULL', "VALUES('".$this->__get($idname)."'", $sql);
                // }
                $this->onChange(/*[
                    'sql' => $sql
                ]*/);
            }
            $this->isNewRecord = false;
        } else {
            if ($bTransaction) {
                DB::I()->rollback();
            }
        }
        return $result;
    }


    public function saveField($key, $arParams=array())
    {
        $types           = static::getTypes(); // for future
        $values_upd      = '';
        $def             = static::getDefault();
        $idname          = static::getIdName();
        $bTransaction    = false;
        $className = get_called_class();

        if (! static::is($key)) {
            return false;
        }

        $value = $this->__get($key);

        // Выставляем базу и таблицу для запроса
        if (!empty($arParams['sDatabase'])) {
            $sTable = '`'.$arParams['sDatabase'].'`.`'.static::getTable().'`';
        } elseif (static::getDatabase() > '') {
            $sTable = '`'.static::getDatabase().'`.`'.static::getTable().'`';
        } else {
            $sTable = '`'.static::getTable().'`';
        }

        if ($value === null && $key != $idname) {
            if (isset($def[$key])) {
                if (in_array($def[$key], [ 'CURRENT_TIMESTAMP', 'now()', 'NOW()', 'NULL' ])) {
                    $values_upd     = "`" . $key . "`=" . $def[$key];
                } else {
                    $value         = DB::I()->quote($def[$key]); //$value;
                    $values_upd     = "`" . $key . "`=" . $value;
                }
            }
        } else {
            if (is_array($value)) {
                $value = base64_encode(json_encode($value));
            }

            if (!empty($arParams['securesecret']) && !empty($arParams['securefields']) && in_array($key, $arParams['securefields'])) {
                $value = "AES_ENCRYPT(".DB::I()->quote($value).",UNHEX('".$arParams['securesecret']."'))";
            } else {
                $value = DB::I()->quote($value);
            }

            if ($key != $idname || ($def != 'UUID' && $def != 'AUTOINC')) {
                $values_upd = "`" . $key . "`=" . $value;
            }// end if
        }

        $result = 0;
        if ($values_upd > '') {
            if ($key != 'servertime' && static::is('servertime') && static::is('serverid')) {
                $values_upd .= ", `servertime`='" . (string)microtime(true) . "'";
                if (empty($arPairs['serverid'])) {
                    $values_upd .= ", `serverid`='" . App::I()->serverid . "'";
                }
            }

            $sql = "UPDATE " . $sTable . " SET " . $values_upd .
                    " WHERE $idname = '" . $this->$idname . "' ";

            // выполняем запрос на увеличение
            $result = DB::I()->execute($sql);
        }
        // очищаем кеш
        DB::clearInnerCache($className);

        // делаем запрос актуальных данных
        $tmp = DB::getOne(
            [
                'sModel' => $className,
                'arFilter' => [
                    $idname => ['=' => $this->$idname]
                ]
            ]
        );
        if ($tmp) {
            $this->attributes($tmp->__getFields());
        }
        $this->onChange(['fields' => [$key]]);
        return $result;
    }

    public function saveFields($arFields, $arParams=[])
    {
        $types           = static::getTypes(); // for future
        $values_upd      = '';
        $def             = static::getDefault();
        $idname          = static::getIdName();
        $bTransaction    = false;
        $className = get_called_class();

        if (! is_array($arFields) || sizeof($arFields) == 0) {
            return false;
        }

        // Выставляем базу и таблицу для запроса
        if (!empty($arParams['sDatabase'])) {
            $sTable = '`'.$arParams['sDatabase'].'`.`'.static::getTable().'`';
        } elseif (static::getDatabase() > '') {
            $sTable = '`'.static::getDatabase().'`.`'.static::getTable().'`';
        } else {
            $sTable = '`'.static::getTable().'`';
        }

        if (
            ! in_array('servertime', $arFields)
            && static::is('servertime')
            && static::is('serverid')
        ) {
            $arFields[] = 'servertime';
            $this->__set('servertime', (string)microtime(true));
            $arFields[] = 'serverid';
            $this->__set('serverid', App::I()->serverid);
        }

        $values_upd = [];
        foreach($arFields as $key) {
            if (! static::is($key)) {
                continue;
            }
            $value = $this->__get($key);

            if ($value === null && $key != $idname) {
                if (isset($def[$key])) {
                    if (in_array($def[$key], [ 'CURRENT_TIMESTAMP', 'now()', 'NOW()', 'NULL' ])) {
                        $values_upd[]     = "`" . $key . "`=" . $def[$key];
                    } else {
                        $values_upd[]     = "`" . $key . "`=" . $value;
                    }
                }
            } else {
                if (is_array($value)) {
                    $value = base64_encode(json_encode($value));
                }

                if (!empty($arParams['securesecret']) && !empty($arParams['securefields']) && in_array($key, $arParams['securefields'])) {
                    $value = "AES_ENCRYPT(".DB::I()->quote($value).",UNHEX('".$arParams['securesecret']."'))";
                } else {
                    $value = DB::I()->quote($value);
                }

                if ($key != $idname || ($def != 'UUID' && $def != 'AUTOINC')) {
                    $values_upd[] = "`" . $key . "`=" . $value;
                }// end if
            }
        }

        $result = 0;
        if (sizeof($values_upd) > 0) {
            $sql = "UPDATE " . $sTable . " SET " . implode(', ', $values_upd) .
                    " WHERE $idname = '" . $this->$idname . "' ";

            // выполняем запрос на увеличение
            $result = DB::I()->execute($sql);
        } else {
            return false;
        }

        // очищаем кеш
        DB::clearInnerCache($className);

        // делаем запрос актуальных данных
        $tmp = DB::getOne(
            [
                'sModel' => $className,
                'arFilter' => [
                    $idname => ['=' => $this->$idname]
                ]
            ]
        );
        if ($tmp) {
            $this->attributes($tmp->__getFields());
        }
        $this->onChange(['fields' => $arFields]);

        return $result;
    }

    // @return Array
    public static function getClearFields()
    {
        die( 'bred');
    }

    public static function getDefault()
    {
        return array();
    }

    /**
     * увеличить значение поля (использовать, когда нужно увеличить какой-то счетчик и есть риск множественых одновременных изменений
     * @param string $field название поля, которое нужно изменять
     * @param integer $step шаг, на сколько увеличить
     * @param string условия для выбора записей подлежащих изменению
     * @return integer возвращает актуальное значение счетчика
     **/
    public function increment($field, $step=1, $where='')
    {
        $className = get_called_class();
        if (static::getDatabase() > '') {
            $sTable = '`'.static::getDatabase().'`.`'.static::getTable().'`';
        }
        else
        {
            $sTable = '`'.static::getTable().'`';
        }
        $idname = static::getIdName();

        if (!static::is($field)) {
            return false;
        }

        if ($where > '' ) { $where = ' AND '.$where; }

        $addupdate = '';
        if (static::is('servertime') && static::is('serverid')) {
            $this->serverid = App::I()->serverid;
            $this->servertime = (string)microtime(true);
            $addupdate = ", serverid='".App::I()->serverid."', servertime='".$this->servertime."' ";
        }
        $sql = "UPDATE " . $sTable . " SET `".$field."`=`".$field."` + '".$step."'" . $addupdate .
                " WHERE $idname = '" . $this->$idname . "'".$where;

        /* выполняем запрос на увеличение */
        $result = DB::I()->execute($sql);

        /* очищаем кеш */
        DB::clearInnerCache($className);

        /* делаем запрос актуальных данных */
        $tmp = DB::getOne(array(
                'sModel' => $className,
                'arFilter' => array(
                    $idname => array('=' => $this->$idname)
                )
            )
        );

        $this->$field = $tmp->$field;
        $this->onChange(['fields' => [$field]]);
        return $result;
    }

    /* уменьшаем поле на определенный шаг */
    public function decrement($field, $step = 1, $where = '')
    {
        $className = get_called_class();
        if (static::getDatabase() > '') {
            $sTable = '`'.static::getDatabase().'`.`'.static::getTable().'`';
        }
        else
        {
            $sTable = '`'.static::getTable().'`';
        }
        $idname = static::getIdName();

        if (!static::is($field)) {
            return false;
        }

        if ($where > '' ) { $where = ' AND '.$where; }

        $addupdate = '';
        if (static::is('servertime') && static::is('serverid')) {
            $this->serverid = App::I()->serverid;
            $this->servertime = (string)microtime(true);
            $addupdate = ", serverid='".App::I()->serverid."', servertime='".$this->servertime."' ";
        }

        $sql = "UPDATE " . $sTable . " SET `".$field."`=`".$field."` - '".$step."'" . $addupdate .
                " WHERE $idname = '" . $this->$idname . "'".$where;

        $result = DB::I()->execute($sql);
        DB::clearInnerCache($className);


        $tmp = DB::getOne(array(
                'sModel' => $className,
                'arFilter' => array(
                    $idname => array('=' => $this->$idname)
                )
            )
        );
        $this->$field = $tmp->$field;
        $this->onChange(['fields' => [$field]]);
        return $result;
    }

    /*     * ************ Utility ******************* */

    /**
     * Возвращаем поле с датой в нужном формате
     * @param string $name название поля
     * @param string $format формат данных
     * @return string отформатированная дата
     */
    public function formatDate($name, $format = 'd.m.Y')
    {
        $str = $this->__get($name);
        $str = strtotime($str);

        if ($str > 0) {
            return date($format, $str);
        } else {
            return '';
        }
    }

    /**
     * @return string возвращает строку где переврод строки заменен на <br />
     */
    public function getTextToHtml($name)
    {
        if (isset($this->arFields[$name])) {
            return str_replace("\n", '<br />', $this->arFields[$name]);
        }
        return '';
    }

    public function validate()
    {
        return Validator::isValidateModel($this);
    }

    /** error functions **/

    /**
     * Добавляем ошибку
     * @param string $sError      текст ошибки
     * @param string $sField      название поля в котором обнаружена ошибка
     * @return Model
     */
    public function addError($sError, $sField='_')
    {
        DB::addError($sError, get_class($this), $sField);
        return $this;
    }

    /**
     * Проверяем, есть ли ошибка в каком-то поле или в целом в моделе
     * @param string $sField      название поля которое проверяется (нужено указать "_" - для проверки общих ошибок)
     * @return boolean
     */
    public function isError($sField='_')
    {
        return DB::isError(get_class($this), $sField);
    }

    /**
     * Проверяем, есть ли ошибка в каком-то поле или в целом в моделе
     * @param string $sField      название поля которое проверяется (нужено указать "_" - для проверки общих ошибок)
     * @return boolean
     */
    public function isErrors()
    {
        return DB::isErrors(get_class($this));
    }

    /**
     * Функция возвращает массив из текстов ошибок для указанного поля
     *
     * @return array
     */
    public function getError($sField = '_', $isClear = true)
    {
        return DB::getError(get_class($this), $sField, $isClear);
    }

    /**
     * Функция возвращает массив из текстов ошибок для указанного поля
     *
     * @return array
     */
    public function getErrors($isClear = false)
    {
        return DB::getErrors(get_class($this), $isClear);
    }

    /**
     * @param $arParams
     * @param array $arSysOptions
     * @return static
     */
    public static function getRow($arParams, $arSysOptions=array())
    {
        $arParams['sModel'] =  get_called_class();
        return DB::I()->getOne($arParams, $arSysOptions);
    }


    /**
     * @param $arParams
     * @param array $arSysOptions
     * @return static[]
     */
    public static function getRows($arParams, $arSysOptions=array())
    {
        $arParams['sModel'] =  get_called_class();
        return DB::I()->getAll($arParams, $arSysOptions);
    }

    public static function getCount($arParams, $arSysOptions=array())
    {
        $arParams['sModel'] =  get_called_class();
        return DB::I()->getCountAll($arParams, $arSysOptions);
    }

    public static function getMax($field,$arParams, $arSysOptions=array())
    {
        $arParams['sModel'] =  get_called_class();
        $arParams['fields'] = '`'.$field.'` as cnt';
        $arParams['arSort'] = array($field => 'desc');
        $arParams['iPageSize'] = 1;
        $arParams['iPage'] = 1;
        $sSql = DB::generateSelectSQL($arParams);
        $st = DB::I()->query($sSql, ['nocache' => true]);
        $iResult = false;
        if ($st) {
            $arTmp = $st->fetch(\PDO::FETCH_ASSOC);
            if (isset($arTmp['cnt'])) {
                $iResult=$arTmp['cnt'];
            }
        }

        return $iResult;
    }

    public static function getMin($field, $arParams, $arSysOptions=array())
    {
        $arParams['sModel'] =  get_called_class();
        $arParams['fields'] = 'min('. $field .') as cnt';
        $sSql = DB::generateSelectSQL($arParams);
        $st = DB::I()->query($sSql, ['nocache' => true]);
        $iResult = false;
        if ($st) {
            $arTmp = $st->fetch(\PDO::FETCH_ASSOC);
            if (isset($arTmp['cnt'])) {
                $iResult=$arTmp['cnt'];
            }

        }

        return $iResult;
    }

    public static function getById($id, $arParams=array())
    {
        $idName = static::getIdName();
        $sModel =  get_called_class();
        $arSpecs = array();
        if (isset($arParams['nocache']) && $arParams['nocache']) {
            $arSpecs['nocache'] = true;
        }
        if (! empty($arParams['securesecret']) && ! empty($arParams['securefields'])) {
            return DB::getOne(
                array(
                    'sModel' => $sModel,
                    'arFilter' => array(
                        $idName => array('=' => intval($id) )
                    ),
                    'iPageSize' => 1,
                    'securesecret' => $arParams['securesecret'],
                    'securefields' => $arParams['securefields']
                ), $arSpecs
            );
        }
        return DB::getOne(
            array(
                'sModel' => $sModel,
                'arFilter' => array(
                    $idName => array('=' => intval($id) )
                ),
                'iPageSize' => 1
            ), $arSpecs
        );
    }

    public function setMaxValue($fieldname, $arParams, $step=1)
    {
        if (static::is($fieldname)) {
            $table = static::getTable();
            $idname = static::getIdName();
            $arParams['fields'] =  '`'.$table.'`.`'.$fieldname.'`';
            $arParams['sModel'] =  get_called_class();
            $arParams['arSort'] = [$fieldname => 'desc'];

            $sSql = DB::generateSelectSQL($arParams);

            $addupdate = '';
            if (static::is('servertime') && static::is('serverid')) {
                $this->serverid = App::I()->serverid;
                $this->servertime = (string)microtime(true);
                $addupdate = ", t0.serverid='".App::I()->serverid."', t0.servertime='".$this->servertime."' ";
            }

            $sql = "UPDATE `" . static::getTable() . "` as t0, (" . $sSql . " LIMIT 1 FOR UPDATE) as t1 SET t0.`".$fieldname."` = t1.`".$fieldname."`+".intval($step). $addupdate . " WHERE `".$idname."` = '".$this->$idname."' ";

            try {
                $result = DB::I()->execute($sql);
            } catch (\PDOException $e) {
                $result = false;
                $state = $e->getMessage();
                if (strpos($state, ' Deadlock found when trying to get lock; try restarting transaction') !== false) {
                    $result = DB::I()->execute($sql);
                } else {
                    throw $e;
                }
            }
            DB::clearInnerCache(get_called_class());

            if ($result !== false) {
                if ($result === 0) {
                    $err = DB::I()->errorInfo();
                    if ($err[0] != '00000') {
                        echo "<div class='error'>" . $sql;
                        var_dump($err);
                        echo '</div>';
                    }
                }

                $result = true;

                $tmp = static::getRow(
                    [
                        'fields' => '`'.$table.'`.`'.$fieldname.'` ',
                        'arFilter' => [
                            $idname => ['=' => $this->$idname]
                        ]
                    ],['nocache' => true]
                );
                $this->$fieldname = $tmp->$fieldname;
                $this->onChange(['fields' => [$fieldname]]);

            } else {
                $result = false;
            }
            return $result;
        }
        return false;
    }

    public function __toString()
    {
        return json_encode($this->__getFields(), JSON_UNESCAPED_UNICODE);
    }

    public function jsonSerialize() {
        return $this->__getFields();
    }
}
//end class
