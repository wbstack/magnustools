<?PHP

class WDI {

	var $q ;
	var $j ;
	
	function WDI ( $q ) {
		$q = 'Q' . preg_replace ( '/\D/' , '' , "$q" ) ;
		$this->q = $q ;
		$url = "https://www.wikidata.org/w/api.php?action=wbgetentities&ids=$q&format=json" ;
		$j = json_decode ( file_get_contents ( $url ) ) ;
		$this->j = $j->entities->$q ;
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

	function getClaims ( $p ) {
		$ret = array() ;
		$p = 'P' . preg_replace ( '/\D/' , '' , "$p" ) ;
		if ( !isset($this->j->claims) ) return $ret ;
		if ( !isset($this->j->claims->$p) ) return $ret ;
		return $this->j->claims->$p ;
	}
	
	function hasClaims ( $p ) {
		return count($this->getClaims($p)) > 0 ;
	}

	
}

?>