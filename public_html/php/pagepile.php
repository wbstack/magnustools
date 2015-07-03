<?PHP

class PagePile {

	public $error ;

	protected $mysql_server = 'tools-db' ;
	protected $dbname = 's51211__pagepile_p' ;
	protected $file_root = '/shared/pagepile' ;
	
	protected $db ;
	protected $sqlite_file ;
	protected $sqlite ;
	protected $wiki ;
	protected $namespace_cache ;
	protected $pile_id ;
	
	function __construct ( $sqlite_file = '' ) {
		$this->namespace_cache = (object) array() ;
		
		if ( $sqlite_file != '' ) {
			if ( is_numeric ( $sqlite_file ) ) $this->openSqliteID ( $sqlite_file ) ;
			else $this->openSqliteFile ( $sqlite_file ) ;
		}
	}

	function __destruct () {
		$this->close() ;
	}
	
	
	
	public function getPageNumber () {
		$sqlite = $this->getSqlite() ;
		if ( !isset($sqlite) ) die ( "SQLITE not ready" ) ;
		$sql = "select count(*) AS cnt FROM pages" ;
		return $this->sqlite->querySingle ( $sql ) * 1 ;
	}
	
	public function getPileID () {
		return $this->pile_id ;
	}
	
	public function getSqliteFile() {
		return $this->sqlite_file ;
	}
	
	public function getSqlite() {
		if ( !isset($this->sqlite) ) {
			$this->sqlite = new SQLite3 ( $this->getSqliteFile() ) ;
			if ( !isset($this->sqlite) ) die ( "No sqlite database open. Use createNewPile()." ) ;
		}
		return $this->sqlite ;
	}
	
	public function openSqliteID ( $id ) {
		$id = preg_replace ( '/\D/' , '' , $id ) * 1 ;
		$this->openDB() ;
		$sql = "SELECT * FROM piles WHERE id=$id" ;
		if(!$result = $this->db->query($sql)) die('There was an error running the query [' . $this->db->error . ']');
		if($o = $result->fetch_object()){
			$this->pile_id = $id ;
			return $this->openSqliteFile ( $o->path ) ;
		} else die ( "No pile #$id!" ) ;
	}
	
	public function getWiki () {
		if ( !isset($this->wiki) ) {
			$sqlite = $this->getSqlite() ;
			$this->wiki = $sqlite->querySingle ( "SELECT `value` FROM meta WHERE `key`='wiki'" ) ;
		}
		return $this->wiki ;
	}
	
	public function openSqliteFile ( $file ) {
		if ( !file_exists ( $file ) ) die ( "SQLITE file $file does not exist!" ) ;
		if ( filesize ( $file ) == 0 ) die ( "SQLITE file $file is empty!" ) ;
		$this->close() ;

		$this->sqlite_file = $file ;
		
		
		$this->loadNamespaces ( $this->getWiki() ) ;
		$this->loadNamespaces ( 'enwiki' ) ; // Default
	}
	
	// Use with language and project, or just the wiki (e.g. "enwiki") as language
	public function createNewPile ( $language , $project ) {
		$this->wiki = $language ;
		if ( $language != '' && $project != '' ) {
			$this->wiki = $this->lp2wiki ( $language , $project ) ;
		}
		
		// Create unique file name
		$file = $this->getNewSqliteFile() ;
#		print "<pre>!$file!</pre>" ; exit ( 0 ) ;
		$this->initSqliteFile($file) ;
		$this->loadNamespaces ( $this->getWiki() ) ;
		$this->loadNamespaces ( 'enwiki' ) ; // Default
	}
	
	public function addPage ( $page , $ns = -999 , $json = array() ) {
		$sqlite = $this->getSqlite() ;
		if ( gettype($json) == 'object' or gettype($json) == 'array' ) $json = json_encode ( (object) $json ) ;
		$json = $sqlite->escapeString ( $json ) ;
		
		if ( $ns == -999 ) {
			$a = $this->splitFullPageName ( $page ) ;
			$page = $a[0] ;
			$ns = $a[1] ;
		} else {
			$page = str_replace ( ' ' , '_' , $page ) ;
		}
		$page = $sqlite->escapeString ( $page ) ;
		
		$ns *= 1 ;
		$sql = "INSERT OR IGNORE INTO pages (page,ns,json) VALUES ('$page',$ns,'$json')" ;
		$sqlite->exec ( $sql ) ;
	}
	
	public function getCurrentTimestamp () {
		return date('Ymdgis') ;
	}
	
	public function duplicate () {
		$ret = new PagePile ;
		$ret->getNewSqliteFile () ;
		$source = $this->getSqliteFile() ;
		$target = $ret->getSqliteFile() ;
		if ( !copy ( $source , $target ) ) die ( "Copy from $source to $target failed!" ) ;
		return $ret ;
	}
	
	public function subset ( $other_pile_candidate ) {
		$sqlite = $this->getSqlite() ;
		$other_pile = $this->getAsPile($other_pile_candidate) ;
		$other = $other_pile->getSqliteFile() ;
		if ( !isset($other) ) die ( "No 'other' sqlite file to subset" ) ;
		$sql = array() ;
		$sql[] = "ATTACH DATABASE '" . $sqlite->escapeString($other) . "' AS 'other'" ;
		$sql[] = "BEGIN EXCLUSIVE TRANSACTION" ;
		$sql[] = "DELETE FROM main.pages WHERE NOT EXISTS (SELECT * FROM other.pages WHERE other.pages.page=main.pages.page AND other.pages.ns=main.pages.ns)" ;
		$sql[] = "COMMIT" ;
		$sql[] = "DETACH DATABASE 'other'" ;
		$sql = implode ( '; ' , $sql ) ; $sqlite->exec ( $sql ) ;
		$this->addTrack ( array ( 'action' => 'subset' , 'pile' => $other_pile->getTrack() , 'ts' => $this->getCurrentTimestamp() ) ) ;
	}
	
	public function union ( $other_pile_candidate ) {
		$sqlite = $this->getSqlite() ;
		$other_pile = $this->getAsPile($other_pile_candidate) ;
		$other = $other_pile->getSqliteFile() ;
		if ( !isset($other) ) die ( "No 'other' sqlite file to subset" ) ;
		$sql = array() ;
		$sql[] = "ATTACH DATABASE '" . $sqlite->escapeString($other) . "' AS 'other'" ;
		$sql[] = "BEGIN EXCLUSIVE TRANSACTION" ;
		$sql[] = "INSERT OR IGNORE INTO main.pages (page,ns,json) SELECT page,ns,json FROM other.pages" ;
		$sql[] = "COMMIT" ;
		$sql[] = "DETACH DATABASE 'other'" ;
		$sql = implode ( '; ' , $sql ) ;
		$sqlite->exec ( $sql ) ;
		$this->addTrack ( array ( 'action' => 'union' , 'pile' => $other_pile->getTrack() , 'ts' => $this->getCurrentTimestamp() ) ) ;
	}
	

	public function getNewSqliteFile ( $user = '__SYSTEM__' ) {
		if ( get_current_user() == 'tools.pagepile' ) {
			return $this->getNewSqliteFileDirect ( $user ) ;
		} else {
			return $this->getNewSqliteFileFromApi ( $user ) ;
		}
	}
	
	
	public function addTrack ( $o ) {
		$orig = $this->getTrack() ;
		$this->setTrack ( array ( $orig , $o ) ) ;
	}
	
	public function getTrack () {
		$sqlite = $this->getSqlite() ;
		$sql = "SELECT `value` FROM meta WHERE `key`='track'" ;
		$r = $sqlite->querySingle ( $sql ) ;
		if ( !isset($r) or $r == '' ) $r = '{"action":"blank"}' ;
		$j = json_decode ( $r ) ;
		return $j ;
	}

	public function setTrack ( $o ) {
		$sqlite = $this->getSqlite() ;
		$sql = "DELETE FROM meta WHERE `key`='track'; INSERT INTO meta (`key`,`value`) VALUES ('track','" . $sqlite->escapeString(json_encode($o)) . "')" ;
		$sqlite->exec ( $sql ) ;
	}
	
	
// ********************


	protected function getAsPile ( $d ) {
		if ( is_string($d) or is_numeric($d) ) {
			return new PagePile ( $d ) ;
		} else {
			return $d ; // Is a PagePile, I hope...
		}
	}

	protected function getNewSqliteFileDirect ( $user ) {
		$this->openDB() ;
		$file = '' ;
		while (true) {
			$filename = uniqid('pagepile', true) . '.sqlite';
			$file = $this->file_root . '/' . $filename ;
			if (!file_exists($file)) break;
		}
		touch ( $file ) ;
		chmod ( $file , 0766 ) ;
		$ts = $this->getCurrentTimestamp() ;
		$sql = "INSERT INTO piles (path,user,created,touched) VALUES ('" . $this->db->real_escape_string($file) . "','" . $this->db->real_escape_string($user) . "','$ts','$ts')" ;
		if(!$result = $this->db->query($sql)) die('There was an error running the query [' . $this->db->error . ']');
		$this->pile_id = $this->db->insert_id ;
		$this->sqlite_file = $file ;
//		print "<pre>$file</pre>" ;
//		print "<pre>{$this->pile_id}</pre>" ;
		return $file ;
	}

	protected function getNewSqliteFileFromApi ( $user ) {
		ini_set('user_agent','PagePile tool'); # Fake user agent
		$url = 'https://tools.wmflabs.org/pagepile/api.php?action=create_pile&user=' . urlencode($user) ;
		$j = json_decode ( file_get_contents ( $url ) ) ;
		if ( $j->status != 'OK' ) die ( "Cannot create new pile: " . $j->status ) ;
		$this->pile_id = $j->pile->id ;
		$this->sqlite_file = $j->pile->file ;
//		print "<pre>{$this->pile_id}</pre>" ;
		return $this->sqlite_file ;
	}

	protected function mytrim ( $s ) {
		return str_replace ( ' ' , '_' , trim ( str_replace ( '_' , ' ' , $s ) ) ) ;
	}

	protected function splitFullPageName ( $fullpage ) {
		$fullpage = $this->mytrim ( $fullpage ) ;
		$parts = explode ( ':' , $fullpage , 2 ) ;
		if ( count ( $parts ) == 1 ) return array ( $fullpage , 0 ) ; // No ":", default namespace 0
		$parts[0] = $this->mytrim ( $parts[0] ) ;
		$parts[1] = $this->mytrim ( $parts[1] ) ;
//		print "<pre>" ; print_r ( $parts ) ; print "</pre>" ;
		$wiki = $this->getWiki() ;
//		print "<pre>" ; print_r ( $this->namespace_cache->$wiki ) ; print "</pre>" ;
		if ( isset ( $this->namespace_cache->$wiki->name2id[$parts[0]] ) ) return array ( ucfirst($parts[1]) , $this->namespace_cache->$wiki->name2id[$parts[0]] ) ;
		if ( $wiki != 'enwiki' ) {
			$wiki = 'enwiki' ;
			if ( isset ( $this->namespace_cache->$wiki->name2id[$parts[0]] ) ) return array ( ucfirst($parts[1]) , $this->namespace_cache->$wiki->name2id[$parts[0]] ) ;
		}
		return array ( $fullname , 0 ) ; // Not a namespace prefix
	}

	protected function initSqliteFile ( $file ) {
		if ( file_exists ( $file ) ) {
			if ( filesize ( $file ) > 0 ) die ( "File $file exists and is not empty, cannot init!" ) ;
		}
		$this->sqlite_file = $file ;
		$sqlite = $this->getSqlite() ;
		
		// Create tables
		$sqlite->exec ( 'CREATE TABLE pages (id INTEGER PRIMARY KEY,page MEDIUMTEXT,ns INTEGER,json MEDIUMTEXT)' ) ;
		$sqlite->exec ( 'CREATE TABLE meta (id INTEGER PRIMARY KEY,key MEDIUMTEXT,value MEDIUMTEXT)' ) ;
		
		// Create indices
		$sqlite->exec ( 'CREATE UNIQUE INDEX page_index ON pages (page,ns)' ) ;
		
		// Misc
		$sqlite->exec ( 'INSERT INTO meta (key,value) values ("wiki","' . $sqlite->escapeString($this->getWiki())  . '")' ) ;
		$sqlite->exec ( 'INSERT INTO meta (key,value) values ("track","'.json_encode(array('action'=>'created','id'=>$this->getPileID(),'ts'=>$this->getCurrentTimestamp())).'")' ) ;
	}

	protected function close () {
		if ( isset($this->sqlite) ) $this->sqlite->close() ;
		unset ( $this->sqlite ) ;
		if ( isset ( $this->db ) ) $this->db->close() ;
		unset ( $this->db ) ;
		unset ( $this->wiki ) ;
		unset ( $pile_id ) ;
	}

	protected function loadNamespaces ( $wiki ) {
		if ( isset($this->namespace_cache->$wiki) ) return ;
		$server = $this->wiki2server ( $wiki ) ;
		$url = "https://$server/w/api.php?action=query&meta=siteinfo&siprop=namespaces|namespacealiases&format=json" ;
		$j = json_decode ( file_get_contents ( $url ) ) ;
		$this->namespace_cache->$wiki = (object) array ( 'name2id' => array() , 'id2name' => array() ) ;
		$star = '*' ;
		foreach ( $j->query->namespaces AS $v ) {
			$this->namespace_cache->$wiki->name2id[str_replace(' ','_',$v->$star)] = $v->id ;
			if ( isset($v->canonical) ) $this->namespace_cache->$wiki->id2name[$v->id] = str_replace(' ','_',$v->canonical) ;
		}
		if ( !isset($j->query->namespacealiases) ) return ;
		foreach ( $j->query->namespacealiases AS $v ) {
			$this->namespace_cache->$wiki->name2id[str_replace(' ','_',$v->$star)] = $v->id ;
		}
	}
	
	protected function wiki2server ( $wiki ) {
		if ( $wiki == 'wikidatawiki' ) return 'www.wikidata.org' ;
		if ( $wiki == 'commonswiki' ) return 'commons.wikimedia.org' ;
		if ( !preg_match ( '/^(.+)(wik.+)$/' , $wiki , $m ) ) die ( "Cannot parse wiki $wiki" ) ;
		$language = $m[1] ;
		$project = $m[2] ;
		if ( $project == 'wiki' ) $project = 'wikipedia' ;
		return "$language.$project.org" ;
	}
	
	protected function openDB () {
		if ( isset($this->db) ) return ;
		$ini_file = $_SERVER["DOCUMENT_ROOT"] . '/../replica.my.cnf' ;
		if ( preg_match ( '/^tools\.(.+)$/' , get_current_user() , $m ) ) {
			$ini_file = "/data/project/{$m[1]}/replica.my.cnf" ;
		}
		$ini = parse_ini_file ( $ini_file ) ;
		$this->db = new mysqli($this->mysql_server, $ini['user'], $ini['password'], $this->dbname);
		if($this->db->connect_errno > 0) {
			$this->error = 'Unable to connect to database [' . $this->db->connect_error . ']';
			die ( $this->error ) ;
			return false ;
		}
	}

	protected function lp2wiki ( $language , $project ) {
		$ret = $language ;
		if ( $language == 'commons' ) $ret = 'commonswiki' ;
		elseif ( $language == 'wikidata' || $project == 'wikidata' ) $ret = 'wikidatawiki' ;
		elseif ( $language == 'mediawiki' || $project == 'mediawiki' ) $ret = 'mediawikiwiki' ;
		elseif ( $language == 'meta' && $project == 'wikimedia' ) $ret = 'metawiki' ;
		elseif ( $project == 'wikipedia' ) $ret .= 'wiki' ;
		elseif ( $project == 'wikisource' ) $ret .= 'wikisource' ;
		elseif ( $project == 'wiktionary' ) $ret .= 'wiktionary' ;
		elseif ( $project == 'wikibooks' ) $ret .= 'wikibooks' ;
		elseif ( $project == 'wikinews' ) $ret .= 'wikinews' ;
		elseif ( $project == 'wikiversity' ) $ret .= 'wikiversity' ;
		elseif ( $project == 'wikivoyage' ) $ret .= 'wikivoyage' ;
		elseif ( $project == 'wikiquote' ) $ret .= 'wikiquote' ;
		elseif ( $language == 'meta' ) $ret .= 'metawiki' ;
		else if ( $project == 'wikimedia' ) $ret .= $language.$project ;
		else die ( "Cannot construct wiki name for $language.$project - aborting." ) ;
		return $ret ;
	}
	
}

?>