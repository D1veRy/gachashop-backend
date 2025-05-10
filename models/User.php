<?php

namespace app\models;

use yii\db\ActiveRecord;
use Yii;

class User extends ActiveRecord implements \yii\web\IdentityInterface
{
    public $id;
    public $username;
    public $password;
    public $authKey;
    public $accessToken;
    public $cashback = 0;

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
        // Находим пользователя по токену
        foreach (self::$users as $user) {
            if ($user['accessToken'] === $token) {
                return new static($user);
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey()
    {
        return $this->authKey;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey)
    {
        return $this->authKey === $authKey;
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
}
