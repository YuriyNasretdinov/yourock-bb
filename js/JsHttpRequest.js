/**
 * JsHttpRequest: JavaScript "AJAX" data loader (script-xml support only!)
 * Minimized version: see debug directory for the complete one.
 *
 * @license LGPL
 * @author Dmitry Koterov, http://en.dklab.ru/lib/JsHttpRequest/
 * @version 5.x $Id$
 */
function JsHttpRequest(){
var t=this;
t.onreadystatechange=null;
t.readyState=0;
t.responseText=null;
t.responseXML=null;
t.status=200;
t.statusText="OK";
t.responseJS=null;
t.caching=false;
t.loader=null;
t.session_name="PHPSESSID";
t._ldObj=null;
t._reqHeaders=[];
t._openArgs=null;
t._errors={inv_form_el:"Invalid FORM element detected: name=%, tag=%",must_be_single_el:"If used, <form> must be a single HTML element in the list.",js_invalid:"JavaScript code generated by backend is invalid!\n%",url_too_long:"Cannot use so long query with GET request (URL is larger than % bytes)",unk_loader:"Unknown loader: %",no_loaders:"No loaders registered at all, please check JsHttpRequest.LOADERS array",no_loader_matched:"Cannot find a loader which may process the request. Notices are:\n%"};
t.abort=function(){
with(this){
if(_ldObj&&_ldObj.abort){
_ldObj.abort();
}
_cleanup();
if(readyState==0){
return;
}
if(readyState==1&&!_ldObj){
readyState=0;
return;
}
_changeReadyState(4,true);
}
};
t.open=function(_2,_3,_4,_5,_6){
with(this){
if(_3.match(/^((\w+)\.)?(GET|POST)\s+(.*)/i)){
this.loader=RegExp.$2?RegExp.$2:null;
_2=RegExp.$3;
_3=RegExp.$4;
}
try{
if(document.location.search.match(new RegExp("[&?]"+session_name+"=([^&?]*)"))||document.cookie.match(new RegExp("(?:;|^)\\s*"+session_name+"=([^;]*)"))){
_3+=(_3.indexOf("?")>=0?"&":"?")+session_name+"="+this.escape(RegExp.$1);
}
}
catch(e){
}
_openArgs={method:(_2||"").toUpperCase(),url:_3,asyncFlag:_4,username:_5!=null?_5:"",password:_6!=null?_6:""};
_ldObj=null;
_changeReadyState(1,true);
return true;
}
};
t.send=function(_7){
if(!this.readyState){
return;
}
this._changeReadyState(1,true);
this._ldObj=null;
var _8=[];
var _9=[];
if(!this._hash2query(_7,null,_8,_9)){
return;
}
var _a=null;
if(this.caching&&!_9.length){
_a=this._openArgs.username+":"+this._openArgs.password+"@"+this._openArgs.url+"|"+_8+"#"+this._openArgs.method;
var _b=JsHttpRequest.CACHE[_a];
if(_b){
this._dataReady(_b[0],_b[1]);
return false;
}
}
var _c=(this.loader||"").toLowerCase();
if(_c&&!JsHttpRequest.LOADERS[_c]){
return this._error("unk_loader",_c);
}
var _d=[];
var _e=JsHttpRequest.LOADERS;
for(var _f in _e){
var ldr=_e[_f].loader;
if(!ldr){
continue;
}
if(_c&&_f!=_c){
continue;
}
var _11=new ldr(this);
JsHttpRequest.extend(_11,this._openArgs);
JsHttpRequest.extend(_11,{queryText:_8.join("&"),queryElem:_9,id:(new Date().getTime())+""+JsHttpRequest.COUNT++,hash:_a,span:null});
var _12=_11.load();
if(!_12){
this._ldObj=_11;
JsHttpRequest.PENDING[_11.id]=this;
return true;
}
if(!_c){
_d[_d.length]="- "+_f.toUpperCase()+": "+this._l(_12);
}else{
return this._error(_12);
}
}
return _f?this._error("no_loader_matched",_d.join("\n")):this._error("no_loaders");
};
t.getAllResponseHeaders=function(){
with(this){
return _ldObj&&_ldObj.getAllResponseHeaders?_ldObj.getAllResponseHeaders():[];
}
};
t.getResponseHeader=function(_13){
with(this){
return _ldObj&&_ldObj.getResponseHeader?_ldObj.getResponseHeader(_13):null;
}
};
t.setRequestHeader=function(_14,_15){
with(this){
_reqHeaders[_reqHeaders.length]=[_14,_15];
}
};
t._dataReady=function(_16,js){
with(this){
if(caching&&_ldObj){
JsHttpRequest.CACHE[_ldObj.hash]=[_16,js];
}
responseText=responseXML=_16;
responseJS=js;
if(js!==null){
status=200;
statusText="OK";
}else{
status=500;
statusText="Internal Server Error";
}
_changeReadyState(2);
_changeReadyState(3);
_changeReadyState(4);
_cleanup();
}
};
t._l=function(_18){
var i=0,p=0,msg=this._errors[_18[0]];
while((p=msg.indexOf("%",p))>=0){
var a=_18[++i]+"";
msg=msg.substring(0,p)+a+msg.substring(p+1,msg.length);
p+=1+a.length;
}
return msg;
};
t._error=function(msg){
msg=this._l(typeof (msg)=="string"?arguments:msg);
msg="JsHttpRequest: "+msg;
if(!window.Error){
throw msg;
}else{
if((new Error(1,"test")).description=="test"){
throw new Error(1,msg);
}else{
throw new Error(msg);
}
}
};
t._hash2query=function(_1e,_1f,_20,_21){
if(_1f==null){
_1f="";
}
if((""+typeof (_1e)).toLowerCase()=="object"){
var _22=false;
if(_1e&&_1e.parentNode&&_1e.parentNode.appendChild&&_1e.tagName&&_1e.tagName.toUpperCase()=="FORM"){
_1e={form:_1e};
}
for(var k in _1e){
var v=_1e[k];
if(v instanceof Function){
continue;
}
var _25=_1f?_1f+"["+this.escape(k)+"]":this.escape(k);
var _26=v&&v.parentNode&&v.parentNode.appendChild&&v.tagName;
if(_26){
var tn=v.tagName.toUpperCase();
if(tn=="FORM"){
_22=true;
}else{
if(tn=="INPUT"||tn=="TEXTAREA"||tn=="SELECT"){
}else{
return this._error("inv_form_el",(v.name||""),v.tagName);
}
}
_21[_21.length]={name:_25,e:v};
}else{
if(v instanceof Object){
this._hash2query(v,_25,_20,_21);
}else{
if(v===null){
continue;
}
if(v===true){
v=1;
}
if(v===false){
v="";
}
_20[_20.length]=_25+"="+this.escape(""+v);
}
}
if(_22&&_21.length>1){
return this._error("must_be_single_el");
}
}
}else{
_20[_20.length]=_1e;
}
return true;
};
t._cleanup=function(){
var _28=this._ldObj;
if(!_28){
return;
}
JsHttpRequest.PENDING[_28.id]=false;
var _29=_28.span;
if(!_29){
return;
}
_28.span=null;
var _2a=function(){
_29.parentNode.removeChild(_29);
};
JsHttpRequest.setTimeout(_2a,50);
};
t._changeReadyState=function(s,_2c){
with(this){
if(_2c){
status=statusText=responseJS=null;
responseText="";
}
readyState=s;
if(onreadystatechange){
onreadystatechange();
}
}
};
t.escape=function(s){
return escape(s).replace(new RegExp("\\+","g"),"%2B");
};
}
JsHttpRequest.COUNT=0;
JsHttpRequest.MAX_URL_LEN=2000;
JsHttpRequest.CACHE={};
JsHttpRequest.PENDING={};
JsHttpRequest.LOADERS={};
JsHttpRequest._dummy=function(){
};
JsHttpRequest.TIMEOUTS={s:window.setTimeout,c:window.clearTimeout};
JsHttpRequest.setTimeout=function(_2e,dt){
window.JsHttpRequest_tmp=JsHttpRequest.TIMEOUTS.s;
if(typeof (_2e)=="string"){
id=window.JsHttpRequest_tmp(_2e,dt);
}else{
var id=null;
var _31=function(){
_2e();
delete JsHttpRequest.TIMEOUTS[id];
};
id=window.JsHttpRequest_tmp(_31,dt);
JsHttpRequest.TIMEOUTS[id]=_31;
}
window.JsHttpRequest_tmp=null;
return id;
};
JsHttpRequest.clearTimeout=function(id){
window.JsHttpRequest_tmp=JsHttpRequest.TIMEOUTS.c;
delete JsHttpRequest.TIMEOUTS[id];
var r=window.JsHttpRequest_tmp(id);
window.JsHttpRequest_tmp=null;
return r;
};
JsHttpRequest.query=function(url,_35,_36,_37){
var req=new this();
req.caching=!_37;
req.onreadystatechange=function(){
if(req.readyState==4){
_36(req.responseJS,req.responseText);
}
};
req.open(null,url,true);
req.send(_35);
};
JsHttpRequest.dataReady=function(d){
var th=this.PENDING[d.id];
delete this.PENDING[d.id];
if(th){
th._dataReady(d.text,d.js);
}else{
if(th!==false){
throw "dataReady(): unknown pending id: "+d.id;
}
}
};
JsHttpRequest.extend=function(_3b,src){
for(var k in src){
_3b[k]=src[k];
}
};
JsHttpRequest.LOADERS.xml={loader:function(req){
JsHttpRequest.extend(req._errors,{xml_no:"Cannot use XMLHttpRequest or ActiveX loader: not supported",xml_no_diffdom:"Cannot use XMLHttpRequest to load data from different domain %",xml_no_headers:"Cannot use XMLHttpRequest loader or ActiveX loader, POST method: headers setting is not supported, needed to work with encodings correctly",xml_no_form_upl:"Cannot use XMLHttpRequest loader: direct form elements using and uploading are not implemented"});
this.load=function(){
if(this.queryElem.length){
return ["xml_no_form_upl"];
}
if(this.url.match(new RegExp("^([a-z]+://[^\\/]+)(.*)","i"))){
if(RegExp.$1.toLowerCase()!=document.location.protocol+"//"+document.location.hostname.toLowerCase()){
return ["xml_no_diffdom",RegExp.$1];
}
}
var xr=null;
if(window.XMLHttpRequest){
try{
xr=new XMLHttpRequest();
}
catch(e){
}
}else{
if(window.ActiveXObject){
try{
xr=new ActiveXObject("Microsoft.XMLHTTP");
}
catch(e){
}
if(!xr){
try{
xr=new ActiveXObject("Msxml2.XMLHTTP");
}
catch(e){
}
}
}
}
if(!xr){
return ["xml_no"];
}
var _46=window.ActiveXObject||xr.setRequestHeader;
if(!this.method){
this.method=_46&&this.queryText.length?"POST":"GET";
}
if(this.method=="GET"){
if(this.queryText){
this.url+=(this.url.indexOf("?")>=0?"&":"?")+this.queryText;
}
this.queryText="";
if(this.url.length>JsHttpRequest.MAX_URL_LEN){
return ["url_too_long",JsHttpRequest.MAX_URL_LEN];
}
}else{
if(this.method=="POST"&&!_46){
return ["xml_no_headers"];
}
}
this.url+=(this.url.indexOf("?")>=0?"&":"?")+"JsHttpRequest="+(req.caching?"0":this.id)+"-xml";
var id=this.id;
xr.onreadystatechange=function(){
if(xr.readyState!=4){
return;
}
xr.onreadystatechange=JsHttpRequest._dummy;
req.status=null;
try{
req.status=xr.status;
req.responseText=xr.responseText;
}
catch(e){
}
if(!req.status){
return;
}
try{
var _48=req.responseText||"{ js: null, text: null }";
eval("JsHttpRequest._tmp = function(id) { var d = "+_48+"; d.id = id; JsHttpRequest.dataReady(d); }");
}
catch(e){
return req._error("js_invalid",req.responseText);
}
JsHttpRequest._tmp(id);
JsHttpRequest._tmp=null;
};
xr.open(this.method,this.url,true,this.username,this.password);
if(_46){
for(var i=0;i<req._reqHeaders.length;i++){
xr.setRequestHeader(req._reqHeaders[i][0],req._reqHeaders[i][1]);
}
xr.setRequestHeader("Content-Type","application/octet-stream");
}
xr.send(this.queryText);
this.span=null;
this.xr=xr;
return null;
};
this.getAllResponseHeaders=function(){
return this.xr.getAllResponseHeaders();
};
this.getResponseHeader=function(_4a){
return this.xr.getResponseHeader(_4a);
};
this.abort=function(){
this.xr.abort();
this.xr=null;
};
}};

JsHttpRequest.LOADERS.script={loader:function(req){
JsHttpRequest.extend(req._errors,{script_only_get:"Cannot use SCRIPT loader: it supports only GET method",script_no_form:"Cannot use SCRIPT loader: direct form elements using and uploading are not implemented"});
this.load=function(){
if(this.queryText){
this.url+=(this.url.indexOf("?")>=0?"&":"?")+this.queryText;
}
this.url+=(this.url.indexOf("?")>=0?"&":"?")+"JsHttpRequest="+this.id+"-"+"script";
this.queryText="";
if(!this.method){
this.method="GET";
}
if(this.method!=="GET"){
return ["script_only_get"];
}
if(this.queryElem.length){
return ["script_no_form"];
}
if(this.url.length>JsHttpRequest.MAX_URL_LEN){
return ["url_too_long",JsHttpRequest.MAX_URL_LEN];
}
var th=this,d=document,s=null,b=d.body;
if(!window.opera){
this.span=s=d.createElement("SCRIPT");
var _43=function(){
s.language="JavaScript";
if(s.setAttribute){
s.setAttribute("src",th.url);
}else{
s.src=th.url;
}
b.insertBefore(s,b.lastChild);
};
}else{
this.span=s=d.createElement("SPAN");
s.style.display="none";
b.insertBefore(s,b.lastChild);
s.innerHTML="Workaround for IE.<s"+"cript></"+"script>";
var _43=function(){
s=s.getElementsByTagName("SCRIPT")[0];
s.language="JavaScript";
if(s.setAttribute){
s.setAttribute("src",th.url);
}else{
s.src=th.url;
}
};
}
JsHttpRequest.setTimeout(_43,10);
return null;
};
}};