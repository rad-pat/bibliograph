<?php
/* ************************************************************************

   Bibliograph: Collaborative Online Reference Management

   http://www.bibliograph.org

   Copyright:
     2004-2014 Christian Boulanger

   License:
     LGPL: http://www.gnu.org/licenses/lgpl.html
     EPL: http://www.eclipse.org/org/documents/epl-v10.php
     See the LICENSE file in the project's top-level directory for details.

   Authors:
   *  Christian Boulanger (cboulanger)

************************************************************************ */

require_once "bibliograph/lib/cql/cql2.php";

qcl_import("bibliograph_schema_BibtexSchema");
qcl_import("qcl_locale_Manager");

/**
 * Singleton object which acts as a tool for working with the CQL query
 * language
 *
 * @see http://www.loc.gov/standards/sru/resources/cql-context-set-v1-2.html
 */
class bibliograph_schema_CQL
  extends qcl_core_Object
{

  public $booleans = array( "and", "or", "not" );

  public $modifiers = array(
    "is", "isnot","contains", "notcontains", "startswith",
    "=", ">", ">=", "<", "<=", "<>"
  );

  protected $dictionary = array();

  /**
   * Exists only for POEditor to pick up the translation messages.
   */
  function marktranslations()
  {
    _("and"); _("or"); _("not");
    _("is"); _("isnot"); _("contains"); _("notcontains"); _("startswith");
  }

  /**
   * Returns singleton sinstance
   * @return bibliograph_schema_CQL
   */
  public static function getInstance()
  {
    return qcl_getInstance( __CLASS__ );
  }

  /**
   * Returns the dictionary of words to be translated into english
   * booleans, modifiers or object properties
   * @param bibliograph_model_ReferenceModel $model
   * @return array The dictionary for the model
   */
  protected function getDictionary(bibliograph_model_ReferenceModel $model)
  {
    $modelClass = $model->className();
    if( ! $this->dictionary[ $modelClass ] )
    {
      $localeMgr = qcl_locale_Manager::getInstance();
      $availableLocales = $localeMgr->getAvailableLocales();
      $dict = array();

      // translate words for each locale
      foreach( $availableLocales as $locale)
      {
        $localeMgr->setLocale($locale);

        // modifiers and booleans
        foreach( array_merge($this->modifiers, $this->booleans) as $word)
        {
          // skip non-words
          if( strtolower($word) == strtoupper($word) ) continue;
          $translated = $localeMgr->tr($word);
          $dict[$translated]=$word;
        }

        // model indexes
        foreach ( $model->getSchemaModel()->getIndexNames() as $index )
        {
          $fields = $model->getSchemaModel()->getIndexFields( $index );
          // @todo we only use the first, but it should really search all of them
          $property = $fields[0];
          $translated = $localeMgr->tr($index);
          $dict[$translated]=$property;
        }
      }
      // revert to standard locale
      $localeMgr->setLocale();

      $this->dictionary[ $modelClass ] = $dict;
    }
    return $this->dictionary[ $modelClass ];
  }


  /**
   * Adds conditions to a DB query object from a qcl query
   *
   * @param stdClass $query
   *    The query data object from the json-rpc request
   * @param qcl_data_db_Query $qclQuery
   *    The query object used by the query behavior
   * @param bibliograph_model_ReferenceModel $model
   *    The model on which the query should be performed
   * @throws bibliograph_schema_Exception
   * @throws Exception
   * @throws JsonRpcException
   * @return qcl_data_db_Query
   */
  public function addQueryConditions(
    stdClass $query,
    qcl_data_db_Query $qclQuery,
    bibliograph_model_ReferenceModel $model
  ){
    /*
     * get qcl query
     */
    $error = "First argument must be object and have a 'cql' property";
    qcl_assert_has_property( $query, "cql", $error );
    qcl_assert_valid_string( $query->cql, $error );
    $cqlQuery = trim($query->cql);

    /*
     * Translate operators, booleans and indexes.
     */
    $dict = $this->getDictionary($model);
    $this->debug($dict);

    // regular expression to find thing that are not inside quotation marks
    // see http://stackoverflow.com/questions/11324749/a-regex-to-detect-string-not-enclosed-in-double-quotes
    $regExp = '@(?<![\S"])(\b%s\b)(?![\S"])@i';
    $search = array_map( function( $word ) use ($regExp) {
      return sprintf($regExp, $word );
    }, array_keys( $dict ) );
    $replace = array_values( $dict );
    $cqlQuery = preg_replace($search, $replace, $cqlQuery);

    /*
     * Queries that don't contain any operators or booleans are converted into a
     * query connected by "AND"
     */
    $found = false;
    $operators = array_merge($this->booleans,$this->modifiers);
    foreach( $operators as $find )
    {
      if ( strstr( $cqlQuery, $find ) ) // @todo more efficient lookup
      {
        $found=true; break;
      }
    }
    if ( ! $found )
    {
      $cqlQuery = implode( " and ", explode(" ", trim( $cqlQuery ) ) );
    }

    /*
     * create and configure parser object
     */
    $parser = new cql_Parser( $cqlQuery );
    $parser->setBooleans( $this->booleans );
    $parser->setModifiers( $this->modifiers );
    $parser->setSortWords( array("sortby" ) );

    //$this->debug( $cqlQuery );

    /*
     * parse CQL string
     */
    $cqlObject = $parser->query();
    if ( $cqlObject instanceof cql_Diagnostic )
    {
      throw new qcl_server_ServiceException( "Could not parse query." );
    }

    /*
     * populate query object
     */
    $this->convertCqlObjectToQclQuery( $cqlObject, $qclQuery, $model );

    return $qclQuery;
  }


  /**
   * Recursive function to convert a CQL object structure into
   * a qcl_data_db_Query. Boolean operators are ignored at the moment,
   * everything is connected with boolean "AND".
   *
   * @param cql_Object $cqlObject
   * @param qcl_data_db_Query $qclQuery
   * @param bibliograph_model_ReferenceModel $model
   * @throws LogicException
   * @throws JsonRpcException
   * @throws bibliograph_schema_Exception
   * @return void
   * @todo implement other operators, this requires reworking of how
   * the 'where' queries are created in the QueryBehavior
   */
  protected function convertCqlObjectToQclQuery(
    cql_Object $cqlObject,
    qcl_data_db_Query $qclQuery,
    bibliograph_model_ReferenceModel $model
  ){
    if ( $cqlObject instanceof cql_Triple )
    {
      $this->convertCqlObjectToQclQuery( $cqlObject->leftOperand, $qclQuery, $model );
      $this->convertCqlObjectToQclQuery( $cqlObject->rightOperand, $qclQuery, $model );
    }
    elseif ( $cqlObject instanceof cql_SearchClause )
    {
      /*
       * look for index. for now, if there is no index,
       * use an index named 'fulltext', which must exist in the model.
       */
      $index = $cqlObject->index->value;
      if( ! $index )
      {
        $index = "fulltext";
        $property = null;
      }

      /*
       * else, translate index into property
       */
      else
      {
        if( $model->hasProperty( $index ) )
        {
          $property = $index;
        }
        else
        {
          throw new bibliograph_schema_Exception($this->tr("Index '%s' does not exist.", $index ) );
        }
        $index = null;
      }

      $relation = strtolower($cqlObject->relation->value);
      $term     = $cqlObject->term->value;

      switch( $relation )
      {
        /*
         * simple field comparison. compare numeric values normally
         * and replace "*" with "%" for "LIKE" comparisons for strings
         */
        case "=":
        case "is":
          if( is_numeric($term) )
          {
            $operator = "=";
          }
          else
          {
            $operator = "LIKE";
            $term = str_replace("*","%",$term);
          }
          break;

        /*
         * containing values
         */
        case "contains":
          $operator = "LIKE";
          $term = "%$term%";
          break;

        case "notcontains":
          $operator = "NOT LIKE";
          $term = "%$term%";
          break;

        case "startswith":
          $operator = "LIKE";
          $term = "$term%";
          break;

        case ">":
        case "<":
        case ">=":
        case "<=":
          $operator = $relation;
          break;

        case "<>":
        case "isnot":
          $operator = "!=";
          break;

        default:
          throw new JsonRpcException("Cannot yet deal with relation '$relation'. " . typeof( $cqlObject ) );
      }

      if ( $property )
      {
        // @todo OR and NOT connectors
        $qclQuery->where[$property] = array( $operator, $term );
      }
      elseif ( $index )
      {
        $qclQuery->match[$index] = trim( $qclQuery->match[$index] . " " . $term );
      }

    }

    /**
     * Syntax error
     */
    elseif ( $cqlObject instanceof cql_Diagnostic )
    {
      throw new JsonRpcException( $cqlObject->toTxt() );
    }

    /**
     * Unknown Object, shouldn't ever get here
     */
    else
    {
      throw new LogicException("Cannot yet deal with object " . get_class( $cqlObject ) );
    }
  }
}
?>