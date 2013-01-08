<?php

class OXUtils
{
  
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
   * @param array     $dataArray
   * @param array|object  $syncObject
   * @param array     $mapping
   * @param string    $dateTimeTarget   possible values: false|"ox"|"php"
   */
  public function mapValues($dataArray, $syncObject, $mapping, $dateTimeTarget=false, $timezoneOffset=0){
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
  public function timestampPHPtoOX($phpstamp, $timezoneOffset=0){
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
  public function timestampOXtoPHP($oxstamp, $timezoneOffset=0){
    if (strlen($oxstamp) > 3){
      $oxstamp = substr($oxstamp, 0, -3);
    } else {
      return $timezoneOffset;
    }
    $oxstamp = intval($oxstamp) + $timezoneOffset;
    return $oxstamp;
  }
  
    /**
   * reverses a mapping
   * 
   * @param array $mapping
   */
  public function reversemap($mapping){
    $data = array();
    $sections = array_keys($mapping);
    foreach ($sections as &$section){
      $data[$section] = array_flip($mapping[$section]);
    }
    return $data;
  }
  
}

?>