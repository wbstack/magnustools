// ENFORCE HTTPS
if (location.protocol != 'https:') location.href = 'https:' + window.location.href.substring(window.location.protocol.length);

var vue_components = {
	template_container_base_id : 'vue_component_templates' ,
	components_base_url : 'https://tools-static.wmflabs.org/magnustools/resources/vue/' ,
	loadComponents : function ( components , callback ) {
		var me = this ;
		var cnt = 0 ;
		$.each ( components , function ( dummy , component ) {
			cnt++ ;
			me.loadComponent ( component , function(){
				if ( --cnt == 0 ) callback() ;
			} ) ;
		} ) ;
	} ,
	loadComponent ( component , callback ) {
		var me = this ;
		var id = me.template_container_base_id + '-' + component ;
		if ( $('#'+id).length > 0 ) return callback() ;
		$('body').append($("<div id='"+id+"' style='display:none'>"));
		var url = me.components_base_url+component+'.html' ;
		$('#'+id).load(url,function(){ callback() }) ;
	}
}
