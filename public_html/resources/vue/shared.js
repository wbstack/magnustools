// ENFORCE HTTPS
if (location.protocol != 'https:') location.href = 'https:' + window.location.href.substring(window.location.protocol.length);

function loadVueModules ( modules , callback ) {
	var cnt = 0 ;
	var vue_module_templates_id = 'vue_module_templates' ;
	$('body').append($("<div id='"+vue_module_templates_id+"'>"));
	$.each ( modules , function ( dummy , module ) {
		cnt++ ;
		var url = 'https://tools-static.wmflabs.org/magnustools/resources/vue/'+module+'.html' ;
		$('#'+vue_module_templates_id).append($("<div>")).load(url,function(){
			cnt-- ;
			if ( cnt > 0 ) return ;
			callback() ;
		}) ;
	} ) ;
}