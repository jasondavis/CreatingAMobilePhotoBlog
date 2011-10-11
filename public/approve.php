<?php
require_once "../include/config.php";

// connect to database
$db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);

// receive incoming token
$token = isset($_GET["token"]) && ctype_alnum($_GET["token"]) ? $_GET["token"] : null;

if (!empty($token)) {
    // verify token
    $sql = "SELECT pending_id FROM pending WHERE token = :token AND is_valid = 'N'";
    $stm = $db->prepare($sql);
    $stm->execute(array(":token" => $token));
    $pendID = $stm->fetchColumn();
    $stm->closeCursor();

    if (!empty($pendID)) {
        // set the entry to be published
        $sql = "UPDATE pending SET is_valid = 'Y' WHERE pending_id = :pend_id";
        $stm = $db->prepare($sql);
        $stm->execute(array(":pend_id" => $pendID));
        echo "<p>Publishing...</p>";
    }
    else {
        echo "<p>Invalid token.</p>";
    }
}
