<?php
namespace IFMiniLib;

use IFMiniLib\DB;
use App;

/**
 * Класс для работы с объектами в базе. Нужен для описания типов объектов и облегечения
 * работы с рутинными операциями: save, delete
 *
 * Если у поля проставить тип UUID то нужно задать uidMin и uidMax
 * между которыми будет создаваться случайное значение
 * Если нужно чтобы UUID/GUID - были различны на разных серверах, то нужно
 * в конфиге нужно велючить сдвиг App::one()->needUidOffset == 'yes' и
 * задать величину сдвига, например 2:  App::one()->uidOffset=2
 * это будет означать что все случайные числа будут заканчиваться на 2
 */
class Model implements JsonSerializable
{
    public const VALUE_AUTOINC = 'AUTOINC';
    public const VALUE_UUID = 'UUID';
    public const VALUE_GUID = 'GUID';
    public const VALUE_VALUE = 'VALUE';
    public const VALUE_NONE = 'NONE';

    public $uidMin = 100000000000;
    public $uidMax = 999999999999;

    /**
     * @var string $lastError последнее сообщение об ошибке
     */
    public static $lastError = '';

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
     * @var array $fields - массив название и значений полей
     */
    protected $fields     = [];



    public function __destruct()
    {
        $this->fields = null;
        return true;
    }

    /**
     * @param array $fields поля для инициализации объекта
     */
    public function __construct($fields = [])
    {
        /* если начальных данных нет, то создадим массив с пустыми данными */
        if (sizeof($this->fields) == 0) {
            $this->fields = static::getClearFields();
        }

        if (is_array($fields) && sizeof($fields) > 0) {
            foreach ($fields as $key => $value) {
                if (key_exists($key, $this->fields)) {
                    $this->fields[$key] = $value;
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
        foreach ($this->fields as $key => $value) {
            $str .= "\t'$key' => " . DB::one()->quote($value) . ",\n";
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
        return $this->fields;
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
        return [];
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
        return [];
    }

    /* сделана на будущее */
    public static function getTypes()
    {
        return [];
    }

    /* @return возвращает название первичного ключа */
    public static function getIdName()
    {
        return 'id';
    }

    /* значение ключа по умолчанию */
    public static function getIdDefault()
    {
        return self::VALUE_AUTOINC;
    }

    /**
     * Возвращает TRUE если запрашиваемое поле с именем $name есть в модели/таблице
     * @param string $name
     * @return boolean
     **/
    public static function is($name)
    {
        $result = false;
        $fields = static::getClearFields();
        if (key_exists($name, $fields)) {
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

        if (key_exists($name, $this->fields)) {
            return $this->fields[$name];
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
        if (sizeof($this->fields) == 0) {
            $this->fields = $this->getClearFields();
        }

        if (key_exists($name, $this->fields)) {
            $this->fields[$name] = $val;
        } else {
            $arBackTrace = debug_backtrace();
            $arLast = $arBackTrace[0];
            throw new ErrorException('Cannot set value to unknown fields ' . get_class($this) . '::$' . $name, E_USER_ERROR, 1, $arLast['file'], $arLast['line']);
        }
    }

    public function __isset($name)
    {
        return isset($this->fields[$name]);
    }

    public function __unset($name)
    {
        if (isset($this->fields[$name])) {
            $this->fields[$name] = null;
        }
    }

    /**
     * возвращает список OPTION для вставки в тег SELECT
     * @param string $sRelname название свзяи значения, которой нужно вывести
     * @param string $selected выбранное значение
     */
    public function getOptionsList($sRelname, $selected = '', $where = null, $sViewField = 'sName', $glue = ' / ')
    {
        $relations = $this->$sRelname(false); // $this->getRelations();
        $result = [];

        if ($relations) {
            foreach ($relations as $idx => $mRelation) {
                if (is_array($mRelation)) {
                    $result[] = '<option value="' . $idx . '" ' . ($idx == $selected ? 'selected="selected"' : '') . '>' . $mRelation[0] . '</option>';
                } else {
                    $sKey = $mRelation::getIdName();
                    $result[] = '<option value="' . $mRelation->{$sKey} . '" ' . ($mRelation->{$sKey} == $selected ? 'selected="selected"' : '') . '>' . $mRelation->{$sViewField} . '</option>';
                }
            }/* end foreach */
        }

        return implode("\n", $result);
    }
    //end function

    /**
     * устанавливает значения текущему объекту
     * @param array $params - массив: ключ->значение
     * @param boolean $isClearEmpty - очищать переменные, если передано пустое значение
     * @param boolean $isSetNull - устанавливать ли в нуль значение или игнорировать его
     **/
    public function attributes($params, $isClearEmpty = true, $isSetNull = false)
    {
        $fields = $this->getClearFields();

        foreach ($fields as $key => $val) {
            if (
                isset($params[$key])
                && ($params[$key] != ''
                || $isClearEmpty)
            ) {
                if (
                    $isSetNull
                    && $params[$key] == ''
                ) {
                    $this->fields[$key] = null;
                } else {
                    $this->fields[$key] = $params[$key];
                }
            }
        }//end foreach
        return $this;
    }

    /**
     * Удалить текущий объект из БД
     * Если определена функции beforeDelete, то она будет вызвана перед удалением, а afterDelete - после удаления записи в БД
     */
    public function delete($params = [])
    {
        $bTransaction = false;

        /*
         * Проверяем параметры для передачи в методы before_delete и after_delete
         */
        if (! isset($params['before'])) {
            $params['before'] = false;
        }

        if (! isset($params['after'])) {
            $params['after'] = false;
        }

        /*
         * Выставляем базу и таблицу для запроса
         */
        if (! empty($params['database'])) {
            $table = '`' . $params['database'] . '`.`' . static::getTable() . '`';
        } elseif (static::getDatabase() > '') {
            $table = '`' . static::getDatabase() . '`.`' . static::getTable() . '`';
        } else {
            $table = '`' . static::getTable() . '`';
        }

        /*
         * Проверяем необходимость использования транзакции
         */
        if (isset($params['transaction']) && $params['transaction'] == true) {
            $bTransaction = true;
            DB::one()->begin();
        }

        /* если есть функция, которую нужно вызвать до удаления - вызываем ее */
        if (! $this->beforeDelete($params['before'])) {
            if ($bTransaction) {
                DB::one()->rollback();
            }
            return false;
        }

        /* удаляем запись */
        $idKey   = static::getIdName();
        $sql     = "DELETE FROM " . $table . " WHERE " . $idKey . " = '" . $this->fields[$idKey] . "' LIMIT 1";
        $result  = DB::one()->execute($sql);

        /* очищаем кеш связанный с этим классом/таблицей */
        $className = get_called_class();
        DB::clearInnerCache($className);

        if (! $result) {
            $err = DB::one()->errorInfo();
            if ($err[0] != '00000') {
                App::one()->log('Error in ' . $className . '::delete[' . $sql . ']; ' . print_r($err, true), [], 'error');
            }//end if
        }

        /* если удаление прошло успешно */
        if ($result > 0) {
            /* если определен метод, который нужно вызывать после удаления - вызываем его */
            if ($this->afterDelete($params['after']) === false) {
                /* если что-то пошло не так и у нас запущена транзакция, то откатываем удаление */
                if ($bTransaction) {
                    DB::one()->rollback();
                }
                $result = false;
            } else {
                /* если запущена транзакция, фиксируем ее */
                if ($bTransaction) {
                    DB::one()->commit();
                }
                $this->onChange(['action' => 'delete']);
            }
        } elseif ($result === false && $bTransaction) {
            /* если произошла ошибка и определена транзакция - откатываемся */
            DB::one()->rollback();
        }

        return $result;
    } //end function

    /* тригеры событий, которые можно вызывать перед и после удаления и сохранения */
    protected function beforeDelete($mParams = [])
    {
        return true;
    }

    protected function afterDelete($mParams = [])
    {
        return true;
    }

    protected function beforeSave($mParams = [])
    {
        return true;
    }

    protected function afterSave($mParams = [])
    {
        return true;
    }

    protected function onChange($mParams = [])
    {
        return true;
    }

    public function getUid()
    {
        return str_replace('.', '', uniqid('', true));
    }

    /**
     * Сохраняем объект в БД. Если объект новый и поле не автоинкрементное, то перед вызовом этого метода
     * нужно установить флаг новой записи в TRUE: $obj->isNewRecord = true;
     * @return mixed больше 0, если сохраненно успешно, false если ошибка (ошибка сохраняется в DB::$lastError)
     */
    public function save($params = [])
    {
        if ($this->readOnly) {
            return false;
        }

        $values          = [];
        $names           = [];
        $types           = static::getTypes(); /* for future */
        $values_secrets  = [];
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
        if (!empty($params['database'])) {
            $table = '`' . $params['database'] . '`.`' . static::getTable() . '`';
        } elseif (static::getDatabase() > '') {
            $table = '`' . static::getDatabase() . '`.`' . static::getTable() . '`';
        } else {
            $table = '`' . static::getTable() . '`';
        }

        /*
         * Проверяем параметры для передачи в методы before_save и after_save
         */
        if (!isset($params['before'])) {
            $params['before'] = false;
        }
        if (!isset($params['after'])) {
            $params['after'] = false;
        }

        /*
         * Проверяем необходимость использования транзакции
         */
        if (isset($params['transaction']) && $params['transaction'] == true) {
            $bTransaction = true;
            DB::one()->begin();
        }

        if (! $this->beforeSave($params['before'])) {
            if ($bTransaction) {
                DB::one()->rollback();
            }
            return false;
        }

        foreach ($this->fields as $key => $value) {
            $names[$key] = $key;
            if ($value === null && $key != $idname) {
                if (isset($def[$key])) {
                    if (in_array($def[$key], array( 'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP(4)', 'now()', 'NOW()', 'NULL' ))) {
                        $values[$key] = $def[$key];
                    } elseif (in_array($def[$key], [self::VALUE_UUID, self::VALUE_GUID])) {
                        $try = 0;
                        do {
                            if ($def[$key] == self::VALUE_UUID) {
                                $uid = mt_rand($this->uidMin, $this->uidMax);
                            } elseif ($def[$key] == self::VALUE_GUID) {
                                $uid = $this->genUid();
                            }
                            if (App::one()->needUidOffset == 'yes' && App::one()->uidOffset > 0) {
                                $uid = substr('' . $uid, 0, -1) . App::one()->uidOffset;
                            }
                            $isExists = DB::getCountAll([
                                'database' => $this->getDatabase(),
                                'model' => get_called_class(),
                                'filter' => [
                                    $key => ['=' => $uid]
                                ]
                            ]);
                            $try++;
                            if ($try == 5) {
                                App::one()->log("Many attempts to exclude " . $key . " duplicates (" . $def[$key] . " try:{$try})", ['duplicateUid' => $uid, 'key' => $key, 'table' => $sTable], 'error');
                            }
                        } while ($isExists);
                        $values[$key] = $uid;
                        $this->__set($key, $uid);
                    } else {
                        $values[$key] = $def[$key];
                    }
                } else {
                    unset($names[$key]);
                }
            } else {
                if (is_array($value)) {
                    $value = base64_encode(json_encode($value));
                }

                if (!empty($params['securesecret']) && !empty($params['securefields']) && in_array($key, $params['securefields'])) {
                    unset($names[$key]);
                    $values_secrets[$key] = $value;
                } else {
                    $values[$key] = $value;
                }
            }
        }//end foreach

        if ($this->isNewRecord) {
            if (empty($values[$idname]) || $values[$idname] == "''") {
                $idDefaultType = $this->getIdDefault();
                if (in_array($idDefaultType, [self::VALUE_UUID, self::VALUE_GUID])) {
                    $try = 0;
                    do {
                        if ($idDefaultType == self::VALUE_UUID) {
                            $uid = mt_rand($this->uidMin, $this->uidMax);
                        } elseif ($idDefaultType == self::VALUE_GUID) {
                            $uid = $this->genUid();
                        }

                        if (App::one()->needUidOffset == 'yes' && App::one()->uidOffset > 0) {
                            $uid = substr('' . $uid, 0, -1) . App::one()->uidOffset;
                        }
                        $isExists = DB::getCountAll([
                            'database' => $this->getDatabase(),
                            'model' => get_called_class(),
                            'filter' => [
                                $idname => ['=' => $uid]
                            ]
                        ]);
                        $try++;
                        if ($try == 5) {
                            App::one()->log("Many attempts to exclude " . $idname . " duplicates (" . $idDefaultType . " try:{$try})", ['duplicateUid' => $uid, 'key' => $idname, 'table' => $sTable], 'error');
                        }
                    } while ($isExists);
                    $values[$idname] = $uid;
                    $this->$idname = $uid;

                    $values[$idname] = $uid;
                    $this->__set($idname, $uid);
                } elseif ($idDefaultType == self::VALUE_AUTOINC) {
                    $values[$idname] = null;
                }

                if ($idname > '') {
                    $names[$idname] = $idname;
                }
            }

            $keys = array_keys($values);
            $keys_secrets = array_keys($values_secrets);
            $sql = "INSERT INTO " . $table . ' (`'
                    . implode('`,`', $keys)
                    . (sizeof($keys_secrets) > 0 ? '`,`' . implode('`,`', $keys_secrets) : '')
                . "`) VALUES(:"
                    . implode(', :', $keys)
                    . (sizeof($keys_secrets) > 0 ? ', :' . implode(', :', $keys_secrets) : '')
                . ")";

            if (isset($params['onduplicateupdate']) && isset($values[$params['onduplicateupdate']])) {
                $sql .= " ON DUPLICATE KEY UPDATE `" . $params['onduplicateupdate'] . "` = :" . $params['onduplicateupdate'];
            } elseif (isset($params['onduplicateupdateall'])) {
                $updateKeys = [];
                foreach ($keys as $lkey) {
                    if ($lkey == $idname) {
                        continue;
                    }
                    $updateKeys[] = "`$lkey` = VALUES(`$lkey`)";
                }
                $sql .= " ON DUPLICATE KEY UPDATE " . implode(',', $updateKeys); //ON DUPLICATE KEY UPDATE `a`=VALUES(`a`), `b`=VALUES(`b`), `c`=VALUES(`c`)
            }
        } else {
            $upd = [];
            foreach ($values as $key => $value) {
                if (
                    $key == $idname
                    && (
                        !isset($def[$key])
                        || in_array($def[$key], [self::VALUE_AUTOINC, self::VALUE_UUID, self::VALUE_GUID])
                    )
                ) {
                    continue;
                }
                $upd[] = $key . ' = :' . $key;
            }
            if (sizeof($values_secrets) > 0) {
                foreach ($values_secrets as $key => $value) {
                    if (
                        $key == $idname
                        && (
                            !isset($def[$key])
                            || in_array($def[$key], [self::VALUE_AUTOINC, self::VALUE_UUID, self::VALUE_GUID])
                        )
                    ) {
                        continue;
                    }
                    $upd[] = $key . " = AES_ENCRYPT(:" . $key . ", UNHEX('" . $params['securesecret'] . "'))";
                }
            }

            $sql = "UPDATE " . $table . " SET "
                . implode(', ', $upd)
                . " WHERE " . $idname . ' = :' . $idname
                . " LIMIT 1";
        }//end if else

        $st = DB::one()->prepare($sql);
        $result = false;
        if ($st) {
            $result = $st->execute($values);
        }

        DB::clearInnerCache($className);

        if ($result !== false) {
            if ($result === 0) {
                $err = DB::one()->errorInfo();
                if ($err[0] != '00000') {
                    App::one()->log('Error in ' . $className . '::save;', ['debug' => print_r($err, true), 'sql' => $sql], 'error');
                }
            }

            if (static::getIdDefault() == self::VALUE_AUTOINC && $this->isNewRecord) {
                $this->__set($idname, DB::one()->getLastID());
            }

            $result = true;
        } else {
            $result = false;
        }

        if (!$result) {
            //@todo вставить добавление ошибок возникших при сохранении
        }

        if ($result) {
            if (false === $this->afterSave($params['after'])) {
                if ($bTransaction) {
                    DB::one()->rollback();
                }
                return false;
            } else {
                if ($bTransaction) {
                    DB::one()->commit();
                }

                $this->onChange(
                /*[
                    'sql' => $sql
                ]*/
                );
            }
            $this->isNewRecord = false;
        } else {
            if ($bTransaction) {
                DB::one()->rollback();
            }
        }
        return $result;
    }


    public function saveField($key, $arams = [])
    {
        $types           = static::getTypes(); // for future
        $values          = [];
        $values_secrets  = [];
        $def             = static::getDefault();
        $idname          = static::getIdName();
        $bTransaction    = false;
        $className = get_called_class();

        if (!static::is($key) || $key == $idname) {
            return false;
        }

        $value = $this->__get($key);

        // Выставляем базу и таблицу для запроса
        if (!empty($arams['database'])) {
            $table = '`' . $arams['database'] . '`.`' . static::getTable() . '`';
        } elseif (static::getDatabase() > '') {
            $table = '`' . static::getDatabase() . '`.`' . static::getTable() . '`';
        } else {
            $table = '`' . static::getTable() . '`';
        }

        if ($value === null && $key != $idname) {
            if (isset($def[$key])) {
                $values[$key] = $def[$key];
            } else {
                $values[$key] = null;
            }
        } else {
            if (is_array($value)) {
                $value = base64_encode(json_encode($value));
            }

            if (
                !empty($params['securesecret'])
                && !empty($params['securefields'])
                && in_array($key, $params['securefields'])
            ) {
                $values_secrets[$key] = $value;
            } else {
                $values[$key] = $value;
            }
        }

        $result = 0;
        if (sizeof($values) + sizeof($values_secrets) > 0) {
            if ($key != 'serverTS' && static::is('serverTS') && static::is('serverId')) {
                $values['serverTS'] = (string)microtime(true);
                if ($key != 'serverId' && App::one()->serverId > '' && $this->serverId !== App::one()->serverId) {
                    $values['serverId'] = App::one()->serverId;
                }
            }

            $upd = [];
            foreach ($values as $key => $value) {
                if ($key == $idname) {
                    continue;
                }
                $upd[] = $key . ' = :' . $key;
            }
            if (sizeof($values_secrets) > 0) {
                foreach ($values_secrets as $key => $value) {
                    if ($key == $idname) {
                        continue;
                    }
                    $upd[] = $key . " = AES_ENCRYPT(:" . $key . ", UNHEX('" . $arParams['securesecret'] . "'))";
                }
            }

            $sql = "UPDATE " . $sTable . 
                " SET " . implode(", ", $upd) .
                " WHERE $idname = :" . $idname;
            $values[$idname] = $this->$idname;

            $st = DB::one()->prepare($sql);
            $result = false;
            if ($st) {
                $result = $st->execute($values);
            }
        }
        // очищаем кеш
        DB::clearInnerCache($className);

        // делаем запрос актуальных данных
        $tmp = DB::getOne(
            [
                'model' => $className,
                'filter' => [
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

    public function saveFields($fields, $params = [])
    {
        $types           = static::getTypes(); // for future
        $values          = [];
        $values_secrets  = [];
        $def             = static::getDefault();
        $idname          = static::getIdName();
        $bTransaction    = false;
        $className = get_called_class();

        if (! is_array($fields) || sizeof($fields) == 0) {
            return false;
        }

        // Выставляем базу и таблицу для запроса
        if (!empty($params['database'])) {
            $table = '`' . $params['database'] . '`.`' . static::getTable() . '`';
        } elseif (static::getDatabase() > '') {
            $table = '`' . static::getDatabase() . '`.`' . static::getTable() . '`';
        } else {
            $table = '`' . static::getTable() . '`';
        }

        if (
            !in_array('serverTS', $fields)
            && static::is('serverTS')
            && static::is('serverId')
        ) {
            $fields[] = 'serverTS';
            $this->__set('serverTS', (string)microtime(true));
            $values['serverTS'] = $this->serverTS;
            if ($this->serverId !== App::one()->serverId) {
                $fields[] = 'serverId';
                $this->__set('serverId', App::one()->serverId);
                $values['serverId'] = $this->serverId;
            }
        }

        foreach ($fields as $key) {
            if (! static::is($key)) {
                continue;
            }
            $value = $this->__get($key);

            if ($value === null && $key != $idname) {
                if (isset($def[$key])) {
                    if (in_array($def[$key], [ 'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP(4)', 'now()', 'NOW()', 'NULL' ])) {
                        $values[$key] = $def[$key];
                    } else {
                        $values[$key] = $value;
                    }
                }
            } else {
                if (is_array($value)) {
                    $value = base64_encode(json_encode($value));
                }

                if (!empty($arParams['securesecret']) && !empty($arParams['securefields']) && in_array($key, $arParams['securefields'])) {
                    $values_secrets[$key] = $value;
                } else {
                    $values[$key] = $value;
                }
            }
        }

        $result = 0;
        if (sizeof($values) + sizeof($values_secrets) > 0) {
            $upd = [];
            foreach ($values as $key => $value) {
                if ($key == $idname) {
                    continue;
                }
                $upd[] = $key . ' = :' . $key;
            }
            if (sizeof($values_secrets) > 0) {
                foreach ($values_secrets as $key => $value) {
                    if ($key == $idname) {
                        continue;
                    }
                    $upd[] = $key . " = AES_ENCRYPT(:" . $key . ", UNHEX('" . $arParams['securesecret'] . "'))";
                }
            }

            $sql = "UPDATE " . $sTable . " SET " . implode(", ", $upd) .
                    " WHERE $idname = :" . $idname;
            $values[$idname] = $this->$idname;

            $st = DB::one()->prepare($sql);
            $result = false;
            if ($st) {
                $result = $st->execute($values);
            }
        } else {
            return false;
        }

        // очищаем кеш
        DB::clearInnerCache($className);

        // делаем запрос актуальных данных
        $tmp = DB::getOne([
            'model' => $className,
            'filter' => [
                $idname => ['=' => $this->$idname]
            ]
        ]);
        if ($tmp) {
            $this->attributes($tmp->__getFields());
        }
        $this->onChange(['fields' => $fields]);

        return $result;
    }

    // @return Array
    public static function getClearFields()
    {
        die('bred');
    }

    public static function getDefault()
    {
        return [];
    }

    /**
     * увеличить значение поля (использовать, когда нужно увеличить какой-то счетчик и есть риск множественых одновременных изменений
     * @param string $field название поля, которое нужно изменять
     * @param integer $step шаг, на сколько увеличить
     * @param string условия для выбора записей подлежащих изменению
     * @return integer возвращает актуальное значение счетчика
     **/
    public function increment($field, $step = 1, $where = '')
    {
        $className = get_called_class();
        if (static::getDatabase() > '') {
            $table = '`' . static::getDatabase() . '`.`' . static::getTable() . '`';
        } else {
            $table = '`' . static::getTable() . '`';
        }
        $idname = static::getIdName();

        if (!static::is($field)) {
            return false;
        }

        if ($where > '') {
            $where = ' AND ' . $where;
        }

        $addupdate = '';
        $upd = "`" . $field . "` = `" . $field . "` + " . $step;
        $values = [];
        if (static::is('serverTS') && static::is('serverId')) {
            $this->serverId = $values['serverId'] = App::one()->serverid;
            $this->serverTS = $values['serverTS'] = (string)microtime(true);
            $upd .= ", `serverTS` = :serverTS, `serverId` = :serverId ";
        }
        $values[$idname] = $this->$idname;

        $sql = "UPDATE " . $table . " SET " . $upd .
                " WHERE $idname = :" . $idname . " " . $where;

        $st = DB::one()->prepare($sql);
        $result = false;
        if ($st) {
            $result = $st->execute($values);
        }

        /* очищаем кеш */
        DB::clearInnerCache($className);

        /* делаем запрос актуальных данных */
        $tmp = DB::getOne([
            'model' => $className,
            'filter' => [
                $idname => ['=' => $this->$idname]
            ]
        ]);

        if ($tmp) {
            $this->attributes($tmp->__getFields());
        }

        $this->onChange(['fields' => [$field]]);
        return $result;
    }

    /* уменьшаем поле на определенный шаг */
    public function decrement($field, $step = 1, $where = '')
    {
        $className = get_called_class();
        if (static::getDatabase() > '') {
            $table = '`' . static::getDatabase() . '`.`' . static::getTable() . '`';
        } else {
            $table = '`' . static::getTable() . '`';
        }
        $idname = static::getIdName();

        if (!static::is($field)) {
            return false;
        }

        if ($where > '') {
            $where = ' AND ' . $where;
        }

        $addupdate = '';
        $upd = "`" . $field . "` = `" . $field . "` - " . $step;
        $values = [];
        if (static::is('serverTS') && static::is('serverId')) {
            $this->serverId = $values['serverId'] = App::one()->serverid;
            $this->serverTS = $values['serverTS'] = (string)microtime(true);
            $upd .= ", `serverTS` = :serverTS, `serverId` = :serverId ";
        }
        $values[$idname] = $this->$idname;

        $sql = "UPDATE " . $table . " SET " . $upd .
                " WHERE $idname = :" . $idname . " " . $where;

        $st = DB::one()->prepare($sql);
        $result = false;
        if ($st) {
            $result = $st->execute($values);
        }

        /* очищаем кеш */
        DB::clearInnerCache($className);

        /* делаем запрос актуальных данных */
        $tmp = DB::getOne([
            'model' => $className,
            'filter' => [
                $idname => ['=' => $this->$idname]
            ]
        ]);

        if ($tmp) {
            $this->attributes($tmp->__getFields());
        }

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
        if (isset($this->fields[$name])) {
            return str_replace("\n", '<br />', $this->fields[$name]);
        }
        return '';
    }

    public function validate()
    {
        if (!Validator::one()->validate($this->__getFields(), static::getRules())) {
            $errors = Validator::one()->getErrors();
            foreach ($errors as $field => $fieldErrors) {
                $this->addError(implode(', ', $fieldErrors), $field);
            }
            return false;
        } else {
            $this->attributes(Validator::one()->data);
        }

        return true;
    }
    /** error functions **/

    /**
     * Добавляем ошибку
     * @param string $sError      текст ошибки
     * @param string $sField      название поля в котором обнаружена ошибка
     * @return Model
     */
    public function addError($sError, $sField = '_')
    {
        DB::addError($sError, get_class($this), $sField);
        return $this;
    }

    /**
     * Проверяем, есть ли ошибка в каком-то поле или в целом в моделе
     * @param string $sField      название поля которое проверяется (нужено указать "_" - для проверки общих ошибок)
     * @return boolean
     */
    public function isError($sField = '_')
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
     * @param $params
     * @param array $sysOptions
     * @return static
     */
    public static function getRow($params, $sysOptions = [])
    {
        $params['model'] = get_called_class();
        return DB::one()->getOne($params, $sysOptions);
    }


    /**
     * @param $params
     * @param array $sysOptions
     * @return static[]
     */
    public static function getRows($params, $sysOptions = [])
    {
        $params['model'] =  get_called_class();
        return DB::one()->getAll($params, $sysOptions);
    }

    public static function getCount($params, $sysOptions = [])
    {
        $params['model'] =  get_called_class();
        return DB::one()->getCountAll($params, $sysOptions);
    }

    public static function getMax($field, $params, $sysOptions = [])
    {
        $params['model'] =  get_called_class();
        $params['fields'] = '`' . $field . '` as cnt';
        $params['sort'] = array($field => 'desc');
        $params['pageSize'] = 1;
        $params['page'] = 1;
        $sSql = DB::generateSelectSQL($params);
        $st = DB::one()->query($sSql, ['nocache' => true]);
        $iResult = false;
        if ($st) {
            $arTmp = $st->fetch(\PDO::FETCH_ASSOC);
            if (isset($arTmp['cnt'])) {
                $iResult = $arTmp['cnt'];
            }
        }

        return $iResult;
    }

    public static function getMin($field, $params, $sysOptions = [])
    {
        $params['model'] =  get_called_class();
        $params['fields'] = 'min(' . $field . ') as cnt';
        $sSql = DB::generateSelectSQL($params);
        $st = DB::one()->query($sSql, ['nocache' => true]);
        $iResult = false;
        if ($st) {
            $arTmp = $st->fetch(\PDO::FETCH_ASSOC);
            if (isset($arTmp['cnt'])) {
                $iResult = $arTmp['cnt'];
            }
        }

        return $iResult;
    }

    public static function getSum($field, $params, $sysOptions = array())
    {
        $params['model'] =  get_called_class();
        $params['fields'] = 'SUM(`' . $field . '`) as sum';
        $params['pageSize'] = 1;
        $params['page'] = 1;
        $sql = DB::generateSelectSQL($params);
        $st = DB::one()->query($sql, $sysOptions);
        $iResult = false;
        if ($st) {
            $tmp = $st->fetch(\PDO::FETCH_ASSOC);
            if (isset($tmp['sum'])) {
                $iResult = $tmp['sum'];
            }
        }
        if ($iResult == false) {
            $iResult = 0;
        }
        return $iResult;
    }

    public static function getById($id, $sysParams = [])
    {
        $idName = static::getIdName();
        $model =  get_called_class();
        $specs = [];
        if (isset($sysParams['nocache']) && $sysParams['nocache']) {
            $specs['nocache'] = true;
        }
        $params = [
            'model' => $model,
            'arFilter' => [
                $idName => ['=' => $id]
            ],
            'pageSize' => 1
        ];

        if (!empty($sysParams['securesecret']) && !empty($sysParams['securefields'])) {
            $params['securesecret'] = $sysParams['securesecret'];
            $params['securefields'] = $sysParams['securefields'];
        }

        return DB::getOne($params, $specs);
    }

    public function setMaxValue($fieldname, $params, $step = 1)
    {
        if (static::is($fieldname)) {
            $table = static::getTable();
            $idname = static::getIdName();
            $params['fields'] =  'MAX(`' . $table . '`.`' . $fieldname . '`) as `' . $fieldname . '`';
            $params['model'] =  get_called_class();

            $values = [];
            $sql = DB::generateSelectSQL($params, $values);

            $addupdate = '';
            if (static::is('serverTS') && static::is('serverId')) {
                $this->serverId = App::one()->serverId;
                $this->serverTS = (string)microtime(true);
                $addupdate = ", t0.serverId='" . App::one()->serverId . "', t0.serverTS='" . $this->serverTS . "' ";
            }

            $sql = "UPDATE `" . static::getTable() . "` as t0, (" . $sql . " LIMIT 1) as t1 SET t0.`" . $fieldname . "` = t1.`" . $fieldname . "`+" . intval($step) . $addupdate . " WHERE `" . $idname . "` = '" . $this->$idname . "' ";

            try {
                $result = DB::one()->execute($sql);
            } catch (PDOException $e) {
                $result = false;
                $state = $e->getMessage();
                if (strpos($state, ' Deadlock found when trying to get lock; try restarting transaction') !== false) {
                    $result = DB::one()->execute($sql);
                } else {
                    throw $e;
                }
            }
            $className =  get_called_class();
            DB::clearInnerCache($className);

            if ($result !== false) {
                if ($result === 0) {
                    $err = DB::one()->errorInfo();
                    if ($err[0] != '00000') {
                        App::one()->log('Error in ' . $className . '::delete[' . $sql . ']; ' . print_r($err, true), [], 'error');
                    }
                }

                $result = true;

                $tmp = static::getRow(
                    [
                        'fields' => '`' . $table . '`.`' . $fieldname . '` ',
                        'filter' => [
                            $idname => ['=' => $this->$idname]
                        ]
                    ],
                    ['nocache' => true]
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

    public function __toArray()
    {
        return $this->__getFields();
    }

    public function __serialize(): array
    {
        return $this->__getFields();
    }

    public function __unserialize(array $data): void
    {
        $this->attributes($data);
    }

    public function jsonSerialize() {
        return $this->__getFields();
    }

    /**
     * Функция извлекает значение зашифрованного свойства
     * @param string $param Название параметра
     * @return mixed|string|null
     */
    public function getSecureField(string $param)
    {
        if (property_exists($this, '_' . $param)) {
            // Инициализируем параметр
            if ($this->{'_' . $param} === null) {
                //TODO после удаление полей из модели надо убрать обращение к $this->param (заменить на '')
                if (!empty($this->{'seca_' . $param})) {
                    $this->{'_' . $param} = App::one()->decryptString($this->{'seca_' . $param}, $this->getSecret());
                } elseif (!empty($this->$param)) { // TODO после удаление полей из модели надо убрать
                    $this->{'_' . $param} = base64_decode($this->$param); //TODO после удаление полей из модели надо убрать обращение к $this->param (заменить на '')
                } else {
                    $this->{'_' . $param} = '';
                }
            }

            return $this->{'_' . $param};
        }

        return '';
    }

    public function setSecureFields(array $params)
    {
        foreach ($params as $param => $data) {
            $this->setSecureField($param, $data);
        }
    }

    /**
     * Функция устанавливает значение поля
     * @param string $param
     * @param string $data
     * @return bool
     * @throws Exception
     */
    private function setSecureField(string $param, string $data): bool
    {
        if (!property_exists($this, '_' . $param)) {
            throw new Exception('Property _' . $param . ' does not exist in class ' . get_class($this));
        }

        $this->{'_' . $param} = $data;

        return true;
    }

    /**
     * Метод разбирает хранящийся в $this->fields[$key] (и потенциально закодированный в base64) json в массив,
     * перезаписывает $this->fields[$key] этим массивом и возвращает его.
     * Если там и так уже массив - возвращает его в неизменном виде.
     *
     * @param string $key Ключ в $this->fields
     * @return array
     * @throws \ErrorException
     */
    public function getFieldAsArray(string $key): array
    {
        if (empty($key)) {
            return [];
        }

        if (!array_key_exists($key, $this->fields)) {
            $trace = debug_backtrace();
            throw new \ErrorException(
                'Unknown field ' . get_class($this) . '::$' . $key,
                E_USER_ERROR,
                1,
                $trace[0]['file'],
                $trace[0]['line']
            );
        }

        $value = $this->fields[$key];

        if (is_array($value)) {
            return $value;
        }

        if (empty($value) || !is_string($value)) {
            $this->fields[$key] = [];
            return [];
        }

        if (substr($value, 0, 1) === '{' || substr($value, 0, 1) === '[') {
            $value = json_decode($value, true);
        } else {
            $value = json_decode(base64_decode($value), true);
        }

        $this->fields[$key] = $value;
        return $value;
    }

    /**
     * Метод добавляет значение $value с ключом $field в массив $this->fields[$key].
     * Если в массиве уже есть такой ключ, его значение будет перезаписано.
     *
     * @param string $key Ключ поля в $this->fields, в котором хранится массив или json (может быть закодирован в base64)
     * @param string $field Ключ в массиве
     * @param mixed $value Значение для добавления/перезаписи
     * @return void
     * @throws \ErrorException
     */
    public function setArrayFieldValue(string $key, string $field, $value): void
    {
        if (empty($key)) {
            return;
        }

        $array = $this->getFieldAsArray($key);
        $array[$field] = $value;
        $this->fields[$key] = $array;
    }
}
//end class
