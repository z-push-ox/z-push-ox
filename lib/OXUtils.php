<?php

class OXUtils
{

  /**
   * Returns an array which is the diff of $obj1 and $obj2
   *
   * @param SYNC_OBJECT $obj1
   * @param SYNC_OBJECT $obj2
   *
   * @access public
   * @return array
   */
  public function diffSyncObjects( $obj1, $obj2 )
  {
    // convert objects to arrays
    $obj1 = json_decode(json_encode($obj1), true);
    $obj2 = json_decode(json_encode($obj2), true);
    $diff = array();
    foreach ($obj1 as $key => $value) {
      if (array_key_exists($key, $obj2)) {
        if ($value != $obj2[$key]) {
          $diff[$key] = $value;
        }
      } else {
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
   *
   * @param array     $dataArray
   * @param array|object  $syncObject
   * @param array     $mapping
   * @param string    $dateTimeTarget   possible values: false|"ox"|"php"
   */
  public function mapValues( $dataArray, $syncObject, $mapping, $dateTimeTarget = false, $opt = array())
  {
    if (array_key_exists('allday', $opt)){
      $allday = $opt['allday'];
    }
    else{
      $allday = false;
    }
    if (array_key_exists('expectedTimezone', $opt)){
      $expectedTimezone = $opt['expectedTimezone'];
    }
    else{
      $expectedTimezone = null;
    }
    $timezone = null;
    
    $sections = array_keys($mapping);
    //ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(DEBUG: ' . json_encode($sections) . ')');

    foreach ($sections as &$section) {
      $datafields = array_keys($mapping[$section]);
      ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(Section: ' . $section . ')');
      foreach ($datafields as &$datafield) {
        if (array_key_exists($datafield, $dataArray)) {
          $asyncKey = $mapping[$section][$datafield];

          if ($section == 'strings') {
            $syncObject = $this -> _setValue($syncObject, $asyncKey, $this -> _getValue($dataArray, $datafield));
          }

          if ($section == 'booleans') {
            if ($dateTimeTarget == "ox") {
              if ($dataArray[$datafield] == 1) {
                $dataArray[$datafield] = 'true';
              } else {
                $dataArray[$datafield] = 'false';
              }
            }
            if ($dateTimeTarget == "php") {
              if ($dataArray[$datafield] == 'true') {
                $dataArray[$datafield] = 1;
              } else {
                $dataArray[$datafield] = 0;
              }
            }
            $syncObject = $this -> _setValue($syncObject, $asyncKey, $this -> _getValue($dataArray, $datafield));
          }
          
          if ($section == 'timezone'){
            ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(TIMEZONE START: ' . $this -> _getValue($dataArray, $datafield) . '   DateTimeTarget: ' . $dateTimeTarget . ')');
            switch ($dateTimeTarget)
            {
              case "ox" :
                $timezone = $this -> getTimezone($this -> _getValue($dataArray, $datafield));
                ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(TIMEZONE walking OX   TZ: ' . $timezone);
                break;
              case "php" :
                $timezone = $this -> getASTimezone($this -> _getValue($dataArray, $datafield));
                ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(TIMEZONE walking PHP   TZ: ' . $timezone);
                break;
            }
            ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(TIMEZONE END: ' . $timezone . ')');
            $syncObject = $this -> _setValue($syncObject, $asyncKey, $timezone);
          }
          
        }
      }
    }
    
    if ($timezone){
      if ($dateTimeTarget == 'php'){
        $timezone = $this -> getTimezone($timezone);
      }
    }
    
    foreach ($sections as &$section) {
      $datafields = array_keys($mapping[$section]);
      ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(Section: ' . $section . ')');
      // need to know the timezone if it is an allday event
      foreach ($datafields as &$datafield) {
        if (array_key_exists($datafield, $dataArray)) {
          $asyncKey = $mapping[$section][$datafield];
          
          if ($section == 'dates') {
            $datetTime = null;
            switch ($dateTimeTarget)
            {
              // OX uses UTC DateTimes while AciveSync uses localtime in allday events
              case "ox" :
                if ($allday){
                  ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(TIMEZONE used: ' . $timezone . ' DateTimeTarget: ' . $dateTimeTarget . ')');
                  $datetTime = $this -> _getValue($dataArray, $datafield);
                  $datetTime = $this -> convert_time_zone($datetTime, $timezone, 'UTC');
                  $datetTime = $this -> timestampPHPtoOX($datetTime);
                }
                else{
                  $datetTime = $this -> timestampPHPtoOX($this -> _getValue($dataArray, $datafield));
                }
                break;
              case "php" :
                if ($allday){
                  ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::mapValues(TIMEZONE used: ' . $timezone . ' DateTimeTarget: ' . $dateTimeTarget . ')');
                  $datetTime = $this -> timestampOXtoPHP($this -> _getValue($dataArray, $datafield));
                  //$datetTime = $this -> convert_time_zone($datetTime, 'UTC', $timezone);
                }
                else{
                  $datetTime = $this -> timestampOXtoPHP($this -> _getValue($dataArray, $datafield));
                }
                break;
              default :
                $datetTime = $this -> _getValue($dataArray, $datafield);
                break;
            }
            $syncObject = $this -> _setValue($syncObject, $asyncKey, $datetTime);
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
  public function timestampPHPtoOX( $phpstamp )
  {
    if ($phpstamp == null) {
      return null;
    }
    return $phpstamp . "000";
  }

  /**
   * Converts a OX timestamp to a php one
   *
   */
  public function timestampOXtoPHP( $oxstamp )
  {
    if (strlen($oxstamp) > 3) {
      $oxstamp = substr($oxstamp, 0, -3);
    } else {
      return 0;
    }
    return $oxstamp;
  }

  /**
   * reverses a mapping
   *
   * @param array $mapping
   */
  public function reversemap( $mapping )
  {
    $data = array();
    $sections = array_keys($mapping);
    foreach ($sections as &$section) {
      $data[$section] = array_flip($mapping[$section]);
    }
    return $data;
  }

  function jsaddslashes( $s )
  {
    $o = "";
    $l = strlen($s);
    for ($i = 0; $i < $l; $i++) {
      $c = $s[$i];
      switch($c)
      {
        case '<' :
          $o .= '\\x3C';
          break;
        case '>' :
          $o .= '\\x3E';
          break;
        case '\'' :
          $o .= '\\\'';
          break;
        case '\\' :
          $o .= '\\\\';
          break;
        case '"' :
          $o .= '\\"';
          break;
        case "\n" :
          $o .= '\\n';
          break;
        case "\r" :
          $o .= '\\r';
          break;
        default :
          $o .= $c;
      }
    }
    return $o;
  }
  
  /**
   * Returns a timezone abbreviation (e.g. CET, MST etc.) that matches to the {@param $_offsets}
   *
   * If {@see $_expectedTimezone} is set then the method will return this timezone if it matches.
   *
   * @param String | array $_offsets
   * @return String [timezone abbreviation e.g. CET, MST etc.]
   */
  function getTimezone($_offsets, $_expectedTimezone = null)
  {
    $TZconverter = ActiveSync_TimezoneConverter :: getInstance();
    return $TZconverter -> getTimezone($_offsets, $_expectedTimezone = null);
  }
  
  function getASTimezone($_timezone, $_startDate = null)
  {
    $TZconverter = ActiveSync_TimezoneConverter :: getInstance();
    return $TZconverter -> encodeTimezone($_timezone, $_startDate = null);
  }
  
  function convert_time_zone($date_time, $from_tz, $to_tz)
  {
    if (!$from_tz){
      $from_tz = 'UTC';
    }
    if (!$to_tz){
      $to_tz = 'UTC';
    }
    $date_time = intval($date_time);
    
    $time_object1 = new DateTime();
    $time_object1 -> setTimezone(new DateTimeZone($from_tz));
    $time_object1 -> setTimestamp($date_time);
    
    $time_object2 = new DateTime();
    $time_object2 -> setTimezone(new DateTimeZone($to_tz));
    $time_object2 -> setTimestamp($date_time);
    
    $offets = $time_object1 -> getOffset() - $time_object2 -> getOffset();
    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOX::convert_time_zone(offset: ' . $offets . '  newTimeStamp: ' . ($date_time + $offets) . ')');
    return $date_time + $offets;
  }
  
}
?>