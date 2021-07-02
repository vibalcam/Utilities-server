<?php
const DIR = __DIR__; // debug
// const DIR = "/var/www/util"; // production
require_once DIR . '/db_config.php';

// Maintenance mode
if(MAINTENANCE_MODE) {
    $response = array();
    $response[SUCCESS] = 0;
    $response[MESSAGE] = "In maintenance: try again later";
    echo json_encode($response);
    return;
}

function accessDenied() {
    $response = array();
    $response[SUCCESS] = 0;
    $response[MESSAGE] = "No access";
    echo json_encode($response);
}

function debug($r) {
    $response = array();
    $response[SUCCESS] = 0;
    $response[MESSAGE] = $r;
    echo json_encode($response);
}

$headers = getallheaders();
$clientId = $headers[CLIENT_ID];
$pwd = $headers[PASSWORD];
$clientVersion = $headers[CLIENT_VERSION];
//$clientId = 0;
//$pwd = PHP_PWD;
// $clientVersion = VERSION;

// Check password
if(!isset($pwd) || $pwd!==PHP_PWD) {
    accessDenied();
    return;
}

// Check version of client
if(!isset($clientVersion) || $clientVersion!=VERSION) {
    $response = array();
    $response[SUCCESS] = 0;
    $response[MESSAGE] = "Old version: update app";
    echo json_encode($response);
    return;
}

// Check if client exists or is going to sign-up
require_once DIR . '/DB_Connect.php';

$db = new DB_Connect();
$connection = $db->getConnection();

if($clientId!=0) { // 0 for sign-up
    $checkClient = $db->prepare("SELECT CLID FROM clients WHERE CLID=?");
    $checkClient->bind_param("i",$clientId);
    $checkClient->execute();
    $checkClient->store_result();
    if($checkClient->num_rows === 0) {
        accessDenied();
        return;
    }
}

// Redirect to function needed
$reqCode = $headers[REQ_CODE];
//$reqCode = REQ_CASHBOXES;
if($clientId==0) { // sign up
    require_once DIR . '/signUp.php';
    signUp();
} else if(isset($reqCode)) {
    if ($reqCode == REQ_CASHBOXES) { // modify cashboxes
        require_once DIR . '/modifyCashBoxes.php';
        modifyCashBoxes($clientId);
    } else if ($reqCode == REQ_ENTRIES) { // modify entries
        require_once DIR . '/modifyEntries.php';
        modifyEntries($clientId);
    } else if ($reqCode == REQ_CHANGES_RCV) { // changes received
        require_once DIR . '/getChanges.php';
        changesReceived($clientId);
    } else if ($reqCode == REQ_CHANGES_GET) { // get changes
        require_once DIR . '/getChanges.php';
        getChanges($clientId);
    } else if ($reqCode == REQ_DELETE_USER) { // delete user
        require_once DIR . '/getChanges.php';
        deleteUser($clientId);
    } else if ($reqCode == REQ_PARTICIPANTS) { // modify participants
        require_once DIR . '/modifyParticipants.php';
        modifyParticipants($clientId);
    } else if ($reqCode == REQ_CASHBOX_GET) { // get cashbox participants
        require_once DIR . '/getChanges.php';
        getParticipantsForCashBox($clientId);
    } else
        accessDenied();
} else
    accessDenied();
