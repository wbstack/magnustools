<?PHP

require_once ( '/data/project/magnustools/public_html/php/ToolforgeCommon.php' ) ;

class Logger {
	public $tfc ;
	public $db ;
	private $tools_table_name = 'tools' ;
	private $logs_table_name = 'logs' ;

	public function __construct () {
		$this->tfc = new ToolforgeCommon('logger') ;
		$this->db = $this->tfc->openDBtool ( 'tool_logging' ) ;
	}

	public function getToolByNameAndMethod ( $toolname , $method ) {
		$this->sanitizeToolAndMethodName ( $toolname , $method ) ;
		$sql = "SELECT id FROM `{$this->tools_table_name}` WHERE `name`='" . $this->db->real_escape_string($toolname) . "' AND `method`='" . $this->db->real_escape_string($method) . "'" ;
		$result = $this->tfc->getSQL ( $this->db , $sql ) ;
		while($o = $result->fetch_object()) $tool_id = $o->id ;
		return $tool_id ;
	}

	protected function createNewToolAndMethod ( $toolname , $method ) {
		$this->sanitizeToolAndMethodName ( $toolname , $method ) ;
		$date = $this->today() ;
		$sql = "INSERT IGNORE INTO `{$this->tools_table_name}` (`name`,`method`,`start_log`) VALUES ('" . $this->db->real_escape_string($toolname) . "','" . $this->db->real_escape_string($method) . "',{$date})" ;
		$this->tfc->getSQL ( $this->db , $sql ) ;
	}

	public function getOrCreateToolByNameAndMethod ( $toolname , $method ) {
		$this->sanitizeToolAndMethodName ( $toolname , $method ) ;
		$tool_id = $this->getToolByNameAndMethod ( $toolname , $method ) ;
		if ( isset($tool_id) ) return $tool_id ;
		$this->createNewToolAndMethod ( $toolname , $method ) ;
		$tool_id = $this->getToolByNameAndMethod ( $toolname , $method ) ;
		if ( !isset($tool_id) ) $this->micDrop ( "Could not create tool '{$toolname}' method '{$method}'" ) ;
		return $tool_id ;
	}

	public function deleteToolById ( $tool_id ) {
		$sql = "DELETE FROM `{$this->logs_table_name}` WHERE `tool_id`={$tool_id}" ;
		$this->tfc->getSQL ( $this->db , $sql ) ;
		$sql = "DELETE FROM `{$this->tools_table_name}` WHERE `id`={$tool_id}" ;
		$this->tfc->getSQL ( $this->db , $sql ) ;
	}

	public function addToLog ( $tool_id , $increase = 1 ) {
		# Create tool/date entry if necessary, increase usage count
		$date = $this->today() ;
		$sql = "INSERT INTO `{$this->logs_table_name}` (`tool_id`,`date`,`used`) VALUES ({$tool_id},{$date},1) ON DUPLICATE KEY UPDATE `used`=`used`+{$increase}" ;
		$this->tfc->getSQL ( $this->db , $sql ) ;
	}

	public function getAllUses ( $tool_id ) {
		$sql = "SELECT sum(`used`) AS `uses` FROM `{$this->logs_table_name}` WHERE `tool_id`={$tool_id}" ;
		$result = $this->tfc->getSQL ( $this->db , $sql ) ;
		while($o = $result->fetch_object()) return $o->uses ;
	}

	public function micDrop ( $error = '' ) {
		$out = (object) ["status"=>"OK"] ;
		$callback = $this->tfc->getRequest ( 'callback' , '' ) ;
		if ( $error != '' ) {
			$out->status = 'ERROR' ;
			$out->error = trim($error) ;
		}
		if ( $callback != '' ) print $callback.'(' ;
		print json_encode ( $out ) ."\n" ;
		if ( $callback != '' ) print ')' ;
		$this->tfc->flush();
		ob_end_flush() ;
		exit(0) ;
	}

	protected function sanitizeToolAndMethodName ( &$toolname , &$method ) {
		$method = preg_replace ( '|\s*[\(\)].*$|' , '' , $method ) ;
		$toolname = trim ( strtolower ( $toolname ) ) ;
		$method = trim ( strtolower ( $method ) ) ;
		if ( $toolname == '' ) $logger->micDrop ( "No tool name supplied" ) ;
	}

	protected function today() {
		$date = date ( 'Ymd' ) * 1 ;
		return $date ;
	}

}

?>