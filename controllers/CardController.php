<?php

namespace app\controllers;

use app\models\User;
use Yii;
use yii\web\Controller;
use app\models\Card;
use app\controllers\BaseApiController;
use yii\filters\auth\HttpBearerAuth;
use yii\web\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use yii\web\UnauthorizedHttpException;

class CardController extends BaseApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
        ];

        return $behaviors;
    }
    public function actionSave()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $headers = Yii::$app->request->headers;
        $authHeader = $headers->get('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return ['status' => 'error', 'message' => 'Отсутствует токен'];
        }

        $token = $matches[1];
        $user = User::findIdentityByAccessToken($token);

        Yii::info('User object: ' . var_export($user, true), __METHOD__);

        if (!$user) {
            return ['status' => 'error', 'message' => 'Неверный токен'];
        }

        $data = json_decode(Yii::$app->request->getRawBody(), true);

        if (isset($data['save_card']) && $data['save_card']) {
            $card = new Card();
            $card->card_name = $data['card_name'] ?? null;
            $card->card_number = $data['card_number'] ?? null;
            $card->card_date = $data['card_date'] ?? null;
            $card->user_id = $user->id;

            Yii::info('Перед сохранением карты: ' . var_export($card->attributes, true), __METHOD__);

            if ($card->save()) {
                return ['status' => 'success'];
            } else {
                return ['status' => 'error', 'message' => 'Ошибка при сохранении карты', 'errors' => $card->errors];
            }
        }

        return ['status' => 'no_save'];
    }


    private function getCardType($cardNumber)
    {
        // Проверяем первые цифры номера карты и определяем тип
        $firstDigit = substr($cardNumber, 0, 1);
        $bin = substr($cardNumber, 0, 6);  // Первые 6 цифр карты

        if ($bin === '2200') {
            return 'МИР';
        } elseif ($firstDigit === '4') {
            return 'VISA';
        } elseif ($firstDigit === '5') {
            return 'Mastercard';
        } else {
            return 'Неизвестно';
        }
    }
    public function actionGetUserCards()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

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

            $cards = Yii::$app->db->createCommand('
            SELECT id, card_name, card_number, user_id 
            FROM cards 
            WHERE user_id = :user_id
        ')
                ->bindValue(':user_id', $userId)
                ->queryAll();
            Yii::error("queryAll вернул: " . print_r($cards, true));

            return [
                'cards' => array_map(function ($card) {
                    Yii::error("Обработка карты: " . print_r($card, true));
                    $cardType = $this->getCardType($card['card_number']);
                    return [
                        'id' => $card['id'],
                        'cardType' => $cardType,
                        'lastFour' => substr($card['card_number'], -4),
                    ];
                }, $cards),
            ];

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Неверный токен авторизации'];
        }
    }
    // Метод для получения списка карт пользователя
    public function actionCards()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Получаем токен из заголовка
        $authHeader = Yii::$app->request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['status' => 'error', 'message' => 'Токен не предоставлен'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid ?? null;

            if (!$userId) {
                Yii::$app->response->statusCode = 401;
                return ['status' => 'error', 'message' => 'Неверный токен'];
            }

            // Находим карты по user_id
            $cards = Card::findAll(['user_id' => $userId]);

            if (empty($cards)) {
                return [
                    'status' => 'error',
                    'message' => 'Карты не найдены',
                ];
            }

            return [
                'status' => 'success',
                'cards' => $cards,
            ];
        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['status' => 'error', 'message' => 'Ошибка авторизации: ' . $e->getMessage()];
        }
    }
    // Метод для удаления карты по ID
    public function actionDelete($id)
    {
        $card = Card::findOne($id);
        $userId = $this->getUserIdFromToken();
        if ($card && $card->user_id == $userId) {
            $card->delete();
            return $this->asJson(['success' => true, 'message' => 'Карта удалена']);
        } else {
            throw new \yii\web\NotFoundHttpException('Карта не найдена или у вас нет прав на удаление этой карты');
        }
    }

}
