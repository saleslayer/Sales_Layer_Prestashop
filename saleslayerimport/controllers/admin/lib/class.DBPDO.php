<?php
/**
 * $Id$
 *
 * Mini mySQL class
 *
 * @modified  2015-04-23
 * @version   1.0
 * @author    Sales Layer
 * @copyright 2019 Sales Layer
 * @license   License: GPLv3  License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

class slyrSQL
{
    public $error = null;
    public $pdo = false;

    private $hostname;
    private $username;
    private $password;
    private $database;
    private $persistent = false;

    public function __construct($dbn, $usr, $pwd, $host = 'localhost')
    {
        $this->database = $dbn;
        $this->username = $usr;
        $this->password = $pwd;
        $this->hostname = $host;
        $this->connect();
    }

    public function connect()
    {
        if (!$this->pdo) {
            try {
                $this->pdo = new PDO(
                    'mysql:' . ($this->database ? 'dbname=' . $this->database . ';' : '') . 'host=' . $this->hostname,
                    $this->username,
                    $this->password,
                    array(PDO::ATTR_PERSISTENT => $this->persistent)
                );
            } catch (PDOException $e) {
                $this->error = $e->getMessage();

                return false;
            }
        } else {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        }

        return true;
    }

    public function execute($query, $values = null)
    {
        if ($values == null) {
            $values = array();
        } else {
            if (!is_array($values)) {
                $values = array($values);
            }
        }

        $stmt = $this->prepQuery($query);

        if ($stmt->execute($values) === true) {
            if (stripos($query, 'insert ') === 0) {
                $out = $this->pdo->lastInsertId();
            } else {
                if (preg_match('/^(select|show)\s+/i', $query)) {
                    $out = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $out = true;
                }
            }
            $this->error = null;
        } else {
            $out = null;
            $err = $stmt->errorInfo();
            $this->error = 'SQL error: (' . $err[1] . ') ' . $err[2];
        }

        return $out;
    }

    public function prepQuery($query)
    {
        return $this->pdo->prepare($query);
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
}
