<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
   <div class="content">
      <div class="row">
         <?php if (! is_gdpr()) { ?>
         <div class="panel_s">
            <div class="panel-body">
               <div class="col-md-12 text-center">
                  <h4>
                     <?= _l('gdpr_not_enabled'); ?>
                  </h4>
                  <?php if(!empty($native_company_gdpr)){ ?>
                  <?= form_open(admin_url('gdpr/enable')); ?>
                  <input type="hidden" name="_native_company_csrf" value="<?= e($native_gdpr_csrf); ?>">
                  <button type="submit" class="btn btn-primary"><?= _l('enable_gdpr'); ?></button>
                  <?= form_close(); ?>
                  <?php }else{ ?>
                  <a href="<?= admin_url('gdpr/enable'); ?>"
                     class="btn btn-primary"><?= _l('enable_gdpr'); ?></a><?php } ?>
               </div>
            </div>
         </div>
         <?php } else { ?>
         <?php if ($save == true) { ?>
         <?= form_open(admin_url('gdpr/save?page=' . $page)); ?>
         <?php if(!empty($native_company_gdpr)){ ?><input type="hidden" name="_native_company_csrf" value="<?= e($native_gdpr_csrf); ?>"><?php } ?>
         <?php } ?>
         <div class="col-md-3">
            <ul class="nav navbar-pills navbar-pills-flat nav-tabs nav-stacked">
               <li<?php if ($page == 'general') {
                   echo ' class="active"';
               } ?>>
                  <a
                     href="<?= admin_url('gdpr/index?page=general'); ?>"><?= _l('settings_group_general'); ?></a>
                  </li>
                  <li<?php if ($page == 'portability') {
                      echo ' class="active"';
                  } ?>>
                     <a
                        href="<?= admin_url('gdpr/index?page=portability'); ?>"><?= _l('gdpr_right_to_data_portability'); ?></a>
                     </li>
                     <li<?php if ($page == 'forgotten') {
                         echo ' class="active"';
                     } ?>>
                        <a
                           href="<?= admin_url('gdpr/index?page=forgotten'); ?>"><?= _l('gdpr_right_to_erasure'); ?></a>
                        </li>
                        <li<?php if ($page == 'informed') {
                            echo ' class="active"';
                        } ?>>
                           <a
                              href="<?= admin_url('gdpr/index?page=informed'); ?>"><?= _l('gdpr_right_to_be_informed'); ?></a>
                           </li>
                           <li<?php if ($page == 'rectification') {
                               echo ' class="active"';
                           } ?>>
                              <a
                                 href="<?= admin_url('gdpr/index?page=rectification'); ?>"><?= _l('gdpr_right_of_access'); ?>/<?= _l('gdpr_right_to_rectification'); ?></a>
                              </li>
                              <li<?php if ($page == 'consent') {
                                  echo ' class="active"';
                              } ?>>
                                 <a
                                    href="<?= admin_url('gdpr/index?page=consent'); ?>"><?= _l('gdpr_consent'); ?></a>
                                 </li>
            </ul>
         </div>
         <div class="col-md-9">
            <div class="panel_s">
               <div class="panel-body">
                  <?php if(empty($native_company_gdpr))hooks()->do_action('before_admin_gdpr_settings'); ?>
                  <?php $this->load->view('admin/gdpr/pages/' . $page); ?>
               </div>
            </div>
         </div>
         <?php if ($save == true) { ?>
         <div class="btn-bottom-toolbar text-right">
            <button type="submit"
               class="btn btn-primary"><?= _l('save'); ?></button>
         </div>
         <?= form_close(); ?>
         <?php } ?>
         <?php } ?>
      </div>
   </div>
</div>
<div id="page-tail"></div>
<?php init_tail(); ?>
<script>
   $(function() {
      $('.removalStatus').on('change', function(e) {
         var id = $(this).attr('data-id');
         var val = $(this).selectpicker('val');

         // Event is invoked twice? Second is jQuery object
         if (typeof(val) != 'string') {
            return;
         }
         <?php if(!empty($native_company_gdpr)){ ?>
         var select=$(this),revision=select.attr('data-revision');
         if(!/^(?:[1-9][0-9]{0,8}|[a-f0-9]{64})$/.test(revision||'')||select.data('saving'))return;
         var body={revision:revision,_native_company_csrf:<?= json_encode($native_gdpr_csrf,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>};
         if(typeof csrfData!=='undefined')body[csrfData.token_name]=csrfData.hash;
         select.data('saving',true).prop('disabled',true).selectpicker('refresh');
         $.ajax({url:admin_url+'gdpr/change_removal_request_status/'+encodeURIComponent(id)+'/'+encodeURIComponent(val),type:'POST',dataType:'json',data:body})
           .done(function(result){if(!result||result.success!==true||!/^(?:[1-9][0-9]{0,8}|[a-f0-9]{64})$/.test(String(result.revision))){select.selectpicker('val',select.attr('data-current-status'));return;}select.attr('data-revision',String(result.revision)).attr('data-current-status',result.status);})
           .fail(function(){select.selectpicker('val',select.attr('data-current-status'));alert_float('danger',app.lang.something_went_wrong);})
           .always(function(){select.data('saving',false).prop('disabled',false).selectpicker('refresh');});
         <?php }else{ ?>
         requestGet('gdpr/change_removal_request_status/' + id + '/' + val);
         <?php } ?>
      });
   });
</script>
</body>

</html>