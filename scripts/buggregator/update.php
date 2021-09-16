#!/usr/bin/env php
<?php

require_once ( 'Buggregator.php' ) ;

$buggregator = new Buggregator ;

if ( $argv[1] == 'update' ) $buggregator->update() ;
else if ( $argv[1] == 'maintenance' ) $buggregator->maintenance() ;
else if ( $argv[1] == 'toolhub' ) $buggregator->toolhub_update() ;
else print "Nothing to do!\n" ;

?>