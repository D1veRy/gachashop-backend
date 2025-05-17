<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class Card extends ActiveRecord
{
    /**
     * Указываем таблицу, с которой работает модель
     */
    public static function tableName()
    {
        return 'cards'; // Имя таблицы в базе данных
    }

    /**
     * Правила валидации данных
     */
    public function rules()
    {
        return [
            [['card_name', 'card_number', 'card_date'], 'required'], // обязательные поля
            ['card_number', 'string', 'max' => 16], // Номер карты — строка длиной 16 символов
            ['card_date', 'string', 'max' => 5], // Формат MM/YY
        ];
    }
}