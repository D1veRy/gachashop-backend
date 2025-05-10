<?php

namespace app\controllers;

use Yii;
use app\models\UserIdentity;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\Cors;

class RegisterController extends Controller
{
    public function behaviors()
    {
        return [
            'corsFilter' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => ['https://d1very.github.io'],  // Фронтенд-адрес
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Allow-Methods' => ['GET', 'POST', 'OPTIONS'],
                    'Access-Control-Allow-Headers' => ['Content-Type', 'Authorization', 'X-CSRF-Token'],
                ],
            ],
        ];
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

        // Получаем сырые данные из запроса
        $rawData = file_get_contents('php://input');
        Yii::info('Raw POST data: ' . $rawData, __METHOD__);

        // Декодируем JSON в массив
        $bodyData = json_decode($rawData, true);
        Yii::info('Decoded POST data: ' . print_r($bodyData, true), __METHOD__);

        // Получаем никнейм и пароль
        $nickname = $bodyData['nickname'] ?? null;
        $password = $bodyData['password'] ?? null;

        // Проверяем, что оба поля переданы
        if (!$nickname || !$password) {
            return [
                'status' => 'error',
                'message' => 'Никнейм или пароль не могут быть пустыми',
            ];
        }

        // Находим пользователя по никнейму
        $user = UserIdentity::findByLogin($nickname, $password);

        // Если пользователь не найден, возвращаем ошибку
        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'Неверный никнейм или пароль',
            ];
        }

        // Логируем пользователя
        Yii::info('User object: ' . print_r($user, true), __METHOD__);

        // Вход пользователя, сохраняем его в сессии
        Yii::$app->user->login($user, 3600 * 24 * 30); // 30 дней
        Yii::$app->session->close();

        // После успешного входа возвращаем новый CSRF токен
        return [
            'status' => 'success',
            'message' => 'Вы успешно вошли!',
            'avatar_url' => $user->avatar_url,  // Возвращаем URL аватарки
            'csrfToken' => Yii::$app->request->getCsrfToken(),  // Новый CSRF токен
        ];
    }


}