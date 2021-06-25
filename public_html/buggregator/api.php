<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

require_once ( '/data/project/magnustools/scripts/buggregator/Buggregator.php' ) ;

$buggregator = new Buggregator ;

$action = $buggregator->tfc->getRequest('action','') ;
$out = [ 'status' => 'OK' , 'data' => [] ] ;

if ( $action == 'get_issues' ) {
	$limit = $buggregator->tfc->getRequest('limit',25)*1 ;
	$offset = $buggregator->tfc->getRequest('offset',0)*1 ;
	$sort_by = $buggregator->escape($buggregator->tfc->getRequest('sort_by','acsending')) ;
	$sort_order = $buggregator->escape($buggregator->tfc->getRequest('sort_order','acsending')) ;

	$sql_core = [] ;
	# TODO 
	# label
	# status
	# site
	# description
	# tool
	# priority
	if ( count($sql_core) == 0 ) $sql_core = '' ;
	else $sql_core = ' WHERE (' . implode ( '),(' , $sql_core ) . ')' ;

	$sql = "SELECT * FROM `issue` {$sql_core}" ;
	if ( $sort_by != '' ) {
		$sql .= " ORDER BY `{$sort_by}`" ;
		if ( $sort_order == 'descending' ) $sql .= " DESC" ;
	}
	$sql .= " LIMIT {$limit} OFFSET {$offset}" ;
	$out['sql'] = $sql ;
	$result = $buggregator->getSQL ( $sql ) ;
	while($o = $result->fetch_object()) $out['data']['results'][] = $o ;
	
	$sql = "SELECT count(*) AS `cnt` FROM `issue` {$sql_core}" ;
	$result = $buggregator->getSQL ( $sql ) ;
	if($o = $result->fetch_object()) $out['data']['stats']['this_query'] = $o->cnt ;

	$sql = "SELECT count(*) AS `cnt` FROM `issue` WHERE `status`='OPEN'" ;
	$result = $buggregator->getSQL ( $sql ) ;
	if($o = $result->fetch_object()) $out['data']['stats']['total_open'] = $o->cnt ;

} else {
	$o->status = "Unknown action '{$action}'" ;
}

header('Content-Type: application/json');
echo json_encode($out) ;

?>