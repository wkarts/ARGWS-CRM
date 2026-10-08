<?php defined("BASEPATH") or exit("No direct script access allowed"); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <h2>Upload de OFX para Conciliação Bancária</h2>
        
        <!-- Formulário de Upload -->
        <?php echo form_open_multipart(admin_url("finance/conciliacao/upload_ofx")); ?>
            <div class="form-group">
                <label for="bank_id">Selecione o Banco:</label>
                <select name="bank_id" id="bank_id" class="form-control" required>
                    <option value="">Selecione</option>
                    <?php foreach ($banks as $bank): ?>
                        <option value="<?php echo $bank["id"]; ?>"><?php echo $bank["name"]; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="ofx_file">Arquivo OFX:</label>
                <input type="file" name="ofx_file" id="ofx_file" class="form-control" accept=".ofx" required>
                <small class="text-muted">Certifique-se de que o arquivo esteja no formato OFX (SGML).</small>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-upload"></i> Upload e Processar OFX
            </button>
        <?php echo form_close(); ?>
    </div>
</div>
<?php init_tail(); ?>
