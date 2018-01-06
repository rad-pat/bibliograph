<?php

// for whatever reason, this is not loaded early enough
require_once __DIR__ . '/../_bootstrap.php';

class AccessCest
{

  public function _fixtures()
  {
    return require __DIR__ . '/../fixtures/_access_models.php';
  }

  public function tryToAccessMethodWithoutAuthentication(FunctionalTester $I)
  {
    $I->sendJsonRpcRequest("access","username");
    // @todo we don't get a proper error yet, so we have to check that the result is null
    $I->assertSame( [null], $I->grabDataFromResponseByJsonPath('$.result')); 
  }

  public function tryToLoginAnonymously(FunctionalTester $I)
  {
    $I->loginAnonymously();
  }
 
  public function tryAuthenticateWithPassword(FunctionalTester $I)
  {
    $I->loginWithPassword('admin','admin');
    $I->sendJsonRpcRequest('access','username');
    $I->assertSame( $I->grabJsonRpcResult(), "admin" );
    // test session persistence
    for ($i=1; $i < 4; $i++) { 
      $I->sendJsonRpcRequest('access','count');
      $I->assertSame( $I->grabJsonRpcResult(), $i );   
    }
    $I->logout();
  }
}