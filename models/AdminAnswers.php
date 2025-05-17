<?php

namespace app\models;

use yii\db\ActiveRecord;

class AdminAnswers extends ActiveRecord
{
    public static function tableName()
    {
        return 'admin_answers'; // Имя таблицы
    }

    public function rules()
    {
        return [
            [['review_id', 'admin_id', 'text'], 'required'], // обязательные поля
            [['review_id', 'admin_id'], 'integer'],
            ['text', 'string'], // текст ответа должен быть строкой
        ];
    }

    public function getReview()
    {
        return $this->hasOne(UserReview::class, ['id' => 'review_id']);
    }
}