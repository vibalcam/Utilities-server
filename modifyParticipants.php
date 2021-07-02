<?php
const DIR = __DIR__;
require_once DIR . '/db_config.php';

function modifyParticipants($clientId) {
    $response = array();
    $participants = json_decode(file_get_contents('php://input'),true);
//    $participants = json_decode("{\"amount\":2.0,\"cashBoxId\":19,\"date\":1586216204717,\"info\":\"\",\"id\":1,\"opCode\":1}",true);
//    $clientId = 1;

    if (!(isset($participants, $clientId))) {
        $response[SUCCESS] = 0;
        $response[MESSAGE] = MSG_ERROR;
        echo json_encode($response);
        return;
    }
    
    require_once DIR . '/DB_Connect.php';

    // Transform participants to array if it's not one
    if(isset($participants[OP_CODE]))
        $participants = array($participants);

    $db = new DB_Connect();
    $connection = $db->getConnection();
    $values = array();

    // Turn auto-commit false to create transaction
    $connection->autocommit(FALSE);

    foreach ($participants as $p) {
        $opCode = $p[OP_CODE];
        $id = $p[ID];

        switch ($opCode) {
            case INSERT_PARTICIPANT:
                // OpCode, id, eid, name, isFrom, amount
                // id is temporal id to identify back at client
                $amount = $p[AMOUNT];
                $eid = $p[ENTRY_ID];
                $name = $p[NAME];
                $isFrom = $p[IS_FROM];

                if (!isset($insert)) {
                    $insert = $db->prepare(
                        "INSERT INTO entries_participants (EID, name, isFrom, amount) VALUES (?,?,?,?)");
                    $insert->bind_param("isid", $eid, $name, $isFrom, $amount);
                }
                if($insert->execute()) {
                    $out = $connection->insert_id;
                    $db->createEntryNotifications($out, $clientId, $opCode)->execute();
                } else {
                    // echo $insert->error;
                    $out = -1;
                }
                break;

            case DELETE_PARTICIPANT:
                // OpCode, id
                if (!isset($delete)) {
                    $selectCount = $db->prepare("SELECT EID, isFrom FROM entries_participants WHERE PID=?");
                    $selectCount->bind_param("i", $id);
                    $count = $db->prepare("SELECT COUNT(*) as count FROM entries_participants 
                    WHERE EID=? AND isFrom=?");
                    $count->bind_param("ii", $eid, $isFrom);
                    $delete = $db->prepare("DELETE FROM entries_participants WHERE PID=?");
                    $delete->bind_param("i", $id);
                }
                // First check if this is the only participant
                if($selectCount->execute() && ($result = $selectCount->get_result())) {
                    $result = $result->fetch_all(MYSQLI_ASSOC)[0];
                    $eid = $result['EID'];
                    $isFrom = $result['isFrom'];
                    // $result->close();
                    if($count->execute() && ($resultCount = $count->get_result())) {
                        if($resultCount->fetch_all(MYSQLI_ASSOC)[0]['count'] > 1) {
                            $resultCount->close();
                            // First notify, since we need to have the entry to get the clients to notify
                            // Also delete other notifications with same id
                            $db->createDeleteParticipantNotifications($id)->execute();
                            $db->createEntryNotifications($id, $clientId, $opCode)->execute();
                            if($delete->execute()) {
                                $delete->store_result();
                                if($delete->affected_rows > 0)
                                    $out = 1;
                                else
                                    $out = NON_EXISTENT_WARNING;
                                break;
                            }
                        } else {
                            $resultCount->close();
                            $out = NOT_ALLOWED;
                            break;
                        }
                    }
                }
                $out = -1;
                break;

            case UPDATE_PARTICIPANT:
                // OpCode, id, amount
                $amount = $p[AMOUNT];

                if (!isset($update)) {
                    $update = $db->prepare(
                        "UPDATE entries_participants SET amount=? WHERE PID=?");
                    $update->bind_param("di", $amount, $id);
                }
                if($update->execute()) {
                    $update->store_result();
                    if($update->affected_rows > 0) {
                        // Delete other notifications refering to the same id
                        $db->createDeleteParticipantNotifications($id)->execute();
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

//            echo implode(", ", $p) . "</br>";
    }
    $connection->autocommit(TRUE);
    $response[SUCCESS] = 1;
    $response[VALUES] = $values;

    echo json_encode($response);
}