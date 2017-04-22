<?php
/**
 * 
 */
class ModelUsers extends Model
{
    
    /**
     * функция возвращает название таблицы в которой хранятся сущности данного класса
     * @return string
     */
    static public function getTable()
    {
        return 'users';
    }

    /**
     * функция возвращает название модели
     * @return string
     */
    static public function getTitle()
    {
        return 'Пользователи';
    }
    
    /**
     * функция возвращает способ генерации значения первичного ключа
     *
     * @example
     *      UUID - случайное число между $uidMin и $uidMax
     *      AUTOINC - автоинкрементный счетчик (в MySQL)
     *      GUID - случайное значение
     * @return  string
     */
    static public function getIdDefault()
    {
        return 'AUTOINC';
    }

    /**
     * функция возвращает название первичного ключа
     * @return string
     */
    static public function getIdName()
    {
        return 'id';
    }

    /**
     * функция возвращает список полей модели
     * @return array
     */
    static public function getClearFields()
    {
        return array(
            'id' => null,
            'name' => null,
            'email' => null,
            'passwd' => null,
            'statusid' => null,
            'created' => null,
            'modified' => null,
            'expired' => null,
        );
    }

    /**
     * функция возвращает значение по умолчанию для полей модели
     * @return array
     */
    static public function getDefault()
    {
        return array(
            'name' => '',
            'passwd' => '',
            'statusid' => '0',
            'modifed' => '0000-00-00 00:00:00',
            'created' => 'CURRENT_TIMESTAMP',
            'expired' => '0000-00-00 00:00:00',
        );
    }// end method

    /**
     * список функций отвечающих за свзяи с другими моделями
     */
    
    
    public function status($isOne=true, $idx=0)
    {
        $arRelations = array(
            '0' => array('Ожидает подтверждения'),
            '1' => array('Активен'),
            '2' => array('Заблокирован'),
            '3' => array('Удален'),
        );
        return $isOne
            ? (
                isset($arRelations[$this->statusid])
                ? $arRelations[$this->statusid][$idx]
                : null
            )
            : $arRelations;
    }

}
