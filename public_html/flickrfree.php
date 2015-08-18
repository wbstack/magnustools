<?PHP
//error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
//ini_set('display_errors', 'On');

include_once ( "php/common.php" ) ;

$fk = trim ( file_get_contents ( "../flickr_key.txt" ) ) ;
$url = "https://api.flickr.com/services/rest/?method=flickr.photos.getRecent&api_key=$fk&extras=license,date_upload,owner_name,icon_server,geo,tags&per_page=500" ;
$data = file_get_contents ( $url ) ;
$lines = explode ( "/>" , $data ) ;

print get_common_header ( "flickrfree.php" , 'FlickrFree' ) ;
print "<p>Recent free images uploaded on flickr</p>" ;
print "<table class='table table-condensed table-striped'>" ;

$licenses = array () ;
$licenses[4] = 'CC-BY 2.0' ;
$licenses[5] = 'CC-BY-SA 2.0' ;
$licenses[7] = 'Public domain' ;

$tdtop = "style='border-top:2px solid black'" ;
$top = '' ;
foreach ( $lines AS $l ) {
	$matches = array () ;
	preg_match ( '/license="(.)"/' , $l , $matches ) ;
	$license = $matches[1] ;
	if ( $license != 4 /*CC-BY*/ and $license != 5 /*CC-BY-SA*/ and $license != 7 /*PD*/ ) continue ;
	
	preg_match ( '/farm="([^"]+)"/' , $l , $matches ) ; $farm = $matches[1] ;
	preg_match ( '/server="([^"]+)"/' , $l , $matches ) ; $server = $matches[1] ;
	preg_match ( '/title="([^"]+)"/' , $l , $matches ) ; $title = $matches[1] ;
	preg_match ( '/owner="([^"]+)"/' , $l , $matches ) ; $owner = $matches[1] ;
	preg_match ( '/ownername="([^"]+)"/' , $l , $matches ) ; $ownername = $matches[1] ;
	preg_match ( '/tags="([^"]+)"/' , $l , $matches ) ; $tags = $matches[1] ;
	preg_match ( '/id="([^"]+)"/' , $l , $matches ) ; $id = $matches[1] ;

	# Get thumbnail
	$url = "https://api.flickr.com/services/rest/?method=flickr.photos.getSizes&api_key=$fk&photo_id=$id" ;
	$data = file_get_contents ( $url ) ;
	$data = array_pop ( explode ( 'width="100"' , $data , 2 ) ) ;
	$data = array_shift ( explode ( '/>' , $data , 2 ) ) ;
	preg_match ( '/source="([^"]+)"/' , $data , $matches ) ; $thumb_url = $matches[1] ;
	
	$flickr_owner_url = "https://flickr.com/photos/$owner" ;
	$flickr_url = "$flickr_owner_url/$id" ;
	
	$nn = $id ;
	if ( $title != '' ) $nn = $title ;
	$nn = urlencode ( "$nn.jpg" ) ;
	$f2c = "//tools.wmflabs.org/flickr2commons/?photoid=$id" ;
	
	print "<tr>" ;
	print "<td $top><a href='$flickr_url' target='_blank'><img border='0' width='100' src='$thumb_url' /></a></td>" ;
	print "<td $top>" ;
	print "<b>$title</b><br/>" ;
	print "By user <i><a target='_blank' href='$flickr_owner_url'>$ownername</a></i><br/>" ;
	print "Tags : <i>$tags</i><br/>" ;
	print "License : <i>" . $licenses[$license] . "</i>" ;
	print "</td><td $top><a target='_blank' href='$f2c'>Upload to Commons</a>" ;
	print "</td>" ;
	print "</tr>" ;
	myflush() ;
	
	$top = $tdtop ;
#	print htmlentities ( $l ) . "<br/>" ;
}

print "</table>" ;
print get_common_footer() ;
