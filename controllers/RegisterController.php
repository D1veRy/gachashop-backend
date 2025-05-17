<?php

namespace app\controllers;

use app\models\User;
use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\Cors;
use app\controllers\BaseApiController;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use yii\filters\auth\HttpBearerAuth;

class RegisterController extends BaseApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            'except' => ['login', 'register'],
        ];

        return $behaviors;
    }
    public function actionCsrfToken()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['csrfToken' => Yii::$app->request->getCsrfToken()];
    }

    public function actionIndex()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            $users = Yii::$app->db->createCommand('
                SELECT id, nickname, password_hash, secret_question, answer_secret_question
                    FROM public.users
            ')->queryAll();
            Yii::info('Fetched user data: ' . json_encode($users), __METHOD__);
            return $users;
        } catch (\Exception $e) {
            Yii::error("Failed to fetch user data: " . $e->getMessage());
            Yii::$app->response->statusCode = 400;
            return ['error' => 'Ошибка при получении пользовательских данных'];
        }
    }

    public function actionRegister()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Получаем данные из тела запроса в формате JSON
        $rawData = file_get_contents('php://input');
        $bodyData = json_decode($rawData, true);

        // Проверим, приходят ли данные
        $nickname = $bodyData['nickname'] ?? null;
        $password = $bodyData['password'] ?? null;
        $secretQuestion = $bodyData['secretQuestion'] ?? null;
        $answerSecretQuestion = $bodyData['answerSecretQuestion'] ?? null;
        $defaultAvatarUrl = 'https://res.cloudinary.com/dhicjidvw/image/upload/v1744728130/%D0%90%D0%B2%D0%B0%D1%82%D0%B0%D1%80_%D0%BD%D0%BE%D0%B2%D0%B8%D1%87%D0%BA%D0%BE%D0%B2_gnixd5.jpg';

        if (!$nickname || !$password || !$secretQuestion || !$answerSecretQuestion) {
            return [
                'status' => 'error',
                'message' => 'Отсутствуют поля для добавления в базу данных'
            ];
        }

        $existingUser = Yii::$app->db->createCommand('SELECT id FROM public.users WHERE nickname = :nickname')
            ->bindValue(':nickname', $nickname)
            ->queryOne();

        if ($existingUser) {
            return [
                'status' => 'error',
                'message' => 'Этот никнейм уже существует'
            ];
        }

        // Хешируем пароль
        $passwordHash = Yii::$app->security->generatePasswordHash($password);

        // Генерируем новый auth_key
        $authKey = Yii::$app->security->generateRandomString();

        try {
            // Вставка данных в таблицу
            $result = Yii::$app->db->createCommand()->insert('users', [
                'nickname' => $nickname,
                'password_hash' => $passwordHash,
                'secret_question' => $secretQuestion,
                'answer_secret_question' => $answerSecretQuestion,
                'avatar_url' => $defaultAvatarUrl,
                'auth_key' => $authKey,  // Сохраняем auth_key в базе
                'user_cashback' => 0,
                'is_admin' => 0,
            ])->execute();

            // Возвращаем CSRF токен в ответе
            return [
                'status' => 'success',
                'message' => 'Регистрация пользователя успешна!',
                'csrfToken' => Yii::$app->request->getCsrfToken(),  // Возвращаем CSRF токен
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Ошибка регистрации'
            ];
        }
    }

    public function actionLogin()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $rawData = file_get_contents('php://input');
        $bodyData = json_decode($rawData, true);

        $nickname = $bodyData['nickname'] ?? null;
        $password = $bodyData['password'] ?? null;

        if (!$nickname || !$password) {
            return [
                'status' => 'error',
                'message' => 'Никнейм или пароль не могут быть пустыми',
            ];
        }

        $user = User::findByLogin($nickname, $password);

        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'Неверный никнейм или пароль',
            ];
        }

        // Генерация JWT
        $key = Yii::$app->params['jwtSecretKey'];  // секретный ключ из конфига
        $payload = [
            'iss' => 'localhost',  // издатель
            'aud' => 'localhost',  // аудитория
            'iat' => time(),            // время выпуска
            'exp' => time() + 3600 * 24 * 30, // срок жизни 30 дней
            'uid' => $user->id,
            'nickname' => $user->nickname,
        ];

        $jwt = JWT::encode($payload, $key, 'HS256');

        return [
            'status' => 'success',
            'message' => 'Вы успешно вошли!',
            'token' => $jwt,
            'avatar_url' => $user->avatar_url,
        ];
    }
}
