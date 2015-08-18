<?PHP
//error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
//ini_set('display_errors', 'On');

include_once ( "php/common.php" ) ;

$fk = trim ( file_get_contents ( "../flickr_key.txt" ) ) ;

$licenses = array () ;
$licenses[4] = 'CC-BY 2.0' ;
$licenses[5] = 'CC-BY-SA 2.0' ;
$licenses[7] = 'Public domain' ;
$licenses[8] = 'U.S. Government' ;
$licenses[9] = 'CC-0' ;
$licenses[10] = 'Public Domain Mark' ;

$tdtop = "style='border-top:2px solid black'" ;
$top = '' ;
$page = 1 ;
$id_cache = array() ;

print get_common_header ( "flickrfree.php" , 'FlickrFree' ) ;
print "<p>Recent free images uploaded on flickr</p>" ;
print "<table class='table table-condensed table-striped'>" ;

while ( 1 ) {
	$url = "https://api.flickr.com/services/rest/?method=flickr.photos.getRecent&api_key=$fk&extras=license,date_upload,owner_name,url_t,geo,tags&per_page=500&&format=json&nojsoncallback=1&page=$page" ;
	$j = json_decode ( file_get_contents ( $url ) ) ;

	foreach ( $j->photos->photo AS $f ) {
		$license = $f->license ;
		if ( !isset($licenses[$license]) ) continue ;
		if ( isset ( $id_cache[$f->id] ) ) continue ;
		$id_cache[$f->id] = 1 ;
	
		$flickr_owner_url = "https://flickr.com/photos/" . $f->owner ;
		$flickr_url = "$flickr_owner_url/" . $f->id ;
	
		print "<tr>" ;
		print "<td $top><a href='$flickr_url' target='_blank'><img border='0' width='100' src='{$f->url_t}' /></a></td>" ;
		print "<td $top>" ;
		print "<b>{$f->title}</b><br/>" ;
		print "By user <i><a target='_blank' href='$flickr_owner_url'>{$f->ownername}</a></i><br/>" ;
		print "Tags : <i>{$f->tags}</i><br/>" ;
		print "License : <i>" . $licenses[$license] . "</i>" ;
		print "</td><td $top><a target='_blank' href='//tools.wmflabs.org/flickr2commons/?photoid={$f->id}'>Upload to Commons</a>" ;
		print "</td>" ;
		print "</tr>" ;
	
		$top = $tdtop ;
	}
	
	$page++ ;
	if ( $page > 2 ) break ; // Max 2 pages available
}

print "</table>" ;
print get_common_footer() ;
myflush() ;

?>