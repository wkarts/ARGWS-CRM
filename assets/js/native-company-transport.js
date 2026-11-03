/* One captured-context transport for the existing native jQuery handlers. No automatic requests. */
(function(global,$){'use strict';
 if(!document.body.classList.contains('native-company-admin'))return;
 if(global.NativeCompanyTransport)throw Error('native_transport_already_bound');
 var c=global.NativeCompanyHeaderConfig;if(!c)throw Error('native_transport_config_missing');
 var root=new URL(c.root,location.href),context=token(c.context),csrf=token(c.csrf),framework=c.frameworkCsrf?{name:token(c.frameworkCsrf.name),value:token(c.frameworkCsrf.value)}:null;
 if(root.origin!==location.origin||root.username||root.password||root.search||root.hash||!root.pathname.endsWith('/'))throw Error('native_transport_root_invalid');
 function token(v){if(typeof v!=='string'||!v||/[\r\n\0]/.test(v))throw Error('native_transport_token_invalid');return v;}
 function url(value){var u=new URL(value,root.href);if(u.username||u.password||/%(?:2f|5c|00)/i.test(u.pathname))throw Error('native_transport_path_invalid');return u;}
 function inside(u){return u.origin===root.origin&&u.pathname.startsWith(root.pathname);}
 function secretName(name){return name==='_company_context'||name==='_native_company_csrf'||(framework&&name===framework.name);}
 function forbidUrlSecrets(u){for(var name of u.searchParams.keys())if(secretName(name))throw Error('native_transport_secret_in_url');}
 function bindHeader(headers,name,value){for(var k of Object.keys(headers)){if(k.toLowerCase()===name.toLowerCase()){if(headers[k]!==value)throw Error('native_transport_token_mismatch');delete headers[k];}}headers[name]=value;}
 function dataToken(data,name,value){
   if(typeof FormData!=='undefined'&&data instanceof FormData){var existing=data.getAll(name);if(existing.length>1||(existing.length===1&&existing[0]!==value))throw Error('native_transport_token_mismatch');if(!existing.length)data.append(name,value);return data;}
   if(typeof data==='string'){var params=new URLSearchParams(data),values=params.getAll(name);if(values.length>1||(values.length===1&&values[0]!==value))throw Error('native_transport_token_mismatch');if(!values.length)params.append(name,value);return params.toString();}
   if(data==null)data={};if(typeof data!=='object'||Array.isArray(data))throw Error('native_transport_data_invalid');
   var result=Object.assign({},data);if(Object.prototype.hasOwnProperty.call(result,name)&&result[name]!==value)throw Error('native_transport_token_mismatch');result[name]=value;return result;
 }
 function validateBody(data,mutation){
   var entries;if(typeof data==='string')entries=new URLSearchParams(data).entries();else if(typeof FormData!=='undefined'&&data instanceof FormData)entries=data.entries();else entries=Object.entries(data||{});
   for(var entry of entries){if(!secretName(entry[0]))continue;if(!mutation)throw Error('native_transport_secret_in_url');var expected=entry[0]==='_company_context'?context:entry[0]==='_native_company_csrf'?csrf:framework.value;if(entry[1]!==expected)throw Error('native_transport_token_mismatch');}
 }
 function prepare(options){
   var u=url(options.url||location.href),headers=Object.assign({},options.headers||{}),method=String(options.type||options.method||'GET').toUpperCase();
   forbidUrlSecrets(u);
   if(!inside(u)){for(var h of Object.keys(headers))if(/^x-(?:company-context|native-company-csrf)$/i.test(h))throw Error('native_transport_external_token');var outgoing=options.data;if(typeof outgoing==='string'&&String(options.contentType||'').toLowerCase().includes('application/json')){try{outgoing=JSON.parse(outgoing);}catch(e){throw Error('native_transport_data_invalid');}}var body=typeof outgoing==='string'?new URLSearchParams(outgoing).entries():(typeof FormData!=='undefined'&&outgoing instanceof FormData?outgoing.entries():Object.entries(outgoing||{}));for(var pair of body)if(secretName(pair[0]))throw Error('native_transport_external_token');return options;}
   if(['jsonp','script'].includes(String(options.dataType||'').toLowerCase()))throw Error('native_transport_executable_response_denied');
   var mutation=method!=='GET'&&method!=='HEAD';if(mutation&&framework&&String(options.contentType||'').toLowerCase().includes('application/json'))throw Error('native_transport_framework_requires_form');validateBody(options.data,mutation);
   bindHeader(headers,'X-Company-Context',context);
   if(mutation){bindHeader(headers,'X-Native-Company-CSRF',csrf);if(framework)options.data=dataToken(options.data,framework.name,framework.value);}
   options.headers=headers;options.url=u.href;return options;
 }
 $.ajaxPrefilter(function(options){prepare(options);});
 global.NativeCompanyTransport=Object.freeze({
   request:function(path,method,data,options){var u=url(path);if(!inside(u))throw Error('native_transport_route_invalid');var o=Object.assign({},options||{},{url:u.href,type:method,data:data});prepare(o);return $.ajax(o);},
   navigate:function(path){var u=url(path);forbidUrlSecrets(u);if(!inside(u))throw Error('native_transport_route_invalid');global.location.assign(u.href);},
   // Explicit authenticated-response handoff only; never reads mutable global app/cookie state.
   rotateFrameworkToken:function(next){if(!framework||!next||next.context!==context||next.name!==framework.name)throw Error('native_transport_rotation_invalid');framework={name:framework.name,value:token(next.value)};}
 });
})(window,jQuery);
