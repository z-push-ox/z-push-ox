<?php

/*
 * This work is licensed under the Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-nc-sa/3.0/
 */

include_once ('lib/OXConnector.php');
include_once ('lib/OXUtils.php');
include_once ('mail/emails.php');
include_once ('contacts/contacts.php');
include_once ('calendar/calendar.php');
include_once ('lib/default/diffbackend/diffbackend.php');
include_once ('include/mimeDecode.php');
require_once ('include/z_RFC822.php');
require_once ('lib/utils/timezoneutil.php');
require_once 'HTTP/Request2.php';

class BackendOX extends BackendDiff {

  private $session = false;
  private $cookiejar = true;
  private $root_folder = array();
  private $OXConnector;
  private $OXUtils;

  public function __construct() {
    $this -> OXConnector = new OXConnector();
    $this -> OXUtils = new OXUtils();
  }

  /**
   * Authenticates the user
   *
   * @param string        $username
   * @param string        $domain
   * @param string        $password
   *
   * @access public
   * @return boolean
   */
  public function Logon($username, $domain, $password) {
    ZLog::Write(LOGLEVEL_DEBUG, "BackendOX::Logon()");

    if (!empty($domain)) {
      $username = $username . "@" . $domain;
      ZLog::Write(LOGLEVEL_DEBUG, "BackendOX::Logon() Found a domain, using '$username' as username.");
    }

    $response = $this -> OXConnector -> OXreqPOST('/ajax/login?action=login', array('name' => $username, 'password' => $password, ));
    if ($response) {
      if (array_key_exists("session", $response)) {
        $this -> OXConnector -> setSession($response["session"]);
        ZLog::Write(LOGLEVEL_DEBUG, "BackendOX::Logon() - Login successfully, get SessionID: " . $this -> session);

        $this -> EmailSync = new OXEmailSync($this -> OXConnector);
        $this -> ContactSync = new OXContactSync($this -> OXConnector, $this -> OXUtils);
        $this -> CalendarSync = new OXCalendarSync($this -> OXConnector, $this -> OXUtils);

        return true;
      }
    }
    return false;
  }

  /**
   * Logs off
   *
   * @access public
   * @return boolean
   */
  public function Logoff() {
    ZLog::Write(LOGLEVEL_DEBUG, "BackendOX::Logoff()");
    $response = $this -> OXConnector -> OXreqGET('/ajax/login', array('action' => 'logout', 'session' => $this -> OXConnector -> getSession(), ));
    if ($response) {
      ZLog::Write(LOGLEVEL_DEBUG, "BackendOX::Logoff() - Logged off successfully");
      return true;
    }
  }

  /**
   * Returns a list (array) of folders.
   * In simple implementations like this one, probably just one folder is returned.
   *
   * @access public
   * @return array
   */
  public function GetFolderList() {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetFolderList()');

    // Get the list of calendar and contact folders:
    $response = $this -> OXConnector -> OXreqGET('/ajax/folders', array('action' => 'root', 'session' => $this -> OXConnector -> getSession(), 'allowed_modules' => 'contacts,calendar',
    //http://oxpedia.org/wiki/index.php?title=HTTP_API#CommonFolderData
    'columns' => '1,', ));

    if ($response) {
      $folder_list = array();
      foreach ($response["data"] as &$root_folder) {
        $root_folder = $root_folder[0];
        $this -> root_folder[] = $root_folder;
        $folderlist = $this -> GetSubFolders($root_folder);
        foreach ($folderlist as &$folder) {
          $folder_list[] = $this -> StatFolder($folder);
        }
      }
    }

    // now get all mail folders:
    $response = $this -> OXConnector -> OXreqGET('/ajax/folders', array('action' => 'list', 'parent' => 'default0', // personal email folder ?
    'session' => $this -> OXConnector -> getSession(), 'allowed_modules' => 'mail',
    //http://oxpedia.org/wiki/index.php?title=HTTP_API#CommonFolderData
    'columns' => '1,', ));

    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetFolderList() - mailrepsonse: ' . print_r($response, true));

    if ($response) {
      foreach ($response["data"] as &$root_folder) {
        ZLog::Write(LOGLEVEL_DEBUG, "root_folder: " . $root_folder);
        $root_folder = $root_folder[0];
        $this -> root_folder[] = $root_folder;
        $folderlist = $this -> GetSubFolders($root_folder);
        $folder_list[] = $this -> StatFolder($root_folder);
        foreach ($folderlist as &$folder) {
          if (!is_numeric($folder)) {
            $folder_list[] = $this -> StatFolder($folder);
            ZLog::Write(LOGLEVEL_DEBUG, "folder: " . $folder);
          }
        }
      }
    }

    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetFolderList() - folder_list: ' . print_r($folder_list, true));

    return $folder_list;
  }

  private function GetSubFolders($id) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetSubFolders(' . $id . ')');
    $lst = array();
    $response = $this -> OXConnector -> OXreqGET('/ajax/folders', array('action' => 'list', 'session' => $this -> OXConnector -> getSession(),
    //http://oxpedia.org/wiki/index.php?title=HTTP_API#CommonFolderData
    'parent' => $id, 'columns' => '1,301,', ));

    ZLOG::Write(LOGLEVEL_DEBUG, 'BackendOX::GetSubFolder(' . $id . ') - response: ' . print_r($response, true));

    foreach ($response["data"] as &$folder) {
      // restrict to contacts | calendar | mail
      if (in_array($folder[1], array("contacts", "calendar", "mail"))) {
        $lst[] = $folder[0];
        $subfolders = $this -> GetSubFolders($folder[0]);
        foreach ($subfolders as &$subfolder) {
          $lst[] = $subfolder;
        }
      }
    }

    ZLOG::Write(LOGLEVEL_DEBUG, 'BackendOX::GetSubFolder() - lst: ' . print_r($lst, true));

    return $lst;
  }

  /**
   * Returns an actual SyncFolder object
   *
   * @param string        $id           id of the folder
   *
   * @access public
   * @return object       SyncFolder with information
   */
  public function GetFolder($id) {

    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetFolder(' . $id . ')');
    $response = $this -> OXConnector -> OXreqGET('/ajax/folders', array('action' => 'get', 'session' => $this -> OXConnector -> getSession(), 'id' => $id,
    //http://oxpedia.org/wiki/index.php?title=HTTP_API#CommonFolderData
    'columns' => '1,20,300,301,316', //objectID�| parentfolderID | title | module/type
    ));
    if ($response) {

      ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetFolder(' . $id . ') title: ' . $response["data"]["title"] . ' module: ' . $response["data"]["module"]);

      $folder = new SyncFolder();
      $folder -> serverid = $id;
      if (array_key_exists($response["data"]["folder_id"], $this -> root_folder) || $response["data"]["folder_id"] == "default0") {
        $folder -> parentid = "0";
      } else {
        $folder -> parentid = $response["data"]["folder_id"];
      }
      $folder -> displayname = $response["data"]["title"];
      switch ($response["data"]["module"]) {
        case "contacts" :
          $folder -> type = SYNC_FOLDER_TYPE_CONTACT;
          break;
        case "calendar" :
          $folder -> type = SYNC_FOLDER_TYPE_APPOINTMENT;
          break;
        case "mail" :
          ZLog::Write(LOGLEVEL_DEBUG, "Mail folder: " . $id . " FolderType : " . $response["data"]["standard_folder_type"]);
          switch ( $response["data"]["standard_folder_type"] ) {
            case "7" :
              $folder -> type = SYNC_FOLDER_TYPE_INBOX;
              break;

            case "9" :
              $folder -> type = SYNC_FOLDER_TYPE_DRAFTS;
              break;

            case "10" :
              $folder -> type = SYNC_FOLDER_TYPE_SENTMAIL;
              break;

            case "12" :
              $folder -> type = SYNC_FOLDER_TYPE_WASTEBASKET;
              break;

            default :
              $folder -> type = SYNC_FOLDER_TYPE_USER_MAIL;
              break;
          }
          break;
        default :
          return false;
          break;
      }

      return $folder;
    }

    return false;
  }

  /**
   * Returns folder stats. An associative array with properties is expected.
   *
   * @param string        $id             id of the folder
   *
   * @access public
   * @return array
   */
  public function StatFolder($id) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::StatFolder(' . $id . ')');
    $folder = $this -> GetFolder($id);

    $stat = array();
    $stat["id"] = $id;
    $stat["parent"] = $folder -> parentid;
    $stat["mod"] = $folder -> displayname;

    return $stat;
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
  public function ChangeFolder($folderid, $oldid, $displayname, $type) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::ChangeFolder(' . $folderid . ',' . $oldid . ',' . $displayname . ',' . $type . ')');
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
  public function DeleteFolder($id, $parentid) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::ChangeFolder(' . $id . ',' . $parentid . ')');
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
  public function GetMessageList($folderid, $cutoffdate) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetMessageList(' . $folderid . ')  cutoffdate: ' . $cutoffdate);

    $folder = $this -> GetFolder($folderid);

    return $this -> callModuleHandlerForFolder("GetMessageList", $folder, array($folder, $cutoffdate));
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
  public function GetMessage($folderid, $id, $contentparameters) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetMessage(' . $folderid . ', ' . $id . ', ..)');

    $folder = $this -> GetFolder($folderid);

    return $this -> callModuleHandlerForFolder("GetMessage", $folder, array($folder, $id, $contentparameters));
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
  public function StatMessage($folderid, $id) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::StatMessage(' . $folderid . ', ' . $id . ')');

    $folder = $this -> GetFolder($folderid);

    return $this -> callModuleHandlerForFolder("StatMessage", $folder, array($folder, $id));
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
  public function ChangeMessage($folderid, $id, $message) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::ChangeMessage(' . $folderid . ', ' . $id . ', message: ' . json_encode($message) . ')');
    
    $folder = $this -> GetFolder($folderid);

    return $this -> callModuleHandlerForFolder("ChangeMessage", $folder, array($folder, $id, $message));
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
  public function SetReadFlag($folderid, $id, $flags) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::SetReadFlag(' . $folderid . ', ' . $id . ', ..)');

    $folder = $this -> GetFolder($folderid);

    return $this -> callModuleHandlerForFolder("SetReadFlag", $folder, array($folder, $id, $flags));
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
  public function DeleteMessage($folderid, $id) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::DeleteMessage(' . $folderid . ', ' . $id . ')');
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
  public function MoveMessage($folderid, $id, $newfolderid) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::MoveMessage(' . $folderid . ', ' . $id . '...)');
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
  public function SendMail($sm) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::SendMail()');
    return false;
  }

  /**
   * Returns the waste basket
   *
   * @access public
   * @return string
   */
  public function GetWasteBasket() {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetWasteBasket()');
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
  public function GetAttachmentData($attname) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetAttachmentData(' . $attname . ')');
    return false;
  }

  /**
   * Calls a function for a specific module for a folder.
   *
   * @param string    $functionName
   * @param folder    $folder
   *
   * @return anything
   */
  private function callModuleHandlerForFolder($functionName, $folder, $args) {
    switch ($folder->type) {
      case SYNC_FOLDER_TYPE_CONTACT :
        return call_user_func_array(array($this -> ContactSync, "$functionName"), $args);
        break;

      case SYNC_FOLDER_TYPE_APPOINTMENT :
        return call_user_func_array(array($this -> CalendarSync, "$functionName"), $args);
        break;

      // These are all email: SYNC_FOLDER_TYPE_USER_MAIL, SYNC_FOLDER_TYPE_INBOX, SYNC_FOLDER_TYPE_WASTEBASKET
      case SYNC_FOLDER_TYPE_USER_MAIL :
        return call_user_func_array(array($this -> EmailSync, "$functionName"), $args);
        break;

      case SYNC_FOLDER_TYPE_INBOX :
        return call_user_func_array(array($this -> EmailSync, "$functionName"), $args);
        break;

      case SYNC_FOLDER_TYPE_WASTEBASKET :
        return call_user_func_array(array($this -> EmailSync, "$functionName"), $args);
        break;

      default :
        break;
    }

    return false;
  }

}
?>
