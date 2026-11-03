<?php
declare(strict_types=1);
/** The same native avatar markup; no staff lookup, global options, cache or network. */
final class StaffAvatarPresentation
{
    public static function render($result,$id,array$classes,string$type,array$attributes,callable$url,callable$exists):string
    {
        $id=trim((string)$id);$base=$url('assets/images/user-placeholder.jpg');$attrs='';
        foreach($attributes as$key=>$value){if(!preg_match('/^[A-Za-z][A-Za-z0-9_:-]*$/D',(string)$key))throw new InvalidArgumentException('Invalid image attribute');$attrs.=$key.'="'.htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" ';}
        $blank='<img src="'.$base.'" '.$attrs.' class="'.implode(' ',$classes).'" />';
        if(!$result)return$blank;
        if($result->profile_image!==null){
            $file=(string)$result->profile_image;
            if(!preg_match('/^[1-9][0-9]*$/D',$id)||!preg_match('/^[a-z_]+$/D',$type)||$file===''||basename($file)!==$file||preg_match('/[\\\\\x00-\x1f\x7f]/',$file))return$blank;
            $path='uploads/staff_profile_images/'.$id.'/'.$type.'_'.$file;
            if($exists($path))return'<img '.$attrs.' src="'.$url($path).'" class="'.implode(' ',$classes).'" />';
        }return$blank;
    }
}
