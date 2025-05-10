<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class CategoriesController extends Controller
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
            $options = Yii::$app->db->createCommand('
               SELECT
                    categories.category_name AS catname,
                    option_name AS optname
                FROM 
                    public.options
                INNER JOIN 
                    categories ON categories.id = options.category_id
                ORDER BY 
                    options.id;
            ')->queryAll();

            return $options;
        } catch (\Exception $e) {
            Yii::error("Failed to fetch products: " . $e->getMessage());
            Yii::$app->response->statusCode = 400;
            return ['error' => 'Failed to fetch products'];
        }
    }
}