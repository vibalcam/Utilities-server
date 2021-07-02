<?php
const DIR = __DIR__;

class DB_Connect {
    /**
     * @var mysqli
     */
    private static $connection;
    /**
     * @var mysqli_stmt
     */
    private static $entryNotStatement;
    /**
     * @var mysqli_stmt
     */
    private static $deleteNotStatement;
    private static $deleteNotPartStatement;
    private $statements = array();

    // constructor
    function __construct() {
        $this->getConnection();
    }

    // destructor
    function __destruct() {
        $this->close();
    }

    public function getConnection() {
        if(!isset(self::$connection) || !self::$connection->ping()) {
            require_once DIR . '/db_config.php';

            // Create connection and select database
            self::$connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);

            // Check connection
            if (self::$connection->connect_errno) {
                $response = array();
                $response[SUCCESS] = 0;
                $response[MESSAGE] = "Internal connection failed";
                echo json_encode($response);
                die();
            //    die('Connection failed: ' . self::$connection->connect_errno . '' | '' . self::$connection->connect_error);
            }
        }
        return self::$connection;
    }

    function close() {
//        echo 'Closing...';
        foreach($this->statements as $stm)
            $stm->close();
        if(isset(self::$entryNotStatement))
            self::$entryNotStatement->close();
        if(isset(self::$deleteNotStatement))
            self::$deleteNotStatement->close();
        if(isset(self::$deleteNotPartStatement))
            self::$deleteNotPartStatement->close();
        self::$connection->close();
    }

    function prepare($sql) {
        $statement = $this->getConnection()->prepare($sql);
        array_push($this->statements,$statement);
        return $statement;
    }

    // Funciones
    function createEntryNotifications($entryId, $creator, $opCode) {
        if(!isset(self::$entryNotStatement)) {
            self::$entryNotStatement = $this->getConnection()->prepare(
                "INSERT INTO notifications (ID,CLID,OpName) " .
                "SELECT ?,cc.CLID, sc.name " .
                "FROM shared_entries AS se " .
                "LEFT JOIN cashboxesAndClients as cc ON se.SID=cc.SID " .
                "JOIN status_codes as sc " .
                "WHERE se.EID=? AND cc.CLID!=? AND sc.id=?"
            );
        }
        self::$entryNotStatement->bind_param("iiii",$entryId,$entryId,$creator,$opCode);
        return self::$entryNotStatement;
    }

    function createDeleteEntryNotifications($entryId) {
        if(!isset(self::$deleteNotStatement)) {
            // delete all notifications related to entries
            self::$deleteNotStatement = $this->getConnection()->prepare(
                sprintf("DELETE FROM notifications 
                WHERE ID=? and OpName IN 
                (SELECT sc.name FROM status_codes as sc WHERE sc.id=-1 OR sc.id=1 OR sc.id=2)",
                UPDATE, INSERT, DELETE)
            );

            // self::$deleteNotStatement = $this->getConnection()->prepare(
            //     sprintf("DELETE FROM notifications AS n
            //     LEFT JOIN status_codes AS sc on n.OpName = sc.name 
            //     WHERE n.ID=? AND (sc.id=%d OR sc.id=%d OR sc.id=%d)",
            //     UPDATE, INSERT, DELETE)
            // );
        }
        self::$deleteNotStatement->bind_param("i",$entryId);
        return self::$deleteNotStatement;
    }

    function createDeleteParticipantNotifications($entryId) {
        if(!isset(self::$deleteNotPartStatement)) {
            // delete all notifications related to participants
            self::$deleteNotPartStatement = $this->getConnection()->prepare(
                sprintf("DELETE FROM notifications 
                WHERE ID=? and OpName IN 
                (SELECT sc.name FROM status_codes as sc WHERE sc.id=-1 OR sc.id=1 OR sc.id=2)",
                UPDATE_PARTICIPANT, INSERT_PARTICIPANT, DELETE_PARTICIPANT)
            );
            

            // self::$deleteNotPartStatement = $this->getConnection()->prepare(
            //     sprintf("DELETE FROM notifications AS n
            //     LEFT JOIN status_codes AS sc on n.OpName = sc.name 
            //     WHERE n.ID=? AND (sc.id=%d OR sc.id=%d OR sc.id=%d)",
            //     UPDATE_PARTICIPANT, INSERT_PARTICIPANT, DELETE_PARTICIPANT)
            // );
        }
        self::$deleteNotPartStatement->bind_param("i",$entryId);
        return self::$deleteNotPartStatement;
    }
}