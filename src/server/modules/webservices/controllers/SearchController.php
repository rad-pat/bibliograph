<?php

namespace app\modules\webservices\controllers;

use app\models\User;
use app\modules\webservices\connectors\Worldcat;
use lib\cql\Diagnostic;
use lib\cql\Parser;
use lib\exceptions\TimeoutException;
use Yii;
use Exception;
use app\controllers\{ traits\AuthTrait, traits\DatasourceTrait };
use app\modules\webservices\Module;
use app\models\Datasource;
use app\modules\webservices\models\{ Record, Search, Datasource as WebservicesDatasource };
use lib\dialog\ServerProgress;
use lib\exceptions\UserErrorException;
use lib\bibtex\BibtexParser;

/**
 * Class ProgressController
 * @package modules\webservices\controllers
 * @property Module $module
 */
class SearchController extends \yii\web\Controller
{
  use AuthTrait;
  use DatasourceTrait;

  protected function getNoAuthActions()
  {
    return ['index','test'];
  }

  public function actionIndex()
  {
    return "nothing here";
  }

  public function actionTest()
  {
    Yii::$app->user->login(User::findByNamedId("admin"));
    $this->actionProgress("webservices_crossref","9780804767712","1234");
  }

  /**
   * Executes a request on the remote server. Called
   * by the ServerProgress widget on the client. If server times out
   * it will retry up to three times.
   *
   * @param string $datasource The name of the datasource
   * @param string $query The cql query
   * @param string $id The id of the progress widget
   * @param ServerProgress|null $progressBar Only used internally
   * @return string Chunked HTTP response
   * @todo use DTO
   */
  public function actionProgress(string $datasource, string $query, string $id, ServerProgress $progressBar=null)
  {
    static $retries = 0;
    if( ! $progressBar ){
      $progressBar = new ServerProgress($id);
    }
    try {
      $this->sendRequest($datasource, $query, $progressBar);
      $progressBar->dispatchClientMessage("webservices.dataReady", $query);
      return $progressBar->complete();
    } catch (TimeoutException $e) {
      // retry
      if( $retries < 4){
        $progressBar->setProgress(0, Yii::t("webservices", "Server timed out. Trying again..."));
        sleep(rand(1,3));
        return $this->actionProgress($datasource, $query, $id, $progressBar );
      } else {
        return $progressBar->error(Yii::t("webservices", "Server timed out."));
      }
    } catch (UserErrorException $e) {
      return $progressBar->error($e->getMessage());
    } catch (\Throwable $e) {
      Yii::error($e);
      return $progressBar->error($e->getMessage());
    }
  }


  /**
   * Does the actual work of executing the request on the remote server.
   *
   * @param string $datasourceName
   * @param $query
   * @param ServerProgress|null $progressBar
   *    A progressbar object responsible for displaying the progress
   *    on the client (optional)
   * @return void
   * @throws TimeoutException
   * @throws UserErrorException
   * @throws Exception
   */
  public function sendRequest( string $datasourceName, $query, ServerProgress $progressBar = null)
  {
    $datasource = Datasource::getInstanceFor($datasourceName);
    if( ! $datasource or ! $datasource instanceof WebservicesDatasource ){
      throw new \InvalidArgumentException("Invalid datasource '$datasourceName'.");
    }
    // set datasource table prefixes
    Search::setDatasource($datasource);
    Record::setDatasource($datasource);

    // remember last datasource used
    $this->module->setPreference("lastDatasource", $datasourceName );

    $connectorId = str_replace(Module::CATEGORY . "_", "", $datasourceName);
    $connector = $datasource->createConnector($connectorId);

    $query = Module::fixQuery($query);
    $cql = (new Parser($query))->query();
    if( $cql instanceof Diagnostic ){
      throw new UserErrorException(Yii::t( Module::CATEGORY, "Could not parse query: {error}", [
        'error' => $cql->toTxt()
        ]));
    }

    Yii::debug("Executing query '{$cql->toCQL()}' on webservice '$datasourceName' ...", Module::CATEGORY);

    if ($progressBar) {
      $progressBar->setProgress(0, Yii::t(Module::CATEGORY, "Waiting for webservice..."));
    }

    $hits = $connector->search($cql);

    if ($progressBar) {
      $progressBar->setProgress(25, Yii::t(
        Module::CATEGORY, "{number} records found.",
        ['number'=>$hits]
      ));
    }
    Yii::debug("Found $hits records...", Module::CATEGORY);

    // delete existing search
    $userId = Yii::$app->user->identity->getId();
    Yii::debug("Deleting existing search data for query '$query'...", Module::CATEGORY);
    /** @var Search[] $searches */
    $searches = (array) Search::find()->where(['query' => $query, 'UserId' => $userId ])->all();
    foreach ($searches as $search) {
      try {
        $search->delete();
      } catch (\Throwable $e) {
        Yii::debug($e->getMessage(),Module::CATEGORY);
      }
    }
    // create new search
    $search = new Search([
      'query' => $query,
      'datasource' => $datasourceName,
      'hits' => $hits,
      'UserId' => $userId
    ]);
    $search->save();
    $searchId = $search->id;
    Yii::debug("Created new search record #$searchId for query '$query' for user #$userId.", Module::CATEGORY);

    if ( $hits === 0) {
      Yii::debug("Empty result set, aborting...", Module::CATEGORY);
      return;
    }

    // saving to local cache
    Yii::debug("Caching records...", Module::CATEGORY);

    $step = 50 / count($hits);
    $i = 0;

    // Get iterator from generator
    $recordIterator = $connector->recordIterator();
    /** @var Record $record */
    foreach ($recordIterator as $record) {
      if ($progressBar) {
        $progressBar->setProgress(
          round (50 + ($step * $i++)),
          Yii::t(Module::CATEGORY, "Caching records...")
        );
      }
      $record->SearchId = $searchId;
      $record->save();
    }
  }
}