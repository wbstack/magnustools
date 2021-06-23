<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

require_once('/data/project/magnustools/public_html/php/ToolforgeCommon.php') ;

class Issue {
	const ZERO_TIME = '0000-00-00 00:00:00' ;
	const FIELDS = ['label','status','date_created','date_last','site','url','description','tool'] ;

	protected $issue_id ;
	protected $label ;
	protected $status = 'OPEN' ;
	protected $date_created = self::ZERO_TIME ;
	protected $date_last = self::ZERO_TIME ;
	protected $site = 'WIKI' ;
	protected $url = '' ;
	protected $description = '' ;
	protected $tool = 0 ;

	public function get_or_create_issue_id ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function extract_times () {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function construct_url ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function determine_tool ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function get_associated_urls ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function get_associated_users ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function set_times () {
		$times = $this->extract_times() ;
		foreach ( $times as $time ) {
			if ( $this->date_created==self::ZERO_TIME or $this->date_created>$time ) $this->date_created = $time ;
			if ( $this->date_last<$time ) $this->date_last = $time ;
		}
	}

	protected function add_associated_urls ( $buggregator ) {
		$urls = $this->get_associated_urls ( $buggregator ) ;
		foreach ( $urls as $url ) {
			$url = $buggregator->escape ( $url ) ;
			$sql = "INSERT IGNORE INTO `url_issue` (`url`,`issue_id`) VALUES ('{$url}',{$this->issue_id})" ;
			$buggregator->getSQL ( $sql ) ;
		}
	}

	protected function add_associated_users ( $buggregator ) {
		$user_id2role = $this->get_associated_users ( $buggregator ) ;
		foreach ( $user_id2role AS $user_id => $role ) {
			$user_id *= 1 ;
			$role = $buggregator->escape ( $role ) ;
			$sql = "INSERT IGNORE INTO `user_issue` (`user_id`,`issue_id`,`role`) VALUES ({$user_id},{$this->issue_id},'{$role}')" ;
			$buggregator->getSQL ( $sql ) ;
		}
	}

	protected function create_as_new_issue ( $buggregator ) {
		$this->set_times() ;
		$this->construct_url ( $buggregator ) ;
		$this->determine_tool ( $buggregator ) ;
		$values = [] ;
		foreach ( self::FIELDS AS $field ) $values[] = $buggregator->escape($this->$field);
		$fields = implode('`,`',self::FIELDS) ;
		$values = implode("','",$values) ;
		$sql = "INSERT IGNORE INTO `issue` (`{$fields}`) VALUES ('{$values}')" ;
		$buggregator->getSQL ( $sql ) ;
		$this->issue_id = $buggregator->last_insert_id() ;
		$this->add_associated_urls ( $buggregator ) ;
		$this->add_associated_users ( $buggregator ) ;
		return $this->issue_id ;
	}
}

class WikiIssue extends issue {
	protected $wikitext ;
	protected $wiki_page_id ;

	public static function new ( $wiki_page_id , $label , $wikitext ) {
		$ret = new WikiIssue ;
		$ret->label = trim($label) ;
		$ret->wikitext = trim($wikitext) ;
		$ret->wiki_page_id = $wiki_page_id * 1 ;
		return $ret ;
	}

	public function get_or_create_issue_id ( $buggregator ) {
		if ( isset($this->issue_id) ) return $this->issue_id ; # Already has an issue ID

		# Paranoia
		if ( !isset($this->label) ) throw new Exception("{__METHOD__}: No label set");
		if ( !isset($this->wiki_page_id) ) throw new Exception("{__METHOD__}: No wiki_page_id set");

		# Try page/label
		$safe_label = $buggregator->escape($this->label) ;
		$sql = "SELECT * FROM `issue`,`wiki_issue` WHERE `site`='WIKI' AND `label`='{$safe_label}' AND `issue_id`=`issue`.`id` AND `wiki_page_id`={$this->wiki_page_id}" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) {
			$this->issue_id = $o->id ;
			return $this->issue_id ;
		}

		# Create new issue
		$this->description = $buggregator->tfc->trimWikitextMarkup($this->wikitext);
		$this->create_as_new_issue($buggregator) ;
		
		# Create new wiki issue
		$wikitext = $buggregator->escape ( $this->wikitext ) ;
		$sql = "INSERT IGNORE INTO `wiki_issue` (`wiki_page_id`,`issue_id`,`wikitext`) VALUES ({$this->wiki_page_id},{$this->issue_id},'{$wikitext}')" ;
		$buggregator->getSQL ( $sql ) ;
	}

	protected function construct_url ( $buggregator ) {
		$this->url = '' ;
		$sql = "SELECT * FROM `wiki_page` WHERE `id`={$this->wiki_page_id}" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()){
			$server = $buggregator->tfc->getWebserverForWiki($o->wiki) ;
			if ( $server == '' ) return ;
			$page = urlencode($o->page) ;
			$hash = urlencode($this->label) ;
			$this->url = "https://{$server}/wiki/{$page}#{$hash}" ;
		}
	}

	protected function determine_tool ( $buggregator ) {
		$this->tool = 0 ;
		$sql = "SELECT * FROM `wiki_page` WHERE `id`={$this->wiki_page_id}" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) $this->tool = $o->tool_hint ;
	}

	protected function get_associated_urls ( $buggregator ) {
		if ( preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $this->wikitext, $m) ) {
			return $m[0] ;
		}
		return [] ;
	}

	protected function get_associated_users ( $buggregator ) {
		$user_id2role = [] ;
		if ( preg_match_all('#\[\[(User|Benutzer):(.+?)(\||\]\])#',$this->wikitext,$m) ) {
			$role = 'CREATOR' ; # Assuming first user name is the creator
			$usernames = $m[2] ;
			foreach ( $usernames AS $username ) {
				$username = trim ( $username ) ;
				if ( $username == '' ) continue ;
				$user = new User ( $username , 'WIKI' ) ;
				$user_id = $user->get_or_create_user_id ( $buggregator ) ;
				if ( isset($user_id2role[$user_id]) ) continue ;
				$user_id2role[$user_id] = $role ;
				$role = 'UNKNOWN' ;
			}
		}
		return $user_id2role ;
	}

	protected function extract_times () {
		$ret = [] ;
		if ( preg_match_all('|(\d{2}:\d{2}, \d{1,2} \S+? \d{4}) \((UTC|CET|CEST)\)|',$this->wikitext,$m) ) {
			foreach ( $m[1] AS $time ) {
				$ret[] = date('Y-m-d H:i:00',strtotime($time));
			}
		}
		return $ret ;
	}

}

class User {
	protected $user_id ;
	protected $username ;
	protected $site ;

	public function __construct ( $username , $site ) {
		$this->site = $site ;
		$this->username = $this->sanitize($username) ;
	}

	protected function sanitize ( $username ) {
		if ( $this->site == 'WIKI' ) {
			$username = str_replace ( '_' , ' ' , $username ) ;
		}
		$username = trim ( $username ) ;
		return $username ;
	}

	public function get_or_create_user_id ( $buggregator ) {
		$username = $buggregator->escape($this->username) ;
		$site = $buggregator->escape($this->site) ;
		$sql = "SELECT * FROM `user` WHERE `name`='{$username}' AND `site`='{$site}'" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) {
			$this->user_id = $o->id ;
		} else {
			$sql = "INSERT IGNORE INTO `user` (`name`,`site`) VALUES ('{$username}','{$site}')" ;
			$buggregator->getSQL ( $sql ) ;
			$this->user_id = $buggregator->last_insert_id() ;
		}
		return $this->user_id ;
	}
}

class Buggregator {
	public $tfc ;
	protected $tool_db ;

	public function __construct () {
		$this->tfc = new ToolforgeCommon ( 'buggregator' ) ;
		$this->tool_db = $this->tfc->openDBtool('buggregator') ;
	}

	public function update_from_wikipages() {
		$sql = "SELECT * FROM `wiki_page`" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()){
			$wikitext = $this->tfc->getWikiPageText($o->wiki,$o->page) ;
			$rows = explode ( "\n" , $wikitext ) ;
			$label = '' ;
			$wikitext = '' ;
			foreach ( $rows AS $row ) {
				if ( preg_match('|^\s*={2,}\s*(.+?)\s*={2,}\s*$|',$row,$m) ) {
					if ( $label != '' ) {
						$issue = WikiIssue::new($o->id,$label,$wikitext) ;
						$issue->get_or_create_issue_id($this);
					}
					$label = $m[1] ;
					$wikitext = '' ;
					continue ;
				}
				$wikitext .= "{$row}\n" ;
			}
			if ( $label != '' ) {
				$issue = WikiIssue::new($o->id,$label,$wikitext) ;
				$issue->get_or_create_issue_id($this);
			}
		}
	}
	
	public function getSQL ( $sql ) {
		return $this->tfc->getSQL ( $this->tool_db , $sql ) ;
	}

	public function escape ( $s ) {
		return $this->tool_db->real_escape_string ( $s ) ;
	}

	public function last_insert_id () {
		return $this->tool_db->insert_id ;
	}

}

?>