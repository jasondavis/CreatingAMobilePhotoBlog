<?php
class POP3
{
    private $conn;

    // make connection
    public function __construct($host, $user, $pass, $folder = "INBOX", $port = 110, $useSSL = false) {
        $ssl = ($useSSL) ? "" : "/novalidate-cert";
        $mailbox = sprintf("{%s:%d/pop3%s}%s", $host, $port, $ssl, $folder);
        $this->conn = imap_open($mailbox, $user, $pass);
    }

    // close connection and trigger expunge
    public function __destruct() {
        imap_close($this->conn, CL_EXPUNGE);
    }

    // fetch the body of an email with no attachments
    public function fetchBody($msgNum = "") {
        return imap_fetchbody($this->conn, $msgNum, 1);
    }

    // retrieve a list of messages
    public function listMessages($msgNum = "") {
        $msgList = array();
        if ($msgNum) {
            $range = $msgNum;
        }
        else {
            $info = imap_check($this->conn);
            $range = "1:" . $info->Nmsgs;
        }
        $response = imap_fetch_overview($this->conn, $range);
        foreach ($response as $msg) {
            $msgList[$msg->msgno] = (array)$msg;
        }
        return $msgList;
    }

    // delete a message
    public function deleteMessage($msgNum) {
        return imap_delete($this->conn, $msgNum);
    }

    // parse headers into usable code
    public function parseHeaders($headers) {
        $headers = preg_replace('/\r\n\s+/m', "", $headers);
        preg_match_all('/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)?\r\n/m',
            $headers, $matches);
        foreach ($matches[1] as $key => $value) {
            $result[$value] = $matches[2][$key];
        }
        return $result;
    }

    // separate MIME types
    public function mimeToArray($msgNum, $parseHeaders = false) {
        $mail = imap_fetchstructure($this->conn, $msgNum);
        $mail = $this->getParts($msgNum, $mail, 0);
        if ($parseHeaders) {
            $mail[0]["parsed"] = $this->parseHeaders($mail[0]["data"]);
        }
        return $mail;
    }

    // separate mail parts
    public function getParts($msgNum, $part, $prefix) {
        $attachments = array();
        $attachments[$prefix] = $this->decodePart($msgNum, $part, $prefix);

        // multi-part
        if (isset($part->parts)) {
            $prefix = ($prefix) ? $prefix . "." : "";
            foreach ($part->parts as $number => $subpart) {
                $attachments = array_merge($attachments, $this->getParts($msgNum, $subpart, $prefix . ($number + 1)));
            }
        }
        return $attachments;
    }

    // decode attachments
    public function decodePart($msgNum, $part, $prefix) {
        $attachment = array();

        if ($part->ifdparameters) {
            foreach ($part->dparameters as $obj) {
                $attachment[strtolower($obj->attribute)] = $obj->value;
                if(strtolower($obj->attribute) == "filename") {
                    $attachment["is_attachment"] = true;
                    $attachment["filename"] = $obj->value;
                }
            }
        }

        if ($part->ifparameters) {
            foreach ($part->parameters as $obj) {
                $attachment[strtolower($obj->attribute)] = $obj->value;
                if (strtolower($obj->attribute) == "name") {
                    $attachment["is_attachment"] = true;
                    $attachment["name"] = $obj->value;
                }
            }
        }

        $attachment["data"] = imap_fetchbody($this->conn, $msgNum, $prefix);
        // 3 is base64
        if ($part->encoding == 3) {
            $attachment["data"] = base64_decode($attachment["data"]);
        }
        // 4 is quoted-printable
        else if ($part->encoding == 4) {
            $attachment["data"] = quoted_printable_decode($attachment["data"]);
        }
        return($attachment);
    }
}