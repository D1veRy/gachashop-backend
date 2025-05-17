<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\controllers\BaseApiController;

class CategoriesController extends BaseApiController
{
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
