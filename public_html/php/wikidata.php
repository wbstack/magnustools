<?PHP

$wikidata_preferred_langs = array ('en','de','nl','fr','es','it','zh') ;
$wikidata_api_url = 'https://www.wikidata.org/w/api.php' ;

class WDI {

	var $q ;
	var $j ;
	
	function WDI ( $q = '' ) {
		global $wikidata_api_url ;
		if ( $q != '' ) {
			$q = 'Q' . preg_replace ( '/\D/' , '' , "$q" ) ;
			$this->q = $q ;
			$url = "$wikidata_api_url?action=wbgetentities&ids=$q&format=json" ;
			$j = json_decode ( file_get_contents ( $url ) ) ;
			$this->j = $j->entities->$q ;
		}
	}
	
	function getQ () {
		return $this->q ;
	}
	
	function getLabel ( $lang = '' ) {
		global $wikidata_preferred_langs ;
		if ( !isset ( $this->j->labels ) ) return $this->q ;
		if ( isset ( $this->j->labels->$lang ) ) return $this->j->labels->$lang->value ; // Shortcut
		
		$score = 9999 ;
		$best = $this->q ;
		foreach ( $this->j->labels AS $v ) {
			$p = array_search ( $v->language , $wikidata_preferred_langs ) ;
			if ( $p === false ) $p = 999 ;
			$p *= 1 ;
			if ( $p >= $score ) continue ;
			$score = $p ;
			$best = $v->value ;
		}
		return $best ;
	}
	
	function getDesc ( $lang = '' ) {
		global $wikidata_preferred_langs ;
		if ( !isset ( $this->j->descriptions ) ) return '' ;
		if ( isset ( $this->j->descriptions->$lang ) ) return $this->j->descriptions->$lang->value ; // Shortcut
		
		$score = 9999 ;
		$best = '' ;
		foreach ( $this->j->descriptions AS $v ) {
			$p = array_search ( $v->language , $wikidata_preferred_langs ) ;
			if ( $p === false ) $p = 999 ;
			if ( $p*1 >= $score*1 ) continue ;
			$score = $p ;
			$best = $v->value ;
		}
		return $best ;
	}
	
	function getTarget ( $claim ) {
		$nid = 'numeric-id' ;
		if ( !isset($claim->mainsnak) ) return false ;
		if ( !isset($claim->mainsnak->datavalue) ) return false ;
		if ( !isset($claim->mainsnak->datavalue->value) ) return false ;
		if ( !isset($claim->mainsnak->datavalue->value->$nid) ) return false ;
		return 'Q'.$claim->mainsnak->datavalue->value->$nid ;
	}
	
	function hasLabel ( $label ) {
		if ( !isset($this->j->labels) ) return false ;
		foreach ( $this->j->labels AS $lab ) {
			if ( $lab->value == $label ) return true ;
		}
		return false ;
	}
	
	function hasExternalSource ( $claim ) {
		return false ; // DUMMY
	}

	function sanitizeProp ( $p ) {
		return 'P' . preg_replace ( '/\D/' , '' , "$p" ) ;
	}
	
	function getStrings ( $p ) {
		$ret = array() ;
		if ( !$this->hasClaims($p) ) return $ret ;
		$claims = $this->getClaims($p) ;
		foreach ( $claims AS $c ) {
			if ( !isset($c->mainsnak) ) continue ;
			if ( !isset($c->mainsnak->datavalue) ) continue ;
			if ( !isset($c->mainsnak->datavalue->value) ) continue ;
			if ( !isset($c->mainsnak->datavalue->type) ) continue ;
			if ( $c->mainsnak->datavalue->type != 'string' ) continue ;
			$ret[] = $c->mainsnak->datavalue->value ;
		}
		return $ret ;
	}
	
	function getFirstString ( $p ) {
		$strings = $this->getStrings ( $p ) ;
		if ( count($strings) == 0 ) return '' ;
		return $strings[0] ;
	}

	function getClaims ( $p ) {
		$ret = array() ;
		$p = $this->sanitizeProp ( $p ) ;
		if ( !isset($this->j) ) return $ret ;
		if ( !isset($this->j->claims) ) return $ret ;
		if ( !isset($this->j->claims->$p) ) return $ret ;
		return $this->j->claims->$p ;
	}
	
	function hasClaims ( $p ) {
		return count($this->getClaims($p)) > 0 ;
	}
	
	function getSitelink ( $wiki ) {
		if ( !isset($this->j) ) return ;
		if ( !isset($this->j->sitelinks) ) return ;
		if ( !isset($this->j->sitelinks->$wiki) ) return ;
		return $this->j->sitelinks->$wiki->title ;
	}
	
	function getProps () {
		$ret = array() ;
		if ( !isset($this->j) ) return $ret ;
		if ( !isset($this->j->claims) ) return $ret ;
		foreach ( $this->j->claims AS $p => $v ) $ret[] = $p ;
		return $ret ;
	}
	
	function getClaimByID ( $id ) {
		if ( !isset($this->j->claims) ) return ;
		foreach ( $this->j->claims AS $p => $v ) {
			foreach ( $v AS $dummy => $claim ) {
				if ( $claim->id == $id ) return $claim ;
			}
		}
	}

	
}

class WikidataItemList {

	var $items = array() ;

	function sanitizeQ ( &$q ) {
		if ( preg_match ( '/^P\d+$/i' , "$q" ) ) {
			$q = strtoupper ( $q ) ;
		} else {
			$q = 'Q'.preg_replace('/\D/','',"$q") ;
		}
	}
		
    function loadItems ( $list ) {
    	global $wikidata_api_url ;
    	$qs = array(array()) ;
    	foreach ( $list AS $q ) {
    		$this->sanitizeQ($q) ;
	    	if ( isset($this->items[$q]) ) continue ;
	    	if ( count($qs[count($qs)-1]) == 50 ) $qs[] = array() ;
    		$qs[count($qs)-1][] = $q ;
    	}
    	
    	if ( count($qs) == 1 and count($qs[0]) == 0 ) return ;
    	
    	foreach ( $qs AS $sublist ) {
    		if ( count ( $sublist ) == 0 ) continue ;
			$url = "$wikidata_api_url?action=wbgetentities&ids=" . implode('|',$sublist) . "&format=json" ;
			$j = json_decode ( file_get_contents ( $url ) ) ;
			if ( !isset($j) or !isset($j->entities) ) continue ;
			foreach ( $j->entities AS $q => $v ) {
				$this->items[$q] = new WDI ;
				$this->items[$q]->q = $q ;
				$this->items[$q]->j = $v ;
			}
    	}
    }
    
    function loadItem ( $q ) {
    	return $this->loadItems ( array ( $q ) ) ;
    }
    
    function getItem ( $q ) {
    	$this->sanitizeQ($q) ;
    	if ( !isset($this->items[$q]) ) return ;
    	return $this->items[$q] ;
    }
    
    function getItemJSON ( $q ) {
    	$this->sanitizeQ($q) ;
    	return $this->items[$q]->j ;
    }
    
    function hasItem ( $q ) {
    	$this->sanitizeQ($q) ;
    	return isset($this->items[$q]) ;
    }
	

}

?>