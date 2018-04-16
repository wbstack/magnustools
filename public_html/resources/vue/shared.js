'use strict';

// ENFORCE HTTPS
if (location.protocol != 'https:') location.href = 'https:' + window.location.href.substring(window.location.protocol.length);

let vue_components = {
	toolname : window.location.pathname.replace(/(\/|\.php|\.html{0,1})+$/,'').replace(/^.*\//,'') , // Guessing tool name, override if necessary!
	components : {} ,
	template_container_base_id : 'vue_component_templates' ,
	components_base_url : 'https://tools-static.wmflabs.org/magnustools/resources/vue/' ,
	loadComponents : function ( components ) {
		return Promise.all ( components.map ( component => this.loadComponent(component) ) ) ;
	} ,
	getComponentID ( component ) {
		if ( typeof this.components[component] != 'undefined' ) return this.components[component] ;
		this.components[component] = this.template_container_base_id + '-' + Object.keys(this.components).length ;
		return this.components[component] ;
	} ,
	getComponentURL ( component ) {
		return  /^(http:|https:|\/|\.)/.test(component) || /\.html$/.test(component) ? component : this.components_base_url+component+'.html' ;
	} ,
	loadComponent ( component ) {
		let id = this.getComponentID ( component ) ;
		if ( $('#'+id).length > 0 ) return Promise.resolve() ; // Already loaded/loading
		$('body').append($("<div>").attr({id:id}).css({display:'none'}));
		return fetch ( this.getComponentURL(component) )
			.then ( (response) => response.text() )
			.then ( (html) => $('#'+id).html(html) )
	}
}
