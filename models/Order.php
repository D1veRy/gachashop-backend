<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class Order extends ActiveRecord
{
    // Дефолтный метод для получения таблицы
    public static function tableName()
    {
        return 'orders';  // Убедитесь, что имя таблицы корректное
    }

    // Метод для получения количества заказов пользователя
    public static function getOrderCountByUserId($userId)
    {
        return self::find()
            ->where(['user_id' => $userId])
            ->count(); // Возвращаем количество заказов
    }

    // Метод для вычисления суммы кэшбэка
    public static function calculateCashback($totalAmount, $isFirstOrder)
    {
        $cashbackPercentage = $isFirstOrder ? 7 : 5; // 7% для первого заказа, 5% для всех остальных
        return ($totalAmount * $cashbackPercentage) / 100;
    }

    public $code;

    public function rules()
    {
        return [
            [['user_id', 'order_name', 'order_date', 'order_price', 'order_cashback', 'order_status', 'payment_method'], 'required'],
            [['order_code'], 'safe'],  // Добавьте это правило
            // остальные правила
        ];
    }

    public function getOrderStatus()
    {
        return $this->hasOne(OrderStatus::class, ['id' => 'order_status']);
    }
}