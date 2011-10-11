#! /usr/bin/php
<?php
chdir(pathinfo(__FILE__, PATHINFO_DIRNAME));
require_once "../include/POP3.php";
require_once "../include/config.php";

// connect to database
$db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);

// prepared SQL statements
$sqlSelectValid = "SELECT is_valid FROM pending WHERE message_id = :msgno";
$stmSelectValid = $db->prepare($sqlSelectValid);

$sqlInsertToken = "INSERT INTO pending (message_id, token) VALUES (:msgno, :token)";
$stmInsertToken = $db->prepare($sqlInsertToken);

$sqlInsertPost = "INSERT INTO blog_posts (title, body, create_ts) VALUES (:title, :body, FROM_UNIXTIME(:timestamp))";
$stmInsertPost = $db->prepare($sqlInsertPost);

$sqlInsertImage = "INSERT INTO images (post_id, image_path) VALUES (:post_id, :img_path)";
$stmInsertImage = $db->prepare($sqlInsertImage);

$sqlDeletePending = "DELETE FROM pending WHERE message_id = :msgno";
$stmDeletePending = $db->prepare($sqlDeletePending);

// retrieve messages
$pop3 = new POP3(EMAIL_HOST, EMAIL_USER, EMAIL_PASSWORD);
$msgList = $pop3->listMessages();

if (!empty($msgList)) {
    foreach ($msgList as $value) {
        // see if a token exists
        $stmSelectValid->execute(array(":msgno" => $value["msgno"]));
        $isValid = $stmSelectValid->fetchColumn();

        // message has been approved
        if ($isValid == "Y") {
            // get message contents
            $msg = $pop3->mimeToArray($value["msgno"], true);
            // convert date to timestamp
            $timestamp = strtotime($value["date"]);
            if ($timestamp === false) {
                $timestamp = null;
            }
            $title = $value["subject"];
            if (sizeof($msg) > 1) {
                $body = (isset($msg["1.1"])) ? $msg["1.1"]["data"] : $msg[1]["data"];
            }
            else {
                $body = $pop3->fetchBody($value["msgno"]);
            }
            // copy images to server
            $files = array();
            foreach ($msg as $parts) {
                if (isset($parts["filename"])) {
                    $dir = ROOT_PATH . IMG_PATH;
                    $ext = strtolower(pathinfo($parts["filename"], PATHINFO_EXTENSION));
                    // only accept jpg or png
                    if (in_array($ext, array("jpg","png"))) {
                        // give the file a unique name
                        $hash = sha1($parts["data"]);
                        $file = $hash . "." . $ext;
                        $thumb = $hash . "_t." . $ext;

                        if (!file_exists($dir . $file)) {
                            // copy image and make thumbnails
                            $img = new Imagick();
                            $img->readimageblob($parts["data"]);
                            $img->writeImage($dir . $file);
                            $img->thumbnailImage(MAX_WIDTH, 0);
                            $img->writeImage($dir . $thumb);
                            $img->clear();
                            $img->destroy();

                            $files[] = IMG_PATH . $file;
                        }
                    }
                }
            }

            // update database
            if (isset($timestamp, $title, $body)) {
                // insert post
                $stmInsertPost->execute(array(":title" => $title, ":body" => $body, ":timestamp" => $timestamp));
                $postID = $db->lastInsertId();

                // insert images
                $stmInsertImage->bindParam(":post_id", $postID);
                $stmInsertImage->bindParam(":img_path", $path);
                foreach($files as $path) {
                    $stmInsertImage->execute();
                }

                // delete token
                $stmDeletePending->execute(array(":msgno" => $value["msgno"]));
            }
            // mark e-mail for deletion
            $pop3->deleteMessage($value["msgno"]);
        }
        // message has no approval token
        else  {
            // create a unique token
            $token = md5(uniqid(mt_rand(), true));
            $stmInsertToken->execute(array(":msgno" => $value["msgno"], ":token" => $token));

            // send email for approval
            $title = htmlentities($value["subject"], ENT_QUOTES);
            $subject = "Pending Post Notification: " . $title;
            $message = '<a href="http://www.example.com/approve.php?token=' . $token . '">Click Here To Approve</a>';
            mail(PUBLIC_EMAIL, $subject, $message);
        }
    }
}
