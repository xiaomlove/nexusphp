//** All Levels Navigational Menu- (c) Dynamic Drive DHTML code library: http://www.dynamicdrive.com
//** Script Download/ instructions page: http://www.dynamicdrive.com/dynamicindex1/ddlevelsmenu/
//** Usage Terms: http://www.dynamicdrive.com/notice.htm

//** Current version: 4.0 See changelog.txt for details

if (typeof dd_domreadycheck=="undefined") //global variable to detect if DOM is ready
	var dd_domreadycheck=false

var ddlevelsmenu={
mql: (window.matchMedia)? window.matchMedia("screen and (max-width: 700px)") : {matches:false, addListener: function(){}}, // CSS media query to switch to mobile menu when matched
enableshim: true, //enable IFRAME shim to prevent drop down menus from being hidden below SELECT or FLASH elements? (tip: disable if not in use, for efficiency)

arrowpointers:{
	downarrow: ["ddlevelsfiles/arrow-down.gif", 11,7], //[path_to_down_arrow, arrowwidth, arrowheight]
	rightarrow: ["ddlevelsfiles/arrow-right.gif", 12,12], //[path_to_right_arrow, arrowwidth, arrowheight]
	backarrow: ["ddlevelsfiles/left.gif"], //[path_to_back_arrow, arrowwidth, arrowheight]
	showarrow: {toplevel: true, sublevel: true} //Show arrow images on top level items and sub level items, respectively?
},
hideinterval: 200, //delay in milliseconds before entire menu disappears onmouseout.
effects: {enableswipe: true, enableslide: true, enablefade: true, duration: 200},
httpsiframesrc: "blank.htm", //If menu is run on a secure (https) page, the IFRAME shim feature used by the script should point to an *blank* page *within* the secure area to prevent an IE security prompt. Specify full URL to that page on your server (leave as is if not applicable).

///No need to edit beyond here////////////////////

topmenuids: [], //array containing ids of all the primary menus on the page
menuclone: {}, //object containing a clone of each top level menu plus its sub ULs (for mobile menu sake)
topitems: {}, //object array containing all top menu item links
subuls: {}, //object array containing all ULs
lastactivesubul: {}, //object object containing info for last mouse out menu item's UL
topitemsindex: -1,
ulindex: -1,
hidetimers: {}, //object array timer
shimadded: false,
nonFF: !/Firefox[\/\s](\d+\.\d+)/.test(navigator.userAgent), //detect non FF browsers
ismobile:navigator.userAgent.match(/(iPad)|(iPhone)|(iPod)|(android)|(webOS)/i) != null, //boolean check for popular mobile browsers
mobilezindex: 1000,

getoffset:function(what, offsettype){
	return (what.offsetParent)? what[offsettype]+this.getoffset(what.offsetParent, offsettype) : what[offsettype]
},

getoffsetof:function(el){
	el._offsets={left:this.getoffset(el, "offsetLeft"), top:this.getoffset(el, "offsetTop")}
},

getwindowsize:function(){
	this.docwidth=window.innerWidth? window.innerWidth-10 : this.standardbody.clientWidth-10
	this.docheight=window.innerHeight? window.innerHeight-15 : this.standardbody.clientHeight-18
},

gettopitemsdimensions:function(){
	for (var m=0; m<this.topmenuids.length; m++){
		var topmenuid=this.topmenuids[m]
		for (var i=0; i<this.topitems[topmenuid].length; i++){
			var header=this.topitems[topmenuid][i]
			var submenu=document.getElementById(header.getAttribute('rel'))
			header._dimensions={w:header.offsetWidth, h:header.offsetHeight, submenuw:submenu.offsetWidth, submenuh:submenu.offsetHeight}
		}
	}
},

isContained:function(m, e){
	var e=window.event || e
	var c=e.relatedTarget || ((e.type=="mouseover")? e.fromElement : e.toElement)
	while (c && c!=m)try {c=c.parentNode} catch(e){c=m}
	if (c==m)
		return true
	else
		return false
},

addpointer:function(target, imgclass, imginfo, BeforeorAfter){
	var pointer=document.createElement("img")
	pointer.src=imginfo[0]
	pointer.style.width=imginfo[1]+"px"
	pointer.style.height=imginfo[2]+"px"
	if(imgclass=="rightarrowpointer"){
		pointer.style.left=target.offsetWidth-imginfo[2]-2+"px"
	}
	pointer.className=imgclass
	var target_firstEl=target.childNodes[target.firstChild.nodeType!=1? 1 : 0] //see if the first child element within A is a SPAN (found in sliding doors technique)
	if (target_firstEl && target_firstEl.tagName=="SPAN"){
		target=target_firstEl //arrow should be added inside this SPAN instead if found
	}
	if (BeforeorAfter=="before")
		target.insertBefore(pointer, target.firstChild)
	else
		target.appendChild(pointer)
},

css:function(el, targetclass, action){
	var needle=new RegExp("(^|\\s+)"+targetclass+"($|\\s+)", "ig")
	if (action=="check")
		return needle.test(el.className)
	else if (action=="remove")
		el.className=el.className.replace(needle, "")
	else if (action=="add" && !needle.test(el.className))
		el.className+=" "+targetclass
},

addshimmy:function(target){
	var shim=(!window.opera)? document.createElement("iframe") : document.createElement("div") //Opera 9.24 doesnt seem to support transparent IFRAMEs
	shim.className="ddiframeshim"
	shim.setAttribute("src", location.protocol=="https:"? this.httpsiframesrc : "about:blank")
	shim.setAttribute("frameborder", "0")
	target.appendChild(shim)
	try{
		shim.style.filter='progid:DXImageTransform.Microsoft.Alpha(style=0,opacity=0)'
	}
	catch(e){}
	return shim
},

positionshim:function(header, submenu, dir, scrollX, scrollY){
	if (header._istoplevel){
		var scrollY=window.pageYOffset? window.pageYOffset : this.standardbody.scrollTop
		var topgap=header._offsets.top-scrollY
		var bottomgap=scrollY+this.docheight-header._offsets.top-header._dimensions.h
		if (topgap>0){
			this.shimmy.topshim.style.left=scrollX+"px"
			this.shimmy.topshim.style.top=scrollY+"px"
			this.shimmy.topshim.style.width="99%"
			this.shimmy.topshim.style.height=topgap+"px" //distance from top window edge to top of menu item
		}
		if (bottomgap>0){
			this.shimmy.bottomshim.style.left=scrollX+"px"
			this.shimmy.bottomshim.style.top=header._offsets.top + header._dimensions.h +"px"
			this.shimmy.bottomshim.style.width="99%"
			this.shimmy.bottomshim.style.height=bottomgap+"px" //distance from bottom of menu item to bottom window edge
		}
	}
},

hideshim:function(){
	this.shimmy.topshim.style.width=this.shimmy.bottomshim.style.width=0
	this.shimmy.topshim.style.height=this.shimmy.bottomshim.style.height=0
},

getoffset:function(what, offsettype){
	return (what.offsetParent)? what[offsettype]+this.getoffset(what.offsetParent, offsettype) : what[offsettype]
},


buildmenu:function(mainmenuid, header, submenu, submenupos, istoplevel, dir){
	header._master=mainmenuid //Indicate which top menu this header is associated with
	header._pos=submenupos //Indicate pos of sub menu this header is associated with
	header._istoplevel=istoplevel
	if (istoplevel){
		this.addEvent(header, function(e){
		ddlevelsmenu.hidemenu(ddlevelsmenu.subuls[this._master][parseInt(this._pos)].parentNode)
		}, "click")
	}
	this.subuls[mainmenuid][submenupos]=submenu
	header._dimensions={w:header.offsetWidth, h:header.offsetHeight, submenuw:submenu.offsetWidth, submenuh:submenu.offsetHeight}
	this.getoffsetof(header)
	submenu.parentNode.style.left=0
	submenu.parentNode.style.top=0
	submenu.parentNode.style.visibility="hidden"
	submenu.style.visibility="hidden"
	this.addEvent(header, function(e){ //mouseover event
		if (ddlevelsmenu.ismobile || !ddlevelsmenu.isContained(this, e)){
			var submenu=ddlevelsmenu.subuls[this._master][parseInt(this._pos)]
			if (this._istoplevel){
				ddlevelsmenu.css(this, "selected", "add")
				clearTimeout(ddlevelsmenu.hidetimers[this._master][this._pos])
			}
			ddlevelsmenu.getoffsetof(header)
			var scrollX=window.pageXOffset? window.pageXOffset : ddlevelsmenu.standardbody.scrollLeft
			var scrollY=window.pageYOffset? window.pageYOffset : ddlevelsmenu.standardbody.scrollTop
			var submenurightedge=this._offsets.left + this._dimensions.submenuw + (this._istoplevel && dir=="topbar"? 0 : this._dimensions.w)
			var submenubottomedge=this._offsets.top + this._dimensions.submenuh
			//Sub menu starting left position
			var menuleft=(this._istoplevel? this._offsets.left + (dir=="sidebar"? this._dimensions.w : 0) : this._dimensions.w)
			if (submenurightedge-scrollX>ddlevelsmenu.docwidth){ // not enough room to drop right?
				menuleft+= -this._dimensions.submenuw + (this._istoplevel && dir=="topbar" ? this._dimensions.w : -this._dimensions.w)
				if ( !(this._istoplevel && dir=="topbar") && (this._offsets.left - this._dimensions.submenuw) < scrollX ){ // if no room to drop left either
					menuleft = 0
				}
			}
			submenu.parentNode.style.left=menuleft+"px"
			//Sub menu starting top position
			var menutop=(this._istoplevel? this._offsets.top + (dir=="sidebar"? 0 : this._dimensions.h) : this.offsetTop)
			if (submenubottomedge-scrollY>ddlevelsmenu.docheight){ //no room downwards?
				if (this._dimensions.submenuh<this._offsets.top+(dir=="sidebar"? this._dimensions.h : 0)-scrollY){ //move up?
					menutop+= - this._dimensions.submenuh + (this._istoplevel && dir=="topbar"? -this._dimensions.h : this._dimensions.h)
				}
				else{ //top of window edge
					menutop+= -(this._offsets.top-scrollY) + (this._istoplevel && dir=="topbar"? -this._dimensions.h : 0)
				}
			}
			submenu.parentNode.style.top=menutop+"px"
			if (ddlevelsmenu.enableshim && (ddlevelsmenu.effects.enableswipe==false || ddlevelsmenu.nonFF)){ //apply shim immediately only if animation is turned off, or if on, in non FF2.x browsers
				ddlevelsmenu.positionshim(header, submenu, dir, scrollX, scrollY)
			}
			else{
				submenu.FFscrollInfo={x:scrollX, y:scrollY}
			}
			ddlevelsmenu.showmenu(header, submenu, dir)
			if (!ddlevelsmenu.ismobile){
				if (e.preventDefault)
					e.preventDefault()
				if (e.stopPropagation)
					e.stopPropagation()
			}
			else{ //if is mobile
				if (header._istoplevel || e.target.parentNode.getElementsByTagName('ul').length>0){ //if user clicks on a header (instead of a menu item)
					e.preventDefault()
					e.stopPropagation()
				}
			}
		}
	}, (this.ismobile)? "click" : "mouseover")
	this.addEvent(header, function(e){ //mouseout event
		var submenu=ddlevelsmenu.subuls[this._master][parseInt(this._pos)]
		if (this._istoplevel){
			if (!ddlevelsmenu.isContained(this, e) && !ddlevelsmenu.isContained(submenu.parentNode, e)) //hide drop down div if mouse moves out of menu bar item but not into drop down div itself
				ddlevelsmenu.hidemenu(submenu.parentNode)
		}
		else if (!this._istoplevel && !ddlevelsmenu.isContained(this, e)){
			ddlevelsmenu.hidemenu(submenu.parentNode)
		}

	}, "mouseout")
},

buildmobilemenu:function(menuid, ul, dir){
	function flattenul(ul, cloneulBol, callback, finalcall){
		var callback = callback || function(){}
		var finalcall = finalcall || function(){}
		var docfrag = document.createDocumentFragment()
		var targetul = cloneulBol? ul.cloneNode(true) : ul
		var subuls = targetul.getElementsByTagName('ul')
		var subulscount = subuls.length
		for (var i=subulscount-1; i>=0; i--){
			var subul = subuls[i]
			var header = subuls[i].parentNode
			docfrag.appendChild( subuls[i] )
			callback(i, header, subul)
		}
		docfrag.appendChild( targetul )
		finalcall(targetul)
		return docfrag
	}

	if (!document.getElementById(menuid+'-mobile')){ // if mobile menu outermost container not created yet
		var mobilecontainer = document.createElement('nav')
		mobilecontainer.setAttribute('id', menuid+'-mobile')
		mobilecontainer.className = 'mobilelevelsmenu'
		//mobilecontainer.style.display = 'none'
		document.body.appendChild(mobilecontainer)
		var mobiletoggle = document.getElementById(menuid+'-mobiletoggle')
		if (mobiletoggle){
			this.addEvent(mobiletoggle, function(e){
				ddlevelsmenu.togglemobilemenu(menuid)
				e.stopPropagation()
				e.preventDefault()
			}, "click")
		}
	}
	else{ // else, just reference mobile menu
		var mobilecontainer = document.getElementById(menuid+'-mobile')
	}

	var flattened = flattenul(ul, false,
		function(i, header, subul){ // loop through header LIs and sub ULs
			var rightarrow = document.createElement('img')
			rightarrow.src = ddlevelsmenu.arrowpointers.rightarrow[0]
			rightarrow.className = "rightarrowpointer"
			header.getElementsByTagName('a')[0].appendChild(rightarrow)
			header._submenuref = subul
			subul.className = 'submenu'
			var breadcrumb = document.createElement('li')
			breadcrumb.className = "breadcrumb"
			breadcrumb.innerHTML = '<img src="' + ddlevelsmenu.arrowpointers.backarrow[0] + '" class="backarrowpointer" /> ' + header.getElementsByTagName('a')[0].firstChild.nodeValue
			breadcrumb._headerref = header
			subul.insertBefore(breadcrumb, subul.getElementsByTagName('li')[0])
			ddlevelsmenu.addEvent(header, function(e){
				var headermenu = this.parentNode
				var submenu = this._submenuref
				ddlevelsmenu.animatemobilesubmenu(submenu, '100%', 0)
				e.stopPropagation()
				e.preventDefault()
			}, "click")
			ddlevelsmenu.addEvent(breadcrumb, function(e){
				var parentmenu = this._headerref.parentNode
				ddlevelsmenu.animatemobilesubmenu(parentmenu, '-100%', 0)
				e.stopPropagation()
				e.preventDefault()
			}, "click")
		},
		function(topul){
			topul.style.zIndex = ddlevelsmenu.mobilezindex++
		}
	)
	mobilecontainer.appendChild(flattened)
},

setopacity:function(el, value){
	el.style.opacity=value
	if (typeof el.style.opacity!="string"){ //if it's not a string (ie: number instead), it means property not supported
		el.style.MozOpacity=value
		try{
			if (el.filters){
				el.style.filter="progid:DXImageTransform.Microsoft.alpha(opacity="+ value*100 +")"
			}
		} catch(e){}
	}
},

animatemobilesubmenu:function(targetul, beforeleft, afterleft){
		// See http://stackoverflow.com/questions/18564942/clean-way-to-programmatically-use-css-transitions-from-js/31862081#31862081
		this.css(targetul, 'notransition', 'add')
		targetul.style.zIndex = ddlevelsmenu.mobilezindex++
		targetul.style.left = beforeleft
		window.getComputedStyle(targetul).left // force layout reflow
		this.css(targetul, 'notransition', 'remove')
		targetul.style.left = afterleft	
},


togglemobilemenu:function(mainmenuid, xoffset, yoffset){
	var toggler = document.getElementById(mainmenuid + '-mobiletoggle')
	var mobilemenu = document.getElementById(mainmenuid + '-mobile')
	if (mobilemenu){
		if (!ddlevelsmenu.css(mobilemenu, 'open', 'check')){			
			ddlevelsmenu.css(mobilemenu, 'open', 'add')
			if (toggler)
				ddlevelsmenu.css(toggler, 'open', 'add')
		}
		else{
			ddlevelsmenu.css(mobilemenu, 'open', 'remove')
			if (toggler)
				ddlevelsmenu.css(toggler, 'open', 'remove')
		}
	}
	return false
},

showmenu:function(header, submenu, dir){
	if (this.effects.enableswipe || this.effects.enablefade){
		if (this.effects.enableswipe){
			var endpoint=(header._istoplevel && dir=="topbar")? header._dimensions.submenuh : header._dimensions.submenuw
			submenu.parentNode.style.width=submenu.parentNode.style.height=0
			submenu.parentNode.style.overflow="hidden"
		}
		if (this.effects.enablefade){
			submenu.parentNode.style.width=submenu.offsetWidth+"px"
			submenu.parentNode.style.height=submenu.offsetHeight+"px"
			this.setopacity(submenu.parentNode, 0) //set opacity to 0 so menu appears hidden initially
		}
		submenu._curanimatedegree=0
		submenu.parentNode.style.visibility="visible"
		submenu.style.visibility="visible"
		clearInterval(submenu._animatetimer)
		submenu._starttime=new Date().getTime() //get time just before animation is run
		submenu._animatetimer=setInterval(function(){ddlevelsmenu.revealmenu(header, submenu, endpoint, dir)}, 10)
	}
	else{
		submenu.parentNode.style.visibility="visible"
		submenu.style.visibility="visible"
	}
},

revealmenu:function(header, submenu, endpoint, dir){
	var elapsed=new Date().getTime()-submenu._starttime //get time animation has run
	if (elapsed<this.effects.duration){
		if (this.effects.enableswipe){
			if (submenu._curanimatedegree==0){ //reset either width or height of sub menu to "auto" when animation begins
				submenu.parentNode.style[header._istoplevel && dir=="topbar"? "width" : "height"]=(header._istoplevel && dir=="topbar"? submenu.offsetWidth : submenu.offsetHeight)+"px"
			}
			submenu.parentNode.style[header._istoplevel && dir=="topbar"? "height" : "width"]=(submenu._curanimatedegree*endpoint)+"px"
			if (this.effects.enableslide){
				submenu.style[header._istoplevel && dir=="topbar"? "top" : "left"]=Math.floor((submenu._curanimatedegree-1)*endpoint)+"px"
			}
		}
		if (this.effects.enablefade){
			this.setopacity(submenu.parentNode, submenu._curanimatedegree)
		}
	}
	else{
		clearInterval(submenu._animatetimer)
		if (this.effects.enableswipe){
			submenu.parentNode.style.width=submenu.offsetWidth+"px"
			submenu.parentNode.style.height=submenu.offsetHeight+"px"
			submenu.parentNode.style.overflow="visible"
			if (this.effects.enableslide){
				submenu.style.top=0;
				submenu.style.left=0;
			}
		}
		if (this.effects.enablefade){
			this.setopacity(submenu.parentNode, 1)
			submenu.parentNode.style.filter=""
		}
		if (this.enableshim && submenu.FFscrollInfo) //if this is FF browser (meaning shim hasn't been applied yet
			this.positionshim(header, submenu, dir, submenu.FFscrollInfo.x, submenu.FFscrollInfo.y)
	}
	submenu._curanimatedegree=(1-Math.cos((elapsed/this.effects.duration)*Math.PI)) / 2
},

hidemenu:function(submenu){
	if (typeof submenu._pos!="undefined"){ //if submenu is outermost DIV drop down menu
		this.css(this.topitems[submenu._master][parseInt(submenu._pos)], "selected", "remove")
		if (this.enableshim)
			this.hideshim()
	}
	clearInterval(submenu.firstChild._animatetimer)
	submenu.style.left=0
	submenu.style.top="-1000px"
	submenu.style.visibility="hidden"
	submenu.firstChild.style.visibility="hidden"
},


addEvent:function(target, functionref, tasktype) {
	if (target.addEventListener)
		target.addEventListener(tasktype, functionref, false);
	else if (target.attachEvent)
		target.attachEvent('on'+tasktype, function(){return functionref.call(target, window.event)});
},

domready:function(functionref){ //based on code from the jQuery library
	if (dd_domreadycheck){
		functionref()
		return
	}
	// Mozilla, Opera and webkit nightlies currently support this event
	if (document.addEventListener) {
		// Use the handy event callback
		document.addEventListener("DOMContentLoaded", function(){
			document.removeEventListener("DOMContentLoaded", arguments.callee, false )
			functionref();
			dd_domreadycheck=true
		}, false )
	}
	else if (document.attachEvent){
		// If IE and not an iframe
		// continually check to see if the document is ready
		if ( document.documentElement.doScroll && window == window.top) (function(){
			if (dd_domreadycheck){
				functionref()
				return
			}
			try{
				// If IE is used, use the trick by Diego Perini
				// http://javascript.nwbox.com/IEContentLoaded/
				document.documentElement.doScroll("left")
			}catch(error){
				setTimeout( arguments.callee, 0)
				return;
			}
			//and execute any waiting functions
			functionref();
			dd_domreadycheck=true
		})();
	}
	if (document.attachEvent && parent.length>0) //account for page being in IFRAME, in which above doesn't fire in IE
		this.addEvent(window, function(){functionref()}, "load");
},


init:function(mainmenuid, dir, mobile){

	var desktopmenu = document.getElementById(mainmenuid)
	var mobilemenu = document.getElementById(mainmenuid + '-mobile')
	var toggler = document.getElementById(mainmenuid + '-mobiletoggle')

	if (typeof this.menuclone[mainmenuid] != "object"){ // if sub menus haven't been cloned yet (for sake of mobile menu generation)
		this.menuclone[mainmenuid] = desktopmenu.getElementsByTagName("ul")[0].cloneNode(true) // clone and get UL inside top menu container
		var menuclone = this.menuclone[mainmenuid]
		var alllinks=menuclone.getElementsByTagName("a")
		for (var i=0; i<alllinks.length; i++){
			if (alllinks[i].getAttribute('rel')){
				var dropul=document.getElementById(alllinks[i].getAttribute('rel'))
				var dropulClone = dropul.cloneNode(true)
				dropulClone.removeAttribute('id')
				dropulClone.removeAttribute('class')
				alllinks[i].parentNode.appendChild(dropulClone)
			}
		}
		this.addEvent(document, function(e){ // hide mobile menu on click of document
			var mobilemenu = document.getElementById(mainmenuid + '-mobile')
			var toggler = document.getElementById(mainmenuid + '-mobiletoggle')
			if (mobilemenu)
				ddlevelsmenu.css(mobilemenu, 'open', 'remove')
			if (toggler)
				toggler.className = toggler.className.replace(/\s*open/g, "")
		}, 'click')
	}

	if (mobile){ // if mobile mode (media query match)
		if (!mobilemenu){ // if mobile menu mode and mobile menu not generated yet
			this.buildmobilemenu(mainmenuid, this.menuclone[mainmenuid], dir)
		}
		if (desktopmenu.style.display != "none")
			desktopmenu.style.display = "none"
		if (toggler)
			toggler.style.display = "block"
		return
	}
	else{ // if regular menu mode but it's already generated, exit
		desktopmenu.style.display = "block"
		if (mobilemenu)
			ddlevelsmenu.css(mobilemenu, 'open', 'remove')
		if (toggler){
			toggler.className = toggler.className.replace(/\s*open/g, "")
			toggler.style.display = "none"
		}
		if (typeof this.topitems[mainmenuid] == "object"){
			return
		}
	}
	this.standardbody=(document.compatMode=="CSS1Compat")? document.documentElement : document.body
	this.topitemsindex=-1
	this.ulindex=-1
	this.topmenuids.push(mainmenuid)
	this.topitems[mainmenuid]=[] //declare array on object
	this.subuls[mainmenuid]=[] //declare array on object
	this.hidetimers[mainmenuid]=[] //declare hide entire menu timer
	this.enableshim = (this.ismobile)? false : this.enableshim //disable shim if mobile browser
	if (this.enableshim && !this.shimadded){
		this.shimmy={}
		this.shimmy.topshim=this.addshimmy(document.body) //create top iframe shim obj
		this.shimmy.bottomshim=this.addshimmy(document.body) //create bottom iframe shim obj
		this.shimadded=true
	}
	var menubar=document.getElementById(mainmenuid)
	var alllinks=menubar.getElementsByTagName("a")
	var shelldivs=[]
	this.getwindowsize()
	for (var i=0; i<alllinks.length; i++){
		if (alllinks[i].getAttribute('rel')){
			this.topitemsindex++
			this.ulindex++
			var menuitem=alllinks[i]
			this.topitems[mainmenuid][this.topitemsindex]=menuitem //store ref to main menu links
			var dropul=document.getElementById(menuitem.getAttribute('rel'))
			var shelldiv=document.createElement("div") // create DIV which will contain the UL
			shelldiv.className="ddsubmenustyle"
			dropul.removeAttribute("class")
			shelldiv.appendChild(dropul)
			document.body.appendChild(shelldiv) //move main DIVs to end of document
			shelldivs.push(shelldiv)
			shelldiv.style.zIndex=2000 //give drop down menus a high z-index
			shelldiv._master=mainmenuid  //Indicate which main menu this main DIV is associated with
			shelldiv._pos=this.topitemsindex //Indicate which main menu item this main DIV is associated with
			this.addEvent(shelldiv, function(e){  // 3.03 code
				e.stopPropagation()
				e.cancelBubble = true
			}, "touchstart")
			this.addEvent(shelldiv, function(e){  // 3.03 code
				ddlevelsmenu.hidemenu(this)
			}, "click")			
			var arrowclass=(dir=="sidebar")? "rightarrowpointer" : "downarrowpointer"
			var arrowpointer=(dir=="sidebar")? this.arrowpointers.rightarrow : this.arrowpointers.downarrow
			if (this.arrowpointers.showarrow.toplevel)
				this.addpointer(menuitem, arrowclass, arrowpointer, (dir=="sidebar")? "before" : "after")
			this.buildmenu(mainmenuid, menuitem, dropul, this.ulindex, true, dir) //build top level menu
			shelldiv.onmouseover=function(){
				clearTimeout(ddlevelsmenu.hidetimers[this._master][this._pos])
			}
			this.addEvent(shelldiv, function(e){ //hide menu if mouse moves out of main DIV element into open space
				if (!ddlevelsmenu.isContained(this, e) && !ddlevelsmenu.isContained(ddlevelsmenu.topitems[this._master][parseInt(this._pos)], e)){
					var dropul=this
					if (ddlevelsmenu.enableshim)
						ddlevelsmenu.hideshim()
					ddlevelsmenu.hidetimers[this._master][this._pos]=setTimeout(function(){
						ddlevelsmenu.hidemenu(dropul)
					}, ddlevelsmenu.hideinterval)
				}
			}, "mouseout")
			var subuls=dropul.getElementsByTagName("ul")
			for (var c=0; c<subuls.length; c++){
				this.ulindex++
				var parentli=subuls[c].parentNode
				var subshell=document.createElement("div")
				subshell.appendChild(subuls[c])
				parentli.appendChild(subshell)
				if (this.arrowpointers.showarrow.sublevel)
					this.addpointer(parentli.getElementsByTagName("a")[0], "rightarrowpointer", this.arrowpointers.rightarrow, "before")
				this.buildmenu(mainmenuid, parentli, subuls[c], this.ulindex, false, dir) //build sub level menus
			}
		}
	} //end for loop
	this.addEvent(window, function(){ddlevelsmenu.getwindowsize(); ddlevelsmenu.gettopitemsdimensions()}, "resize")
	if (this.ismobile){  // 3.03 code
		this.addEvent(document, function(e){
			for (var i=0; i<shelldivs.length; i++){
				ddlevelsmenu.hidemenu(shelldivs[i])
			}
		}, 'touchstart')
	}
},

setup:function(mainmenuid, dir){
	this.domready(function(){ddlevelsmenu.init(mainmenuid, dir, ddlevelsmenu.mql.matches)})
	this.mql.addListener(function(){
		ddlevelsmenu.domready(function(){ddlevelsmenu.init(mainmenuid, dir, ddlevelsmenu.mql.matches)})
	})
}

}