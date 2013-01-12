<?php

class OXContactSync
{

  private $OXConnector;
  private $OXUtils;

  public $mappingContactsOXtoASYNC = array(
    'strings' => array(
      'file_as' => 'fileas',
      'last_name' => 'lastname',
      'first_name' => 'firstname',
      'second_name' => 'middlename',
      'nickname' => 'nickname',
      'title' => 'title',
      'department' => 'department',
      'suffix' => 'suffix',
      'anniversary' => 'anniversary',
      'assistant_name' => 'assistantname',
      'telephone_assistant' => 'assistnamephonenumber',
      'spouse_name' => 'spouse',
      'note' => 'body',
      'instant_messenger1' => 'imaddress',
      'instant_messenger2' => 'imaddress2',
      'city_home' => 'homecity',
      'country_home' => 'homecountry',
      'postal_code_home' => 'homepostalcode',
      'state_home' => 'homestate',
      'street_home' => 'homestreet',
      'city_business' => 'businesscity',
      'country_business' => 'businesscountry',
      'postal_code_business' => 'businesspostalcode',
      'state_business' => 'businessstate',
      'street_business' => 'businessstreet',
      'city_other' => 'othercity',
      'country_other' => 'othercountry',
      'postal_code_other' => 'otherpostalcode',
      'state_other' => 'otherstate',
      'street_other' => 'otherstreet',
      'manager_name' => 'managername',
      'email1' => 'email1address',
      'email2' => 'email2address',
      'email3' => 'email3address',
      'company' => 'companyname',
      'position' => 'jobtitle',
      'url' => 'webpage',
      'telephone_home1' => 'homephonenumber',
      'telephone_home2' => 'home2phonenumber',
      'cellular_telephone1' => 'mobilephonenumber',
      'pagernumber' => 'telephone_pager',
      'telephone_car' => 'carphonenumber',
      'fax_home' => 'homefaxnumber',
      'telephone_radio' => 'radiophonenumber',
      'telephone_business1' => 'businessphonenumber',
      'telephone_business2' => 'business2phonenumber',
      'fax_business' => 'businessfaxnumber',
      'categories' => 'categories',

      // String indicating the Japanese phonetic rendering
      // http://msdn.microsoft.com/en-us/library/office/aa221860(v=office.11).aspx
      'yomiFirstName' => 'yomifirstname',
      'yomiLastName' => 'yomilastname',
      'yomiCompany' => 'yomicompanyname',
    ),
    'dates' => array('birthday' => 'birthday', ),
    'datetimes' => array(),
  );

  public $mappingContactsASYNCtoOX = array();
  // will be filled after login

  public function OXContactSync( $OXConnector, $OXUtils )
  {
    $this -> OXConnector = $OXConnector;
    $this -> OXUtils = $OXUtils;
    $this -> mappingContactsASYNCtoOX = $this -> OXUtils -> reversemap($this -> mappingContactsOXtoASYNC);
    ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync initialized.');
  }

  public function GetMessageList( $folder, $cutoffdate )
  {

    $folderid = $folder -> serverid;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::GetMessageList(' . $folderid . ')  cutoffdate: ' . $cutoffdate);

    // handle contacts
    $response = $this -> OXConnector -> OXreqGET('/ajax/contacts', array(
      'action' => 'all',
      'session' => $this -> OXConnector -> getSession(),
      'folder' => $folderid,
      'columns' => '1,5,', //objectID�| last modified
    ));

    ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::GetMessageList(folderid: ' . $folderid . '  folder: ' . $folder -> displayname . '  data: ' . json_encode($response) . ')');

    $messages = array();
    foreach ($response["data"] as &$contact) {
      $message = array();
      $message["id"] = $contact[0];
      $message["mod"] = $contact[1];
      $message["flags"] = 1;
      // always 'read'
      $messages[] = $message;
    }

    return $messages;
  }

  public function GetMessage( $folder, $id, $contentparameters )
  {

    $folderid = $folder -> serverid;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::GetMessage(' . $folderid . ', ' . $id . ', ...)');

    $response = $this -> OXConnector -> OXreqGET('/ajax/contacts', array(
      'action' => 'get',
      'session' => $this -> OXConnector -> getSession(),
      'id' => $id,
      'folder' => $folderid,
    ));

    return $this -> OXUtils -> mapValues($response["data"], new SyncContact( ), $this -> mappingContactsOXtoASYNC, 'php');

  }

  public function StatMessage( $folder, $id )
  {
    $folderid = $folder -> serverid;

    // Default values:
    $message = array();
    $message["id"] = $id;
    $message["flags"] = 1;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::StatMessage(' . $folderid . ', ' . $id . ', ...)');

    $response = $this -> OXConnector -> OXreqGET('/ajax/contacts', array(
      'action' => 'get',
      'session' => $this -> OXConnector -> getSession(),
      'id' => $id,
      'folder' => $folderid,
    ));
    $message["mod"] = $response["data"]["last_modified"];
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
  public function ChangeMessage( $folder, $id, $message, $contentParameters )
  {

    $folderid = $folder -> serverid;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::ChangeMessage(' . $folderid . ', ' . $id . ', ...)');

    if (!$id) {
      //id is not set => create object
      $createResponse = $this -> OXConnector -> OXreqPUT('/ajax/contacts', array(
        'action' => 'new',
        'session' => $this -> OXConnector -> getSession(),
      ), array('folder_id' => $folderid, // set the folder in which the user should be created
      ));

      if (!$createResponse) {
        ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::ChangeMessage(failed to create object in folder: ' . $folder -> displayname . ')');
        throw new StatusException( 'failed to create new object in folder: ' . $folder -> displayname, SYNC_STATUS_SYNCCANNOTBECOMPLETED );
        return false;
      }

      $id = $createResponse["data"]["id"];
      ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::ChangeMessage(' . $folderid . ', ' . $id . ', ...) New contact created with id "' . $id . '"');
    }

    //id is set => change object
    $oldmessage = $this -> GetMessage($folder, $id, null);
    $diff = $this -> OXUtils -> diffSyncObjects($message, $oldmessage);
    $stat = $this -> StatMessage($folder, $id);

    $diffOX = $this -> OXUtils -> mapValues($diff, array(), $this -> mappingContactsASYNCtoOX, 'ox');
    $response = $this -> OXConnector -> OXreqPUT('/ajax/contacts', array(
      'action' => 'update',
      'session' => $this -> OXConnector -> getSession(),
      'folder' => $folderid,
      'id' => $id,
      'timestamp' => $stat["mod"],
    ), $diffOX);

    if ($response) {
      ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::ChangeMessage(successfully changed - folder: ' . $folder -> displayname . '   id: ' . $id . ')');
      return $this -> StatMessage($folder, $id);
    } else {
      throw new StatusException( 'could not change contact: ' . $id . ' in folder: ' . $folder -> displayname, SYNC_STATUS_SYNCCANNOTBECOMPLETED );
      return false;
    }

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
  public function DeleteMessage( $folder, $id, $contentParameters )
  {

    $folderid = $folder -> serverid;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::DeleteMessage(' . $folderid . ', ' . $id . ')');

    $stat = $this -> StatMessage($folder, $id);
    $response = $this -> OXConnector -> OXreqPUT('/ajax/contacts', array(
      'action' => 'delete',
      'session' => $this -> OXConnector -> getSession(),
      'timestamp' => $stat["mod"],
    ), array(
      'folder' => $folderid,
      'id' => $id,
    ));
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
  public function MoveMessage( $folder, $id, $newfolderid, $contentParameters )
  {
    $folderid = $folder -> serverid;
    ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::MoveMessage(' . $folderid . ', ' . $id . ', ' . $newfolderid . ')');
  }

  /**
   * Changes the 'read' flag of a message on disk
   *
   * @param string        $folder       id of the folder
   * @param string        $id             id of the message
   * @param int           $flags          read flag of the message
   *
   * @access public
   * @return boolean                      status of the operation
   * @throws StatusException              could throw specific SYNC_STATUS_* exceptions
   */
  public function SetReadFlag( $folder, $id, $flags, $contentParameters )
  {
    ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::SetReadFlag(' . $folderid . ', ' . $id . ', ' . $flags . ')');
  }

  /**
   * Deletes a folder
   *
   * @param folder        $folder
   * @param string        $parent         is normally false
   *
   * @access public
   * @return boolean                      status - false if e.g. does not exist
   * @throws StatusException              could throw specific SYNC_FSSTATUS_* exceptions
   *
   */
  public function DeleteFolder( $folder, $parentid )
  {
    $id = $folder->serverid;
    
    ZLog::Write(LOGLEVEL_DEBUG, 'OXContactSync::DeleteFolder(' . $id . ',' . $parentid . ')');

    return true;
  }

}
?>