<?php
namespace University\FileAsync\Entity;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\TextField;
use Bitrix\Main\Entity\DatetimeField;

class FileTaskTable extends DataManager
{
    public static function getTableName()
    {
        return 'b_uni_file_task';
    }

    public static function getMap()
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new IntegerField('FILE_ID', [
                'required' => true,
            ]),
            new StringField('STATUS', [
                'size' => 20,
                'default' => 'pending',
            ]),
            new TextField('ERROR_MESSAGE'),
            new DatetimeField('CREATED_AT'),
            new DatetimeField('UPDATED_AT'),
        ];
    }
}