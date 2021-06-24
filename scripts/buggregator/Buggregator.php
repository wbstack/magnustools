<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

require_once('/data/project/magnustools/public_html/php/ToolforgeCommon.php') ;

class Issue {
	const ZERO_TIME = '0000-00-00 00:00:00' ;
	const FIELDS = ['label','status','date_created','date_last','site','url','description','tool','priority'] ;

	protected $issue_id ;
	protected $label ;
	protected $status = 'OPEN' ;
	protected $date_created = self::ZERO_TIME ;
	protected $date_last = self::ZERO_TIME ;
	protected $site = 'WIKI' ;
	protected $url = '' ;
	protected $description = '' ;
	protected $tool = 0 ;
	protected $priority = 'NORMAL' ;

	public function id() { return $this->issue_id * 1 ; }
	public function label() { return $this->label ; }
	public function status() { return $this->status ; }
	public function date_created() { return $this->date_created ; }
	public function date_last() { return $this->date_last ; }
	public function site() { return $this->site ; }
	public function url() { return $this->url ; }
	public function description() { return $this->description ; }
	public function tool() { return $this->tool * 1 ; }
	public function priority() { return $this->priority ; }

	public function set_status ( $new_status ) { $this->status = trim(strtoupper($new_status)) ; }

	public static function new_from_object ( $o ) {
		$ret = new self ;
		$ret->fill_from_object($o) ;
		return $ret ;
	}

	public function get_or_create_issue_id ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function extract_times () {
		# NEEDS TO RETURN AN ARRAY!
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function construct_url ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	public function determine_tool ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function get_associated_urls ( $buggregator ) {
		# DEFAULT: Scrape description for URLs, change as required!
		if ( preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $this->description, $m) ) {
			return $m[0] ;
		}
		return [] ;
	}

	protected function get_associated_users ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function fill_from_object ( $o ) {
		if ( !isset($o->id) ) throw new Exception("{__METHOD__}: `id` not in given object") ;
		$this->issue_id = $o->id ;
		foreach ( self::FIELDS AS $field ) {
			if ( !isset($o->$field) ) throw new Exception("{__METHOD__}: Field {$field} not in given object") ;
			$this->$field = $o->$field ;
		}
	}

	public function set_times () {
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

	public function update_in_database ( $buggregator , $fields = self::FIELDS ) {
		if ( $this->issue_id == 0 ) throw new Exception("{__METHOD__}: No issue ID");
		$values = [] ;
		foreach ( $fields AS $field ) $values[] = "`{$field}`='".$buggregator->escape($this->$field)."'";
		$sql = "UPDATE `issue` SET " . implode(',',$values) . " WHERE `id`={$this->issue_id}" ;
		$buggregator->getSQL ( $sql ) ;
	}
}

class GitIssue extends issue {
	protected $git_id ;
	protected $git_repo_id ;
	protected $tmp_user_names = [] ;

	protected function extract_times () { return [] ; }
	protected function construct_url ( $buggregator ) {}
	public function determine_tool ( $buggregator ) {}

	protected function get_associated_users ( $buggregator ) {
		$user_id2role = [] ;
		foreach ( $this->tmp_user_names AS $username ) {
			$user = new User ( $username , $this->site ) ;
			$user_id = $user->get_or_create_user_id ( $buggregator ) ;
			if ( count($user_id2role) == 0 ) $user_id2role[$user_id] = 'CREATOR' ;
			else if ( !isset($user_id2role[$user_id]) ) $user_id2role[$user_id] = 'UNKNOWN' ;
		}
		return $user_id2role ;
	}

	public function get_or_create_issue_id ( $buggregator ) {
		if ( isset($this->issue_id) ) return $this->issue_id ; # Already has an issue ID

		# Paranoia
		if ( !isset($this->git_id) ) throw new Exception("{__METHOD__}: No git_id set");
		if ( !isset($this->git_repo_id) ) throw new Exception("{__METHOD__}: No git_repo_id set");

		# Check if exists
		$sql = "SELECT * FROM vw_git_issue WHERE site='{$this->site}' AND git_id={$this->git_id} AND git_repo_id={$this->git_repo_id}" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) {
			$this->issue_id = $o->id ;
			return $this->issue_id ;
		}

		# Create new issue
		$this->create_as_new_issue($buggregator) ;
		
		# Create new wiki issue
		$sql = "INSERT IGNORE INTO `git_issue` (`issue_id`,`git_id`,`git_repo_id`) VALUES ({$this->issue_id},{$this->git_id},{$this->git_repo_id})" ;
		$buggregator->getSQL ( $sql ) ;
		return $this->issue_id ;
	}
}

class GithubIssue extends GitIssue {
	public static function new_from_json ( $git_repo , $j ) {
		$ret = new self ;
		$ret->label = $j->title ;
		$ret->url = $j->html_url ;
		$ret->status = strtoupper($j->state) ;
		$ret->date_created = Buggregator::format_time(strtotime($j->created_at)) ;
		$ret->date_last = Buggregator::format_time(strtotime($j->updated_at)) ;
		$ret->description = $j->body ;
		$ret->tool = $git_repo->tool_id ;
		$ret->site = 'GITHUB' ;
		$ret->git_repo_id = $git_repo->id * 1 ;
		$ret->git_id = $j->number * 1 ;
		$ret->tmp_user_names = [ $j->user->login ] ;
		return $ret ;
	}
}

class BitbucketIssue extends GitIssue {
	public static function new_from_json ( $git_repo , $j ) {
		$ret = new self ;
		$ret->label = "{$j->title} [{$j->kind}/{$j->priority}]" ;
		$ret->url = $j->links->html->href ;
		$ret->status = 'OPEN' ; # default
		$ret->date_created = Buggregator::format_time(strtotime($j->created_on)) ;
		$ret->date_last = Buggregator::format_time(strtotime($j->updated_on)) ;
		$ret->description = $j->content->raw ;
		$ret->tool = $git_repo->tool_id ;
		$ret->site = 'BITBUCKET' ;
		$ret->git_repo_id = $git_repo->id * 1 ;
		$ret->git_id = $j->id * 1 ;
		# TODO creating user
		return $ret ;
	}
}

class WikiIssue extends issue {
	protected $wikitext ;
	protected $wiki_page_id ;
	const DE_MONTHS = [ 'Mär' => 'Mar','Mai' => 'May','Okt' => 'Oct','Dez' => 'Dec'] ;

	public static function new_from_object ( $o ) {
		$ret = new self ;
		$ret->fill_from_object($o) ;
		return $ret ;
	}

	public static function new ( $wiki_page_id , $label , $wikitext ) {
		$ret = new WikiIssue ;
		$ret->label = trim($label) ;
		$ret->wikitext = trim($wikitext) ;
		$ret->wiki_page_id = $wiki_page_id * 1 ;
		return $ret ;
	}

	public function wikitext() { return $this->wikitext ; }
	public function wiki_page_id() { return $this->wiki_page_id * 1 ; }

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
		return $this->issue_id ;
	}

	protected function construct_url ( $buggregator ) {
		$this->url = '' ;
		$sql = "SELECT * FROM `wiki_page` WHERE `id`={$this->wiki_page_id}" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()){
			$server = $buggregator->tfc->getWebserverForWiki($o->wiki) ;
			if ( $server == '' ) return ;
			$page = $buggregator->tfc->urlEncode($o->page) ;
			$hash = $buggregator->tfc->urlEncode($this->label) ;
			$this->url = "https://{$server}/wiki/{$page}#{$hash}" ;
		}
	}

	public function determine_tool ( $buggregator ) {
		# Try wiki page hint
		$this->tool = 0 ;
		$sql = "SELECT * FROM `wiki_page` WHERE `id`={$this->wiki_page_id}" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) $this->tool = $o->tool_hint ;

		# Try tool name
		$toolname2id = $buggregator->get_unique_tool_name_ids() ;
		$candidate_tools = [] ;
		foreach ( $toolname2id as $tool_name => $tool_id ) {
			$pattern = "|\b{$tool_name}\b|i" ;
			if ( !preg_match($pattern,$this->wikitext,$m) ) continue ;
			$candidate_tools[] = $tool_id ;
		}
		$candidate_tools = array_values ( array_unique($candidate_tools) ) ;
		if ( count($candidate_tools) == 1 ) {
			$this->tool = $candidate_tools[0] * 1 ;
		}
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
		if ( preg_match_all('|(\d{2}:\d{2}, \d{1,2}\.* \S+ \d{4}) \([A-Z]+\)|',$this->wikitext,$m) ) {
			foreach ( $m[1] AS $time ) {
				foreach ( self::DE_MONTHS AS $from => $to ) $time = str_replace($from,$to,$time) ;
				$time = strtotime($time) ;
				$ret[] = Buggregator::format_time($time) ;
			}
		}
		return $ret ;
	}

	protected function fill_from_object ( $o ) {
		parent::fill_from_object ( $o ) ;
		foreach ( ['wikitext','wiki_page_id'] AS $field ) {
			if ( !isset($o->$field) ) throw new Exception("{__METHOD__}: Field {$field} not in given object") ;
			$this->$field = $o->$field ;
		}
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
	protected $toolname2id = [] ;

	const IGNORE_TOOL_NAMES = ['data'] ;

	public function __construct () {
		$this->tfc = new ToolforgeCommon ( 'buggregator' ) ;
		$this->tool_db = $this->tfc->openDBtool('buggregator') ;
	}

	static public function format_time ( $time ) {
		return date('Y-m-d H:i:s',$time);
	}

	protected function update_from_wikipages() {
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

	public function get_unique_tool_name_ids() {
		if ( count($this->toolname2id) == 0 ) {
			$sql = "SELECT group_concat(`id`)  AS `id`,lower(regexp_replace(`name`,'_',' ')) AS `name` from `tool` group by lower(regexp_replace(`name`,'_',' ')) having count(*)=1" ;
			$this->toolname2id = [] ;
			$result = $this->getSQL ( $sql ) ;
			while($o = $result->fetch_object()) $this->toolname2id[$o->name] = $o->id ;
			foreach ( self::IGNORE_TOOL_NAMES AS $bad_name ) unset($this->toolname2id[$bad_name]) ;
		}
		return $this->toolname2id ;
	}

	protected function update_from_github () {
		$sql = "SELECT * FROM `git_repo` WHERE `repo_type`='GITHUB'" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()){
			$opts = [ "http" => ["method" => "GET","header" => "Accept: application/vnd.github.v3+json\r\n" ]];
			$context = stream_context_create($opts);
			$json = file_get_contents($o->api_issues_url, false, $context);
			$json = json_decode ( $json ) ;
			foreach ( $json AS $git_issue ) {
				$issue = GithubIssue::new_from_json ( $o , $git_issue ) ;
				$issue->get_or_create_issue_id ( $this ) ;
			}
		}
	}
	
	protected function update_from_bitbucket () {
		$sql = "SELECT * FROM `git_repo` WHERE `repo_type`='BITBUCKET'" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()){
			$url = "{$o->api_issues_url}/?pagelen=100" ;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$json = curl_exec($ch);
			$json = json_decode ( $json ) ;
			if ( !isset($json->values) ) continue ;
			foreach ( $json->values AS $git_issue ) {
				$issue = BitbucketIssue::new_from_json ( $o , $git_issue ) ;
				$issue->get_or_create_issue_id ( $this ) ;
			}
		}
	}
	
	public function update () {
		$this->update_from_wikipages() ;
		$this->update_from_github() ;
		$this->update_from_bitbucket() ;
		$this->maintenance() ;
	}


	protected function maintenance_wiki_dates () {
		$sql = "SELECT * FROM `vw_wiki_issue` WHERE `date_created`='".Issue::ZERO_TIME."' AND `status`='OPEN'" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()){
			$issue = WikiIssue::new_from_object ( $o ) ;
			if ( $issue->date_created() != WikiIssue::ZERO_TIME ) continue ; # Paranoia
			$issue->set_times() ;
			if ( $issue->date_created() == WikiIssue::ZERO_TIME ) continue ; # None found
			$issue->update_in_database ( $this , ['date_created','date_last'] ) ;
		}
	}

	protected function maintenance_wiki_tool_guess () {
		$sql = "SELECT * FROM `vw_wiki_issue` WHERE `tool`=0 AND `status`='OPEN'" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()) {
			$issue = WikiIssue::new_from_object ( $o ) ;
			if ( $issue->tool() != 0 ) continue ; # Paranoia
			$issue->determine_tool ( $this ) ;
			if ( $issue->tool() == 0 ) continue ; # No avail
			$issue->update_in_database ( $this , ['tool'] ) ;
		}
	}

	protected function maintenance_wiki_close_old_replied () {
		# Get all open issues where I wrote something...
		$time = strtotime("-1 month");
		$cutoff_time = self::format_time($time);
		$sql = "SELECT `vw_wiki_issue`.* FROM `vw_wiki_issue`,`user_issue`" ;
		$sql .= " WHERE `date_last`!='".Issue::ZERO_TIME."' AND `date_last`<'{$cutoff_time}' AND `status`='OPEN'" ;
		$sql .= " AND `user_issue`.`issue_id`=`vw_wiki_issue`.`id` AND `user_issue`.`user_id`=14" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()) {
			$issue = WikiIssue::new_from_object ( $o ) ;
			if ( $issue->status() != 'OPEN' ) continue ; # Paranoia
			$rows = explode ( "\n" , trim($issue->wikitext()) ) ;
			$last_row = array_pop($rows);
			# Make sure I wrote on the last row
			if ( !preg_match('|\bMagnus[ _]Manske\b|',$last_row) ) continue ;
			# Old issue, I wrote the last line => closed
			$issue->set_status ( 'CLOSED' ) ;
			$issue->update_in_database ( $this , ['status'] ) ;
		}
	}

	public function maintenance () {
		$this->maintenance_wiki_dates() ;
		$this->maintenance_wiki_tool_guess() ;
		$this->maintenance_wiki_close_old_replied() ;
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