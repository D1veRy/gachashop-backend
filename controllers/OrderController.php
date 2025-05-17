<?php

namespace app\controllers;

use app\models\UserReview;
use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\models\Order;
use app\models\User;
use app\models\OrderStatus;
use app\controllers\BaseApiController;
use yii\filters\auth\HttpBearerAuth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class OrderController extends BaseApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
        ];

        return $behaviors;
    }
    public function actionCount()
    {
        $user = Yii::$app->user->identity;

        if (!$user) {
            return $this->asJson(['error' => 'Пользователь не авторизован']);
        }

        $orderCount = Order::find()->where(['user_id' => $user->id])->count();
        return $this->asJson(['count' => $orderCount]);
    }

    public function actionCreate()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $headers = Yii::$app->request->headers;
        $authHeader = $headers->get('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Отсутствует токен авторизации'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userIdFromToken = $decoded->uid;

            // Получаем данные из тела запроса
            $rawData = file_get_contents('php://input');
            $data = json_decode($rawData, true);

            if (!$data) {
                return ['success' => false, 'error' => 'Не удалось декодировать JSON данные'];
            }

            // Проверяем обязательные поля, кроме user_id
            if (!isset($data['order_name'], $data['order_date'], $data['order_price'], $data['order_cashback'], $data['order_status'], $data['order_method'])) {
                return ['success' => false, 'error' => 'Не все необходимые поля переданы'];
            }

            // Если user_id передаётся в теле запроса, то можно проверить совпадение:
            if (isset($data['user_id']) && $data['user_id'] != $userIdFromToken) {
                Yii::$app->response->statusCode = 401;
                return ['message' => 'Неверный пользователь'];
            }

            // Теперь используем userId из токена, а не из тела запроса
            $userId = $userIdFromToken;
            $orderName = $data['order_name'];
            $orderDate = $data['order_date'];
            $orderPrice = $data['order_price'];
            $orderCashback = $data['order_cashback'];
            $orderStatus = $data['order_status'];
            $orderMethod = $data['order_method'];

            Yii::$app->db->createCommand("SET TIMEZONE TO 'Europe/Moscow'")->execute();

            $dateTime = new \DateTime($orderDate, new \DateTimeZone('UTC'));
            $dateTime->setTimezone(new \DateTimeZone('Europe/Moscow'));
            $orderDate = $dateTime->format('Y-m-d H:i:s');

            $order = new Order();
            $order->user_id = $userId;
            $order->order_name = $orderName;
            $order->order_date = $orderDate;
            $order->order_price = $orderPrice;
            $order->order_cashback = $orderCashback;
            $order->order_status = $orderStatus;
            $order->payment_method = $orderMethod;

            if ($orderMethod === 'card' && isset($data['card_number'])) {
                $order->card_number = $data['card_number'];
            }

            if ($order->save()) {
                $user = User::findIdentity($userId);
                if ($user && $orderCashback > 0 && $orderMethod === "card") {
                    $user->updateCashback($orderCashback);
                }

                $code = $this->generateUniqueCode();
                $order->order_code = $code;
                $order->save(false);

                return [
                    'success' => true,
                    'order_id' => $order->id,
                    'order_code' => $code,
                ];
            } else {
                return ['success' => false, 'errors' => $order->errors];
            }
        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Неверный токен авторизации'];
        }
    }

    public function actionOrders()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Получаем токен из заголовка
        $authHeader = Yii::$app->request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Не передан токен'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey']; // Секретный ключ из конфигурации

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid ?? null;

            if (!$userId) {
                Yii::$app->response->statusCode = 401;
                return ['error' => 'Невалидный токен'];
            }

            // Получаем все заказы пользователя
            $orders = Order::find()
                ->where(['user_id' => $userId])
                ->with('orderStatus')
                ->asArray()
                ->all();

            // Обрабатываем каждый заказ
            $orders = array_map(function ($order) use ($userId) {
                $order['order_status'] = $order['orderStatus']['name'] ?? 'Неизвестно';
                unset($order['orderStatus']);

                // Проверка наличия отзыва
                $hasReview = UserReview::find()
                    ->where(['order_id' => $order['id'], 'user_id' => $userId])
                    ->exists();

                $order['has_review'] = !$hasReview;

                return $order;
            }, $orders);

            return $orders;

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Ошибка авторизации'];
        }
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

        $headers = Yii::$app->request->headers;
        $authHeader = $headers->get('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Отсутствует токен авторизации'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid;

            // Можно проверить существование пользователя, если надо
            $user = User::findOne($userId);
            if (!$user) {
                Yii::$app->response->statusCode = 401;
                return ['message' => 'Пользователь не найден'];
            }

            // Теперь ищем заказ по коду
            $order = Order::find()->where(['order_code' => $orderCode])->one();

            if ($order) {
                return ['status' => 'success', 'order_id' => $order->id];
            } else {
                return ['status' => 'error', 'message' => 'Заказ не найден'];
            }

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Неверный токен авторизации'];
        }
    }
    public function actionGetUserOrders()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $authHeader = Yii::$app->request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'JWT токен не предоставлен'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new \Firebase\JWT\Key($key, 'HS256'));
            $userId = $decoded->uid ?? null;

            if (!$userId) {
                Yii::$app->response->statusCode = 401;
                return ['error' => 'Невалидный токен'];
            }

            // Получаем пользователя, чтобы проверить is_admin
            $user = User::findOne($userId);
            if (!$user || $user->is_admin != 1) {
                Yii::$app->response->statusCode = 403;
                return ['error' => 'Доступ запрещён. Только администратор может видеть все заказы'];
            }

            // Если админ — выводим все заказы
            $orders = Order::find()
                ->with('orderStatus')
                ->all();

            $response = [];
            foreach ($orders as $order) {
                $response[] = [
                    'id' => $order->id,
                    'order_name' => $order->order_name,
                    'order_date' => $order->order_date,
                    'order_price' => $order->order_price,
                    'user_id' => $order->user_id,
                    'order_status_name' => $order->orderStatus->name,
                    'order_code' => $order->order_code,
                ];
            }

            return $response;

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Ошибка авторизации'];
        }
    }
    public function actionDelete($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // JWT проверка
        $authHeader = Yii::$app->request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'JWT токен не предоставлен'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid ?? null;

            if (!$userId) {
                Yii::$app->response->statusCode = 401;
                return ['error' => 'Невалидный токен'];
            }

            $order = Order::findOne($id);
            $user = User::findOne($userId);  // Получаем пользователя из БД

            if (!$user) {
                Yii::$app->response->statusCode = 403;
                return ['error' => 'Пользователь не найден'];
            }

            if ($order && ($user->is_admin == 1)) {
                Yii::$app->db->createCommand()
                    ->delete('reviews', ['order_id' => $order->id])
                    ->execute();

                $order->delete();

                return ['status' => 'success'];
            }

            return ['status' => 'error', 'message' => 'Order not found or access denied'];
        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Ошибка авторизации'];
        }
    }
    public function actionUpdateStatus($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $authHeader = Yii::$app->request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'JWT токен не предоставлен'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid ?? null;

            if (!$userId) {
                Yii::$app->response->statusCode = 401;
                return ['error' => 'Невалидный токен'];
            }

            // Получаем пользователя для проверки прав
            $user = User::findOne($userId);
            if (!$user || $user->is_admin != 1) {
                Yii::$app->response->statusCode = 403;
                return ['error' => 'Доступ запрещён: только админ может изменять статус заказа'];
            }

            $order = Order::findOne($id);
            if ($order && Yii::$app->request->isPost) {
                $newStatus = Yii::$app->request->post('status');
                $order->order_status = $newStatus;
                if ($order->save()) {
                    return ['status' => 'success'];
                } else {
                    return ['status' => 'error', 'message' => 'Ошибка при сохранении статуса'];
                }
            }

            return ['status' => 'error', 'message' => 'Неверный запрос или заказ не найден'];
        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Ошибка авторизации'];
        }
    }
    public function actionGetStatuses()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Проверка JWT
        $authHeader = Yii::$app->request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'JWT токен не предоставлен'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            JWT::decode($token, new \Firebase\JWT\Key($key, 'HS256'));

            $statuses = OrderStatus::getAllStatuses();

            return [
                'statuses' => array_map(function ($status) {
                    return [
                        'id' => $status->id,
                        'name' => $status->name,
                    ];
                }, $statuses)
            ];

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Ошибка авторизации'];
        }
    }
}
