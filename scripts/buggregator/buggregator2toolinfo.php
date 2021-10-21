#!/usr/bin/env php
<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

require_once ( '/data/project/magnustools/scripts/buggregator/Buggregator.php' ) ;
require_once ( '/data/project/magnustools/public_html/php/oauth.php' ) ;

$outfile = "/data/project/magnustools/public_html/toolinfo_buggregator.json" ;

$buggregator = new Buggregator ;
$buggregator->toolhub_update() ;

$bad_tags = [
	"deactivated",
	"replaced",
	"no results",
	"inactive",
	"broken",
	"planned",
	"error",
	"fixme",
	"replaced",
];

$tool_repo = [] ;
$sql = "SELECT * FROM `git_repo`" ;
$result = $buggregator->getSQL ( $sql ) ;
while($o = $result->fetch_object()) $tool_repo[$o->tool_id] = $o->html_url ;

$names = [] ;
$tools = [] ;
$sql = "SELECT * FROM `tool`" ;
$result = $buggregator->getSQL ( $sql ) ;
while($o = $result->fetch_object()) {
	if ( $o->name == "WHOLE TOOL" ) continue ;
	if ( $o->name == "index" ) continue ;
	if ( $o->url == '' ) continue ; # Nothing to point to
	if ( $o->toolhub!='' ) continue ; # Handled elsewhere
	$skip = false ;
	foreach ( $bad_tags AS $tag ) {
		if ( !stristr($o->note, $tag) ) continue ;
		$skip = true ;
		break ;
	}
	if ( $skip ) continue ;
	$name = strtolower("mm_{$o->name}") ;
	$name = preg_replace("|[^a-zA-Z0-9_-]|","",$name) ;
	if ( in_array($name, $names) ) $name .= "_{$o->subdomain}" ;
	if ( in_array($name, $names) ) print "DUPLICATE NAME: {$name}\n" ;
	$names[] = $name ;
	$tool = (object) [
		"name"=>$name,
		"title"=>str_replace('_',' ',$o->name),
		"description"=>$o->note,
		"url"=>$o->url,
		"keywords"=>[],
		"author"=>"Magnus Manske",
		"repository"=>"",
	] ;
	if ( stristr($o->note, "javascript") ) $tool->keywords[] = "javascript" ;
	foreach ( ["wikipedia","wikisource","wikibooks","wikidata","commons"] as $key ) {
		if ( stristr($o->note, $key) ) $tool->keywords[] = $key ;
		if ( stristr($o->name, $key) ) $tool->keywords[] = $key ;
		if ( stristr($o->subdomain, $key) ) $tool->keywords[] = $key ;
	}
	$tool->keywords = array_unique($tool->keywords) ;
	$tool->keywords = implode(", ",$tool->keywords) ;
	if ( isset($tool_repo[$o->id]) ) $tool->repository = $tool_repo[$o->id] ;
	if ( $o->toolhub!='' ) $tool->name = $o->toolhub ;
	$tools[] = $tool ;
}

$j = json_encode($tools,JSON_PRETTY_PRINT);
file_put_contents($outfile, $j);
