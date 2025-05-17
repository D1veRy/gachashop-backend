<?php

namespace app\controllers;

use app\models\User;
use Yii;
use app\models\Product;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\Cors;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use app\controllers\BaseApiController;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ProductsController extends BaseApiController
{
    public function actionGetProduct($id)
    {
        // Выполняем запрос к базе данных для получения информации о товаре
        $product = Yii::$app->db->createCommand("SELECT * FROM public.products WHERE id = :id")
            ->bindValue(':id', $id)
            ->queryOne();

        // Если товар не найден, возвращаем ошибку
        if (!$product) {
            return $this->asJson(['status' => 'error', 'message' => 'Товар не найден']);
        }

        // Возвращаем информацию о товаре в формате JSON
        return $this->asJson($product);
    }

    public function actionIndex()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $products = Product::find()->all();
        return $products;
    }

    public function actionCreate()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $authHeader = Yii::$app->request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['success' => false, 'message' => 'JWT токен не предоставлен'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid ?? null;

            if (!$userId) {
                Yii::$app->response->statusCode = 401;
                return ['success' => false, 'message' => 'Невалидный токен'];
            }

            // Получаем пользователя
            $user = User::findOne($userId);
            if (!$user || $user->is_admin != 1) {
                Yii::$app->response->statusCode = 403;
                return ['success' => false, 'message' => 'Доступ запрещён. Только администратор может добавлять товары'];
            }

            // Получаем POST данные как массив
            $request = Yii::$app->request->post();

            $model = new Product();

            $model->name = $request['name'] ?? null;
            $model->description = $request['description'] ?? null;
            $model->image = $request['image'] ?? null;
            $model->price = $request['price'] ?? null;
            $model->type = $request['type'] ?? null;
            $model->game_id = $request['game_id'] ?? null;
            $model->quantity = $request['quantity'] ?? null;
            $model->days = $request['days'] ?? null;

            if ($model->validate() && $model->save()) {
                return [
                    'success' => true,
                    'message' => 'Продукт успешно добавлен',
                ];
            } else {
                Yii::error('Ошибка валидации: ' . json_encode($model->errors));
                return [
                    'success' => false,
                    'errors' => $model->errors,
                ];
            }

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['success' => false, 'message' => 'Ошибка авторизации: ' . $e->getMessage()];
        }
    }
    public function actionUpdate($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

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

            // Получаем пользователя и проверяем is_admin
            $user = User::findOne($userId);
            if (!$user || $user->is_admin != 1) {
                Yii::$app->response->statusCode = 403;
                return ['error' => 'Доступ запрещён. Только администратор может обновлять товары'];
            }

            // Проверка, что метод имитирует PUT
            if (Yii::$app->request->post('_method') === 'PUT') {
                $product = Product::findOne($id);

                if (!$product) {
                    Yii::$app->response->statusCode = 404;
                    return ['error' => 'Товар не найден'];
                }

                $post = Yii::$app->request->post();
                Yii::info('POST данные: ' . json_encode($post), __METHOD__);


                $product->name = $post['name'] ?? $product->name;
                $product->description = $post['description'] ?? $product->description;
                if (array_key_exists('price', $post)) {
                    $product->price = $post['price'];
                }
                $product->game_id = $post['game_id'] ?? $product->game_id;
                $product->type = $post['type'] ?? $product->type;
                if (array_key_exists('quantity', $post)) {
                    $product->quantity = $post['quantity'];
                }
                if (array_key_exists('days', $post)) {
                    $product->days = $post['days'];
                }
                $product->image = $post['image'] ?? $product->image;

                if (!$product->save()) {
                    Yii::error('Ошибка при сохранении продукта: ' . json_encode($product->errors), __METHOD__);
                    return ['error' => 'Ошибка при обновлении товара'];
                }

                return ['success' => 'Товар обновлен'];
            } else {
                Yii::$app->response->statusCode = 400;
                return ['error' => 'Неправильный метод запроса'];
            }

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Ошибка авторизации: ' . $e->getMessage()];
        }
    }
    public function actionDelete($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // Получаем заголовок Authorization
        $authHeader = Yii::$app->request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['status' => 'error', 'message' => 'JWT токен не предоставлен'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            // Декодируем токен
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid ?? null;

            if (!$userId) {
                Yii::$app->response->statusCode = 401;
                return ['status' => 'error', 'message' => 'Невалидный токен'];
            }

            // ✅ Получаем пользователя и проверяем is_admin
            $user = User::findOne($userId);
            if (!$user || $user->is_admin != 1) {
                Yii::$app->response->statusCode = 403;
                return ['status' => 'error', 'message' => 'Доступ запрещён. Только админ может удалять товары'];
            }

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['status' => 'error', 'message' => 'Ошибка авторизации: ' . $e->getMessage()];
        }

        // Проверяем, что это POST с _method=DELETE
        if (Yii::$app->request->isPost && Yii::$app->request->post('_method') === 'DELETE') {
            $product = Product::findOne($id);

            if ($product === null) {
                throw new NotFoundHttpException("Товар не найден.");
            }

            if ($product->delete()) {
                return ['status' => 'success', 'message' => 'Товар успешно удалён'];
            } else {
                return ['status' => 'error', 'message' => 'Не удалось удалить товар'];
            }
        } else {
            throw new BadRequestHttpException("Неверный метод запроса.");
        }
    }
}
