<?php


/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = NULL)
 *
 * @SuppressWarnings(PHPMD)
*/
class FunctionalTester extends \Codeception\Actor
{
    use _generated\FunctionalTesterActions;

   /**
    * Define custom actions here
    */

  /**
   * Cache the access token
   *
   * @param string|null $t If set, store the value as the current token
   * @return string The access token, if one has been set
   */
  protected function token($t=null)
  {
    static $token = null;
    if( $t ) $token = $t;
    return $token;
  } 

  /**
   * Send a JSONRPC request. Authentication is through an additional 'auth' property of the
   * request or through the 'autt' parameter in the querystring of the URL target. 
   * Throws if the transport layer or the RPC method return an error.
   * 
   * @todo For this to work, you need to patch JsonRpc2\Controller, after line 43
   * `if( ! $requestObject ) $requestObject = (object) $GLOBALS['CODECEPT_RAW_POST_DATA'];`
   *
   * @param string $serviceController
   *    The name of the service (=controller) to be called
   * @param string $method
   *    The name of the RPC method
   * @param array $params
   *    The parameters to be passed to the metho
   * @param boolean $useJsonRpcAuth 
   *    If true, use the JSONRPC 2.0 Auth Extension protocol for authentication. If false,
   *    set the 'auth' querystring parameter. Defaults to true.
   * @param array $queryparams
   *    Optional query parameters to append to the URL
   * @return void
   */
  public function sendJsonRpcRequest( 
    $service, $method, array $params=[], 
    $useJsonRpcAuth=true, array $queryparams=[] )
  {
    /** @var int $id the id of the request */
    static $id = 1;
    
    // headers
    $this->haveHttpHeader('Content-Type', 'application/json');
    $this->haveHttpHeader('Accept', 'application/json'); 

    // payload
    $json = [
      'jsonrpc' => '2.0',
      'method'  => $method,
      'params'  => $params,
      'id'      => $id++
    ];
    // this is neccessary because codeception funtional testing 
    // does not (and maybe cannot) populate the 'php://input' resource
    $GLOBALS['CODECEPT_RAW_POST_DATA'] = $json; 

    $path = $service;

    // authentication
    $token = $this->token();
    if ( $token ){
      if ( $useJsonRpcAuth ){
        $json['auth'] = $token;
      } else {
        $params['auth'] = $token;
      }
      $path .= "&auth=$token"; 
    }    
    
    // send request and validate response
    $this->sendPOST( $path, $json );
    $this->canSeeResponseCodeIs(200);
    $this->seeResponseIsJson();
    $this->seeJsonRpcResult();
    $this->dontSeeJsonRpcError();
  }
  
  /**
   * Expects a token in the json response and caches it.
   *
   * @return void
   */
  public function seeAndSaveTokenInJsonResponse()
  {
    $this->seeResponseJsonMatchesJsonPath('$.result.token');
    $this->token($this->grabDataFromResponseByJsonPath('$.result.token')[0]);
  }

  /**
   * Log in anonymously
   *
   * @return void
   */
  public function loginAnonymously()
  {
    $this->sendJsonRpcRequest( "access","authenticate", [] );
    $this->seeAndSaveTokenInJsonResponse();
  }

  /**
   * Log in with a username and password
   *
   * @return void
   */
  public function loginWithPassword( $user, $password )
  {
    $this->sendJsonRpcRequest( "access","authenticate", [ $user, $password ] );
    $this->seeAndSaveTokenInJsonResponse();
  }

  /**
   * Throws if no RPC result is in the response
   *
   * @return void
   */
  public function seeJsonRpcResult()
  {
    $this->seeResponseJsonMatchesJsonPath('$.result');
  }

  /**
   * Returns the jsonrpc result
   *
   * @return mixed
   */
  public function grabJsonRpcResult()
  {
    $this->seeJsonRpcResult();
    return $this->grabDataFromResponseByJsonPath('$.result')[0];
  }

  /**
   * Throws if the RPC method does not return an error
   *
   * @return void
   */
  public function seeJsonRpcError()
  {
    $this->seeResponseJsonMatchesJsonPath('$.error');
  }

  /**
   * Throws if the RPC method does not return an error
   *
   * @return void
   */  
  public function dontSeeJsonRpcError()
  {
    $this->dontSeeResponseJsonMatchesJsonPath('$.error');
  }

  /**
   * Returns the jsonrpc result
   *
   * @return mixed
   */
  public function grabJsonRpcError()
  {
    $this->seeJsonRpcError();
    return $this->grabDataFromResponseByJsonPath('$.error')[0];
  }

  /**
   * Logs out current user
   *
   * @return void
   */
  public function logout()
  {
    $this->sendJsonRpcRequest('access','logout');
    $this->assertSame( $this->grabJsonRpcResult(), "OK" );   
  }
}