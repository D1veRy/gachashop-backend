<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\controllers\BaseApiController;

class GamesController extends BaseApiController
{
    public function actionIndex()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $products = Yii::$app->db->createCommand('
            SELECT
                image,
                name,
                id
            FROM
                games
            ORDER BY
                id;
        ')->queryAll();

            return $products;
        } catch (\Exception $e) {
            Yii::error("Failed to fetch products: " . $e->getMessage());
            Yii::$app->response->statusCode = 500;

            // !!! ВРЕМЕННО выводим текст ошибки в ответ
            return ['error' => 'Internal Server Error'];
        }
    }
}
