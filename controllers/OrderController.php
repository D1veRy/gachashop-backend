<?php

namespace app\controllers;

use app\models\UserReview;
use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\models\Order;
use app\models\User;
use app\models\OrderStatus;

class OrderController extends Controller
{
    public function behaviors()
    {
        return [
            'corsFilter' => [
                'class' => \yii\filters\Cors::class,
                'cors' => [
                    //Прописываем адрес нашего клиента
                    'Origin' => ['https://d1very.github.io/'],
                    //Добавляем необходимыве заголовки
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'DELETE'],
                    'Access-Control-Request-Headers' => ['*'],
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Max-Age' => 3600
                ],

            ],
        ];
    }

    public function actionCount($user_id)
    {
        // Логика получения количества заказов для пользователя
        $orderCount = Order::find()->where(['user_id' => $user_id])->count();
        return $this->asJson(['count' => $orderCount]);
    }

    public function actionCreate()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Получаем сырые данные из тела запроса
        $rawData = file_get_contents('php://input');

        // Декодируем JSON
        $data = json_decode($rawData, true);

        // Проверяем, что данные получены
        if (!$data) {
            return ['success' => false, 'error' => 'Не удалось декодировать JSON данные'];
        }

        // Проверяем, есть ли необходимые поля
        if (!isset($data['user_id'], $data['order_name'], $data['order_date'], $data['order_price'], $data['order_cashback'], $data['order_status'], $data['order_method'])) {
            return ['success' => false, 'error' => 'Не все необходимые поля переданы'];
        }

        // Присваиваем значения из запроса переменным
        $userId = $data['user_id'];
        $orderName = $data['order_name'];
        $orderDate = $data['order_date'];  // Дата с временем в ISO формате
        $orderPrice = $data['order_price'];  // Цена с валютой
        $orderCashback = $data['order_cashback'];  // Сумма кэшбека
        $orderStatus = $data['order_status'];  // Статус заказа
        $orderMethod = $data['order_method'];  // Метод оплаты

        // Устанавливаем временную зону на уровне базы данных
        Yii::$app->db->createCommand("SET TIMEZONE TO 'Europe/Moscow'")->execute();

        // Преобразуем строку с датой в формат для базы данных (в московское время)
        $dateTime = new \DateTime($orderDate, new \DateTimeZone('UTC'));
        $dateTime->setTimezone(new \DateTimeZone('Europe/Moscow'));
        $orderDate = $dateTime->format('Y-m-d H:i:s');  // Конвертируем в MySQL compatible timestamp

        // Создаем новый заказ
        $order = new Order();
        $order->user_id = $userId;
        $order->order_name = $orderName;
        $order->order_date = $orderDate;  // Дата теперь в правильном формате
        $order->order_price = $orderPrice;
        $order->order_cashback = $orderCashback;
        $order->order_status = $orderStatus;
        $order->payment_method = $orderMethod;

        if ($orderMethod === 'card') {
            $cardNumber = $data['card_number'];
            $order->card_number = $cardNumber;
        }

        // Сохраняем заказ в базе данных
        if ($order->save()) {
            // Логика обновления кэшбека
            $user = User::findIdentity($userId);
            Yii::info('Кэшбек до обновления: ' . $orderCashback, __METHOD__);

            if ($user && $orderCashback > 0 && $orderMethod == "card") {
                $user->updateCashback($orderCashback);
            }

            // Логика генерации уникального кода
            $code = $this->generateUniqueCode();

            // Сохранение кода в заказ
            $order->order_code = $code;
            $order->save(false);  // Сохраняем без валидации, так как заказ уже был сохранен

            return [
                'success' => true,
                'order_id' => $order->id,
                'order_code' => $code,  // Возвращаем сгенерированный код
            ];
        } else {
            return ['success' => false, 'errors' => $order->errors];
        }
    }

    public function actionOrders()
    {
        $userId = Yii::$app->user->id;

        if (!$userId) {
            return ['error' => 'User not logged in'];
        }

        // Получаем все заказы пользователя с информацией о статусе заказа
        $orders = Order::find()
            ->where(['user_id' => $userId])
            ->with('orderStatus') // Метод связи!
            ->asArray()
            ->all();

        $orders = array_map(function ($order) use ($userId) {
            if (isset($order['orderStatus']['name'])) {
                $order['order_status'] = $order['orderStatus']['name'];
            } else {
                $order['order_status'] = 'Неизвестно';
            }
            unset($order['orderStatus']);

            // Проверяем, есть ли отзыв для этого заказа
            $hasReview = UserReview::find()
                ->where(['order_id' => $order['id'], 'user_id' => $userId])
                ->exists();

            // Добавляем новый параметр, который показывает наличие отзыва
            $order['has_review'] = !$hasReview;

            return $order;
        }, $orders);

        return $orders;
    }


    private function generateUniqueCode()
    {
        // Генерация случайного кода
        $code = $this->generateRandomCode();

        // Проверка на уникальность кода в базе данных
        while (Order::find()->where(['order_code' => $code])->exists()) {
            // Генерируем новый код, если текущий уже существует в базе данных
            $code = $this->generateRandomCode();
        }

        return $code;
    }

    private function generateRandomCode()
    {
        // Генерация случайного кода длиной 16 символов (латиница и цифры)
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 16; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }

        $formattedCode = implode('-', str_split($code, 4));

        return $formattedCode;
    }

    public function actionGetOrderIdByCode($orderCode)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Ищем заказ по order_code
        $order = Order::find()->where(['order_code' => $orderCode])->one();

        if ($order) {
            return ['status' => 'success', 'order_id' => $order->id];
        } else {
            return ['status' => 'error', 'message' => 'Заказ не найден'];
        }
    }

    public function actionGetUserOrders()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $orders = Order::find()
            ->with('orderStatus') // Присоединяем статусы
            ->all();

        $response = [];
        foreach ($orders as $order) {
            $response[] = [
                'id' => $order->id,
                'order_name' => $order->order_name,
                'order_date' => $order->order_date,
                'order_price' => $order->order_price,
                'user_id' => $order->user_id,
                'order_status_name' => $order->orderStatus->name, // Имя статуса
                'order_code' => $order->order_code,
            ];
        }

        return $response;
    }

    public function actionDelete($id)
    {
        $order = Order::findOne($id);
        if ($order) {
            // Удаление отзыва по order_id
            \Yii::$app->db->createCommand()
                ->delete('reviews', ['order_id' => $order->id])
                ->execute();

            // Удаление самого заказа
            $order->delete();

            return ['status' => 'success'];
        }

        return ['status' => 'error', 'message' => 'Order not found'];
    }
    public function actionUpdateStatus($id)
    {
        $order = Order::findOne($id);
        if ($order && Yii::$app->request->isPost) {
            $newStatus = Yii::$app->request->post('status');
            $order->order_status = $newStatus;
            $order->save();
            return ['status' => 'success'];
        }
        return ['status' => 'error', 'message' => 'Invalid request'];
    }
    public function actionGetStatuses()
    {
        // Получаем все статусы
        $statuses = OrderStatus::getAllStatuses();

        // Возвращаем в виде JSON
        return [
            'statuses' => array_map(function ($status) {
                return [
                    'id' => $status->id,
                    'name' => $status->name,
                ];
            }, $statuses)
        ];
    }
}