<?PHP

/*
This script logs user events for tools.

PHP:
$tfc->logToolUse ( 'toolname' , 'method' ) ;

or:
require_once ( "php/ToolforgeCommon.php" ) ;
$tfc = new ToolforgeCommon('toolname') ;
$tfc->logToolUse('') ;


JavaScript:
// Logging
$.getJSON ( 'https://tools.wmflabs.org/magnustools/logger.php?tool=flickr2commons&method=upload to commons&callback=?' , function(j){} ) ;

*/

set_time_limit ( 60 * 2 ) ; // Seconds
error_reporting(E_ERROR|E_CORE_ERROR|E_COMPILE_ERROR); // E_ALL|
ini_set('display_errors', 'On');

require_once ( '/data/project/magnustools/public_html/php/ToolforgeCommon.php' ) ;

function micDrop ( $error = '' ) {
	global $tfc , $callback , $out ;
	if ( $error != '' ) {
		$out->status = 'ERROR' ;
		$out->error = trim($error) ;
	}
	if ( $callback != '' ) print $callback.'(' ;
	print json_encode ( $out ) ;
	if ( $callback != '' ) print ')' ;
	$tfc->flush();
	ob_end_flush() ;
	exit(0) ;
}

function getToolByNameAndMethod () {
	global $tfc , $db , $toolname , $method ;
	$sql = "SELECT id FROM `tools` WHERE `name`='" . $db->real_escape_string($toolname) . "' AND `method`='" . $db->real_escape_string($method) . "'" ;
	$result = $tfc->getSQL ( $db , $sql ) ;
	while($o = $result->fetch_object()) $tool_id = $o->id ;
	return $tool_id ;
}

$tfc = new ToolforgeCommon('logger') ;

$toolname = trim ( strtolower ( $tfc->getRequest ( 'tool' , '' ) ) ) ;
$method = trim ( strtolower ( $tfc->getRequest ( 'method' , '' ) ) ) ;
$callback = $tfc->getRequest ( 'callback' , '' ) ;

$out = (object) ["status"=>"OK"] ;
if ( $toolname == '' ) {
	micDrop ( "No tool name supplied" ) ;
}

$date = date ( 'Ymd' ) * 1 ;

$db = $tfc->openDBtool ( 'tool_logging' ) ;
$tool_id = getToolByNameAndMethod () ;
if ( !isset($tool_id) ) {
	$sql = "INSERT IGNORE INTO `tools` (`name`,`method`,`start_log`) VALUES ('" . $db->real_escape_string($toolname) . "','" . $db->real_escape_string($method) . "',{$date})" ;
	$tfc->getSQL ( $db , $sql ) ;
	$tool_id = getToolByNameAndMethod () ;
	if ( !isset($tool_id) ) micDrop ( "Could not create tool '{$toolname}' method '{$method}'" ) ;
}

#$out->tool_id = $tool_id ;

# Create tool/date entry if necessary, increase usage count
$sql = "INSERT INTO `logs` (`tool_id`,`date`,`used`) VALUES ({$tool_id},{$date},1) ON DUPLICATE KEY UPDATE `used`=`used`+1" ;
$tfc->getSQL ( $db , $sql ) ;

micDrop();

?>