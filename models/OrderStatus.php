<?php
namespace app\models;

use yii\db\ActiveRecord;

class OrderStatus extends ActiveRecord
{
    public static function tableName()
    {
        return 'order_status';
    }

    /**
     * Метод, который возвращает все статусы заказа
     */
    public static function getAllStatuses()
    {
        return self::find()->all();
    }
}
