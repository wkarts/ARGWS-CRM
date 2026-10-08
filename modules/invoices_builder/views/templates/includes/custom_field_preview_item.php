  <?php if($type == 'header[invoice_infor]'){ ?>
    <p class="mbot5 control-element-2 <?php echo ib_html_entity_decode($id); ?> d-none"></p>
  <?php } ?>
  <?php if($type == 'sender_receiver[sender_infor]'){ ?>
  	<p class="mbot5 mleft10 mright10 control-element-2 sender_infor <?php echo ib_html_entity_decode($id); ?> d-none"></p>
  <?php } ?>
  <?php if($type == 'sender_receiver[receiver_infor]'){ ?>
  	<p class="mbot5 mleft10 mright10 control-element-2 receiver_infor <?php echo ib_html_entity_decode($id); ?> d-none"></p>
  <?php } ?>
  <?php if($type == 'item_table'){ ?>
  	<td class="text-nowrap control-element <?php echo ib_html_entity_decode($id); ?> d-none" data-id="<?php echo ib_html_entity_decode($id); ?>" data-label="<?php echo ib_html_entity_decode($field_label); ?>" data-sort="1">
       <?php if($value != ''){ ?>
           <span><?php echo ib_html_entity_decode($value); ?></span><input type="hidden" class="hidden_customfield_value" data-formula="" data-type="item_table" data-row-index="" name="item_table|<?php echo ib_html_entity_decode($name); ?>" value="<?php echo ib_html_entity_decode($value); ?>">
       <?php }elseif($formula != ''){   ?>
           <span></span><input type="hidden" class="hidden_customfield_value has-formula" data-formula="<?php echo ib_html_entity_decode($formula); ?>" data-type="item_table" data-row-index="" name="item_table|<?php echo ib_html_entity_decode($name); ?>" value="">
       <?php } ?>
</td>
<?php } ?>
<?php if($type == 'invoice_total'){ ?>
 <p class="mbot5 mleft10 mright10 control-element <?php echo ib_html_entity_decode($id); ?> d-none" data-sort="1">
       <?php if($value != ''){ ?>
           <span><?php echo ib_html_entity_decode($value); ?></span><input type="hidden" class="hidden_customfield_value" data-formula="" data-type="invoice_total" data-row-index="" name="invoice_total|<?php echo ib_html_entity_decode($name); ?>" value="<?php echo ib_html_entity_decode($value); ?>">
       <?php }elseif($formula != ''){   ?>
           <span></span><input type="hidden" class="hidden_customfield_value has-formula" data-formula="<?php echo ib_html_entity_decode($formula); ?>" data-type="invoice_total" data-row-index="" name="invoice_total|<?php echo ib_html_entity_decode($name); ?>" value="">
       <?php } ?>
 </p>
<?php } ?>
<?php if($type == 'bottom_information'){ ?>
 <div class="mbot5 mleft10 mright10 d-grid control-element <?php echo ib_html_entity_decode($id); ?> d-none" data-sort="1"><span></span></div>
<?php } ?>
<?php if($type == 'footer'){ ?>
 <p class="mbot5 mleft10 mright10 control-element control-element <?php echo ib_html_entity_decode($id); ?> d-none" data-sort="1"><span></span></p>
 <?php } ?>