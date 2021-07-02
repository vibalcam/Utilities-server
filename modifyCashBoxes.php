<?php
const DIR = __DIR__;
require_once DIR . '/db_config.php';

function modifyCashBoxes($clientId) {
    $response = array();
    $cashBoxes = json_decode(file_get_contents('php://input'), true);
//    $cashBoxes = json_decode("{\"id\": 2,\"opCode\": 0}", true);

    if (isset($clientId,$cashBoxes)) {
        require_once DIR . '/DB_Connect.php';

        // Transform cashboxes to array if it's not one
        if(isset($cashBoxes[OP_CODE]))
            $cashBoxes = array($cashBoxes);

        $db = new DB_Connect();
        $connection = $db->getConnection();
        $values = array();

        // Turn auto-commit false to create transaction
        $connection->autocommit(FALSE);

        foreach ($cashBoxes as $c) {
            $opCode = $c[OP_CODE];
            $id = $c[ID];

            switch ($opCode) {
                case INSERT:
                    // OpCode, id
                    //$connection->query("INSERT INTO shared_cashboxes (Creator,Created) VALUES (NOW())");
                    //$out = $connection->insert_id;
                    $out = 0;
                    if (!isset($insertCB,$insertCL)) {
                        $insertCB = $db->prepare("INSERT INTO shared_cashboxes (Creator,Created) VALUES (?,NOW())");
                        $insertCB->bind_param("i", $clientId);
                        $insertCL = $db->prepare("INSERT INTO cashboxesAndClients (SID, CLID) VALUES (?,?)");
                        $insertCL->bind_param("ii", $out, $clientId);
                    }
                    $insertCB->execute();
                    $out = $connection->insert_id;
                    $insertCL->execute();
                    // break to not allow invitations for now
                    break;

                case UPDATE: // only allow an invitation at a time
                    // OpCode, id, invitation
                    $invitation = $c[INVITATION];
                    if (!isset($invite)) {
                        $notifOp = CASHBOX_INV;
                        $invite = $db->prepare("INSERT INTO notifications (ID, CLID, OpName) " .
                            "SELECT ?,c.CLID, sc.name " .
                            "FROM clients AS c JOIN status_codes AS sc " .
                            "WHERE Username=? AND sc.id=$notifOp");
                        $invite->bind_param("is", $id, $invitation);
                    }
                    $invite->execute();
                    $invite->store_result();
                    if ($invite->affected_rows)
                        $out = $invite->affected_rows; // should only be 1 (only one invitation)
                    else
                        $out = -1;
//                    echo $out;
                    break;

                case DELETE:
                    // OpCode,id
                    if($id == ID_ALL) {
                        if (!isset($deleteAll)) {
                            $deleteAll = $db->prepare("DELETE FROM cashboxesAndClients WHERE CLID=?");
                            $deleteAll->bind_param("i", $clientId);
                        }
                        $deleteAll->execute();
                    } else {
                        if (!isset($delete)) {
                            $delete = $db->prepare("DELETE FROM cashboxesAndClients WHERE SID=? AND CLID=?");
                            $delete->bind_param("ii", $id, $clientId);
                        }
                        $delete->execute();
                    }
                    $out = 1;
                    break;

                case CASHBOX_INV:
                    // OpCode,id
                    if (!isset($accept)) {
                        $accept = $db->prepare("INSERT INTO cashboxesAndClients (SID, CLID) VALUES (?,?)");
                        $accept->bind_param("ii", $id, $clientId);
                    }
                    if (!($accept->execute())) { // failed
                        $out = -1;
                        break;
                    }

                case CASHBOX_RELOAD:
                    // OpCode,id
                    if (!isset($getData)) {
                        $getData = $db->prepare(
                            sprintf("SELECT EID AS %s, SID AS %s, Amount AS %s, Date AS %s, Info AS %s 
                            FROM shared_entries WHERE SID=? ORDER BY Date DESC", 
                            ID, CASHBOX_ID, AMOUNT, DATE, INFO));
                        $getData->bind_param("i",$id);
                        $getPartData = $db->prepare(
                            sprintf("SELECT PID AS %s, p.EID AS %s, name AS %s, isFrom AS %s, p.amount AS %s 
                            FROM entries_participants AS p LEFT JOIN shared_entries AS e ON e.EID=p.EID 
                            WHERE e.SID=?", 
                            ID, CASHBOX_ID, INFO, DATE, AMOUNT));
                            // ID, ENTRY_ID, NAME, IS_FROM, AMOUNT));
                        $getPartData->bind_param("i",$id);
                    }
                    if ($getData->execute() && ($result = $getData->get_result()) && 
                            $getPartData->execute() && ($resultPart = $getPartData->get_result()) ) {
                        $out = array();
                        $out[ENTRIES] = $result->fetch_all(MYSQLI_ASSOC); // MYSQLI_BOTH, MYSQLI_ASSOC, MYSQLI_NUM
                        $out[PARTICIPANTS] = $resultPart->fetch_all(MYSQLI_ASSOC); // MYSQLI_BOTH, MYSQLI_ASSOC, MYSQLI_NUM
                        // Close result
                        $result->close();
                        $resultPart->close();
                    } else  // failed
                        $out = -1;
                    break;

                // case CASHBOX_INV:
                //     // OpCode,id
                //     if (!isset($accept,$get)) {
                //         $accept = $db->prepare("INSERT INTO cashboxesAndClients (SID, CLID) VALUES (?,?)");
                //         $accept->bind_param("ii", $id, $clientId);
                //         $get = $db->prepare(
                //             sprintf("SELECT EID AS %s, SID AS %s, Amount AS %s, Date AS %s, Info AS %s 
                //             FROM shared_entries WHERE SID=? ORDER BY Date DESC", 
                //             ID, CASHBOX_ID, AMOUNT, DATE, INFO));
                //         $get->bind_param("i",$id);
                //         $getPart = $db->prepare(
                //             sprintf("SELECT id AS %s, EID AS %s, name AS %s, isFrom AS %s, amount AS %s 
                //             FROM entries_participants AS p LEFT JOIN shared_entries AS e ON e.EID=p.EID 
                //             WHERE e.SID=?", 
                //             ID, CASHBOX_ID, INFO, DATE, AMOUNT));
                //             // ID, ENTRY_ID, NAME, IS_FROM, AMOUNT));
                //         $getPart->bind_param("i",$id);
                //     }
                //     if ($accept->execute() && $get->execute() && ($result = $get->get_result()) && 
                //             $getPart->execute() && ($resultPart = $getPart->get_result()) ) {
                //         $out = array();
                //         $out[ENTRIES] = $result->fetch_all(MYSQLI_ASSOC); // MYSQLI_BOTH, MYSQLI_ASSOC, MYSQLI_NUM
                //         $out[PARTICIPANTS] = $resultPart->fetch_all(MYSQLI_ASSOC); // MYSQLI_BOTH, MYSQLI_ASSOC, MYSQLI_NUM
                //         // Close result
                //         $result->close();
                //         $resultPart->close();
                //     } else  // failed
                //         $out = -1;
                //     break;

                default:
                    $out = -1;
            }
            // commit queries
            if ($out >= 0 && $connection->commit())
                $values[$id] = $out;
            else {
                // when error
                $values[$id] = $out >= 0 ? -1 : $out;
                $connection->rollback();
            }

//            echo implode(", ", $c) . "</br>";
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