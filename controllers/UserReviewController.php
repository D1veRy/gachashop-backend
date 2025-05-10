<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\UserReview;
use yii\data\ActiveDataProvider;
use yii\web\Response;


class UserReviewController extends Controller
{
    public function behaviors()
    {
        return [
            'corsFilter' => [
                'class' => \yii\filters\Cors::class,
                'cors' => [
                    'Origin' => ['https://d1very.github.io'],  // Доверенный источник
                    'Access-Control-Request-Method' => ['GET', 'POST', 'OPTIONS'],  // Разрешенные методы
                    'Access-Control-Allow-Headers' => ['Content-Type', 'Authorization', 'X-CSRF-Token'],  // Разрешаем заголовки
                    'Access-Control-Allow-Credentials' => true,  // Разрешаем отправку куки
                    'Access-Control-Max-Age' => 3600,  // Кэширование preflight
                    'Access-Control-Allow-Origin' => ['https://d1very.github.io'],  // Разрешаем доступ с фронтенда
                ],
            ],
        ];
    }
    public function actionCreate()
    {
        $request = Yii::$app->request;
        $reviewData = json_decode($request->getRawBody(), true);

        if ($reviewData && isset($reviewData['user_id'], $reviewData['review'], $reviewData['order_id'])) {

            $userReview = new UserReview();
            $userReview->user_id = $reviewData['user_id'];
            $userReview->review = $reviewData['review'];
            $userReview->created_at = date('Y-m-d H:i:s');
            $userReview->text = !empty($reviewData['text']) ? $reviewData['text'] : null;
            $userReview->order_id = $reviewData['order_id'];

            if ($userReview->save()) {
                return $this->asJson(['status' => 'success']);
            } else {
                return ['status' => 'error', 'errors' => $userReview->getErrors()];
            }
        }

        throw new \yii\web\BadRequestHttpException('Неверный запрос');
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
    }

    public function actionSubmitReply()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        $reviewId = $request->post('review_id');
        $adminId = $request->post('admin_id');
        $text = $request->post('text');

        $review = UserReview::findOne($reviewId);

        if (!$review) {
            return ['success' => false, 'message' => 'Review not found'];
        }

        $review->businessReply = $text;
        $review->admin_id = $adminId;

        if ($review->save()) {
            return [
                'success' => true,
                'data' => [
                    'text' => $review->businessReply,
                    'admin_id' => $review->admin_id,
                ]
            ];
        }

        return ['success' => false, 'message' => 'Failed to save reply'];
    }
    public function actionDeleteReply($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $review = UserReview::findOne($id);
        if (!$review) {
            return ['success' => false, 'message' => 'Review not found'];
        }

        $review->businessReply = null;
        $review->admin_id = null;

        if ($review->save()) {
            return ['success' => true];
        }

        return ['success' => false, 'message' => 'Failed to delete reply'];
    }
}