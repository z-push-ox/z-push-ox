<?php

require_once ('lib/utils/timezoneutil.php');
require_once 'HTTP/Request2.php';

class OXConnector
{

  private $session = false;
  private $cookiejar = true;

  public function OXConnector( )
  {
  }

  public function setSession( $session )
  {
    $this -> session = $session;
    ZLog::Write(LOGLEVEL_DEBUG, "OXConnector::setSession($session)");
  }

  public function getSession( )
  {
    return $this -> session;
  }

  public function OXreqGET( $url, $QueryVariables, $returnResponseObject = false )
  {
    $QueryVariables['timezone'] = 'UTC';
    # all times are UTC
    $request = new HTTP_Request2( OX_SERVER . $url, HTTP_Request2::METHOD_GET );
    $url = $request -> getUrl();
    $url -> setQueryVariables($QueryVariables);
    return $this -> OXreq($request, $returnResponseObject);
  }

  public function OXreqPOST( $url, $QueryVariables, $returnResponseObject = false )
  {
    $request = new HTTP_Request2( OX_SERVER . $url, HTTP_Request2::METHOD_POST );
    $request -> addPostParameter($QueryVariables);
    return $this -> OXreq($request, $returnResponseObject);
  }

  public function OXreqPUT( $url, $QueryVariables, $PutData, $returnResponseObject = false )
  {
    $QueryVariables['timezone'] = 'UTC';
    # all times are UTC,
    $request = new HTTP_Request2( OX_SERVER . $url, HTTP_Request2::METHOD_PUT );
    $request -> setHeader('Content-type: text/javascript; charset=utf-8');
    $url = $request -> getUrl();
    $url -> setQueryVariables($QueryVariables);
    $request -> setBody(utf8_encode(json_encode($PutData)));
    return $this -> OXreq($request, $returnResponseObject);
  }

  public function OXreq( $requestOBJ, $returnResponseObject )
  {
    $requestOBJ -> setCookieJar($this -> cookiejar);
    $requestOBJ -> setHeader('User-Agent: z-push-ox (Version ' . BackendOX::getBackendVersion() . ')');
    $response = $requestOBJ -> send();
    $this -> cookiejar = $requestOBJ -> getCookieJar();
    if ($returnResponseObject) {
      return $response;
    }
    if (200 != $response -> getStatus()) {
      ZLog::Write(LOGLEVEL_WARN, 'BackendOX::OXreq(error: ' . $response -> getBody() . ')');
      return false;
    }
    try {
      $data = json_decode($response -> getBody(), true);
      if (is_array($data) && array_key_exists("error", $data)) {
        ZLog::Write(LOGLEVEL_WARN, 'BackendOX::OXreq(error: ' . $response -> getBody() . ')');
        return false;
      } else {
        return $data;
      }
    } catch (Exception $e) {
      return false;
    }
  }

  public function OXreqPUTforSendMail( $url, $QueryVariables, $PutData, $returnResponseObject = false )
  {
    $QueryVariables['timezone'] = 'UTC';
    # all times are UTC,
    $request = new HTTP_Request2( OX_SERVER . $url, HTTP_Request2::METHOD_PUT );
    $request -> setHeader('Content-type: text/javascript; charset=utf-8');
    $url = $request -> getUrl();
    $url -> setQueryVariables($QueryVariables);
    $request -> setBody(utf8_encode($PutData));
    return $this -> OXreq($request, $returnResponseObject);
  }

}
?>
