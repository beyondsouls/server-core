<?php
    namespace BeyondSouls\Connect;

    require_once dirname(__FILE__) . "/../Utility/Logger.php";


    class Database {
        private $Host;
        private $DBName;
        private $DBUser;
        private $DBPassword;
        private $DBPort;
        private $pdo;
        private $sQuery;
        private $bconnected = false;
        private $log;
        private $parameters;
        
        public $rowCount   = 0;
        public $columnCount   = 0;
        public $querycount = 0;
        
        public function __construct($Host, $DBName, $DBUser, $DBPassword, $DBPort = 3306) {
            $this->log        = new Logger();
            $this->Host       = $Host;
            $this->DBName     = $DBName;
            $this->DBUser     = $DBUser;
            $this->DBPassword = $DBPassword;
            $this->DBPort	  = $DBPort;

            $this->connect();
            $this->parameters = array();
        }
        
        
        private function connect() {
            try {
                $this->pdo = new PDO('mysql:dbname=' . $this->DBName . ';host=' . $this->Host . ';port=' . $this->DBPort . ';charset=utf8', 
                    $this->DBUser, 
                    $this->DBPassword,
                    array(
                        PDO::MYSQL_ATTR_init_COMMAND => "SET NAMES utf8",
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                    )
                );
                
                $this->bconnected = true;
            } catch (PDOException $e) {
                echo $this->ExceptionLog($e->getMessage());
                die();
            }
        }
        
        public function closeconnection() {
            $this->pdo = null;
        }
        
        private function init($query, $parameters = "") {
            if (!$this->bconnected) {
                $this->connect();
            }
            try {
                $this->parameters = $parameters;
                $this->sQuery     = $this->pdo->prepare($this->buildParams($query, $this->parameters));
                
                if (!empty($this->parameters)) {
                    if (array_key_exists(0, $parameters)) {
                        $parametersType = true;
                        array_unshift($this->parameters, "");
                        unset($this->parameters[0]);
                    } else {
                        $parametersType = false;
                    }
                    foreach ($this->parameters as $column => $value) {
                        $this->sQuery->bindParam($parametersType ? intval($column) : ":" . $column, $this->parameters[$column]); //It would be query after loop end(before 'sQuery->execute()').It is wrong to use $value.
                    }
                }
                
                $this->succes = $this->sQuery->execute();
                $this->querycount++;
            }
            catch (PDOException $e) {
                echo $this->ExceptionLog($e->getMessage(), $this->buildParams($query));
                die();
            }
            
            $this->parameters = array();
        }
        
        private function buildParams($query, $params = null) {
            if (!empty($params)) {
                $rawStatement = explode(" ", $query);
                foreach ($rawStatement as $value) {
                    if (strtolower($value) == 'in') {
                        return str_replace("(?)", "(" . implode(",", array_fill(0, count($params), "?")) . ")", $query);
                    }
                }
            }
            return $query;
        }
        
        public function query($query, $params = null, $fetchmode = PDO::FETCH_ASSOC) {
            $query        = trim($query);
            $rawStatement = explode(" ", $query);
            $this->init($query, $params);
            $statement = strtolower($rawStatement[0]);
            
            if ($statement === 'select' || $statement === 'show') {
                return $this->sQuery->fetchAll($fetchmode);
            } elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
                return $this->sQuery->rowCount();
            } else {
                return NULL;
            }
        }
        
        public function lastInsertId() {
            return $this->pdo->lastInsertId();
        }
        
        public function column($query, $params = null) {
            $this->init($query, $params);
            $resultColumn = $this->sQuery->fetchAll(PDO::FETCH_COLUMN);
            $this->rowCount = $this->sQuery->rowCount();
            $this->columnCount = $this->sQuery->columnCount();
            $this->sQuery->closeCursor();
            return $resultColumn;
        }


        public function row($query, $params = null, $fetchmode = PDO::FETCH_ASSOC) {
            $this->init($query, $params);
            $resultRow = $this->sQuery->fetch($fetchmode);
            $this->rowCount = $this->sQuery->rowCount();
            $this->columnCount = $this->sQuery->columnCount();
            $this->sQuery->closeCursor();
            return $resultRow;
        }
        
        public function single($query, $params = null) {
            $this->init($query, $params);
            return $this->sQuery->fetchColumn();
        }
        
        private function ExceptionLog($message, $sql = "") {
            $exception = 'Unhandled Exception. <br />';
            $exception .= $message;
            $exception .= "<br /> You can find the error back in the log.";
            
            if (!empty($sql)) {
                $message .= "\r\nRaw SQL : " . $sql;
            }
            
            $this->log->write($message, $this->DBName . md5($this->DBPassword));

            header("HTTP/1.1 500 Internal Server Error");
            header("Status: 500 Internal Server Error");
            return $exception;
        }
    }