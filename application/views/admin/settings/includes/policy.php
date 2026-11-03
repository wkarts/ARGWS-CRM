<?php defined('BASEPATH') or exit('No direct script access allowed');
if (empty($native_settings) || !is_array($native_settings_policy_values ?? null)) {
    throw new LogicException('Native company policy Settings DTO required');
}
$policyGroup=(string)($group['id']??'');
$values=$native_settings_policy_values;
$esc=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$descriptions=[
    'task_timer_stop'=>'Preferência exclusiva desta empresa. Ao iniciar meu cronômetro, ela pode encerrar o anterior da mesma empresa se eu ainda puder editar a tarefa. Não conclui tarefas nem altera outra empresa.',
    'task_timers'=>'Ao iniciar um cronômetro autorizado, uma tarefa A fazer pode passar para Em andamento na mesma transação. Parar o cronômetro não conclui a tarefa.',
    'task_operations'=>'A prioridade vale quando a criação não escolhe um valor. O acompanhamento automático inclui somente o criador autorizado das novas tarefas de projeto e não modifica tarefas existentes.',
    'task_defaults'=>'Padrões usados em novas tarefas de projeto e avulsas. Valores escolhidos no formulário prevalecem e nenhuma opção inicia cronômetros ou conclui tarefas.',
    'ticket_preferences'=>'Define a ordem das respostas no suporte desta empresa e no portal, sem alterar conteúdo, autoria ou permissões.',
    'pdf_typography'=>'A tipografia é capturada apenas em documentos novos. PDFs já emitidos preservam sua fonte e tamanho originais.',
    'project_files'=>'Permite ao contato ocultar somente arquivos próprios quando as permissões atuais do projeto também autorizarem. O conteúdo e os recibos são preservados.',
];
$expected=[
    'task_timer_stop'=>['auto_stop_tasks_timers_on_new_timer'],
    'task_timers'=>['timer_started_change_status_in_progress'],
    'task_operations'=>['default_task_priority','new_task_auto_follower_current_member'],
    'task_defaults'=>['default_task_status','new_task_auto_assign_current_member'],
    'ticket_preferences'=>['ticket_replies_order'],
    'pdf_typography'=>['pdf_font','pdf_font_size'],
    'project_files'=>['allow_contact_to_delete_files'],
];
if(!isset($expected[$policyGroup])||array_keys($values)!==$expected[$policyGroup])throw new LogicException('Invalid native company policy Settings DTO');
?>
<section class="native-company-policy" data-settings-policy="<?= $esc($policyGroup); ?>">
    <p><?= $esc($descriptions[$policyGroup]); ?></p>
    <?php if($policyGroup==='task_timer_stop'){ ?>
    <div class="form-group"><label for="company-setting-auto_stop_tasks_timers_on_new_timer">Ao iniciar meu cronômetro, parar o anterior desta empresa</label>
        <select class="form-control" id="company-setting-auto_stop_tasks_timers_on_new_timer" name="settings[auto_stop_tasks_timers_on_new_timer]" required>
            <?php foreach(['0'=>'Exigir que eu encerre o anterior','1'=>'Parar meu anterior desta empresa']as$key=>$label){ ?><option value="<?= $key; ?>" <?= $values['auto_stop_tasks_timers_on_new_timer']===(string)$key?'selected':''; ?>><?= $esc($label); ?></option><?php } ?>
        </select>
    </div>
    <?php }elseif($policyGroup==='task_timers'){ ?>
    <div class="form-group"><label for="company-setting-timer_started_change_status_in_progress">Ao iniciar meu cronômetro, mover a tarefa para Em andamento</label>
        <select class="form-control" id="company-setting-timer_started_change_status_in_progress" name="settings[timer_started_change_status_in_progress]" required>
            <?php foreach(['0'=>'Não alterar o estado','1'=>'Mover para Em andamento']as$key=>$label){ ?><option value="<?= $key; ?>" <?= $values['timer_started_change_status_in_progress']===(string)$key?'selected':''; ?>><?= $esc($label); ?></option><?php } ?>
        </select>
    </div>
    <?php }elseif($policyGroup==='task_operations'){ ?>
    <div class="form-group"><label for="company-setting-default_task_priority">Prioridade padrão das novas tarefas</label>
        <select class="form-control" id="company-setting-default_task_priority" name="settings[default_task_priority]" required>
            <?php foreach(['1'=>'Baixa','2'=>'Média','3'=>'Alta','4'=>'Urgente']as$key=>$label){ ?><option value="<?= $key; ?>" <?= $values['default_task_priority']===(string)$key?'selected':''; ?>><?= $esc($label); ?></option><?php } ?>
        </select>
    </div>
    <div class="form-group"><label for="company-setting-new_task_auto_follower_current_member">Acompanhamento das novas tarefas de projeto</label>
        <select class="form-control" id="company-setting-new_task_auto_follower_current_member" name="settings[new_task_auto_follower_current_member]" required>
            <?php foreach(['0'=>'Não seguir automaticamente','1'=>'Seguir minhas novas tarefas de projeto']as$key=>$label){ ?><option value="<?= $key; ?>" <?= $values['new_task_auto_follower_current_member']===(string)$key?'selected':''; ?>><?= $esc($label); ?></option><?php } ?>
        </select>
    </div>
    <?php }elseif($policyGroup==='task_defaults'){ ?>
    <div class="form-group"><label for="company-setting-default_task_status">Estado inicial das novas tarefas</label>
        <select class="form-control" id="company-setting-default_task_status" name="settings[default_task_status]" required>
            <?php foreach(['todo'=>'A fazer','in_progress'=>'Em andamento']as$key=>$label){ ?><option value="<?= $key; ?>" <?= $values['default_task_status']===(string)$key?'selected':''; ?>><?= $esc($label); ?></option><?php } ?>
        </select>
    </div>
    <div class="form-group"><label for="company-setting-new_task_auto_assign_current_member">Responsável sugerido</label>
        <select class="form-control" id="company-setting-new_task_auto_assign_current_member" name="settings[new_task_auto_assign_current_member]" required>
            <?php foreach(['0'=>'Exigir escolha do responsável','1'=>'Sugerir o criador autorizado']as$key=>$label){ ?><option value="<?= $key; ?>" <?= $values['new_task_auto_assign_current_member']===(string)$key?'selected':''; ?>><?= $esc($label); ?></option><?php } ?>
        </select>
    </div>
    <?php }elseif($policyGroup==='ticket_preferences'){ ?>
    <div class="form-group"><label for="company-setting-ticket_replies_order">Ordem das respostas do chamado</label>
        <select class="form-control" id="company-setting-ticket_replies_order" name="settings[ticket_replies_order]" required>
            <?php foreach(['asc'=>'Mais antigas primeiro','desc'=>'Mais recentes primeiro']as$key=>$label){ ?><option value="<?= $key; ?>" <?= $values['ticket_replies_order']===(string)$key?'selected':''; ?>><?= $esc($label); ?></option><?php } ?>
        </select>
    </div>
    <?php }elseif($policyGroup==='pdf_typography'){ ?>
    <div class="form-group"><label for="company-setting-pdf_font">Fonte Unicode dos documentos novos</label>
        <select class="form-control" id="company-setting-pdf_font" name="settings[pdf_font]" required>
            <?php foreach(CompanyPdfTypography::fonts()as$key=>$label){ ?><option value="<?= $esc($key); ?>" <?= $values['pdf_font']===(string)$key?'selected':''; ?>><?= $esc($label); ?></option><?php } ?>
        </select>
    </div>
    <div class="form-group"><label for="company-setting-pdf_font_size">Tamanho da fonte em pontos</label>
        <input class="form-control" id="company-setting-pdf_font_size" name="settings[pdf_font_size]" type="number" min="6" max="24" step="1" value="<?= $esc($values['pdf_font_size']); ?>">
        <small>Entre 6 e 24 pontos, ou vazio para manter o padrão de cada documento.</small>
    </div>
    <?php }elseif($policyGroup==='project_files'){ ?>
    <div class="form-group"><label for="company-setting-allow_contact_to_delete_files">Permitir ao contato ocultar os próprios arquivos de projetos</label>
        <select class="form-control" id="company-setting-allow_contact_to_delete_files" name="settings[allow_contact_to_delete_files]" required>
            <?php foreach(['0'=>'Não permitir','1'=>'Permitir nesta empresa']as$key=>$label){ ?><option value="<?= $key; ?>" <?= $values['allow_contact_to_delete_files']===(string)$key?'selected':''; ?>><?= $esc($label); ?></option><?php } ?>
        </select>
    </div>
    <?php } ?>
</section>
