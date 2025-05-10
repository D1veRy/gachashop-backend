<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class CatalogController extends Controller
{
    public function behaviors()
    {
        return [
            'corsFilter' => [
                'class' => \yii\filters\Cors::class,
                'cors' => [
                    //Прописываем адрес нашего клиента
                    'Origin' => ['https://d1very.github.io'],
                    //Добавляем необходимыве заголовки
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT'],
                    'Access-Control-Request-Headers' => ['*'],
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Max-Age' => 3600
                ],

            ],
        ];
    }

    public function actionIndex()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            $catalog = Yii::$app->db->createCommand('
                SELECT
                    *
                FROM
                    public.products
                ORDER BY
                    id;
            ')->queryAll();

            return $catalog;
        } catch (\Exception $e) {
            Yii::error("Failed to fetch products: " . $e->getMessage());
            Yii::$app->response->statusCode = 400;
            return ['error' => 'Failed to fetch products'];
        }
    }
}