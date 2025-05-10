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

class UserController extends Controller
{
    public function actionGetCsrfToken()
    {
        // Возвращаем CSRF токен
        return $this->asJson([
            'csrfToken' => Yii::$app->request->getCsrfToken()
        ]);
    }

    public function behaviors()
    {
        return [
            'corsFilter' => [
                'class' => \yii\filters\Cors::class,
                'cors' => [
                    'Origin' => ['https://d1very.github.io'],  // Доверенный источник
                    'Access-Control-Request-Method' => ['GET', 'POST', 'OPTIONS'],  // Разрешенные методы
                    'Access-Control-Allow-Headers' => ['Content-Type', 'Authorization', 'X-CSRF-Token'],  // Разрешаем заголовки
                    'Access-Control-Allow-Credentials' => true,  // Разрешаем отправку куки
                    'Access-Control-Max-Age' => 3600,  // Кэширование preflight
                    'Access-Control-Allow-Origin' => ['https://d1very.github.io'],  // Разрешаем доступ с фронтенда
                ],
            ],
        ];
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

    public function actionGetUserCashback($user_id)
    {
        Yii::info("Запрос на получение кэшбека для пользователя: " . $user_id, __METHOD__); // Логируем запрос
        // Получаем данные пользователя
        $user = User::findOne($user_id);
        if ($user) {
            Yii::info("Кэшбек найден: " . $user->user_cashback, __METHOD__);  // Логируем успешный ответ
            return $this->asJson(['user_cashback' => $user->user_cashback]);
        } else {
            Yii::info("Пользователь не найден: " . $user_id, __METHOD__); // Логируем ошибку
            return $this->asJson(['error' => 'Пользователь не найден']);
        }
    }

    public function actionUpdate()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Получаем ID пользователя из параметров запроса
        $userId = Yii::$app->request->get('userId');
        if (!$userId) {
            throw new BadRequestHttpException('User ID is required');
        }

        // Ищем пользователя в базе данных
        $user = User::findOne($userId);
        if (!$user) {
            throw new BadRequestHttpException('User not found');
        }

        // Получаем данные из тела запроса
        $data = Yii::$app->request->post();

        if (isset($data['avatar_url'])) {
            $user->avatar_url = $data['avatar_url'];
        }
        if (isset($data['nickname'])) {
            $user->nickname = $data['nickname'];
        }

        // Сохраняем изменения
        if ($user->save()) {
            return [
                'success' => true,
                'message' => 'User updated successfully',
                'user' => $user
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to update user data',
                'errors' => $user->getErrors()
            ];
        }
    }

    public function actionChangePassword()
    {
        $user = Yii::$app->user->identity;  // Получаем текущего аутентифицированного пользователя через сессию

        if (!$user) {
            Yii::$app->response->statusCode = 401;
            return [
                'status' => 'error',
                'message' => 'Пользователь не авторизован',
            ];
        }

        // Логика изменения пароля
        $currentPassword = Yii::$app->request->post('currentPassword');
        $newPassword = Yii::$app->request->post('newPassword');

        if (!$user->validatePassword($currentPassword)) {
            throw new BadRequestHttpException('Неверный текущий пароль');
        }

        // Обновляем пароль
        $user->setPassword($newPassword);
        if (!$user->save()) {
            throw new BadRequestHttpException('Не удалось обновить пароль');
        }

        return json_encode(['status' => 'success', 'message' => 'Пароль успешно изменен']);
    }

    public function actionGetUserData()
    {
        $user = Yii::$app->user->identity;

        if ($user) {
            return $this->asJson([
                'id' => $user->id,
                'username' => $user->nickname,
                'is_admin' => $user->is_admin,
            ]);
        }

        return $this->asJson(null);
    }

    public function actionView($id)
    {
        // Получаем пользователя по ID
        $user = User::findOne($id);

        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

        // Возвращаем данные пользователя (например, аватар и никнейм)
        return $this->asJson([
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar_url' => $user->avatar_url, // Предполагается, что это поле есть в таблице users
        ]);
    }

    /**
     * Возвращает текущего авторизованного пользователя (если сессия активна)
     */
    public function actionMe()
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->response->statusCode = 401;
            return ['message' => 'Пользователь не авторизован'];
        }

        $user = Yii::$app->user->identity;

        return [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar_url' => $user->avatar_url,
        ];
    }
}