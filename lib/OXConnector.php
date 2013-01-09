<?php

require_once ('lib/utils/timezoneutil.php');
require_once 'HTTP/Request2.php';

class OXConnector {

  private $session = false;
  private $cookiejar = true;

  public function OXConnector() {
  }

  public function setSession($session) {
    $this -> session = $session;
    ZLog::Write(LOGLEVEL_DEBUG, "OXConnector::setSession($session)");
  }

  public function getSession() {
    return $this -> session;
  }

  public function OXreqGET($url, $QueryVariables, $returnResponseObject = false) {
    $QueryVariables['timezone'] = 'UTC';
    # all times are UTC
    $request = new HTTP_Request2(OX_SERVER . $url, HTTP_Request2::METHOD_GET);
    $url = $request -> getUrl();
    $url -> setQueryVariables($QueryVariables);
    return $this -> OXreq($request, $returnResponseObject);
  }

  public function OXreqPOST($url, $QueryVariables, $returnResponseObject = false) {
    $request = new HTTP_Request2(OX_SERVER . $url, HTTP_Request2::METHOD_POST);
    $request -> addPostParameter($QueryVariables);
    return $this -> OXreq($request, $returnResponseObject);
  }

  public function OXreqPUT($url, $QueryVariables, $PutData, $returnResponseObject = false) {
    $QueryVariables['timezone'] = 'UTC';
    # all times are UTC,
    $request = new HTTP_Request2(OX_SERVER . $url, HTTP_Request2::METHOD_PUT);
    $request -> setHeader('Content-type: text/javascript; charset=utf-8');
    $url = $request -> getUrl();
    $url -> setQueryVariables($QueryVariables);
    $request -> setBody(utf8_encode(json_encode($PutData)));
    return $this -> OXreq($request, $returnResponseObject);
  }

  public function OXreq($requestOBJ, $returnResponseObject) {
    $requestOBJ -> setCookieJar($this -> cookiejar);
    $requestOBJ -> setHeader('User-Agent: Z-Push-OX 0.9');
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

  public function OXreqPOSTforSendMail($url, $mailData, $returnResponseObject = false) {

    /*
     * We have to build our own HTTP-Request. I found no way to do requests like this 
     * with the HTTP_Request2 mehtods.
     * 
     * Example (Dumped with "Live HTTP Headers"-Firefox-Addon):
     *  POST /ajax/mail?action=new&session=81270d33e8924c52a3b1f26449fb0882 HTTP/1.1
     *  Host: example.com
     *  [...]
     *  Cookie: _pk_id.2.7b9a=cf0a050d00d75d2c.1357509289.1.1357510032.1357509289.; JSESSIONID=a28b342d0d01482dac641f6ce6e302e3.APP1; open-xchange-secret-gt0jUjX3Nqn09XH3kS34Cg=0dc21c2184484c629a3118b0145e1099; open-xchange-public-session=37e558cbbbce431182528e9ffd57b74f
     *  Content-Type: multipart/form-data; boundary=---------------------------1422138984396912978832213664
     *  Content-Length: 643
     *  -----------------------------1422138984396912978832213664
     *  Content-Disposition: form-data; name="json_0"
     * 
     *  {"from":"Test User <testuser@example.com>","to":"Test User2 <testuser2@example.com>","cc":"","bcc":"","subject":"Testemail","priority":"3","attachments":[{"content_type":"ALTERNATIVE","content":"<html><head><style type=\u0022text/css\u0022>FB_Addon_TelNo{\u000aheight:15px !important;\u000a white-space: nowrap !important;\u000a background-color: #0ff0ff;}</style></head><body style=\u0022\u0022><div>Testemail</div></body></html>"}],"datasources":[]}
     *  -----------------------------1422138984396912978832213664--
     * 
     */

    $delimiter = '-------------' . uniqid();

    $data = '';
    $data .= "--" . $delimiter . "\r\n";
    $data .= 'Content-Disposition: form-data; name="json_0"'."\r\n";
    $data .= "\r\n";
    //$data .= '{"from":"Test User <testuser@eideo.de>","to":"Fabian Kuran <fabian@eideo.de>","cc":"","bcc":"","subject":"Testmail over SendMail()","priority":"3","attachments":[{"content_type":"ALTERNATIVE","content":"<html><head><style type=\\u0022text\/css\\u0022>FB_Addon_TelNo{\\u000aheight:15px !important;\\u000a white-space: nowrap !important;\\u000a background-color: #0ff0ff;}<\/style><\/head><body style=\\u0022\\u0022><div>ThisistatestoverSendMail<\/div><\/body><\/html>"}]}';
    $data .= $mailData;
    $data .= "\r\n--" . $delimiter . "--\r\n";

    $request = new HTTP_Request2(OX_SERVER . $url, HTTP_Request2::METHOD_POST);
    $request -> setHeader('Content-Type: multipart/form-data; boundary=' . $delimiter);
    $request -> setHeader('Content-Length: ' . strlen($data));
    $request -> setBody($data);
    
    return $this -> OXreq($request, $returnResponseObject);

    
  }

}
?>
