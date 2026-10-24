<?php defined('BASEPATH') or exit('No direct script access allowed');
/** Pure presentation. Callers own authorization, section admission, forms and data.
 * content is trusted server-rendered view output, never request/option HTML.
 * No framework hooks, options, storage, routing or callbacks run in this component.
 */
$settingsEscape = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
foreach (['title','heading','active','content'] as $key) {
    if (!isset($settingsPresentation[$key]) || !is_string($settingsPresentation[$key])) throw new LogicException('Invalid settings presentation');
}
if (!isset($settingsPresentation['sections']) || !is_array($settingsPresentation['sections'])) throw new LogicException('Missing settings sections');
foreach ($settingsPresentation['sections'] as $section) {
    if (!is_string($section['title'] ?? null) || !is_array($section['children'] ?? null)) throw new LogicException('Invalid settings section');
    foreach ($section['children'] as $item) {
        if (!is_string($item['id'] ?? null) || !preg_match('/^[a-zA-Z0-9_-]+$/D',$item['id'])
            || !is_string($item['name'] ?? null) || !is_string($item['url'] ?? null)
            || !preg_match('~^(?:/(?!/)|https?://)[^\x00-\x20\\\\]*$~D',$item['url'])
            || !is_string($item['icon'] ?? null) || !preg_match('/^[a-zA-Z0-9 -]+$/D',$item['icon'])) throw new LogicException('Invalid settings item');
    }
}
?>
<div class="row company-native-settings">
 <div class="col-xs-12 col-md-12 col-lg-10">
  <div class="row">
   <div class="col-md-4 col-lg-3">
    <h4 class="tw-font-bold tw-mt-0 tw-text-neutral-800"><?= $settingsEscape($settingsPresentation['title']); ?></h4>
    <nav class="panel_s" aria-label="Seções de definições"><div class="panel-body"><div class="tw-flex tw-flex-col tw-gap-6">
    <?php foreach ($settingsPresentation['sections'] as $section) { ?>
     <div><h4 class="tw-mt-0 tw-mb-4 tw-text-sm tw-text-neutral-500 tw-font-medium"><?= $settingsEscape($section['title']); ?></h4>
      <ul class="tw-space-y-2">
      <?php foreach ($section['children'] as $child) { $selected=$settingsPresentation['active']===$child['id']; ?>
       <li class="settings-group-<?= $settingsEscape($child['id']); ?>"><a href="<?= $settingsEscape($child['url']); ?>" <?= $selected?'aria-current="page"':''; ?> class="tw-group tw-flex tw-items-center tw-text-sm hover:tw-text-neutral-800 focus:tw-text-neutral-800 tw-font-medium tw-gap-2.5 <?= $selected?'tw-text-neutral-800':'tw-text-neutral-600'; ?>">
        <i class="<?= $settingsEscape($child['icon']); ?> fa-fw fa-lg tw-mr-0.5 group-hover:tw-text-neutral-800 <?= $selected?'tw-text-neutral-800':'tw-text-neutral-500'; ?>"></i><span><?= $settingsEscape($child['name']); ?></span>
        <?php if (isset($child['badge']['value']) && $child['badge']['value']!=='') {
            $type=$child['badge']['type']??'info'; if(!in_array($type,['info','success','warning','danger','primary','default'],true))$type='info';
            $color=$child['badge']['color']??''; if(!is_string($color)||!preg_match('/^#[a-fA-F0-9]{6}$/D',$color))$color=''; ?>
        <span class="badge tw-ml-auto bg-<?= $settingsEscape($type); ?>" <?= $color!==''?'style="background-color: '.$settingsEscape($color).'"':''; ?>><?= $settingsEscape($child['badge']['value']); ?></span>
        <?php } ?>
       </a></li>
      <?php } ?>
      </ul>
     </div>
    <?php } ?>
    </div></div></nav>
   </div>
   <div class="col-md-8 col-lg-9">
    <h4 class="tw-font-bold tw-mt-0 tw-text-neutral-800"><?= $settingsEscape($settingsPresentation['heading']); ?></h4>
    <?= $settingsPresentation['content']; ?>
   </div>
  </div>
 </div>
</div>
<?php unset($settingsEscape); ?>
