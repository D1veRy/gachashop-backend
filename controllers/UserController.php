<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\models\User;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;
use yii\web\NotFoundHttpException;
use app\controllers\BaseApiController;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use yii\filters\auth\HttpBearerAuth;

class UserController extends BaseApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
        ];

        return $behaviors;
    }

    public function actionGetUser()
    {
        if (Yii::$app->user->isGuest) {
            // Если пользователь не авторизован, возвращаем ошибку
            return $this->asJson([
                'error' => 'User not logged in'
            ]);
        }

        $userId = Yii::$app->user->id; // Получаем ID пользователя из сессии
        $user = Yii::$app->db->createCommand("SELECT id, nickname, avatar_url FROM public.users WHERE id = :id")
            ->bindValue(':id', $userId)
            ->queryOne();

        if (!$user) {
            return $this->asJson(['error' => 'User not found']);
        }

        return $this->asJson([
            'id' => $user['id'],
            'nickname' => $user['nickname'],
            'avatar_url' => $user['avatar_url'],
        ]);
    }

    public function actionLogout()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            // Проверка CSRF токена (через сессионный куки по умолчанию)
            Yii::$app->user->logout();
            Yii::$app->session->destroy();
        } catch (\Exception $e) {
            Yii::error("Error during logout: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Ошибка при завершении сессии',
            ];
        }
    }

    public function actionCheckSecretQuestion()
    {
        $request = Yii::$app->request;
        $body = json_decode($request->getRawBody(), true);

        $nickname = $body['nickname'] ?? null;
        $answer = $body['answer'] ?? null;

        if (!$nickname || !$answer) {
            return $this->asJson(['status' => 'error', 'message' => 'Отсутствует никнейм или ответ']);
        }

        $user = Yii::$app->db->createCommand("SELECT answer_secret_question FROM public.users WHERE nickname = :nickname")
            ->bindValue(':nickname', $nickname)
            ->queryOne();

        if ($user && $user['answer_secret_question'] === $answer) {
            return $this->asJson(['status' => 'success']);
        }

        return $this->asJson(['status' => 'error', 'message' => 'Неправильный ответ']);
    }
    public function actionResetPassword()
    {
        $request = Yii::$app->request;
        $body = json_decode($request->getRawBody(), true);

        $nickname = $body['nickname'] ?? null;
        $newPassword = $body['newPassword'] ?? null;

        if (!$nickname || !$newPassword) {
            return $this->asJson(['status' => 'error', 'message' => 'Отсутствуют данные для сброса пароля']);
        }

        // Запрашиваем данные пользователя по никнейму
        $user = Yii::$app->db->createCommand("SELECT * FROM public.users WHERE nickname = :nickname")
            ->bindValue(':nickname', $nickname)
            ->queryOne();

        if ($user) {
            // Проверяем, совпадает ли новый пароль с текущим
            if (Yii::$app->security->validatePassword($newPassword, $user['password_hash'])) {
                return $this->asJson(['status' => 'error', 'message' => 'Новый пароль не должен совпадать с предыдущим']);
            }

            // Генерация нового хеша пароля
            $passwordHash = Yii::$app->security->generatePasswordHash($newPassword);

            // Обновляем пароль в базе данных
            Yii::$app->db->createCommand()
                ->update('public.users', ['password_hash' => $passwordHash], ['nickname' => $nickname])
                ->execute();

            return $this->asJson(['status' => 'success', 'message' => 'Пароль успешно изменён']);
        }

        return $this->asJson(['status' => 'error', 'message' => 'Пользователь не найден']);
    }
    public function actionUpdateCashback()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        // Получаем "сырые" данные
        $rawData = $request->getRawBody();
        $data = json_decode($rawData, true);

        Yii::info('Полученные данные: ' . json_encode($data));  // Логируем полученные данные

        if (!isset($data['user_id'], $data['cashback_amount'])) {
            return ['success' => false, 'error' => 'Не все необходимые поля переданы'];
        }

        $userId = $data['user_id'];
        $cashbackAmount = (float) $data['cashback_amount'];  // Преобразуем в float
        Yii::info('Сумма кэшбека для обновления: ' . $cashbackAmount);

        // Находим пользователя
        $user = User::findIdentity($userId);

        if (!$user) {
            Yii::error('Пользователь с ID ' . $userId . ' не найден');
            return ['success' => false, 'error' => 'Пользователь не найден'];
        }

        // Обновляем кэшбек (прибавляем или вычитаем баллы)
        $user->user_cashback += $cashbackAmount;

        // Сохраняем изменения в базе данных
        if ($user->save()) {
            Yii::info('Кэшбек пользователя обновлен: ' . $user->user_cashback, __METHOD__);
            return ['success' => true, 'message' => 'Кэшбек успешно обновлен'];
        } else {
            Yii::error('Ошибка при обновлении кэшбека пользователя');
            return ['success' => false, 'error' => 'Ошибка при обновлении кэшбека'];
        }
    }
    public function actionGetUserCashback()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $headers = Yii::$app->request->headers;
        $authHeader = $headers->get('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Отсутствует токен авторизации'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid;

            $user = User::findOne($userId);
            if (!$user) {
                Yii::$app->response->statusCode = 401;
                return ['message' => 'Пользователь не найден'];
            }

            Yii::info("Кэшбек найден: " . $user->user_cashback, __METHOD__);
            return ['user_cashback' => $user->user_cashback];
        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Неверный токен авторизации'];
        }
    }
    public function actionUpdate()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $headers = Yii::$app->request->headers;
        $authHeader = $headers->get('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Отсутствует токен авторизации'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid;

            $user = User::findOne($userId);
            if (!$user) {
                Yii::$app->response->statusCode = 401;
                return ['message' => 'Пользователь не найден'];
            }

            // Получаем JSON тело запроса
            $data = json_decode(Yii::$app->request->getRawBody(), true);

            if (isset($data['avatar_url'])) {
                $user->avatar_url = $data['avatar_url'];
            }
            if (isset($data['nickname'])) {
                $user->nickname = $data['nickname'];
            }

            if ($user->save()) {
                return [
                    'success' => true,
                    'message' => 'User updated successfully',
                    'user' => $user,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update user data',
                    'errors' => $user->getErrors(),
                ];
            }

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Неверный токен авторизации'];
        }
    }
    public function actionChangePassword()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Получаем и проверяем JWT из заголовка
        $authHeader = Yii::$app->request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['status' => 'error', 'message' => 'JWT токен не предоставлен'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid ?? null;

            if (!$userId) {
                Yii::$app->response->statusCode = 401;
                return ['status' => 'error', 'message' => 'Невалидный токен'];
            }

            $user = User::findOne($userId);
            if (!$user) {
                Yii::$app->response->statusCode = 404;
                return ['status' => 'error', 'message' => 'Пользователь не найден'];
            }

            // Получаем данные запроса
            $data = json_decode(Yii::$app->request->getRawBody(), true);
            $currentPassword = $data['currentPassword'] ?? '';
            $newPassword = $data['newPassword'] ?? '';

            if (!$user->validatePassword($currentPassword)) {
                Yii::$app->response->statusCode = 400;
                return ['status' => 'error', 'message' => 'Неверный текущий пароль'];
            }

            $user->setPassword($newPassword);
            if (!$user->save()) {
                Yii::$app->response->statusCode = 500;
                return ['status' => 'error', 'message' => 'Не удалось обновить пароль', 'errors' => $user->getErrors()];
            }

            return ['status' => 'success', 'message' => 'Пароль успешно изменен'];

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['status' => 'error', 'message' => 'Ошибка авторизации: ' . $e->getMessage()];
        }
    }
    public function actionGetUserData()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $headers = Yii::$app->request->headers;
        $authHeader = $headers->get('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Отсутствует токен авторизации'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid;

            $user = User::findOne($userId);

            if (!$user) {
                Yii::$app->response->statusCode = 401;
                return ['message' => 'Пользователь не найден'];
            }

            return [
                'id' => $user->id,
                'username' => $user->nickname,
                'is_admin' => $user->is_admin,
            ];

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Неверный токен авторизации'];
        }
    }
    /**
     * Возвращает текущего авторизованного пользователя (если сессия активна)
     */
    public function actionMe()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $headers = Yii::$app->request->headers;
        $authHeader = $headers->get('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Отсутствует токен авторизации'];
        }

        $token = $matches[1];
        $key = Yii::$app->params['jwtSecretKey'];

        try {
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            $userId = $decoded->uid;

            // Получаем пользователя по ID
            $user = User::findOne($userId);
            if (!$user) {
                Yii::$app->response->statusCode = 401;
                return ['message' => 'Пользователь не найден'];
            }

            return [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'avatar_url' => $user->avatar_url,
            ];

        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Неверный токен авторизации'];
        }
    }
    public function actionGetById($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $user = User::findOne($id);
        if (!$user) {
            Yii::$app->response->statusCode = 404;
            return ['message' => 'Пользователь не найден'];
        }

        return [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar_url' => $user->avatar_url,
            // добавь что нужно
        ];
    }
}
