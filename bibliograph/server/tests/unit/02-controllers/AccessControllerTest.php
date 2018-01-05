<?php

namespace app\tests\unit\controllers;

// for whatever reason, this is not loaded early enough
require_once __DIR__ . '/../../_bootstrap.php';

use Yii;

class AccessControllerTest extends \app\tests\unit\Base
{
  use \app\controllers\traits\JsonRpcTrait;

  /**
   * @var \UnitTester
   */
  protected $tester;

  public function _fixtures(){
    return require __DIR__ . '/../../fixtures/_access_models.php';
  }

   // @todo needs real error message
  public function testUnauthorizedAccessFails()
  {
    $response = $this->sendJsonRpc('username',null,"access");
    $this->assertEquals( null, $response->getRpcResult());
  }

  public function testAuthenticateWithPassword()
  {
    $response = $this->sendJsonRpc('authenticate',['admin','admin'],"access");
    $result = $response->getRpcResult();
    $this->assertEquals( ['message', 'token', 'sessionId' ], array_keys($result) );
    $this->token($result['token']);
    // test token access
    $response = $this->sendJsonRpc('username');
    $this->assertEquals( 'admin', $response->getRpcResult());
    // test session persistence
    $this->assertEquals( 1, $this->sendJsonRpc('count')->getRpcResult() );
    $this->assertEquals( 2, $this->sendJsonRpc('count')->getRpcResult() );
    $this->assertEquals( 3, $this->sendJsonRpc('count')->getRpcResult() );
    // logout
    $this->assertEquals( "OK", $this->sendJsonRpc('logout')->getRpcResult() );
  }

  public function testLoginAnonymously()
  {
    $result = $this->sendJsonRpc('authenticate',[])->getRpcResult();
    $this->assertEquals( ['message', 'token', 'sessionId' ], array_keys($result) );
    $this->token($result['token']);
    // test token access
    $response = $this->sendJsonRpc('username');
    $this->assertStringStartsWith( 'guest', $response->getRpcResult());    
    // test persistence
    $this->assertEquals( 1, $this->sendJsonRpc('count')->getRpcResult() );
    $this->assertEquals( 2, $this->sendJsonRpc('count')->getRpcResult() );
    $this->assertEquals( 3, $this->sendJsonRpc('count')->getRpcResult() );
    // test logout
    $this->assertEquals( "OK", $this->sendJsonRpc('logout')->getRpcResult() );
  }
}