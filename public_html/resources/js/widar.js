function WiDaR ( callback ) {
	
	this.is_logged_in = false ;
	this.api = '/widar/index.php' ;
	this.userinfo = {} ;
	
	this.isLoggedIn = function () {
		return this.is_logged_in ;
	}
	
	this.getInfo = function () {
		var me = this ;
		$.get ( me.api , {
			action:'get_rights',
			botmode:1
		} , function ( d ) {
			me.is_logged_in = false ;
			me.userinfo = {} ;
			if ( typeof (((d||{}).result||{}).query||{}).userinfo == 'undefined' ) {
				callback() ;
				return ;
			}
			me.userinfo = d.result.query.userinfo ;
			if ( typeof me.userinfo.name != 'undefined' ) me.is_logged_in = true ;
			callback() ;
		} , 'json' ) ;
	}
	
	this.getLoginLink = function ( text ) {
		var h = "<a target='_blank' href='/widar/index.php?action=authorize'>" + text + "</a>" ;
		return h ;
	}
	
	this.getUserName = function () {
		if ( !this.isLoggedIn() ) return ;
		return this.userinfo.name ;
	}
	
	this.genericAction = function ( o , callback ) {
		var me = this ;
		$.get ( me.api , {
			action:'generic',
			json:JSON.stringify(o) ,
			botmode:1
		} , function ( d ) {
			if ( typeof callback != 'undefined' ) callback ( d ) ;
		} , 'json' ) ;
	}
	
	
	this.getInfo() ;
}
