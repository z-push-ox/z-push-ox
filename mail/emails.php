<?php

/*
 * This work is licensed under the Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-nc-sa/3.0/
 */

include_once ('include/mimeDecode.php');
require_once ('include/z_RFC822.php');
require_once ('lib/utils/timezoneutil.php');
require_once 'HTTP/Request2.php';

class OXEmailSync
{

  private $UserID = 0;
  private $session = false;
  private $cookiejar = true;
  private $root_folder = array();
  private $OXConnector;
  private $OXUtils;
  private $displayName;
  private $defaultSenderAddress;

  public function OXEmailSync( $OXConnector, $OXUtils )
  {
    $this -> OXConnector = $OXConnector;
    $this -> OXUtils = $OXUtils;

    $response = $this -> OXConnector -> OXreqGET('/ajax/config/mail/sendaddress', array('session' => $this -> OXConnector -> getSession(), ));

    if ($response)
      $this -> defaultSenderAddress = $response['data'];

    $response = $this -> OXConnector -> OXreqGET('/ajax/config/identifier', array('session' => $this -> OXConnector -> getSession(), ));

    if ($response) {
      $userID = $response['data'];
      $response = $this -> OXConnector -> OXreqGET('/ajax/user', array(
        'action' => 'get',
        'id' => $userID,
        'session' => $this -> OXConnector -> getSession(),
      ));
      if ($response) {
        $this -> displayName = $response['data']['display_name'];
      }
    }

    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync initialized.');
    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync DefaultSenderAddress: ' . $this -> defaultSenderAddress);
    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync DisplayName: ' . $this -> displayName);

  }

  /**
   * Creates or modifies a folder
   *
   * @param string        $folderid       id of the parent folder
   * @param string        $oldid          if empty -> new folder created, else folder is to be renamed
   * @param string        $displayname    new folder name (to be created, or to be renamed to)
   * @param int           $type           folder type
   *
   * @access public
   * @return boolean                      status
   * @throws StatusException              could throw specific SYNC_FSSTATUS_* exceptions
   *
   */
  public function ChangeFolder( $folderid, $oldid, $displayname, $type )
  {
    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::ChangeFolder(' . $folderid . ',' . $oldid . ',' . $displayname . ',' . $type . ')');
    return false;
  }

  /**
   * Deletes a folder
   *
   * @param string        $id
   * @param string        $parent         is normally false
   *
   * @access public
   * @return boolean                      status - false if e.g. does not exist
   * @throws StatusException              could throw specific SYNC_FSSTATUS_* exceptions
   *
   */
  public function DeleteFolder( $id, $parentid )
  {
    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::ChangeFolder(' . $id . ',' . $parentid . ')');
    return false;
  }

  /**
   * Returns a list (array) of messages
   *
   * @param string        $folderid       id of the parent folder
   * @param long          $cutoffdate     timestamp in the past from which on messages should be returned
   *
   * @access public
   * @return array/false  array with messages or false if folder is not available
   */
  public function GetMessageList( $folder, $cutoffdate )
  {

    $folderid = $folder -> serverid;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetMessageList(' . $folderid . ')  cutoffdate: ' . $cutoffdate);
    $messages = array();

    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetMessageList(' . $folderid . '): ' . 'Syncing eMail-Folder');
    $response = $this -> OXConnector -> OXreqGET('/ajax/mail', array(
      'action' => 'all',
      'session' => $this -> OXConnector -> getSession(),
      'sort' => '610',
      'order' => 'desc',
      'folder' => $folderid,
      'columns' => '600,611,610', //objectID�|flags|date
    ));

    // ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetMessageList(' . $folderid . '): ' . 'Response: ' . print_r($response, true));

    foreach ($response["data"] as &$mail) {

      $deleteCheck = $mail[1] & 2;
      // See http://php.net/manual/de/language.operators.bitwise.php
      if ($deleteCheck == 2) {
        // The "deleted"-Flag is set. Do not synchronize this mail to the device.
        continue;
      }

      $message = array();
      $message["id"] = $mail[0];
      $message["flags"] = $mail[1];
      # $message["mod"] = $this->timestampOXtoPHP($mail[2]);
      $message["mod"] = 0;
      $messages[] = $message;

      // respect the cutoffdate
      if ($this -> OXUtils -> timestampOXtoPHP($mail[2]) < $cutoffdate) {
        break;
      }
    }

    return is_array($messages) ? $messages : false;

  }

  /**
   * Returns the actual SyncXXX object type.
   *
   * @param string            $folderid           id of the parent folder
   * @param string            $id                 id of the message
   * @param ContentParameters $contentparameters  parameters of the requested message (truncation, mimesupport etc)
   *
   * @access public
   * @return object/false     false if the message could not be retrieved
   */
  public function GetMessage( $folder, $id, $contentparameters )
  {

    $folderid = $folder -> serverid;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetMessage(' . $folderid . ', ' . $id . ', ..)');

    /*

     public $to;
     public $cc;
     public $from;
     public $subject;
     public $threadtopic;
     public $datereceived;
     public $displayto;
     public $importance;
     public $read;
     public $attachments;
     public $mimetruncated;
     public $mimedata;
     public $mimesize;
     public $bodytruncated;
     public $bodysize;
     public $body;
     public $messageclass;
     public $meetingrequest;
     public $reply_to;

     // AS 2.5 prop
     public $internetcpid;

     // AS 12.0 props
     public $asbody;
     public $asattachments;
     public $flag;
     public $contentclass;
     public $nativebodytype;

     // AS 14.0 props
     public $umcallerid;
     public $umusernotes;
     public $conversationid;
     public $conversationindex;
     public $lastverbexecuted; //possible values unknown, reply to sender, reply to all, forward
     public $lastverbexectime;
     public $receivedasbcc;
     public $sender;

     */

    $bodypreference = $contentparameters -> GetBodyPreference();
    if ($bodypreference !== false) {
      $bpReturnType = Utils::GetBodyPreferenceBestMatch($bodypreference);
    }
    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetMessage(' . $folderid . ', ' . $id . '): bpReturnType: ' . $bpReturnType);

    $output = new SyncMail( );

    $response = $this -> OXConnector -> OXreqGET('/ajax/mail', array(
      'action' => 'get',
      'session' => $this -> OXConnector -> getSession(),
      'folder' => $folderid,
      'id' => $id,
      'unseen' => 'true',
    ));

    foreach ($response["data"]["to"] as &$to) {
      $output -> to[] = $to[1];
    }

    foreach ($response["data"]["from"] as &$from) {
      $output -> from = $from[1];
    }

    $output -> reply_to = array();

    //OX 0 - no prio, 2 - high, 1 - most important, 3 - normal, 5 - lowest
    //AS 0 - low, 1 - normal, 2 - important
    $normalPrio = array(
      0,
      3
    );
    $highPrio = array(
      1,
      2
    );
    $lowPrio = array(
      4,
      5
    );
    if (in_array($response["data"]["priority"], $normalPrio)) {
      $output -> importance = 1;
      ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetMessage(' . $folderid . ', ' . $id . '): Priority is "Normal"');
    } else if (in_array($response["data"]["priority"], $highPrio)) {
      $output -> importance = 2;
      ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetMessage(' . $folderid . ', ' . $id . '): Priority is "High"');
    } else if (in_array($response["data"]["priority"], $lowPrio)) {
      $output -> importance = 0;
      ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetMessage(' . $folderid . ', ' . $id . '): Priority is "Low"');
    }

    $output -> subject = $response["data"]["subject"];
    $output -> read = array_key_exists("unseen", $response["data"]) && $response["data"]["unseen"] == "true" ? false : true;
    $output -> datereceived = $this -> timestampOXtoPHP($response["data"]["received_date"]);

    foreach ($response["data"]["attachments"] as $attachment) {
      ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetMessage(' . $folderid . ', ' . $id . '): Attachment "' . $attachment['id'] . '" has Contenttype "' . $attachment['content_type'] . '"');

      // Extract text/html and text/plain parts:
      $textPlain = "";
      $textHtml = "";
      if ($attachment['content_type'] == "text/plain") {
        $textPlain = html_entity_decode(str_replace("<br>", "\n", $attachment['content']), ENT_NOQUOTES, 'UTF-8');
        // OX sends the Umlauts as HTML-Tags. So we mus convert them :(.
      } else if ($attachment['content_type'] == "text/html") {
        $textHtml = $attachment['content'];
      }
    }

    // Does our client understand AS-Version >= 12.0 ?
    if (Request::GetProtocolVersion() >= 12.0 && $bpReturnType == SYNC_BODYPREFERENCE_MIME) {
      $output -> asbody = new SyncBaseBody( );

      // For MIME-Message we need to get the Mail in "raw":
      $MIMEresponse = $this -> OXConnector -> OXreqGET('/ajax/mail', array(
        'action' => 'get',
        'session' => $this -> OXConnector -> getSession(),
        'folder' => $folderid,
        'id' => $id,
        'unseen' => 'true',
        'src' => 'true'
      ));
      ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetMessage(' . $folderid . ', ' . $id . '): MIME-Response: "' . print_r($MIMEresponse, true));

      $output -> asbody -> data = $MIMEresponse["data"];
      $output -> asbody -> type = SYNC_BODYPREFERENCE_MIME;
      $output -> nativebodytype = SYNC_BODYPREFERENCE_MIME;
      $output -> asbody -> estimatedDataSize = strlen($output -> asbody -> data);
      $output -> messageclass = "IPM.Note";
      $output -> internetcpid = INTERNET_CPID_UTF8;
      $output -> contentclass = "urn:content-classes:message";

      $truncsize = Utils::GetTruncSize($contentparameters -> GetTruncation());
      if (strlen($output -> asbody -> data) > $truncsize) {
        $output -> asbody -> data = Utils::Utf8_truncate($output -> asbody -> data, $truncsize);
        $output -> asbody -> truncated = 1;
      }

    } else if (Request::GetProtocolVersion() >= 12.0 && !empty($textHtml) && $bpReturnType == SYNC_BODYPREFERENCE_HTML) {
      // HTML-Mails:
      $output -> asbody = new SyncBaseBody( );

      $output -> asbody -> data = $textHtml;
      $output -> asbody -> type = SYNC_BODYPREFERENCE_HTML;
      $output -> nativebodytype = SYNC_BODYPREFERENCE_HTML;
      $output -> asbody -> estimatedDataSize = strlen($output -> asbody -> data);
      $output -> messageclass = "IPM.Note";
      $output -> internetcpid = INTERNET_CPID_UTF8;
      $output -> contentclass = "urn:content-classes:message";

      $truncsize = Utils::GetTruncSize($contentparameters -> GetTruncation());
      if (strlen($output -> asbody -> data) > $truncsize) {
        $output -> asbody -> data = Utils::Utf8_truncate($output -> asbody -> data, $truncsize);
        $output -> asbody -> truncated = 1;
      }

    } else if (Request::GetProtocolVersion() >= 12.0 && !empty($textPlain)) {
      // Text-Mails:
      $output -> asbody = new SyncBaseBody( );
      $output -> asbody -> data = $textPlain;
      // $textHtml;
      $output -> asbody -> type = SYNC_BODYPREFERENCE_PLAIN;
      $output -> nativebodytype = SYNC_BODYPREFERENCE_PLAIN;
      $output -> asbody -> estimatedDataSize = strlen($output -> asbody -> data);

      $bpo = $contentparameters -> BodyPreference($output -> asbody -> type);
      if (Request::GetProtocolVersion() >= 14.0 && $bpo -> GetPreview()) {
        $output -> asbody -> preview = Utils::Utf8_truncate(Utils::ConvertHtmlToText($plainBody), $bpo -> GetPreview());
      }
    } else {
      // Default action is to only send the textPlain-Part via AS2.5
      $output -> body = $textPlain;
      $output -> nativebodytype = SYNC_BODYPREFERENCE_PLAIN;
    }

    // ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetMessage(' . $folderid . ', ' . $id . ') Output: ' . print_r($output, true));
    return $output;

  }

  /**
   * Returns message stats, analogous to the folder stats from StatFolder().
   *
   * @param string        $folderid       id of the folder
   * @param string        $id             id of the message
   *
   * @access public
   * @return array
   */
  public function StatMessage( $folder, $id )
  {

    $folderid = $folder -> serverid;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::StatMessage(' . $folderid . ', ' . $id . ')');

    $message = array();
    $message["id"] = $id;
    $message["flags"] = 1;
    // always 'read'

    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::StatMessage(' . $folderid . ', ' . $id . '): ' . 'StatMessage eMail');
    $response = $this -> OXConnector -> OXreqGET('/ajax/mail', array(
      'action' => 'get',
      'session' => $this -> OXConnector -> getSession(),
      'folder' => $folderid,
      'id' => $id,
      'columns' => '600,611', // id, flags
      'unseen' => 'true',
    ));

    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::StatMessage(' . $folderid . ', ' . $id . '): ' . 'StatResponse ' . print_r($response, true));
    # foreach ($response["data"] as &$mail) {
    #     ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::StatMessage('.$folderid.', '.$id.'): '.'StatResponse '.print_r($mail, true));
    #     $message = array();
    #     $message["id"] = $mail["id"];
    #     if ( $mail["flags"] == "32" )
    #         $message["flags"] = 1;
    #     else
    #         $message["flags"] = 0;
    # }
    $message["mod"] = $response["data"]["modified"];

    return $message;

  }

  /**
   * Called when a message has been changed on the mobile.
   * This functionality is not available for emails.
   *
   * @param string        $folderid       id of the folder
   * @param string        $id             id of the message | if id not set create the message
   * @param SyncXXX       $message        the SyncObject containing a message
   *
   * @access public
   * @return array                        same return value as StatMessage()
   * @throws StatusException              could throw specific SYNC_STATUS_* exceptions
   */
  public function ChangeMessage( $folderid, $id, $message )
  {
    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::ChangeMessage(' . $folderid . ', ' . $id . ', message: ' . json_encode($message) . ')');
    $folder = $this -> GetFolder($folderid);
    return false;
  }

  /**
   * Changes the 'read' flag of a message on disk
   *
   * @param string        $folderid       id of the folder
   * @param string        $id             id of the message
   * @param int           $flags          read flag of the message
   *
   * @access public
   * @return boolean                      status of the operation
   * @throws StatusException              could throw specific SYNC_STATUS_* exceptions
   */
  public function SetReadFlag( $folder, $id, $flags )
  {

    $folderid = $folder -> serverid;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::SetReadFlag(' . $folderid . ', ' . $id . ', ' . $flags . ')');

    $value = $flags == 0 ? 'false' : 'true';

    $response = $this -> OXConnector -> OXreqPUT('/ajax/mail', array(
      'action' => 'update',
      'session' => $this -> OXConnector -> getSession(),
      'folder' => $folderid,
      'id' => $id
    ), array(
      'flags' => '32',
      'value' => $value
    ));

    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::SetReadFlag(' . $folderid . ', ' . $id . ', ' . $flags . ') Response: ' . print_r($response, true));

    return true;
  }

  /**
   * Called when the user has requested to delete (really delete) a message
   *
   * @param string        $folderid       id of the folder
   * @param string        $id             id of the message
   *
   * @access public
   * @return boolean                      status of the operation
   * @throws StatusException              could throw specific SYNC_STATUS_* exceptions
   */
  public function DeleteMessage( $folder, $id )
  {

    $folderid = $folder -> serverid;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::DeleteMessage(' . $folderid . ', ' . $id . ')');

    $response = $this -> OXConnector -> OXreqPUT('/ajax/mail', array(
      'action' => 'delete',
      'session' => $this -> OXConnector -> getSession(),
      'folder' => $folderid
    ), array('0' => array(
        'folder' => $folderid,
        'id' => $id
      )));

    if ($response) {
      return true;
    }

    return false;
  }

  /**
   * Called when the user moves an item on the PDA from one folder to another
   * not implemented
   *
   * @param string        $folderid       id of the source folder
   * @param string        $id             id of the message
   * @param string        $newfolderid    id of the destination folder
   *
   * @access public
   * @return boolean                      status of the operation
   * @throws StatusException              could throw specific SYNC_MOVEITEMSSTATUS_* exceptions
   */
  public function MoveMessage( $folder, $id, $newfolderid )
  {
    $folderid = $folder -> serverid;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::MoveMessage(' . $folderid . ', ' . $id . ', ' . $newfolderid . ')');

    $response = $this -> OXConnector -> OXreqPUT('/ajax/mail', array(
      'action' => 'update',
      'session' => $this -> OXConnector -> getSession(),
      'id' => $id,
      'folder' => $folderid
    ), array('folder_id' => $newfolderid));

    if ($response) {
      return true;
    }

    return false;

  }

  /**
   * Sends an e-mail
   * Not implemented here
   *
   * @param SyncSendMail  $sm     SyncSendMail object
   *
   * @access public
   * @return boolean
   * @throws StatusException
   */
  public function SendMail( $sm )
  {
    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::SendMail()');
    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::SendMail() Request: ' . print_r($sm, true));

    $message = $sm -> mime;
    $flags = "";

    if (!empty($sm -> source)) {
      $sourceFolder = $sm -> source -> folderid;
      $sourceId = $sm -> source -> itemid;

      if ($sm -> replyflag == "1") {
        // Mail was answered
        $flag = 1;
      } else if ($sm -> forwardflag == "1") {
        // Mail was forwarded
        $flag = 256;
      }

      // Set the flag:
      $response = $this -> OXConnector -> OXreqPUT('/ajax/mail', array(
        'action' => 'update',
        'session' => $this -> OXConnector -> getSession(),
        'folder' => $sourceFolder,
        'id' => $sourceId
      ), array(
        'set_flags' => "$flag",
        'value' => 'true'
      ));

    }

    if (!empty($this -> defaultSenderAddress)) {
      // Setting "From"-Header to the defaultSenderAddress incl. the displayName.
      $message = preg_replace("/From:.*\n/i", 'From: ' . $this -> displayName . ' <' . $this -> defaultSenderAddress . ">\n", $message);
    }

    // Adding the X-Mailer-Header:
    $message = "X-Mailer: z-push-ox (Version " . BackendOX::getBackendVersion() . ")\n" . $message;

    // The easiest way is to Send the complete MIME-Message directly to OX.
    $response = $this -> OXConnector -> OXreqPUTforSendMail('/ajax/mail', array(
      'action' => 'new',
      'session' => $this -> OXConnector -> getSession(),
    ), $message);
    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::SendMail() PUT-Respone: ' . print_r($response, true));

    return true;

  }

  /**
   * Returns the waste basket
   *
   * @access public
   * @return string
   */
  public function GetWasteBasket( )
  {
    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetWasteBasket()');
    return false;
  }

  /**
   * Returns the content of the named attachment as stream
   * not implemented
   *
   * @param string        $attname
   *
   * @access public
   * @return SyncItemOperationsAttachment
   * @throws StatusException
   */
  public function GetAttachmentData( $attname )
  {
    ZLog::Write(LOGLEVEL_DEBUG, 'OXEmailSync::GetAttachmentData(' . $attname . ')');
    return false;
  }

  /**
   * Get the offset between two timezones in secounds
   *
   * @param int $sourceTimezone
   * @param int $destinationTimezone
   * @return int
   */
  private function getTimezoneOffset( $sourceTimezone, $destinationTimezone )
  {
    if ($sourceTimezone === null) {
      $sourceTimezone = date_default_timezone_get();
    }
    if ($destinationTimezone === null) {
      $destinationTimezone = date_default_timezone_get();
    }
    $sourceTimezone = new DateTimeZone( $sourceTimezone );
    $destinationTimezone = new DateTimeZone( $destinationTimezone );
    $now = time();
    $sourceDate = new DateTime( "now", $sourceTimezone );
    $destinationDate = new DateTime( "now", $destinationTimezone );
    return $destinationTimezone -> getOffset($destinationDate) - $sourceTimezone -> getOffset($sourceDate);
  }

  /**
   * helper function for mapValues
   *
   * @param unknown $object
   * @param string $key
   * @param unknown $value
   */
  private function _setValue( $object, $key, $value )
  {
    if (gettype($object) == 'array') {
      $object[$key] = $value;
    } else {
      $object -> $key = $value;
    }
    return $object;
  }

  /**
   * helper function for mapValues
   *
   * @param unknown $object
   * @param string $key
   * @return unknown
   */
  private function _getValue( $object, $key )
  {
    if (gettype($object) == 'array') {
      return $object[$key];
    } else {
      return $object -> $key;
    }
  }

  /**
   * Converts a php timestamp to a OX one
   *
   */
  private function timestampPHPtoOX( $phpstamp, $timezoneOffset = 0 )
  {
    if ($phpstamp == null) {
      return null;
    }
    $phpstamp = intval($phpstamp) + $timezoneOffset;
    return $phpstamp . "000";
  }

  /**
   * Converts a OX timestamp to a php one
   *
   */
  private function timestampOXtoPHP( $oxstamp, $timezoneOffset = 0 )
  {
    if (strlen($oxstamp) > 3) {
      $oxstamp = substr($oxstamp, 0, -3);
    } else {
      return $timezoneOffset;
    }
    $oxstamp = intval($oxstamp) + $timezoneOffset;
    return $oxstamp;
  }

}
?>
