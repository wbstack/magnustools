<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

require_once ( 'php/ToolforgeCommon.php' );
$tfc = new ToolforgeCommon ( 'spacer' );

print $tfc->getCommonHeader ( "Spacer" ) ;


$id = preg_replace ( '|[<>]|' , '' , $tfc->getRequest ( 'id' , '' ) ) ;

if ( $id == '' ) {
?>

<form method='get' class='form-inline' style='display:inline'>
	ID: 
	<input type='text' name='id' />
	<input type='submit' class='btn btn-outline-primary' value='Get links' />
</form>

<?php
} else {


	print "<h1>{$id}</h1><ul style='font-size:14pt;'>" ;
	print "<li><a class='external' href='https://nssdc.gsfc.nasa.gov/nmc/spacecraft/display.action?id={$id}'>NSSDCA</a></li>" ;
	print "<li><a class='external' href='https://www.n2yo.com/database/?q={$id}#results'>N2Yo</a></li>" ;
	print "</ul></div>" ;

}

print $tfc->getCommonFooter() ;

?>