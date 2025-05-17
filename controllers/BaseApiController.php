<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;

class BaseApiController extends Controller
{
    public function beforeAction($action)
    {
        $origin = Yii::$app->request->headers->get('Origin');
        $response = Yii::$app->response;
        $headers = $response->headers;

        // Разрешить GitHub Pages фронтенд
        if ($origin === 'https://d1very.github.io') {
            $headers->set('Access-Control-Allow-Origin', $origin);
            $headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, DELETE, PUT');
        }

        // Preflight-запросы
        if (Yii::$app->request->isOptions) {
            $response->statusCode = 200;
            $response->content = '';
            $response->send();
            return false;
        }

        return parent::beforeAction($action);
    }

    public function actions()
    {
        return [
            'options' => [
                'class' => 'yii\web\OptionsAction',
            ],
        ];
    }
}