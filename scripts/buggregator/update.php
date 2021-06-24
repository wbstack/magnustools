#!/usr/bin/env php
<?php

require_once ( 'Buggregator.php' ) ;

$buggregator = new Buggregator ;

if ( $argv[1] == 'update' ) $buggregator->update() ;
else print "Nothing to do!\n" ;

?>