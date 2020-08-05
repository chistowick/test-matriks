<?php

/**
 * Подключение к БД
 */
class Database
{

    /**
     * Принимает относительный путь к конфигурации БД
     * 
     * Создает подключение к БД и возвращает дескриптор
     */
    public static function getConnection(string $path_to_config)
    {

        // Подключаем файл с настройками
        require($path_to_config);

        // Создаем подключение к БД
        $dbh = new PDO($dsn, $userName, $password);

        // Возвращаем дескриптор
        return $dbh;
    }
}
