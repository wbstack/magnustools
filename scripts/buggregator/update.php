#!/usr/bin/env php
<?php

require_once ( 'Buggregator.php' ) ;

$buggregator = new Buggregator ;

#$buggregator->update_from_wikipages() ;
$buggregator->maintenance() ;

?>