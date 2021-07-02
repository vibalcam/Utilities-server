<?php
const DIR = __DIR__;
require_once DIR . '/db_config.php';
//echo implode(", ",json_decode("[[1,1,2,5,10,\"DFSFDS\",0],[2,2,2,5,9,\"FDSAFA\",0],[3,null,null,null,null,null,-1]]")[0]);

function modifyEntries($clientId) {
    $response = array();
    //$entries = json_decode($_POST[ENTRIES]);
    $entries = json_decode(file_get_contents('php://input'),true);
//    $entries = json_decode("{\"amount\":2.0,\"cashBoxId\":19,\"date\":1586216204717,\"info\":\"\",\"id\":1,\"opCode\":1}",true);
//    $clientId = $_POST[CLIENT_ID];
//    $clientId = 1;

    if (isset($entries, $clientId)) {
        require_once DIR . '/DB_Connect.php';

        // Transform entries to array if it's not one
        if(isset($entries[OP_CODE]))
            $entries = array($entries);

        $db = new DB_Connect();
        $connection = $db->getConnection();
        $values = array();

        // Turn auto-commit false to create transaction
        $connection->autocommit(FALSE);

        foreach ($entries as $e) {
            $opCode = $e[OP_CODE];
            $id = $e[ID];

            switch ($opCode) {
                case INSERT:
                    // OpCode, id, CashBox id, amount, date, info
                    $cashBoxId = $e[CASHBOX_ID];
                    $amount = $e[AMOUNT];
                    $date = $e[DATE];
                    $info = $e[INFO];

                    if (!isset($insert)) {
                        $insert = $db->prepare(
                            "INSERT INTO shared_entries (SID, Creator, Amount, Date, Info) VALUES (?,?,?,?,?)");
                        $insert->bind_param("iidis", $cashBoxId, $clientId, $amount, $date, $info);
                    }
                    if($insert->execute()) {
                        $out = $connection->insert_id;
                        $db->createEntryNotifications($out, $clientId, $opCode)->execute();
                    } else {
                        echo $insert->error;
                        $out = -1;
                    }
                    break;

                case DELETE:
                    // OpCode, id
                    if (!isset($delete)) {
                        $delete = $db->prepare("DELETE FROM shared_entries WHERE EID=?");
                        $delete->bind_param("i", $id);
                    }
                    // First notify, since we need to have the entry to get the clients to notify
                    $db->createDeleteEntryNotifications($id)->execute();
                    $db->createEntryNotifications($id, $clientId, $opCode)->execute();
                    if($delete->execute()) {
                        $delete->store_result();
                        if($delete->affected_rows > 0)
                            $out = 1;
                        else
                            $out = NON_EXISTENT_WARNING;
                    } else
                        $out = -1;
                    break;

                case UPDATE:
                    // OpCode, id, amount, date, info
                    $amount = $e[AMOUNT];
                    $date = $e[DATE];
                    $info = $e[INFO];

                    if (!isset($update)) {
                        $update = $db->prepare(
                            "UPDATE shared_entries SET Amount=?, Date=?, Info=? WHERE EID=?");
                        $update->bind_param("disi", $amount, $date, $info, $id);
                    }
                    if($update->execute()) {
                        $update->store_result();
                        if($update->affected_rows > 0) {
                            $db->createDeleteEntryNotifications($id)->execute();
                            $db->createEntryNotifications($id, $clientId, $opCode)->execute();
                            $out = 1;
                        } else
                            $out = NON_EXISTENT_WARNING;
                    } else
                        $out = -1;
                    break;

                default:
                    $out = -1;
            }
            // commit queries
            if ($out >= 0 && $connection->commit())
                $values[$id] = $out;
            else {
                $values[$id] = $out >= 0 ? -1 : $out;
                $connection->rollback();
            }

//            echo implode(", ", $e) . "</br>";
        }
        $connection->autocommit(TRUE);
        $response[SUCCESS] = 1;
        $response[VALUES] = $values;
    } else {
        $response[SUCCESS] = 0;
        $response[MESSAGE] = MSG_ERROR;
    }
    echo json_encode($response);
}