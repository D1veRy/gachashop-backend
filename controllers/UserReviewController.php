<?php

namespace app\controllers;

use app\models\User;
use Yii;
use yii\web\Controller;
use app\models\UserReview;
use yii\data\ActiveDataProvider;
use yii\web\Response;
use app\controllers\BaseApiController;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use yii\web\UnauthorizedHttpException;
use yii\web\BadRequestHttpException;


class UserReviewController extends BaseApiController
{
    public function actionCreate()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $headers = Yii::$app->request->headers;
        $authHeader = $headers->get('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['status' => 'error', 'message' => 'Отсутствует токен авторизации'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid;

            if (!$userId) {
                Yii::$app->response->statusCode = 401;
                return ['status' => 'error', 'message' => 'Пользователь не авторизован'];
            }

            $request = Yii::$app->request;
            $reviewData = json_decode($request->getRawBody(), true);

            if ($reviewData && isset($reviewData['review'], $reviewData['order_id'])) {

                $userReview = new UserReview();
                $userReview->user_id = $userId; // Берём ID из JWT
                $userReview->review = $reviewData['review'];
                $userReview->created_at = date('Y-m-d H:i:s');
                $userReview->text = !empty($reviewData['text']) ? $reviewData['text'] : null;
                $userReview->order_id = $reviewData['order_id'];

                if ($userReview->save()) {
                    return ['status' => 'success'];
                } else {
                    return ['status' => 'error', 'errors' => $userReview->getErrors()];
                }
            }

            throw new BadRequestHttpException('Неверный запрос');

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['status' => 'error', 'message' => 'Неверный токен авторизации'];
        }
    }
    public function actionCheckReview()
    {
        $request = Yii::$app->request;
        $userId = $request->get('user_id');
        $orderId = $request->get('order_id');

        // Проверка, существует ли отзыв
        $existingReview = UserReview::find()
            ->where(['user_id' => $userId, 'order_id' => $orderId])
            ->one();

        if ($existingReview) {
            return ['status' => 'exists'];
        } else {
            return ['status' => 'not_exists'];
        }
    }
    public function actionReviews()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Фильтрация по количеству звёзд (если задано)
        $stars = Yii::$app->request->get('stars', 0);

        // Построение запроса с использованием связей моделей
        $query = UserReview::find()
            ->joinWith('orderRecord') // Объединяем таблицу orders
            ->with(['user']) // Загружаем связанные данные пользователя
            ->orderBy(['reviews.created_at' => SORT_DESC]);


        if ($stars > 0) {
            $query->andWhere(['review' => $stars]);
        }

        // Пагинация (по 10 записей на страницу)
        $page = Yii::$app->request->get('page', 1);
        $pageSize = 10;

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $pageSize,
                'page' => $page - 1, // Page начинается с 0
            ],
        ]);

        $reviews = $provider->getModels();
        $response = [];

        foreach ($reviews as $review) {
            $response[] = [
                'review_id' => $review->id,
                'rating' => $review->review,
                'review_text' => $review->text,
                'order_id' => $review->orderRecord->id,
                'review_date' => $review->created_at,
                'user' => $review->user ? [
                    'id' => $review->user->id,
                    'nickname' => $review->user->nickname,
                    'avatar_url' => $review->user->avatar_url,
                ] : null,
                'businessReply' => ($review->businessReply && $review->admin_id && $review->admin) ? [
                    'nickname' => $review->admin->nickname ?? 'Администратор',
                    'avatar_url' => $review->admin->avatar_url ?? '/default-admin-avatar.png',
                    'text' => $review->businessReply,
                ] : null,
            ];
        }

        return [
            'reviews' => $response,
        ];
    }
    public function actionGetAll()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Здесь можно дополнительно декодировать и проверить JWT,
        // если тебе нужен доступ к данным пользователя из токена

        // Например, можно получить токен так (опционально)
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
            // $decoded->uid и т.д. можно использовать для проверки прав

            $reviews = UserReview::find()->all();

            return array_map(function ($review) {
                return [
                    'id' => $review->id,
                    'user_id' => $review->user_id,
                    'review' => $review->review,
                    'text' => $review->text,
                    'order_id' => $review->order_id,
                    'created_at' => $review->created_at,
                    'admin_answer' => $review->businessReply ? [
                        'text' => $review->businessReply,
                        'admin_id' => $review->admin_id,
                    ] : null,
                ];
            }, $reviews);

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Неверный токен авторизации'];
        }
    }
    public function actionSubmitReply()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $request = Yii::$app->request;

        // Получаем JWT из заголовков и декодируем токен, чтобы получить userId
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['success' => false, 'message' => 'JWT токен не предоставлен'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($key, 'HS256'));
            $userId = $decoded->uid ?? null;

            if (!$userId) {
                Yii::$app->response->statusCode = 401;
                return ['success' => false, 'message' => 'Невалидный токен'];
            }

            // Проверяем, что пользователь — админ
            $user = User::findOne($userId);
            if (!$user || $user->is_admin != 1) {
                Yii::$app->response->statusCode = 403;
                return ['success' => false, 'message' => 'Доступ запрещён: только админ может оставлять ответы'];
            }

            $reviewId = $request->post('review_id');
            $text = $request->post('text');

            $review = UserReview::findOne($reviewId);
            if (!$review) {
                return ['success' => false, 'message' => 'Отзыв не найден'];
            }

            $review->businessReply = $text;
            $review->admin_id = $userId;

            if ($review->save()) {
                return [
                    'success' => true,
                    'data' => [
                        'text' => $review->businessReply,
                        'admin_id' => $review->admin_id,
                    ]
                ];
            }

            return ['success' => false, 'message' => 'Не удалось сохранить ответ'];

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['success' => false, 'message' => 'Ошибка авторизации: ' . $e->getMessage()];
        }
    }
    public function actionDeleteReply($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $request = Yii::$app->request;
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['success' => false, 'message' => 'JWT токен не предоставлен'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($key, 'HS256'));
            $userId = $decoded->uid ?? null;

            if (!$userId) {
                Yii::$app->response->statusCode = 401;
                return ['success' => false, 'message' => 'Невалидный токен'];
            }

            $user = User::findOne($userId);
            if (!$user || $user->is_admin != 1) {
                Yii::$app->response->statusCode = 403;
                return ['success' => false, 'message' => 'Доступ запрещён: только админ может удалять ответы'];
            }

            $review = UserReview::findOne($id);
            if (!$review) {
                return ['success' => false, 'message' => 'Отзыв не найден'];
            }

            // Дополнительно можно проверять, что админ удаляет свой ответ, если надо:
            // if ($review->admin_id !== $userId) {
            //     throw new UnauthorizedHttpException('You are not allowed to delete this reply');
            // }

            $review->businessReply = null;
            $review->admin_id = null;

            if ($review->save()) {
                return ['success' => true];
            }

            return ['success' => false, 'message' => 'Не удалось удалить ответ'];

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['success' => false, 'message' => 'Ошибка авторизации: ' . $e->getMessage()];
        }
    }
}
