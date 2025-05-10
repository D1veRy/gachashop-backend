<?php

namespace app\controllers;

use Yii;
use app\models\Product;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\Cors;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;

class ProductsController extends Controller
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
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request->post();

        Yii::info('Полученные данные: ' . json_encode($request), __METHOD__);

        $model = new Product();

        $model->name = $request['name'] ?? null;
        $model->description = $request['description'] ?? null;
        $model->image = $request['image'] ?? null; // Передаем ссылку на изображение
        $model->price = $request['price'] ?? null;
        $model->type = $request['type'] ?? null; // sub (подписка) или pack (пак)
        $model->game_id = $request['game_id'] ?? null;
        $model->quantity = isset($request['quantity']) ? $request['quantity'] : null; // Для паков
        $model->days = isset($request['days']) ? $request['days'] : null; // Для подписок

        if ($model->validate()) {
            if ($model->save()) {
                return [
                    'success' => true,
                    'message' => 'Продукт успешно добавлен',
                ];
            } else {
                return [
                    'success' => false,
                    'errors' => $model->errors,
                ];
            }
        } else {
            \Yii::error('Ошибка валидации: ' . json_encode($model->errors));
            return [
                'success' => false,
                'errors' => $model->errors,
            ];
        }
    }

    public function actionUpdate($id)
    {
        if (Yii::$app->request->post('_method') === 'PUT') {
            $product = Product::findOne($id);

            if (!$product) {
                return $this->asJson(['error' => 'Товар не найден']);
            }

            // Логируем, что мы получаем на сервере
            Yii::info('Обновление продукта: ' . json_encode(Yii::$app->request->post()), __METHOD__);

            // Заполняем модель новыми данными
            $product->name = Yii::$app->request->post('name');
            $product->description = Yii::$app->request->post('description');
            $product->price = Yii::$app->request->post('price');
            $product->game_id = Yii::$app->request->post('game_id');
            $product->type = Yii::$app->request->post('type');
            $product->quantity = Yii::$app->request->post('quantity');
            $product->days = Yii::$app->request->post('days');
            $product->image = Yii::$app->request->post('image');

            // Логируем перед сохранением
            Yii::info('Продукт перед сохранением: ' . json_encode($product), __METHOD__);

            // Пытаемся сохранить
            if (!$product->save()) {
                // Логируем ошибку, если она есть
                Yii::error('Ошибка при сохранении продукта: ' . json_encode($product->errors), __METHOD__);
                return $this->asJson(['error' => 'Ошибка при обновлении товара']);
            }

            return $this->asJson(['success' => 'Товар обновлен']);
        } else {
            return $this->asJson(['error' => 'Неправильный метод запроса']);
        }
    }
    public function actionDelete($id)
    {
        // Проверяем, пришел ли метод DELETE
        if (\Yii::$app->request->isPost && \Yii::$app->request->post('_method') === 'DELETE') {
            // Находим товар по ID
            $product = Product::findOne($id);

            // Если товар не найден
            if ($product === null) {
                throw new NotFoundHttpException("Товар не найден.");
            }

            // Удаляем товар
            if ($product->delete()) {
                return $this->asJson(['status' => 'success', 'message' => 'Товар успешно удалён']);
            } else {
                return $this->asJson(['status' => 'error', 'message' => 'Не удалось удалить товар']);
            }
        } else {
            throw new BadRequestHttpException("Неверный метод запроса.");
        }
    }

}
