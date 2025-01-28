<?php

class Database
{
    private $connection;

    public function __construct($config)
    {
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s",
            $config['host'],
            $config['port'],
            $config['dbname']
        );

        try {
            $this->connection = new PDO($dsn, $config['user'], $config['password']);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
