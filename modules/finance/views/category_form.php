<?php defined("BASEPATH") or exit("No direct script access allowed"); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4><?php echo isset($category) ? "Editar Categoria" : "Adicionar Categoria"; ?></h4>
                <hr>

                <?php echo form_open(
                    admin_url("finance/category/manage" . (isset($category) ? "/" . $category->id : ""))
                ); ?>

                <div class="form-group">
                    <label>Nome da Categoria</label>
                    <input type="text" name="name" class="form-control" value="<?php echo isset($category) ? $category->name : ""; ?>" required>
                </div>

                <div class="form-group">
                    <label>Categoria Pai</label>
                    <select name="father_category" class="form-control">
                        <option value="">Nenhuma (Categoria Raiz)</option>
                        <?php foreach ($categories as $cat): ?>
                            <?php if (empty($cat["father_category"])): // Somente categorias raiz ?>
                                <option value="<?php echo $cat["id"]; ?>" <?php echo isset($category) && $category->father_category == $cat["id"] ? "selected" : ""; ?>>
                                    <?php echo $cat["name"]; ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?php echo isset($category) ? "Atualizar" : "Cadastrar"; ?>
                </button>
                <a href="<?php echo admin_url("finance/category"); ?>" class="btn btn-default">Cancelar</a>

                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>