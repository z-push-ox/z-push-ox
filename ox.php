<?php

/*
 * This work is licensed under the Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-nc-sa/3.0/
 */

include_once('lib/default/diffbackend/diffbackend.php');
include_once('include/mimeDecode.php');
require_once('include/z_RFC822.php');
require_once('lib/utils/timezoneutil.php');
require_once 'HTTP/Request2.php';

class BackendOX extends BackendDiff {
	
	private $session = false;
	private $cookiejar = true;
	private $root_folder = array();
	
	/*
	ToDo:
    public $bodysize;
    public $bodytruncated;
    public $children;
    public $officelocation;
    
    * String indicating the Japanese phonetic rendering
    * http://msdn.microsoft.com/en-us/library/office/aa221860(v=office.11).aspx 

    
    public $rtf;
    public $picture;

    // AS 2.5 props
    public $customerid;
    public $governmentid;
    public $companymainphone;
    public $accountname;
    public $mms;
    
     * the following can not be supported because there are no datafields in OX:
     * - imaddress3
	 */
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
						
					'email1'  => 'email1address',
					'email2'  => 'email2address',
					'email3'  => 'email3address',
						
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
			
			'dates' => array(
					'birthday' => 'birthday',
					),
			
			'datetimes' => array(
					),
	);
	
	public $mappingContactsASYNCtoOX = array(); // will be filled after login
	
	
	/*
	ToDo:
    public $organizername;
    public $recurrence;
    public $sensitivity;
    public $busystatus;
    public $reminder;
    public $rtf;
    public $meetingstatus;
    public $attendees;
    public $bodytruncated;
    public $exception;
    public $deleted;
    public $exceptionstarttime;

    // AS 12.0 props
    public $asbody;
    public $nativebodytype;

    // AS 14.0 props
    public $disallownewtimeprop;
    public $responsetype;
    public $responserequested;
	 */
	public $mappingCalendarOXtoASYNC = array(
			'strings' => array(
					'title' => 'subject',
					//'timezone' => 'timezone',
					'uid' => 'uid',
					//'organizer' => 'organizeremail',
					'location' => 'location',
					'note' => 'body',
					'categories' => 'categories',
					),
			
			'dates' => array(
					'start_date' => 'starttime',
					'end_date' => 'endtime',
			),
			
			'booleans' => array(
					'full_time' => 'alldayevent',
			),
	);
	
	public $mappingCalendarASYNCtoOX = array(); // will be filled after login
	
	
	public $mappingRecurrenceOXtoASYNC = array(
			'strings' => array(
					'interval' => 'interval',
					'occurrences' => 'occurrences',
					'days' => 'dayofweek',
					//'day_in_month' => 'dayofmonth',
					//'day_in_month' => 'weekofmonth',
					//'month' => 'monthofyear', offset of one
					),
			'dates' => array(
					'until' => 'until',
					),
			);
	
	public $mappingRecurrenceASYNCtoOX = array(); // will be filled after login
	
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
		$response = $this->OXreqPOST('/ajax/login?action=login', array(
        		'name' => $username,
        		'password' => $password,
    	));
		if ($response){
			if (array_key_exists("session", $response)){
				$this->session = $response["session"];
				ZLog::Write(LOGLEVEL_DEBUG, "BackendOX::Logon() - login success: " . $this->session);
				// ToDo: this needs refactoring!
				$this->mappingContactsASYNCtoOX = array(
						'strings' => array_flip($this->mappingContactsOXtoASYNC['strings']),
						'dates' => array_flip($this->mappingContactsOXtoASYNC['dates']),
						'datetimes' => array_flip($this->mappingContactsOXtoASYNC['datetimes']),
						);
				$this->mappingCalendarASYNCtoOX = array(
						'strings' => array_flip($this->mappingCalendarOXtoASYNC['strings']),
						'dates' => array_flip($this->mappingCalendarOXtoASYNC['dates']),
						//'datetimes' => array_flip($this->mappingCalendarOXtoASYNC['datetimes']),
						'booleans' => array_flip($this->mappingCalendarOXtoASYNC['booleans']),
						);
				$this->mappingRecurrenceASYNCtoOX = array(
						'strings' => array_flip($this->mappingRecurrenceOXtoASYNC['strings']),
						'dates'=> array_flip($this->mappingRecurrenceOXtoASYNC['dates']),
						);
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
		$response = $this->OXreqGET('/ajax/login', array(
				'action' => 'logout',
				'session' => $this->session,
		));
		if ($response) {
			ZLog::Write(LOGLEVEL_DEBUG, "BackendOX::Logoff() - logoff success ");
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
		$response = $this->OXreqGET('/ajax/folders', array(
				'action' => 'root',
				'session' => $this->session,
				//http://oxpedia.org/wiki/index.php?title=HTTP_API#CommonFolderData
				'columns' => '1,',
		));
		if ($response) {
			$folder_list = array();	
			foreach ($response["data"] as &$root_folder) {
				$root_folder = $root_folder[0];
				$this->root_folder[] = $root_folder;
				$folderlist = $this->GetSubFolders($root_folder);
				foreach ($folderlist as &$folder){
					$folder_list[] = $this->StatFolder($folder);
				}
			}
		}
		return $folder_list;
	}
	
	private function GetSubFolders($id){
		$lst = array();
		$response = $this->OXreqGET('/ajax/folders', array(
				'action' => 'list',
				'session' => $this->session,
				//http://oxpedia.org/wiki/index.php?title=HTTP_API#CommonFolderData
				'parent' => $id,
				'columns' => '1,301,',
		));
		foreach ($response["data"] as &$folder) {
			// restrict to contacts | calendar
			if (in_array($folder[1], array("contacts", "calendar"))){
				$lst[] = $folder[0];
				$subfolders = $this->GetSubFolders($folder[0]);
				foreach ($subfolders as &$subfolder){
					$lst[] = $subfolder;
				}
			}
		}
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
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetFolder('.$id.')');
		$response = $this->OXreqGET('/ajax/folders', array(
				'action' => 'get',
				'session' => $this->session,
				'id' => $id,
				//http://oxpedia.org/wiki/index.php?title=HTTP_API#CommonFolderData
				'columns' => '1,20,300,301,', //objectIDÊ| parentfolderID | title | module/type
		));
		if ($response) {
			$folder = new SyncFolder();
			$folder->serverid = $id;
			if (array_key_exists($response["data"]["folder_id"], $this->root_folder)){
				$folder->parentid = "0";
			}
			else {
				$folder->parentid = $response["data"]["folder_id"];
			}
			$folder->displayname = $response["data"]["title"];
			switch ($response["data"]["module"]) {
				case "contacts":
					$folder->type = SYNC_FOLDER_TYPE_CONTACT;
					break;
				case "calendar":
					$folder->type = SYNC_FOLDER_TYPE_APPOINTMENT;
					break;
				default:
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
		$folder = $this->GetFolder($id);
	
		$stat = array();
		$stat["id"] = $id;
		$stat["parent"] = $folder->parentid;
		$stat["mod"] = $folder->displayname;
		
		ZLog::Write(LOGLEVEL_DEBUG, "BackendOX::StatFolder(' . $id . ') - values: " . json_encode($stat));
		
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
	public function ChangeFolder($folderid, $oldid, $displayname, $type){
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::ChangeFolder(' . $folderid . ',' . $oldid . ',' . $displayname . ','  . $type . ')');
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
	public function DeleteFolder($id, $parentid){
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
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetMessageList('.$folderid.')  cutoffdate: ' . $cutoffdate);
		$folder = $this->GetFolder($folderid);
		$messages = array();
		
		// handle contacts
		if ($folder->type == SYNC_FOLDER_TYPE_CONTACT){
			$response = $this->OXreqGET('/ajax/contacts', array(
					'action' => 'all',
					'session' => $this->session,
					'folder' => $folderid,
					'columns' => '1,5,', //objectIDÊ| last modified
			));
			ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetMessageList(folderid: '.$folderid.'  folder: ' . $folder->displayname . '  data: ' . json_encode($response) . ')');
			foreach ($response["data"] as &$contact) {
				$message = array();
				$message["id"] = $contact[0];
				$message["mod"] = $contact[1];
				$message["flags"] = 1; // always 'read'
				$messages[] = $message;
			}
			return $messages;
		}
		
		// handle calendar
		if ($folder->type == SYNC_FOLDER_TYPE_APPOINTMENT){
			$response = $this->OXreqGET('/ajax/calendar', array(
					'action' => 'all',
					'session' => $this->session,
					'folder' => $folderid,
					'columns' => '1,5,', //objectIDÊ| last modified
					'start' => '0',
					'end' => '2208988800000',
					'recurrence_master' => 'true',
			));
			ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetMessageList(folderid: '.$folderid.'  folder: ' . $folder->displayname . '  data: ' . json_encode($response) . ')');
			foreach ($response["data"] as &$event) {
				$message = array();
				$message["id"] = $event[0];
				$message["mod"] = $event[1];
				$message["flags"] = 1; // always 'read'
				$messages[] = $message;
			}
			return $messages;
		}
		
		return false;
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
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetMessage('.$folderid.', '.$id.', ..)');
		$folder = $this->GetFolder($folderid);
		
		// handle contacts
		if ($folder->type == SYNC_FOLDER_TYPE_CONTACT){
			$response = $this->OXreqGET('/ajax/contacts', array(
					'action' => 'get',
					'session' => $this->session,
					'id' => $id,
					'folder' => $folderid,
			));
			return $this->mapValues($response["data"], new SyncContact(), $this->mappingContactsOXtoASYNC, 'php');
		}
		
		// handle calendar
		if ($folder->type == SYNC_FOLDER_TYPE_APPOINTMENT){
			$response = $this->OXreqGET('/ajax/calendar', array(
					'action' => 'get',
					'session' => $this->session,
					'id' => $id,
					'folder' => $folderid,
					'recurrence_master' => 'true',
			));
			ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetMessage(appointment data: ' . json_encode($response["data"]) . ')');
			$event = $this->mapValues($response["data"], new SyncAppointment(), $this->mappingCalendarOXtoASYNC, 'php');
			$event->timezone = 'UTC';
			$event->recurrence = $this->recurrenceOX2Async($response["data"]);
			ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::GetMessage('.$folderid.', '.$id.', event: ' . json_encode($event) . ')');
			return $event;
		}
		
		return;
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
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::StatMessage('.$folderid.', '.$id.')');
		$folder = $this->GetFolder($folderid);
		$message = array();
		$message["id"] = $id;
		$message["flags"] = 1; // always 'read'
		
		// handle contacts
		if ($folder->type == SYNC_FOLDER_TYPE_CONTACT){
			$response = $this->OXreqGET('/ajax/contacts', array(
					'action' => 'get',
					'session' => $this->session,
					'id' => $id,
					'folder' => $folderid,
			));
			ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::StatMessage(folderid: '.$folderid.'  folder: ' . $folder->displayname . '  contactid:' . $id . '  data: ' . json_encode($response) . ')');
			$message["mod"] = $response["data"]["last_modified"];
			return $message;
		}
		
		// handle calendar
		if ($folder->type == SYNC_FOLDER_TYPE_APPOINTMENT){
			$response = $this->OXreqGET('/ajax/calendar', array(
					'action' => 'get',
					'session' => $this->session,
					'id' => $id,
					'folder' => $folderid,
					'recurrence_master' => 'true',
			));
			$message["mod"] = $response["data"]["last_modified"];
			return $message;
		}
		
		return false;
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
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::ChangeMessage('.$folderid.', '.$id.', message: ' . json_encode($message) . ')');
		$folder = $this->GetFolder($folderid);
		
		if(!$id){
			//id is not set => create object
			
			if ($folder->type == SYNC_FOLDER_TYPE_CONTACT){
				$createResponse = $this->OXreqPUT('/ajax/contacts', array(
						'action' => 'new',
						'session' => $this->session,
					), array(
						'folder_id' => $folderid, // set the folder in which the user should be created
				));
			}
			
			if ($folder->type == SYNC_FOLDER_TYPE_APPOINTMENT){
				$createResponse = $this->OXreqPUT('/ajax/calendar', array(
						'action' => 'new',
						'session' => $this->session,
				), array(
						'folder_id' => $folderid, // set the folder in which the user should be created
						'start_date' => 0,
						'end_date' => 0,
				));
			}
			
			if (!$createResponse){
				ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::ChangeMessage(failed to create object in folder: ' . $folder->displayname . ')');
				throw new StatusException('failed to create new object in folder: ' . $folder->displayname, SYNC_STATUS_SYNCCANNOTBECOMPLETED);
				return false;
			}
			$id = $createResponse["data"]["id"];
		}
		
		$oldmessage = $this->GetMessage($folderid, $id, null);
		$diff = $this->diffSyncObjects($message, $oldmessage);
		$stat = $this->StatMessage($folderid, $id);
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::ChangeMessage(folder: ' . $folder->displayname . '  data: ' . json_encode($message) . ')');
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::ChangeMessage(folder: ' . $folder->displayname . '  oldmessage: ' . json_encode($oldmessage) . ')');
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::ChangeMessage(folder: ' . $folder->displayname . '  sourceDataChanged: ' . json_encode($diff) . ')');
		
		// handle contacts
		if ($folder->type == SYNC_FOLDER_TYPE_CONTACT){
			$diffOX = $this->mapValues($diff, array(), $this->mappingContactsASYNCtoOX, 'ox');
			$response = $this->OXreqPUT('/ajax/contacts', array(
					'action' => 'update',
					'session' => $this->session,
					'folder' => $folderid,
					'id' => $id,
					'timestamp' => $stat["mod"],
			), $diffOX);
		}
		
		
		// handle calendar
		if ($folder->type == SYNC_FOLDER_TYPE_APPOINTMENT){
			$diffOX = $this->mapValues($diff, array(), $this->mappingCalendarASYNCtoOX, 'ox');
			$diffOX = array_merge($diffOX, $this->recurrenceAsync2OX($message->recurrence)); //append recurrence data
			//ZLog::Write(LOGLEVEL_DEBUG, "recurrencedata: " . json_encode( $this->recurrenceAsync2OX($message->recurrence) ));
			$response = $this->OXreqPUT('/ajax/calendar', array(
					'action' => 'update',
					'session' => $this->session,
					'folder' => $folderid,
					'id' => $id,
					'timestamp' => $stat["mod"],
			), $diffOX);
		}
		
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::ChangeMessage(folder: ' . $folder->displayname . '  dataChanged: ' . json_encode($diffOX) . ')');
		
		if ($response){
			ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::ChangeMessage(successfully changed - folder: ' . $folder->displayname . '   id: ' . $id .  ')');
			return $this->StatMessage($folderid, $id);
		} else {
			throw new StatusException('could not change contact: ' . $id . ' in folder: ' . $folder->displayname, SYNC_STATUS_SYNCCANNOTBECOMPLETED);
			return false;
		}
		
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
	public function SetReadFlag($folderid, $id, $flags) {
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::SetReadFlag('.$folderid.', '.$id.', ..)');
		return false;
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
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::DeleteMessage('.$folderid.', '.$id.')');
		$folder = $this->GetFolder($folderid);
		
		//handle contacts
		if ($folder->type == SYNC_FOLDER_TYPE_CONTACT){
			$stat = $this->StatMessage($folderid, $id);
			$response = $this->OXreqPUT('/ajax/contacts', array(
					'action' => 'delete',
					'session' => $this->session,
					'timestamp' => $stat["mod"],
			), array(
					'folder' => $folderid,
					'id' => $id,
			));
			if ($response){
				return true;
			}
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
	public function MoveMessage($folderid, $id, $newfolderid) {
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::MoveMessage('.$folderid.', '.$id.'...)');
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
	
	private function recurrenceOX2Async($data){
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::recurrenceOX2Async(' . json_encode($data) . ')');
		$recurrence = new SyncRecurrence;
		switch ($data["recurrence_type"]){
			case 0:	//no recurrence
				$recurrence = null;
				break;
					
			case 1: //daily
				$recurrence->type = 0;
				$this->mapValues($data, $recurrence, $this->mappingRecurrenceOXtoASYNC, 'php');
				break;
					
			case 2: //weekly
				$recurrence->type = 1;
				$this->mapValues($data, $recurrence, $this->mappingRecurrenceOXtoASYNC, 'php');
				$recurrence->dayofmonth = $data["day_in_month"];
				break;
					
			case 3: //monthly | monthly on the nth day
				$this->mapValues($data, $recurrence, $this->mappingRecurrenceOXtoASYNC, 'php');
				if ($recurrence->dayofweek){
					//monthly on the nth day
					$recurrence->type = 3;
					$recurrence->weekofmonth = $data["day_in_month"];
				}
				else {
					//monthly
					$recurrence->type = 2;
					$recurrence->dayofmonth = $data["day_in_month"];
				}
				break;
					
			case 4: //yearly
				$recurrence->type = 6;
				$this->mapValues($data, $recurrence, $this->mappingRecurrenceOXtoASYNC, 'php');
				$recurrence->monthofyear = intval($data["month"]) + 1;
				$recurrence->weekofmonth = $data["day_in_month"];
				break;
		}
		return $recurrence;
	}
	
	private function recurrenceAsync2OX($data){
		ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::recurrenceAsync2OX(' . json_encode($data) . ')');
		
		$recurrence = array();
		
		if (!$data){
			$recurrence['recurrence_type'] = "0";
			//$recurrence["day_in_month"] = null;
			//$recurrence['interval'] = null;
			//$recurrence['until'] = null;
			//$recurrence['days'] = null;
			//$recurrence['occurrences'] = null;
			return $recurrence;
		}
		
		switch ($data->type){
			
			/*
			case null: //no recurrence
				$recurrence = array_merge( $this->mapValues($data, $recurrence, $this->mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
				$recurrence["recurrence_type"] = 0;
				unset($recurrence['days']);
				break;
				*/
			case 0: //daily
				$recurrence["recurrence_type"] = 1;
				$recurrence = array_merge( $this->mapValues($data, $recurrence, $this->mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
				unset($recurrence['days']);
				break;
				
			case 1: //weekly
				$recurrence["recurrence_type"] = 2;
				$recurrence = array_merge( $this->mapValues($data, $recurrence, $this->mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
				break;
				
			case 2: //monthly
				$recurrence["recurrence_type"] = 3;
				$recurrence = array_merge( $this->mapValues($data, $recurrence, $this->mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
				$recurrence["day_in_month"] = $data->dayofmonth;
				unset($recurrence['days']);
				break;
				
			case 3: //monthly on the nth day
				$recurrence["recurrence_type"] = 3;
				$recurrence = array_merge( $this->mapValues($data, $recurrence, $this->mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
				$recurrence["day_in_month"] = $data->weekofmonth;
				break;
				
			case 6: //yearly
				$recurrence["recurrence_type"] = 4;
				$recurrence = array_merge( $this->mapValues($data, $recurrence, $this->mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
				$recurrence["month"] = intval($data->monthofyear) - 1;
				$recurrence["day_in_month"] = $data->weekofmonth;
				break;
		}
		
		if (array_key_exists('occurrences', $recurrence) && $recurrence['occurrences'] == null){
			unset($recurrence['occurrences']);
		}
		
		if ($recurrence["recurrence_type"] > 2 && array_key_exists('days', $recurrence) && $recurrence['days'] == null){
			$recurrence['days'] = 127;
		}
		
		return $recurrence;
	}
	
	/**
	 * Returns an array which is the diff of $obj1 and $obj2
	 * 
	 * @param SYNC_OBJECT $obj1
	 * @param SYNC_OBJECT $obj2
	 * 
	 * @access private
	 * @return array
	 */
	private function diffSyncObjects($obj1, $obj2){
		// convert objects to arrays
		$obj1 = json_decode(json_encode($obj1), true);
		$obj2 = json_decode(json_encode($obj2), true);
		$diff = array();
		foreach ($obj1 as $key => $value){
			if (array_key_exists($key, $obj2)){
				if ($value != $obj2[$key]){
					$diff[$key] = $value;
				}
			}
			else {
				$diff[$key] = $value;
			}
		}
		return $diff;
	}
	
	/**
	 * Get the offset between two timezones in secounds
	 * 
	 * @param int $sourceTimezone
	 * @param int $destinationTimezone
	 * @return int
	 */
	private function getTimezoneOffset($sourceTimezone, $destinationTimezone){
		if($sourceTimezone === null) {
			$sourceTimezone = date_default_timezone_get();
		}
		if($destinationTimezone === null) {
			$destinationTimezone = date_default_timezone_get();
		}
		$sourceTimezone = new DateTimeZone($sourceTimezone);
		$destinationTimezone = new DateTimeZone($destinationTimezone);
		$now = time();
		$sourceDate = new DateTime("now", $sourceTimezone);
		$destinationDate = new DateTime("now", $destinationTimezone);
		return $destinationTimezone->getOffset($destinationDate) - $sourceTimezone->getOffset($sourceDate);
	}
	
	
	/**
	 * helper function for mapValues
	 * 
	 * @param unknown $object
	 * @param string $key
	 * @param unknown $value
	 */
	private function _setValue($object, $key, $value){
		if (gettype($object) == 'array'){
			$object[$key] = $value;
		}
		else {
			$object->$key = $value;
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
	private function _getValue($object, $key){
		if (gettype($object) == 'array'){
			return $object[$key];
		}
		else {
			return $object->$key;
		}
	}
	
	/**
	 * 
	 * @param array			$dataArray
	 * @param array|object	$syncObject
	 * @param array			$mapping
	 * @param string 		$dateTimeTarget		possible values: false|"ox"|"php"
	 */
	private function mapValues($dataArray, $syncObject, $mapping, $dateTimeTarget=false, $timezoneOffset=0){
		//ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(offset: ' . $timezoneOffset . ')');
		$sections = array_keys($mapping);
		//ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(DEBUG: ' . json_encode($sections) . ')');
		
		foreach ($sections as &$section){
			$datafields = array_keys($mapping[$section]);
			//ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(DEBUG2: ' . json_encode($datafields) . ')');
			foreach ($datafields as &$datafield){
				if (array_key_exists($datafield, $dataArray)){
					$asyncKey = $mapping[$section][$datafield];
					
					if ($section == 'strings'){
						$syncObject = $this->_setValue($syncObject, $asyncKey, $this->_getValue($dataArray, $datafield));
					}
					
					if ($section == 'dates'){
						$datetTime = null;
						switch ($dateTimeTarget) {
							case "ox":
								$datetTime = $this->timestampPHPtoOX($this->_getValue($dataArray, $datafield), $timezoneOffset);
								break;
							case "php":
								$datetTime = $this->timestampOXtoPHP($this->_getValue($dataArray, $datafield), $timezoneOffset);
								break;
							default:
								$datetTime = $this->_getValue($dataArray, $datafield);
								break;
						}
						$syncObject = $this->_setValue($syncObject, $asyncKey, $datetTime);
					}
					
					if ($section == 'booleans'){
						if ($dateTimeTarget == "ox"){
							if ($dataArray[$datafield] == 1){
								$dataArray[$datafield] = 'true';
							}
							else{
								$dataArray[$datafield] = 'false';
							}
						}
						if ($dateTimeTarget == "php"){
							if ($dataArray[$datafield] == 'true' ){
								$dataArray[$datafield] = 1;
							}
							else{
								$dataArray[$datafield] = 0;
							}
						}
						$syncObject = $this->_setValue($syncObject, $asyncKey, $this->_getValue($dataArray, $datafield));
						
					}
				}
			}
		}
		return $syncObject;
	}
	
	/**
	 * Converts a php timestamp to a OX one
	 * 
	 */
	private function timestampPHPtoOX($phpstamp, $timezoneOffset=0){
		if ($phpstamp == null){
			return null;
		}
		$phpstamp = intval($phpstamp) + $timezoneOffset;
		return $phpstamp . "000";
	}
	
	/**
	 * Converts a OX timestamp to a php one
	 *
	 */
	private function timestampOXtoPHP($oxstamp, $timezoneOffset=0){
		if (strlen($oxstamp) > 3){
			$oxstamp = substr($oxstamp, 0, -3);
		} else {
			return $timezoneOffset;
		}
		$oxstamp = intval($oxstamp) + $timezoneOffset;
		return $oxstamp;
	}
	
	private function OXreqGET($url, $QueryVariables, $returnResponseObject=false){
		$QueryVariables['timezone'] = 'UTC';	# all times are UTC
		$request = new HTTP_Request2(OX_SERVER . $url, HTTP_Request2::METHOD_GET);
		$url = $request->getUrl();
		$url->setQueryVariables($QueryVariables);
		return $this->OXreq($request, $returnResponseObject);
	}
	
	private function OXreqPOST($url, $QueryVariables, $returnResponseObject=false){
		$request = new HTTP_Request2(OX_SERVER . $url, HTTP_Request2::METHOD_POST);
		$request->addPostParameter($QueryVariables);
		return $this->OXreq($request, $returnResponseObject);
	}
	
	private function OXreqPUT($url, $QueryVariables, $PutData, $returnResponseObject=false){
		$QueryVariables['timezone'] = 'UTC';	# all times are UTC,
		$request = new HTTP_Request2(OX_SERVER . $url, HTTP_Request2::METHOD_PUT);
		$request->setHeader('Content-type: text/javascript; charset=utf-8');
		$url = $request->getUrl();
		$url->setQueryVariables($QueryVariables);
		$request->setBody(utf8_encode(json_encode($PutData)));
		return $this->OXreq($request, $returnResponseObject);
	}
	
	private function OXreq($requestOBJ, $returnResponseObject){
		$requestOBJ->setCookieJar($this->cookiejar);
		$response = $requestOBJ->send();
		$this->cookiejar = $requestOBJ->getCookieJar();
		if ($returnResponseObject){
			return $response;
		}
		if (200 != $response->getStatus()){
			ZLog::Write(LOGLEVEL_WARN, 'BackendOX::OXreq(error: ' . $response->getBody() . ')');
			return false;
		}
		try {
			$data = json_decode($response->getBody(), true);
			if (array_key_exists("error", $data)){
				ZLog::Write(LOGLEVEL_WARN, 'BackendOX::OXreq(error: ' . $response->getBody() . ')');
				return false;
			}
			else {
				return $data;
			}
		}
		catch (Exception $e) {
			return false;
		}
	}
}

?>