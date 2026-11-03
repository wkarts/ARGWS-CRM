/* Context/transport glue only: native interaction implementations are shared with main.js. */
(function(){'use strict';
 if(!document.body.classList.contains('native-company-admin'))return;
 NativeAdminRuntime.configureUploads();
 init_task_checklist_events();
 $(function(){if(Array.isArray(app.available_tags))NativeAdminWidgets.initTags();NativeAdminWidgets.initTables();init_table_tickets();init_table_staff_projects();NativeAdminWidgets.initVisuals();if(app.options.calendar_first_day!==undefined)NativeAdminWidgets.initDates();NativeAdminWidgets.initSelects();if(window.NativeAdminCalendar)window.NativeAdminCalendar.init();});

 var config=window.NativeCompanyHeaderConfig;if(!config)return;
 function request(path,method,data){return NativeCompanyTransport.request(path,method,data,{dataType:'json'});}
 NativeAdminNavigation.init({isMobile:function(){return document.body.dataset.nativeMobile==='true';},isRTL:document.documentElement.dir==='rtl'?'true':'false',persistSetup:config.setupPersistence?function(state){if(state!=='closed'&&state!=='open')throw Error('native_setup_state_invalid');return request('misc/set_setup_menu_'+state,'POST');}:undefined});
 if(config.search)NativeAdminSearch.init({history:config.history,browser:config.browser,language:config.language,
   search:function(q){return request('misc/search','POST',{q:q});},
   remove:function(row){return request('misc/remove_recent_search/'+row.id,'POST',{revision:row.revision});}
 });
 if(config.notifications){
   var values=config.options.values;
   NativeAdminNotifications.init({initialTitle:document.title,browser:config.browser,label:config.newNotificationLabel,dismissAfter:values.auto_dismiss_desktop_notifications_after===null?'0':values.auto_dismiss_desktop_notifications_after,
     openTask:function(id,comment){return window.init_task_modal(id,comment);},openLead:function(id){return window.init_lead(id);},openEvent:function(id){return window.view_event(id);},openPost:function(id){window.postid=id;},
     transport:{read:function(){return request('misc/notifications_check','GET').then(function(response){if(values.desktop_notifications!=='1')response.notificationsIds=[];return response;});},
       mark:function(id,mode){var path=mode==='seen'?'misc/set_notifications_read':mode==='inline'?'misc/set_notification_read_inline/'+id:id===null?'misc/mark_all_notifications_as_read_inline':'misc/set_desktop_notification_read/'+id;return request(path,'POST');}
     }
   });
   window.set_notification_read_inline=NativeAdminNotifications.markOne;window.mark_all_notifications_as_read_inline=NativeAdminNotifications.markAll;window.fetch_notifications=NativeAdminNotifications.fetch;
 }
}());
