<?php

class SetupControllerCest
{
  public function trySetup(ApiTester $I)
  {
    $I->sendJsonRpcRequest('setup','setup');
    $I->compareJsonRpcResultWith( json_decode('{
      "type": "ServiceResult",
      "events": [
        {
          "name": "ldap.enabled",
          "data": false
        },
        {
          "name": "bibliograph.setup.done",
          "data": null
        }
      ],
      "data": {
        "errors": [
          
        ],
        "messages": [
          "Ini file exists.",
          "File permissions ok.",
          "Database connection ok.",
          "No updates to the databases.",
          "LDAP authentication is not enabled.",
          "Admininstrator email exists.",
          "Example databases were created."
        ]
      }
    }'));
  }
}