<?php

namespace app\models;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use yii\db\ActiveRecord;
use Yii;

class User extends ActiveRecord implements \yii\web\IdentityInterface
{
    public static function tableName()
    {
        return 'users';  // Указываем имя таблицы
    }

    public function attributes()
    {
        return array_merge(parent::attributes(), ['user_cashback']);
    }

    public function rules()
    {
        return [
            [['user_cashback'], 'number'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id)
    {
        return static::findOne($id); // Загрузка из базы данных
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        Yii::info("JWT токен для проверки: $token", __METHOD__);

        try {
            $key = Yii::$app->params['jwtSecretKey'];
            $decoded = JWT::decode($token, new Key($key, 'HS256'));

            Yii::info("JWT успешно декодирован, uid: {$decoded->uid}", __METHOD__);

            return static::findOne($decoded->uid);
        } catch (\Exception $e) {
            Yii::warning("Ошибка декодирования JWT: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash); // password_hash - поле для хеша
    }

    /**
     * Устанавливает кешбек для пользователя
     *
     * @param float $cashback сумма кешбека
     * @return bool результат выполнения
     */
    public function updateCashback($cashback)
    {
        $currentCashback = (float) $this->user_cashback;
        $newCashback = $currentCashback + $cashback;
        $this->user_cashback = $newCashback;

        if (!$this->save()) {
            Yii::error("Ошибка при сохранении кэшбека: " . json_encode($this->errors), __METHOD__);
            return false;
        } else {
            Yii::info('Кэшбек обновлен успешно, новый кэшбек: ' . $this->user_cashback, __METHOD__);
            return true;
        }
    }

    /**
     * Найти пользователя по authKey
     */
    public static function findIdentityByAuthKey($authKey)
    {
        foreach (self::$users as $user) {
            if ($user['authKey'] === $authKey) {
                return new static($user);
            }
        }

        return null;
    }

    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public static function findByLogin($nickname, $password)
    {
        $user = static::findOne(['nickname' => $nickname]);
        if (!$user) {
            return null;
        }
        // Предполагаем, что пароль хранится в поле password_hash
        if (Yii::$app->security->validatePassword($password, $user->password_hash)) {
            return $user;
        }
        return null;
    }
}
