<?php

class DB
{

    private static $writeDBConnection;
    private static $readDBConnection;
    private static $host = 'localhost';
    private static $username = 'root';
    private static $dbPassword = '';// but it should have a password in real api
    private static $dbName = 'tasksdb';

    // write from this
    public static function connectWriteDb(): PDO
    {

        if (self::$writeDBConnection === null) {
            self::$writeDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8',
                self::$username, self::$dbPassword);

            self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        return self::$writeDBConnection;
    }

//    read from this
    public static function connectReadDb(): PDO
    {
        if (self::$readDBConnection === null) {
            self::$readDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8',
                self::$username, self::$dbPassword);

            self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        return self::$readDBConnection;
    }
}
