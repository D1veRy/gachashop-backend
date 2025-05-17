<?php

namespace app\models;

use yii\db\ActiveRecord;

class UserReview extends ActiveRecord
{
    public static function tableName()
    {
        return 'reviews';  // Имя таблицы отзывов
    }

    public function rules()
    {
        return [
            [['user_id', 'order_id', 'review'], 'required'],
            [['created_at'], 'safe'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getOrderRecord()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }


    public function getCreatedAt()
    {
        return $this->created_at; // Возвращает значение поля created_at
    }

    public function getAdmin()
    {
        return $this->hasOne(User::class, ['id' => 'admin_id']);
    }
}