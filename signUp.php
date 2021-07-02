<?php
const DIR = __DIR__;
require_once DIR . '/db_config.php';

function signUp()
{
    $response = array();
    $userName = $_POST[USERNAME];
//    $userName = "pruebatew";

// check for required fields
    if (isset($userName)) {
        $userName = trim($userName);
        if(!empty($userName)) {
            // params fine
            require_once DIR . '/DB_Connect.php';

            $db = new DB_Connect();
            $connection = $db->getConnection();

            $statement = $db->prepare("INSERT INTO clients (Username,Creation) VALUES (?,NOW())");
            $statement->bind_param("s", $userName);

            if ($statement->execute()) {
                // successful
                $response[SUCCESS] = 1;
                $response[MESSAGE] = $connection->insert_id;
            } else {
                // failed
     //        echo $connection->error;
                if (preg_match('/\bDuplicate\b/', $connection->error) == 1) {
                    $response[SUCCESS] = -1;
                    $response[MESSAGE] = "Already existing username";
                } else {
                    $response[SUCCESS] = 0;
                    $response[MESSAGE] = MSG_ERROR;
                }
            }
        } else {
            // If param missing
            $response[SUCCESS] = 0;
            $response[MESSAGE] = MSG_ERROR;
        }
    } else {
        // If param missing
        $response[SUCCESS] = 0;
        $response[MESSAGE] = MSG_ERROR;
    }
    echo json_encode($response);
}