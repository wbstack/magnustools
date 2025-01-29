'use strict';

let router ;
let app ;
let wd = new WikiData() ;
let config = {} ;
var widar_api_url ;

$(document).ready ( function () {
	widar_api_url = 'https://magnustools.toolforge.org/buggregator/api.php' ;
	vue_components.toolname = 'buggregator' ;
	Promise.all ( [
		vue_components.loadComponents ( ['wd-link','tool-translate','tool-navbar','typeahead-search','widar',
			'vue_components/tool-picker.html',
			'vue_components/issue-list.html',
			] ) ,
		new Promise(function(resolve, reject) {
			$.get ( './api.php' , {action:'get_config'} , function(d) {
				config = d.data ;
				resolve();
			} ) ;
		} )
	] )	.then ( () => {
			wd_link_wd = wd ;
			const routes = [
			  { path: '/', component: IssueList , props:true },
			] ;
			router = new VueRouter({routes}) ;
			app = new Vue ( { router } ) .$mount('#app') ;
		} ) ;

	// Logging
	//$.getJSON ( 'https://tools.wmflabs.org/magnustools/logger.php?tool=picturesque&method=loaded&callback=?' , function(j){} ) ;

} ) ;
