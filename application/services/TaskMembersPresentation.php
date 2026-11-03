<?php
declare(strict_types=1);
/** Original task assignee avatars, using already-authorized member facts. */
final class TaskMembersPresentation
{
    public static function render(array$members,string$size,callable$url,callable$avatar,callable$escape):string
    {
        if(!$members)return'';$export='';$out='<div class="tw-flex -tw-space-x-1">';
        foreach($members as$member){$id=(string)$member['id'];$name=(string)$member['name'];if($name==='')continue;
            $out.='<a href="'.$url('profile/'.$id).'">'.$avatar($id,[($size==='md'?'tw-h-7 tw-w-7':'tw-h-5 tw-w-5').' tw-inline-block tw-rounded-full tw-ring-2 tw-ring-white'],'small',['data-toggle'=>'tooltip','data-title'=>$escape($name)]).'</a>';$export.=$escape($name).', ';}
        if($export!=='')$out.='<span class="hide">'.mb_substr($export,0,-2).'</span>';return$out.'</div>';
    }
}
