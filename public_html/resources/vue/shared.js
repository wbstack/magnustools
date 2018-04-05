// ENFORCE HTTPS
if (location.protocol != 'https:') location.href = 'https:' + window.location.href.substring(window.location.protocol.length);

var vue_modules = {
	template_container_base_id : 'vue_module_templates' ,
	modules_base_url : 'https://tools-static.wmflabs.org/magnustools/resources/vue/' ,
	loadModules : function ( modules , callback ) {
		var me = this ;
		var cnt = 0 ;
		$.each ( modules , function ( dummy , module ) {
			cnt++ ;
			me.loadModule ( module , function(){
				if ( --cnt == 0 ) callback() ;
			} ) ;
		} ) ;
	} ,
	loadModule ( module , callback ) {
		var me = this ;
		var id = me.template_container_base_id + '-' + module ;
		if ( $('#'+id).length > 0 ) return callback() ;
		$('body').append($("<div id='"+id+"'>"));
		var url = me.modules_base_url+module+'.html' ;
		$('#'+id).load(url,function(){ callback() }) ;
	}
}
