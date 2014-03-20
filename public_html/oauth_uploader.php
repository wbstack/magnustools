<?PHP

$out = array ( 'error' => 'OK' , 'data' => array() ) ;
$botmode = isset ( $_REQUEST['botmode'] ) ;
if ( $botmode ) {
	header ( 'application/json' ) ; // text/plain
} else {
	error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
	ini_set('display_errors', 'On');
}

require_once ( 'php/oauth.php' ) ;
require_once ( 'php/common.php' ) ;


// https://www.mediawiki.org/wiki/Special:OAuthConsumerRegistration/list
$oa = new MW_OAuth ( 'magnustools' , 'commons' , 'wikimedia' ) ; // OAuth Uploader

function error ( $e ) {
	global $out , $botmode ;
	if ( $botmode ) {
		$out['error'] = $e ;
	} else {
		print "<pre>" . $e . "</pre>" ;
	}
	return false ;
}

function setPageText () {
	global $out , $oa , $botmode ;
	if ( !$oa->isAuthOK() ) return error ( $oa->error ) ;

	$page = trim ( get_request ( "page" , '' ) ) ;
	$text = trim ( get_request ( "text" , '' ) ) ;
	
	if ( $text == '' ) {
		return error ( "No text given" ) ;
	} else if ( ! $oa->setPageText ( $page , $text ) ) {
		return error ( $oa->error ) ;
	}
}


function uploadFromURL () {
	global $out , $oa , $botmode ;
	if ( !$oa->isAuthOK() ) return error ( "Auth not OK: " . $oa->error ) ;

	$url = trim ( get_request ( "url" , '' ) ) ;
	$new_file_name = trim ( get_request ( "newfile" , '' ) ) ;
	$desc = trim ( get_request ( "desc" , '' ) ) ;
	$comment = trim ( get_request ( "comment" , '' ) ) ;
	
	if ( $url == '' ) return error ( "No URL given" ) ;
	
	if ( !$oa->doUploadFromURL ( $url , $new_file_name , $desc , $comment ) ) {
		$out['res'] = $oa->last_res ;
		return error ( $oa->error ) ;
	}
	$out['res'] = $oa->last_res ;
}



function bot_out () {
	global $out , $oa , $botmode ;
	if ( !$botmode ) return ;
	if ( isset ( $oa->error ) ) $out['error'] = $oa->error ;
	if ( isset($_REQUEST['callback']) ) print $_REQUEST['callback']."(" ;
	print json_encode ( $out ) ;
	if ( isset($_REQUEST['callback']) ) print ");" ;
}


if ( !$botmode ) {
	print get_common_header ( '' , 'OAuth file uploader' ) ;
	print "<p>This tool facilitates file uploads to Wikimedia Commons, under jour user name. " ;
	print "You will have to <a href='".htmlspecialchars( $_SERVER['SCRIPT_NAME'] )."?action=authorize'>authorise</a> it first.</p>" ;
}


switch ( isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '' ) {
	case 'authorize':
		$oa->doAuthorizationRedirect();
		break;
	case 'checkauth':
		if ( !$oa->isAuthOK() ) error ( "Auth not OK: " . $oa->error ) ;
		else {
			print "Auth OK!" ;
		}
		break;
	case 'setpagetext':
		setPageText() ;
		break;
	case 'upload':
		uploadFromURL() ;
		break;
	default:
		if ( !$botmode ) {
			print "<h3>Tools using OAuth Uploader</h3>
<ul>
<li><a href='/flickr2commons/index.html'>Flickr2Commons</a></li>
</ul>" ;
		} else {
			$out['error'] = "Unknown action '$action'" ;
		}
}

if ( $botmode ) {
	bot_out() ;
} else {
	print get_common_footer() ;
}

?>