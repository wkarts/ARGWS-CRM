<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<label for="<?= $esc($form_id); ?>-name">Nome</label><input id="<?= $esc($form_id); ?>-name" class="form-control" name="contract[name]" value="<?= $esc($values['name']); ?>" maxlength="191" required>
<label for="<?= $esc($form_id); ?>-body">Texto do contrato</label><textarea id="<?= $esc($form_id); ?>-body" class="form-control" name="contract[body]" maxlength="40000" rows="12" required><?= $esc($values['body']); ?></textarea>
<label for="<?= $esc($form_id); ?>-start">Início</label><input id="<?= $esc($form_id); ?>-start" type="date" class="form-control" name="contract[start_date]" value="<?= $esc($values['start_date']); ?>" required>
<label for="<?= $esc($form_id); ?>-due">Fim</label><input id="<?= $esc($form_id); ?>-due" type="date" class="form-control" name="contract[due_date]" value="<?= $esc($values['due_date']); ?>" required>
