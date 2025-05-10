<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

class UserIdentity extends ActiveRecord implements IdentityInterface
{
    // Указываем, с какой таблицей будет работать эта модель
    public static function tableName()
    {
        return 'users'; // Указываем таблицу с полным именем (схема + таблица)
    }

    // Реализуем методы интерфейса IdentityInterface

    public static function findIdentity($id)
    {
        return static::findOne($id); // Находит пользователя по его ID
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        // Реализуем поиск по токену, если это необходимо
        return static::findOne(['auth_key' => $token]);
    }

    // Новый метод для поиска пользователя по никнейму
    public static function findByLogin($nickname, $password)
    {
        $user = static::findOne(['nickname' => $nickname]);

        Yii::info('User found: ' . print_r($user, true), __METHOD__); // Логирование найденного пользователя

        if ($user && Yii::$app->security->validatePassword($password, $user->password_hash)) {
            return $user;
        }

        return null;
    }

    public function getId()
    {
        return $this->id; // Идентификатор пользователя
    }

    public function getAuthKey()
    {
        return $this->auth_key;  // Возвращаем auth_key, если он есть
    }

    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;  // Проверка auth_key
    }

    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }
}