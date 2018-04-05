// ENFORCE HTTPS
if (location.protocol != 'https:') location.href = 'https:' + window.location.href.substring(window.location.protocol.length);

var vue_module_templates_id = 'vue_module_templates' ;
var vue_modules_loaded = {} ;

function loadVueModule ( module , callback ) {
	if ( typeof vue_modules_loaded[module] != 'undefined' ) return callback() ;
	vue_modules_loaded[module] = true ;
	var id = vue_module_templates_id + '-' + module ;
	$('body').append($("<div id='"+id+"'>"));
	var url = 'https://tools-static.wmflabs.org/magnustools/resources/vue/'+module+'.html' ;
	$('#'+id).load(url,function(){ callback() }) ;
}

function loadVueModules ( modules , callback ) {
	var cnt = 0 ;
	$.each ( modules , function ( dummy , module ) {
		cnt++ ;
		loadVueModule ( module , function(){
			if ( --cnt == 0 ) callback() ;
		} ) ;
	} ) ;
}