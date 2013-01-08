<?php

class OXCalendarSync {

  private $mappingCalendarOXtoASYNC = array('strings' => array('title' => 'subject',
  //'timezone' => 'timezone',
  'uid' => 'uid',
  //'organizer' => 'organizeremail',
  'location' => 'location', 'note' => 'body', 'categories' => 'categories', ), 'dates' => array('start_date' => 'starttime', 'end_date' => 'endtime', ), 'booleans' => array('full_time' => 'alldayevent', ), 'timezone' => array('timezone' => 'timezone', ), );

  private $mappingCalendarASYNCtoOX = array();
  // will be filled after login

  private $mappingRecurrenceOXtoASYNC = array('strings' => array('interval' => 'interval', 'occurrences' => 'occurrences', 'days' => 'dayofweek',
  //'day_in_month' => 'dayofmonth',
  //'day_in_month' => 'weekofmonth',
  //'month' => 'monthofyear', offset of one
  ), 'dates' => array('until' => 'until', ), );

  private $mappingRecurrenceASYNCtoOX = array();
  // will be filled after login

  public function OXCalendarSync($OXConnector, $OXUtils) {
    $this -> OXConnector = $OXConnector;
    $this -> OXUtils = $OXUtils;
    $this -> mappingRecurrenceASYNCtoOX = $this -> OXUtils -> reversemap($this -> mappingRecurrenceOXtoASYNC);
    $this -> mappingCalendarASYNCtoOX = $this -> OXUtils -> reversemap($this -> mappingCalendarOXtoASYNC);
    ZLog::Write(LOGLEVEL_DEBUG, 'OXCalendarSync initialized.');
  }

  public function GetMessageList($folderid, $cutoffdate) {

    $folderid = $folder -> serverid;

    $response = $this -> OXConnector -> OXreqGET('/ajax/calendar', array('action' => 'all', 'session' => $this -> OXConnector -> getSession(), 'folder' => $folderid, 'columns' => '1,5,', //objectID�| last modified
    'start' => '0', 'end' => '2208988800000', 'recurrence_master' => 'true', ));
    ZLog::Write(LOGLEVEL_DEBUG, 'OXCalendarSync::GetMessageList(folderid: ' . $folderid . '  folder: ' . $folder -> displayname . '  data: ' . json_encode($response) . ')');
    foreach ($response["data"] as &$event) {
      $message = array();
      $message["id"] = $event[0];
      $message["mod"] = $event[1];
      $message["flags"] = 1;
      // always 'read'
      $messages[] = $message;
    }
    return $messages;
  }

  public function GetMessage($folder, $id, $contentparameters) {

    $folderid = $folder -> serverid;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXCalendarSync::GetMessage(' . $folderid . ', ' . $id . ', ...)');

    $response = $this -> OXConnector -> OXreqGET('/ajax/calendar', array('action' => 'get', 'session' => $this -> OXConnector -> getSession(), 'id' => $id, 'folder' => $folderid, 'recurrence_master' => 'true', ));
    ZLog::Write(LOGLEVEL_DEBUG, 'OXCalendarSync::GetMessage(appointment data: ' . json_encode($response["data"]) . ')');
    $event = $this -> mapValues($response["data"], new SyncAppointment(), $this -> mappingCalendarOXtoASYNC, 'php');
    $event -> timezone = 'UTC';
    $event -> recurrence = $this -> recurrenceOX2Async($response["data"]);
    ZLog::Write(LOGLEVEL_DEBUG, 'OXCalendarSync::GetMessage(' . $folderid . ', ' . $id . ', event: ' . json_encode($event) . ')');
    return $event;
  }

  public function StatMessage($folder, $id) {
    $folderid = $folder -> serverid;

    // Default values:
    $message = array();
    $message["id"] = $id;
    $message["flags"] = 1;

    ZLog::Write(LOGLEVEL_DEBUG, 'OXCalendarSync::StatMessage(' . $folderid . ', ' . $id . ', ...)');

    $response = $this -> OXConnector -> OXreqGET('/ajax/calendar', array('action' => 'get', 'session' => $this -> OXConnector -> getSession(), 'id' => $id, 'folder' => $folderid, 'recurrence_master' => 'true', ));
    $message["mod"] = $response["data"]["last_modified"];
    return $message;
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
  public function SetReadFlag($folder, $id, $flags) {
    ZLog::Write(LOGLEVEL_DEBUG, 'OXCalendarSync::SetReadFlag(' . $folderid . ', ' . $id . ', ' . $flags . ')');
  }

  private function recurrenceOX2Async($data) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::recurrenceOX2Async(' . json_encode($data) . ')');
    $recurrence = new SyncRecurrence;
    switch ($data["recurrence_type"]) {
      case 0 :
        //no recurrence
        $recurrence = null;
        break;

      case 1 :
        //daily
        $recurrence -> type = 0;
        $this -> mapValues($data, $recurrence, $this -> mappingRecurrenceOXtoASYNC, 'php');
        break;

      case 2 :
        //weekly
        $recurrence -> type = 1;
        $this -> mapValues($data, $recurrence, $this -> mappingRecurrenceOXtoASYNC, 'php');
        $recurrence -> dayofmonth = $data["day_in_month"];
        break;

      case 3 :
        //monthly | monthly on the nth day
        $this -> mapValues($data, $recurrence, $this -> mappingRecurrenceOXtoASYNC, 'php');
        if ($recurrence -> dayofweek) {
          //monthly on the nth day
          $recurrence -> type = 3;
          $recurrence -> weekofmonth = $data["day_in_month"];
        } else {
          //monthly
          $recurrence -> type = 2;
          $recurrence -> dayofmonth = $data["day_in_month"];
        }
        break;

      case 4 :
        //yearly
        $this -> mapValues($data, $recurrence, $this -> mappingRecurrenceOXtoASYNC, 'php');
        $recurrence -> monthofyear = intval($data["month"]) + 1;
        if ($recurrence -> dayofweek) {
          //yearly
          $recurrence -> type = 6;
          $recurrence -> weekofmonth = $data["day_in_month"];
        } else {
          //yearly on the nth day
          $recurrence -> type = 5;
          $recurrence -> dayofmonth = $data["day_in_month"];
        }
        break;
    }
    return $recurrence;
  }

  private function recurrenceAsync2OX($data) {
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::recurrenceAsync2OX(' . json_encode($data) . ')');

    $recurrence = array();

    if (!$data) {
      //if $data is null there is no recurrence
      $recurrence['recurrence_type'] = "0";
      //$recurrence["day_in_month"] = null;
      //$recurrence['interval'] = null;
      //$recurrence['until'] = null;
      //$recurrence['days'] = null;
      //$recurrence['occurrences'] = null;
      return $recurrence;
    }

    switch ($data->type) {

      case 0 :
        //daily
        $recurrence["recurrence_type"] = 1;
        $recurrence = array_merge($this -> mapValues($data, $recurrence, $this -> mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
        unset($recurrence['days']);
        break;

      case 1 :
        //weekly
        $recurrence["recurrence_type"] = 2;
        $recurrence = array_merge($this -> mapValues($data, $recurrence, $this -> mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
        break;

      case 2 :
        //monthly
        $recurrence["recurrence_type"] = 3;
        $recurrence = array_merge($this -> mapValues($data, $recurrence, $this -> mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
        $recurrence["day_in_month"] = $data -> dayofmonth;
        unset($recurrence['days']);
        break;

      case 3 :
        //monthly on the nth day
        $recurrence["recurrence_type"] = 3;
        $recurrence = array_merge($this -> mapValues($data, $recurrence, $this -> mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
        $recurrence["day_in_month"] = $data -> weekofmonth;
        break;

      case 5 :
        //yearly
        $recurrence["recurrence_type"] = 4;
        $recurrence = array_merge($this -> mapValues($data, $recurrence, $this -> mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
        $recurrence["month"] = intval($data -> monthofyear) - 1;
        $recurrence["day_in_month"] = $data -> dayofmonth;
        break;

      case 6 :
        //yearly on the nth day
        $recurrence["recurrence_type"] = 4;
        $recurrence = array_merge($this -> mapValues($data, $recurrence, $this -> mappingRecurrenceASYNCtoOX, 'ox'), $recurrence);
        $recurrence["month"] = intval($data -> monthofyear) - 1;
        $recurrence["day_in_month"] = $data -> weekofmonth;
        break;
    }

    if (array_key_exists('occurrences', $recurrence) && $recurrence['occurrences'] == null) {
      unset($recurrence['occurrences']);
    }

    if ($recurrence["recurrence_type"] > 2 && array_key_exists('days', $recurrence) && $recurrence['days'] == null) {
      $recurrence['days'] = 127;
    }

    return $recurrence;
  }

}
?>