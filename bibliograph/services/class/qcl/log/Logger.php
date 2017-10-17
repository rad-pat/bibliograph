<?php
/*
 * qcl - the qooxdoo component library
 *
 * http://qooxdoo.org/contrib/project/qcl/
 *
 * Copyright:
 *   2007-2015 Christian Boulanger
 *
 * License:
 *   LGPL: http://www.gnu.org/licenses/lgpl.html
 *   EPL: http://www.eclipse.org/org/documents/epl-v10.php
 *   See the LICENSE file in the project's top-level directory for details.
 *
 * Authors:
 *  * Christian Boulanger (cboulanger)
 */

/**
 * path to log file
 */
if ( ! defined( "QCL_LOG_FILE") )
{
  throw new JsonRpcException("You must define the QCL_LOG_FILE constant.");
}


/*
 * Default logger: logs to filesystem
 * @todo add other loggers
 */
class qcl_log_Logger
{

  /**
   * Filters for the logger
   * @var array
   */
  private $filters = array();
  
  private $dirty = false;
  
  /**
   * Constructor
   * @return \qcl_log_Logger
   */
  function __construct()
  {
    $path = $this->getFilterCachePath();
    if( file_exists( $path ) ){
      $this->log( "Loading log filters from file...", "filters" );
      $this->filters = unserialize( file_get_contents($path) );
    } else {
      $this->log( "Initializing log filters...", "filters" );
      $this->_registerInitialFilters();
      $this->dirty = true;
    }
  }
  

  /**
   * Returns singleton instance
   * @return qcl_log_Logger
   */
  public static function getInstance()
  {
    return qcl_getInstance( __CLASS__ );
  }
  
  private function getFilterCachePath()
  {
    return QCL_VAR_DIR . DIRECTORY_SEPARATOR . "bibliograph_filter_cache.dat";
  }
  

  /**
   * Internal method to setup initial filters
   * @return unknown_type
   */
  private function _registerInitialFilters()
  {
    $this->registerFilter("debug",    "Debug messages",true);
    $this->registerFilter("info",     "Informative messages", true);
    $this->registerFilter("warn",     "Warnings", true);
    $this->registerFilter("error",    "Non-fatal errors", true);
    $this->registerFilter("filters",   "Log filter system", false);
  }
  
  /**
   * Register a filter.
   * @param string $filter Filter name
   * @param string $description Short description of what messages the filter is for.
   * @param bool[optional,default true] $state True if enabled, false if disabled
   */
  public function registerFilter( $filter, $description=null, $state=true )
  {
    if ( ! $filter )
    {
      throw new InvalidArgumentException("No filter given.");
    }
    if( $this->isRegistered( $filter ) ) {
      //$this->log( "Filter '$filter' already registered.", "filters" );
      return; 
    }
    $this->filters[$filter] = array(
      'enabled'     => $state,
      'description' => $description
    );
    $this->dirty=true; 
  }


  /**
   * Checks if a filter is registered.
   * @param $filter
   * @return unknown_type
   */
  public function isRegistered($filter)
  {
    return isset( $this->filters[$filter] );
  }

  /**
   * Enables a registered filter.
   * @param string|array $filter
   * @param $value
   * @throws Exception
   * @return void
   */
  public function setFilterEnabled( $filter, $value=true )
  {
    /*
     * If array is given, set all the filters
     */
    if ( is_array( $filter) )
    {
      foreach( $filter as $f )
      {
        $this->setFilterEnabled( $f, $value );
      }
      return;
    }

    $this->checkFilter( $filter );

    if ( ! is_bool($value) )
    {
      throw new Exception("Value parameter must be boolean");
    }

    /*
     * enable/disable filter
     */
    $this->log( "Setting '$filter' to '" . ($value?"on":"off") ."'", "filters" );
    $this->filters[$filter]['enabled'] = $value;
    $this->dirty=true;
  }
  
  /**
   * Save the filters to a cache file
   */
  public function saveFilters()
  {

    if( is_object ( $app = qcl_application_Application::getInstance() ) 
        and is_object ( $accessCtl = $app->getAccessController() )
        and is_object ( $activeUser = $accessCtl->getActiveUser() )
        and $activeUser->hasPermission("log.changeLogFilters")
    ) {
      if( ! $this->dirty ){
         $this->log( "Filters haven't changed", "filters" );
         return; 
      }
      $this->log( "Saving filters...", "filters" );      
      $this->countEnabledFilters();
      file_put_contents( $this->getFilterCachePath(), serialize($this->filters) );
      $this->dirty = false;
    }
  }  
  
  /**
   * Clears the filter cache, for example, if descriptions change
   */
  public function clearFilterCache(){
    @unlink($this->getFilterCachePath());
  }
  
  private function countEnabledFilters()
  {
    $enabled = array();
    foreach($this->filters as $name => $filter){
      if($filter['enabled']) $enabled[]= $name;
    }
    sort($enabled);
    $this->log( "We have now ". count($enabled) . "  enabled filters: " . implode("; ", $enabled), "filters" );   
  }

  /**
   * Check if filter exists
   * @param string $filter
   * @return void
   */
  public function checkFilter( $filter )
  {
    if ( ! $this->isRegistered( $filter ) )
    {
      throw new InvalidArgumentException("Filter '$filter' does not exist." );
    }
  }

  /**
   * Returns the state of the filter.
   * @param string $filter
   * @return boolean
   */
  public function isFilterEnabled( $filter )
  {
    $this->checkFilter( $filter );
    return $this->filters[$filter]['enabled'];
  }

  /**
   * Returns the filter data
   * @return array Ordered array of associative arrays
   * with keys 'name', 'description', and 'enabled'
   */
  public function getFilterData(){
    $data = array();
    foreach( $this->filters as $key => $value){
      $data[] = array(
        "name" => $key ,
        "description" => either($value['description'],$key),
        "enabled" => $value['enabled']
      );
    }
    usort($data,function($a,$b){
      return strcmp($a['description'],$b['description']);
    });
    return $data;
  }

  /**
   * Log message to file on server, if corresponding
   * filters are enabled.
   * @param $msg
   * @param string|array $filters
   * @internal param string $message
   * @return message written to file
   */
  public function log( $msg, $filters="debug" )
  {

    /*
     * check if a matching filter has been enabled
     */
    static $counter = 0;
    $found = false;
    foreach ( (array) $filters as $filter )
    {
       if ( $this->filters[$filter]  )
       {
         $found = true;
         if ( $this->filters[$filter]['enabled'] )
         {
           $message = date( "y-m-j H:i:s" );
           $message .=  "-" . $counter++;
           $message .= ": [$filter] $msg\n";
           $this->writeLog( $message );
           break;
         }
       }
    }

    return $found;
  }

  /**
   * Write to log file.
   * @param string $message
   * @throws JsonRpcError
   * @throws Exception
   */
  protected function writeLog( $message )
  {
    /*
     * if a valid log file exists or can be created, write message to it
     */
    if( QCL_LOG_FILE )
    {

      if ( is_writable( QCL_LOG_FILE )
        or is_writable( dirname( QCL_LOG_FILE ) ) ) // @todo
      {
        /*
         * create log file if it doesn't exist
         */
        if( ! file_exists( QCL_LOG_FILE ) )
        {
          touch( QCL_LOG_FILE );
        }

        /*
         * write message to file
         */
        error_log( $message, 3, QCL_LOG_FILE );

        /*
         * truncated logfile if maximum size has been reached
         */
        if( defined( "QCL_LOG_MAX_FILESIZE" ) and QCL_LOG_MAX_FILESIZE > 0 )
        {
          if( filesize( QCL_LOG_FILE ) > QCL_LOG_MAX_FILESIZE )
          {
            $this->clearLogFile();
          }
        }
      }

      /*
       * else, throw an exception
       */
      else
      {
        throw new Exception( sprintf( "Log file '%s' is not writable.", QCL_LOG_FILE ) );
      }
    }
  }

  /**
   * Logs a message with of level "info".
   * @return void
   * @param mixed $msg
   */
  function info( $msg )
  {
    $this->log( $msg, "info" );
  }

  /**
   * Logs a message with of level "warn".
   * @return void
   * @param $msg string
   */
  function warn( $msg )
  {
    $this->log( $msg, "warn" );
  }

  /**
   * Logs a message with of level "error".
   * @return void
   * @param $msg string
   * @param bool $includeBacktrace
   */
  function error( $msg, $includeBacktrace=false )
  {
    $msg = $msg . "\n";
    if ( $includeBacktrace )
    {
      $ignore = 2;
      $trace = '';
      foreach (debug_backtrace() as $k => $v) {
        if ($k < 2) {
          continue;
        }

        array_walk($v['args'], function (&$item, $key) {
          $item = var_export($item, true);
        });

        $trace .= '#' . ($k - $ignore) . ' ' . $v['file'] . '(' . $v['line'] . '): ' . (isset($v['class']) ? $v['class'] . '->' : '') . $v['function'] . '(' . implode(', ', $v['args']) . ')' . "\n";
      }
      $msg .= $msg . $trace;
    }
    $this->log( $msg , "error" );
  }
  
  /**
   * Empties the log file
   */
  public function clearLogFile(){
    if ( @unlink( QCL_LOG_FILE ) )
    {
      touch( QCL_LOG_FILE );
    }
    else
    {
      $this->warn("Cannot delete logfile.");
    }
  }

}
