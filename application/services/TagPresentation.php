<?php
/** One native tag markup for already-authorized catalogues. No cache, DB or hooks. */
final class TagPresentation
{
    public static function render(array $tags):string
    {
        if(!$tags)return '';
        $html='<div class="tags-labels">';$i=0;$count=count($tags);
        foreach($tags as$tag){
            if(!is_array($tag)||(!is_int($tag['id']??null)&&!is_string($tag['id']??null))||!preg_match('/^[0-9]{1,16}$/D',(string)$tag['id'])||!is_string($tag['name']??null))throw new DomainException('native_tag_presentation_invalid');
            $html.='<span class="label label-tag tag-id-'.$tag['id'].'"><span class="tag">'.htmlspecialchars($tag['name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</span><span class="hide">'.($i!==$count-1?', ':'').'</span></span>';++$i;
        }
        return $html.'</div>';
    }
}
