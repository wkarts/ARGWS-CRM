<?php
/** Calendar/format helper boundary for controller fixtures; chart calculations are production code. */
require_once APPPATH.'services/utilities/Format.php';
if(!function_exists('get_weekdays')){function get_weekdays(){return['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];}}
if(!function_exists('get_weekdays_original')){function get_weekdays_original(){return['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];}}
if(!function_exists('sec2qty')){function sec2qty($seconds){return round(((int)$seconds)/3600,2);}}
if(!function_exists('get_weekdays_between_dates')){function get_weekdays_between_dates($from,$to){$days=[];for($day=$from;$day<=$to;$day=$day->modify('+1 day'))$days[]=$day->format('Y-m-d');return[1=>$days];}}
if(!function_exists('split_weeks_chart_label')){function split_weeks_chart_label($weeks,$index){return 'Week '.$index;}}
