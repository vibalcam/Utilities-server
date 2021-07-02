<?php
const DIR = __DIR__;
require_once DIR . '/db_config.php';

// $statement = $db->prepare(
//     sprintf("SELECT NID AS %s,sc.id AS %s,n.ID AS %s, SID AS %s, Amount AS %s, Date AS %s, Info AS %s 
//     FROM notifications AS n
//     LEFT JOIN shared_entries AS se on se.EID = n.ID
//     LEFT JOIN status_codes AS sc on n.OpName = sc.name 
//     WHERE n.CLID = ? AND (sc.id=%d OR sc.id=%d OR sc.id=%d)  
//     UNION 
//     SELECT NID AS %s,sc.id AS %s,n.ID AS %s, ep.EID AS %s, amount AS %s, null, ep.name AS %s 
//     FROM notifications AS n
//     LEFT JOIN entries_participants AS ep on ep.id = n.ID
//     LEFT JOIN status_codes AS sc on n.OpName = sc.name 
//     WHERE n.CLID = ? AND (sc.id=%d OR sc.id=%d OR sc.id=%d)
//     UNION
//     SELECT NID,stc.id as OpCode,n.ID,null,null,null,c.Username AS %s 
//     FROM notifications as n 
//     LEFT JOIN shared_cashboxes AS shc ON shc.SID = n.ID
//     LEFT JOIN clients AS c ON c.CLID = shc.Creator
//     LEFT JOIN status_codes AS stc on n.OpName = stc.name 
//     WHERE n.CLID = ? AND stc.id=%d
//     ORDER BY NID", NOTIFICATION_ID, OP_CODE, ID, CASHBOX_ID, AMOUNT, DATE, INFO, UPDATE, INSERT, DELETE,
//     NOTIFICATION_ID, OP_CODE, ID, ENTRY_ID, AMOUNT, NAME, UPDATE_PARTICIPANT, INSERT_PARTICIPANT, DELETE_PARTICIPANT
//     INFO));

function getChanges ($clientId) {
    $response = array();
//    $clientId = $_POST[CLIENT_ID];
//    $clientId = 2;

    if (isset($clientId)) {
        require_once DIR . '/DB_Connect.php';

        $db = new DB_Connect();
        $connection = $db->getConnection();

        // First select for entries notifications, second for cashboxes invitations, third for participants notifications
        // long,                long,      long, long,      double, long,   string
        // Notification id, operation code, id, cashbox id, amount, date,   info
        // Notification id, operation code, id, entry id,   amount, isFrom, name
        // Notification id, operation code, id, null,       null,   null,   username

        $statement = $db->prepare(
            sprintf("SELECT NID AS %s,sc.id AS %s,n.ID AS %s, SID AS %s, Amount AS %s, Date AS %s, Info AS %s 
            FROM notifications AS n
            LEFT JOIN shared_entries AS se on se.EID = n.ID
            LEFT JOIN status_codes AS sc on n.OpName = sc.name 
            WHERE n.CLID = ? AND (sc.id=%d OR sc.id=%d OR sc.id=%d)  
            UNION 
            SELECT NID AS %s,sc.id AS %s,n.ID AS %s, ep.EID AS %s, amount AS %s, ep.isFrom AS %s, ep.name AS %s 
            FROM notifications AS n
            LEFT JOIN entries_participants AS ep on ep.EID = n.ID
            LEFT JOIN status_codes AS sc on n.OpName = sc.name 
            WHERE n.CLID = ? AND (sc.id=%d OR sc.id=%d OR sc.id=%d)
            UNION
            SELECT NID AS %s,stc.id AS %s,n.ID AS %s,null,null,null,c.Username AS %s 
            FROM notifications as n 
            LEFT JOIN shared_cashboxes AS shc ON shc.SID = n.ID
            LEFT JOIN clients AS c ON c.CLID = shc.Creator
            LEFT JOIN status_codes AS stc on n.OpName = stc.name 
            WHERE n.CLID = ? AND stc.id=%d
            ORDER BY NID", 
            NOTIFICATION_ID, OP_CODE, ID, CASHBOX_ID, AMOUNT, DATE, INFO, UPDATE, INSERT, DELETE,
            NOTIFICATION_ID, OP_CODE, ID, CASHBOX_ID, AMOUNT, DATE, INFO, UPDATE_PARTICIPANT, INSERT_PARTICIPANT, DELETE_PARTICIPANT,
            NOTIFICATION_ID, OP_CODE, ID, INFO, CASHBOX_INV));
        $statement->bind_param("iii", $clientId,$clientId,$clientId);

        if ($statement->execute() && ($result = $statement->get_result())) {
            $response[SUCCESS] = 1;
            $response[VALUES] = $result->fetch_all(MYSQLI_ASSOC); // MYSQLI_BOTH, MYSQLI_ASSOC, MYSQLI_NUM

            // Close result
            $result->close();
        } else {
            // failed
            $response[SUCCESS] = 0;
            $response[MESSAGE] = MSG_ERROR;
        }
    } else {
        $response[SUCCESS] = 0;
        $response[MESSAGE] = MSG_ERROR;
    }
    echo json_encode($response);
}

function changesReceived($clientId) {
//    $clientId = $_POST[CLIENT_ID];
    //$nids = json_decode($_POST[NOTIFICATION_IDS]);
    $response = array();
    $nids = json_decode(file_get_contents('php://input'));
//    $nids = json_decode("[50,51,53,54,56,57]");

    if (isset($clientId,$nids)) {
        require_once DIR . '/DB_Connect.php';

        $db = new DB_Connect();
        $connection = $db->getConnection();
        $connection->autocommit(FALSE);

        $id = null;
        $statement = $db->prepare("DELETE FROM notifications WHERE NID=?");
        $statement->bind_param("i",$id);
        foreach($nids as $id) {
            $statement->execute();
        }

        if($connection->commit())
            $response[SUCCESS] = 1;
        else {
            $connection->rollback();
            $response[SUCCESS] = 0;
            $response[MESSAGE] = MSG_ERROR;
        }
        $connection->autocommit(TRUE);
    } else {
        $response[SUCCESS] = 0;
        $response[MESSAGE] = MSG_ERROR;
    }

    echo json_encode($response);
}

function deleteUser($clientId) {
    $response = array();
    if(isset($clientId)) {
        require_once DIR . '/DB_Connect.php';

        $db = new DB_Connect();
        $connection = $db->getConnection();
        $connection->autocommit(TRUE); // should be the default

        $statement = $db->prepare("DELETE FROM clients WHERE CLID=?");
        $statement->bind_param("i",$clientId);
        if($statement->execute()) {
            $response[SUCCESS] = 1;
            $response[MESSAGE] = "Deleted user successfully";
        } else {
            $connection->rollback();
            $response[SUCCESS] = 0;
            $response[MESSAGE] = MSG_ERROR;
        }
    } else {
        $response[SUCCESS] = 0;
        $response[MESSAGE] = MSG_ERROR;
    }

    echo json_encode($response);
}

function getParticipantsForCashBox ($clientId) {
    $response = array();
    $sid = json_decode(file_get_contents('php://input'));
//    $clientId = $_POST[CLIENT_ID];
//    $clientId = 2;

    // GET CLIENTS
    if (isset($clientId)) {
        require_once DIR . '/DB_Connect.php';

        $db = new DB_Connect();
        $connection = $db->getConnection();

        $statement = $db->prepare(
            sprintf("SELECT c.Username AS %s
            FROM clients as c 
            LEFT JOIN cashboxesAndClients AS cc ON cc.CLID=c.CLID 
            WHERE cc.SID=? and c.CLID!=?", USERNAME)
        );
        $statement->bind_param("ii", $sid, $clientId);

        if ($statement->execute() && ($result = $statement->get_result())) {
            $response[SUCCESS] = 1;
            $response[VALUES] = $result->fetch_all(MYSQLI_ASSOC); // MYSQLI_BOTH, MYSQLI_ASSOC, MYSQLI_NUM

            // Close result
            $result->close();
        } else {
            // failed
            $response[SUCCESS] = 0;
            $response[MESSAGE] = MSG_ERROR;
        }
    } else {
        $response[SUCCESS] = 0;
        $response[MESSAGE] = MSG_ERROR;
    }
    echo json_encode($response);

    //  GET PARTICIPANTS
    // if (isset($clientId)) {
    //     require_once DIR . '/DB_Connect.php';

    //     $db = new DB_Connect();
    //     $connection = $db->getConnection();

    //     $statement = $db->prepare(
    //         "SELECT name 
    //         FROM entries_participants AS p 
    //         LEFT JOIN shared_entries AS e on e.EID = p.EID 
    //         WHERE e.SID=?"
    //     );
    //     $statement->bind_param("i", $sid);

    //     if ($statement->execute() && ($result = $statement->get_result())) {
    //         $response[SUCCESS] = 1;
    //         $response[VALUES] = $result->fetch_all(MYSQLI_ASSOC); // MYSQLI_BOTH, MYSQLI_ASSOC, MYSQLI_NUM

    //         // Close result
    //         $result->close();
    //     } else {
    //         // failed
    //         $response[SUCCESS] = 0;
    //         $response[MESSAGE] = MSG_ERROR;
    //     }
    // } else {
    //     $response[SUCCESS] = 0;
    //     $response[MESSAGE] = MSG_ERROR;
    // }
}