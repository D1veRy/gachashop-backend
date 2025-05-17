<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'components' => [
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON
        ],
        'i18n' => [
            'translations' => [
                'yii/bootstrap5' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@yii/bootstrap5/messages',
                ],
            ],
        ],
        'timeZone' => 'Europe/Moscow',
        'request' => [
            'cookieValidationKey' => '2qth1u7lIgEbySpL',
            'enableCsrfValidation' => false,
            'enableCookieValidation' => false,
            'csrfParam' => '_csrf',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'session' => [
            'class' => 'yii\web\Session',
            'name' => 'PHPSESSID',
            'useCookies' => true,
            'cookieParams' => [
                'httpOnly' => true,
                'secure' => false,
            ],
            'timeout' => 86400,
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'enableSession' => false,
            'identityClass' => 'app\models\User',
            'loginUrl' => null,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning', 'info'],
                    'logFile' => '@runtime/logs/app.log',
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'class' => 'yii\web\UrlManager',
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => false,
            'rules' => [
                'games' => 'games/index',
                'catalog' => 'catalog/index',
                'products/delete/<id:\d+>' => 'products/delete',
                'GET products/<id:\d+>' => 'products/get-product',
                'GET products' => 'products/index',
                'POST products/create' => 'products/create',
                'products/update/<id:\d+>' => 'products/update',
                'categories' => 'categories/index',
                'GET /register' => 'register/index',
                'POST /register' => 'register/register',
                'POST /register/login' => 'register/login',
                'GET /user/get-user' => 'user/get-user',
                'POST user/check-secret-question' => 'user/check-secret-question',
                'POST user/reset-password' => 'user/reset-password',
                'GET /user/get-csrf-token' => 'user/get-csrf-token',
                'POST user/logout' => 'user/logout',
                'POST user/change-password' => 'user/change-password',
                'GET user/get-user-data' => 'user/get-user-data',
                'GET user/get-user-cashback' => 'user/get-user-cashback',
                'POST user/update-cashback' => 'user/update-cashback',
                'user/me' => 'user/me',
                'GET /user/get-by-id/<id:\d+>' => 'user/get-by-id',
                'GET order/count' => 'order/count',
                'POST order/create' => 'order/create',
                'GET order/get-orders' => 'order/get-orders',
                'GET order/get-statuses' => 'order/get-statuses',
                'order/update-status/<id:\d+>' => 'order/update-status',
                'order/delete/<id:\d+>' => 'order/delete',
                'GET order/get-order-id-by-code/<orderCode>' => 'order/get-order-id-by-code',
                'GET order/orders' => 'order/orders',
                'POST card/save' => 'card/save',
                'GET card/cards' => 'card/cards',
                'card/delete/<id:\d+>' => 'card/delete',
                'GET card/get-user-cards' => 'card/get-user-cards',
                'POST user-review/create' => 'user-review/create',
                'GET user-review/check' => 'user-review/check-review',
                'GET user-review/reviews' => 'user-review/reviews',
                'GET user-review/get-all' => 'user-review/get-all',
                'POST user-review/submit-reply' => 'user-review/submit-reply',
                'user-review/delete-reply/<id:\d+>' => 'user-review/delete-reply',
            ],
        ],
    ],
    'params' => $params,
];

// Для среды разработки добавляем debug и gii
if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        'allowedIPs' => [
            '127.0.0.1',
            '::1',
        ],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

// Для продакшн-среды убираем debug и gii
if (YII_ENV_PROD) {
    unset($config['modules']['debug']);
    unset($config['modules']['gii']);
}

return $config;
