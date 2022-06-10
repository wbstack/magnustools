#!/usr/bin/php
<?PHP

require_once ( '/data/project/magnustools/scripts/logger/Logger.php' ) ;

$logger = new Logger ;

$tool_id = $logger->getOrCreateToolByNameAndMethod('testing','misc requests') ;
print "Tool {$tool_id}\n" ;
$logger->addToLog ( $tool_id ) ;
$logger->addToLog ( $tool_id ) ;
$uses = $logger->getAllUses ( $tool_id ) ;
print "Uses {$uses}, expecting 2\n" ;
$logger->deleteToolById($tool_id) ;
$logger->micDrop();

?>