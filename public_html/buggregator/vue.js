'use strict';

let router ;
let app ;
let wd = new WikiData() ;
let config = {
	status:['OPEN','CLOSED'],
	site:['WIKI','WIKIDATA','GITHUB','BITBUCKET'],
	priority:['HIGH','NORMAL','LOW'],
} ;

$(document).ready ( function () {
	vue_components.toolname = 'buggregator' ;
	Promise.all ( [
		vue_components.loadComponents ( ['wd-link','tool-translate','tool-navbar','typeahead-search',
			'vue_components/issue-list.html',
			] ) ,
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
