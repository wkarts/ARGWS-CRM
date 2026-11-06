<?php defined('BASEPATH') or exit('No direct script access allowed');
if(empty($native_sales_form)||!($native_sales_editor_items instanceof NativeCompanySalesEditorItems))throw new DomainException('native_sales_editor_denied');
echo form_hidden('_native_sales_id',$native_sales_id);
echo form_hidden('_native_sales_revision',$native_sales_revision);
echo form_hidden('_native_sales_request_key',$native_sales_request_key);
echo form_hidden('_native_sales_number_revision',$native_sales_number_selection['revision']);
echo form_hidden('_native_sales_number',$native_sales_number_selection['next_number']);
echo form_hidden('_native_sales_metadata_revision',$native_sales_metadata_revision);
?>
