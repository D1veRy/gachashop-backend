<?php

namespace app\models;

use yii\db\ActiveRecord;

class Product extends ActiveRecord
{
    /**
     * Название таблицы, связанной с моделью
     */
    public static function tableName()
    {
        return 'products'; // Укажите реальное название таблицы
    }

    /**
     * Правила валидации для модели
     */
    public function rules()
    {
        return [
            [['name', 'price', 'type', 'game_id'], 'required'],
            [['description'], 'string'],
            [['price'], 'string'],
            [['image'], 'string'], // Убедитесь, что здесь указано поле image
            [['game_id', 'days'], 'integer'],
            [['quantity'], 'string'],
        ];
    }


}
