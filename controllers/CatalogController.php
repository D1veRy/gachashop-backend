<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\controllers\BaseApiController;

class CatalogController extends BaseApiController
{
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
