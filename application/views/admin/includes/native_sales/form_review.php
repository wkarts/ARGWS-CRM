<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(!empty($native_sales_form)&&$native_sales_id!==''){ ?>
<div class="panel-body">
    <div class="form-group">
        <label for="native-sales-reason">Motivo da alteração</label>
        <input type="text" id="native-sales-reason" name="_native_sales_reason" class="form-control" maxlength="2000" required>
    </div>
    <div class="checkbox checkbox-primary">
        <input type="checkbox" id="native-sales-confirmed" name="_native_sales_confirmed" value="1" required>
        <label for="native-sales-confirmed">Revisei os dados desta empresa e confirmo a alteração do documento aberto.</label>
    </div>
</div>
<?php } ?>
