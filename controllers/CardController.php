<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\Card; // Модель карты

class CardController extends Controller
{

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::class,
            'cors' => [
                'Origin' => ['https://d1very.github.io'],  // Указывайте правильный источник вашего фронта
                'Access-Control-Request-Method' => ['POST', 'OPTIONS', 'DELETE'],  // Разрешаем POST, OPTIONS и DELETE
                'Access-Control-Allow-Credentials' => true,
                'Access-Control-Allow-Headers' => ['Content-Type', 'Authorization', 'X-CSRF-Token'],  // Добавляем нужные заголовки
            ],
        ];

        return $behaviors;
    }
    public function actionSave()
    {
        // Проверка CSRF токена
        if (!Yii::$app->request->validateCsrfToken()) {
            return $this->asJson(['status' => 'error', 'message' => 'CSRF токен не совпадает']);
        }

        $request = Yii::$app->request;
        if ($request->isPost) {
            $data = json_decode($request->getRawBody(), true); // Получаем JSON данные

            if (isset($data['save_card']) && $data['save_card']) {
                $card = new Card();
                $card->card_name = $data['card_name'];
                $card->card_number = $data['card_number'];
                $card->card_date = $data['card_date'];
                $card->user_id = Yii::$app->user->id; // Предполагается, что пользователь авторизован

                if ($card->save()) {
                    return $this->asJson(['status' => 'success']);
                } else {
                    return $this->asJson(['status' => 'error', 'message' => 'Ошибка при сохранении карты']);
                }
            }
        }

        return $this->asJson(['status' => 'no_save']);
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

    public function actionGetUserCards($user_id)
    {
        // Выполняем запрос для получения всех карт пользователя
        $cards = Yii::$app->db->createCommand('
            SELECT id, card_name, card_number, user_id 
            FROM public.cards 
            WHERE user_id = :user_id
        ')
            ->bindValue(':user_id', $user_id)
            ->queryAll();

        // Форматируем данные карт с типом карты
        return [
            'cards' => array_map(function ($card) {
                // Определяем тип карты
                $cardType = $this->getCardType($card['card_number']);

                return [
                    'id' => $card['id'],
                    'cardType' => $cardType,
                    'lastFour' => substr($card['card_number'], -4), // Последние 4 цифры карты
                ];
            }, $cards),
        ];
    }

    // Метод для получения списка карт пользователя
    public function actionCards($user_id)
    {
        // Ищем карты, принадлежащие пользователю
        $cards = Card::findAll(['user_id' => $user_id]);

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
    }

    // Метод для удаления карты по ID
    public function actionDelete($id)
    {
        $card = Card::findOne($id);
        if ($card && $card->user_id == Yii::$app->user->id) {
            $card->delete();
            return $this->asJson(['success' => true, 'message' => 'Карта удалена']);
        } else {
            throw new \yii\web\NotFoundHttpException('Карта не найдена или у вас нет прав на удаление этой карты');
        }
    }

}