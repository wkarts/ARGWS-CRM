<?php
defined('BASEPATH') or exit('No direct script access allowed');
$company_page_title = 'Recorrência de tarefas';
require APPPATH.'views/admin/includes/native_company_layout_head.php';
$esc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
$url = fn($query='') => CompanyRequestScope::adminUrl($this, 'tasks/recurring?kind='.$kind.$query);
$taskUrl = fn($id) => CompanyRequestScope::adminUrl($this, ($kind === 'project' ? 'tasks' : 'tasks/standalone').'?task='.(int)$id);
$states = ['active'=>'Ativa', 'paused'=>'Pausada', 'cancelled'=>'Cancelada', 'completed'=>'Concluída'];
$units = ['day'=>'dias', 'week'=>'semanas', 'month'=>'meses'];
$hidden = function (string $action) use ($esc, $kind, $company_task_recurring_csrf, $request_key, $detail): void {
    foreach (['action'=>$action, 'kind'=>$kind, 'company_task_recurring_csrf'=>$company_task_recurring_csrf, 'request_key'=>$request_key] as $name=>$value) {
        echo '<input type="hidden" name="'.$esc($name).'" value="'.$esc($value).'">';
    }
    if ($detail !== null) {
        echo '<input type="hidden" name="schedule_id" value="'.(int)$detail['id'].'"><input type="hidden" name="revision" value="'.(int)$detail['revision'].'"><input type="hidden" name="cycle" value="'.(int)$detail['completed_cycles'].'">';
    }
};
?>
<section class="company-settings-card">
<h1>Recorrência de tarefas</h1>
<nav class="company-settings-nav" aria-label="Modalidade da tarefa">
<a href="<?= $esc(CompanyRequestScope::adminUrl($this, 'tasks/recurring?kind=standalone')); ?>" <?= $kind === 'standalone' ? 'aria-current="page"' : ''; ?>>Tarefas avulsas</a>
<?php if ($can_project) { ?><a href="<?= $esc(CompanyRequestScope::adminUrl($this, 'tasks/recurring?kind=project')); ?>" <?= $kind === 'project' ? 'aria-current="page"' : ''; ?>>Tarefas de projeto</a><?php } ?>
</nav>
<p>As agendas pertencem à empresa selecionada. Cada ciclo usa o modelo confirmado na criação, com checklist reiniciado e prazo calculado no calendário escolhido.</p>
<?php if ($error !== null) { ?><p role="alert" class="alert alert-danger"><?= $esc($error); ?></p><?php } ?>
<?php if ($detail !== null) { $calendar = $detail['calendar']; ?>
<h2>Agenda #<?= (int)$detail['id']; ?> — <?= $esc($detail['template']['name']); ?></h2>
<p id="schedule-state" role="status"><?= $esc($states[$detail['state']]); ?> · <span id="schedule-cycles"><?= (int)$detail['completed_cycles']; ?>/<?= (int)$calendar['cycles']; ?> ciclos</span> · Revisão <?= (int)$detail['revision']; ?></p>
<p>A cada <?= (int)$calendar['interval']; ?> <?= $esc($units[$calendar['unit']]); ?>, a partir de <?= $esc($calendar['first_date']); ?>, no fuso <strong><?= $esc($calendar['timezone']); ?></strong>. Prazo: <?= (int)$calendar['due_days']; ?> dias após cada ocorrência.</p>
<p>Próxima data local: <strong><?= $esc($detail['next_date'] ?? 'Agenda encerrada'); ?></strong>. <?= $calendar['require_previous_done'] ? 'Exige a conclusão da tarefa anterior; no primeiro ciclo, exige a conclusão do modelo.' : 'A conclusão da tarefa anterior não é uma condição desta agenda.'; ?></p>
<p>Responsável do modelo: #<?= (int)$detail['template']['assignee_id']; ?>. Prioridade: <?= $esc(CompanyTaskPriority::labels()[(int)$detail['template']['priority']]); ?>. Origem: <?= $detail['api_origin'] === null ? 'interface administrativa' : 'API, com autorização revalidada a cada ciclo'; ?>.</p>
<p><a href="<?= $esc($taskUrl($detail['source_id'])); ?>">Abrir tarefa usada como modelo</a></p>
<details><summary>Modelo confirmado</summary><p><?= nl2br($esc($detail['template']['description'])); ?></p><ul><?php foreach ($detail['template']['checklist'] as $check) { ?><li><?= $esc($check['text']); ?></li><?php } ?></ul></details>
<?php if ($can_edit && in_array($detail['state'], ['active','paused'], true)) { ?>
<div class="company-settings-actions">
<?php if ($detail['state'] === 'active') { ?><?= form_open($url('&schedule='.(int)$detail['id'])); $hidden('pause'); ?><button type="submit">Pausar agenda</button><?= form_close(); ?><?php } elseif ((int)$detail['actor_id'] === $actor && $can_create) { ?><?= form_open($url('&schedule='.(int)$detail['id'])); $hidden('resume'); ?><button type="submit">Retomar agenda</button><?= form_close(); ?><?php } ?>
<?= form_open($url('&schedule='.(int)$detail['id'])); $hidden('cancel'); ?><button type="submit">Cancelar próximos ciclos</button><?= form_close(); ?>
</div>
<?php } ?>
<?php if ($can_create && (int)$detail['actor_id'] === $actor && $detail['state'] === 'active') { ?>
<?= form_open($url('&schedule='.(int)$detail['id'])); $hidden('run'); ?><p>Processar verifica somente o próximo ciclo. A tarefa é criada quando a data local e todas as condições forem atendidas.</p><button type="submit">Processar próximo ciclo elegível</button><?= form_close(); ?>
<?php } ?>
<h3>Ciclos confirmados</h3>
<div class="table-responsive"><table class="table" id="schedule-runs"><thead><tr><th>Ciclo</th><th>Data local</th><th>Tarefa</th></tr></thead><tbody><?php foreach ($detail['runs'] as $run) { ?><tr><td><?= (int)$run['cycle']; ?></td><td><?= $esc($run['date']); ?></td><td><a href="<?= $esc($taskUrl($run['task_id'])); ?>">Abrir tarefa #<?= (int)$run['task_id']; ?></a></td></tr><?php } ?></tbody></table></div>
<p>Cancelar preserva os ciclos já criados. Para mudar o calendário ou o modelo, cancele os próximos ciclos desta agenda e revise uma nova agenda.</p>
<?php } ?>
<?php if ($source !== null) { ?>
<h2>Revisar agenda para <?= $esc($source['name']); ?></h2>
<p><a href="<?= $esc($taskUrl($source['id'])); ?>">Conferir tarefa original e responsável</a> · Revisão <?= (int)$source['revision']; ?></p>
<p>O nome, a descrição, o responsável, a prioridade e os itens do checklist serão confirmados como modelo. Comentários, arquivos, tempo, dependências e vínculos financeiros permanecem no histórico original.</p>
<?php if ($can_create && (int)$source['assignee_id'] > 0) { ?>
<?= form_open($url('&source='.(int)$source['id']), ['id'=>'task-recurring-create']); $hidden('create'); ?>
<input type="hidden" name="source_id" value="<?= (int)$source['id']; ?>"><input type="hidden" name="source_revision" value="<?= (int)$source['revision']; ?>">
<div class="company-settings-grid">
<div class="form-group"><label for="recurring-unit">Calendário</label><select id="recurring-unit" class="form-control" name="calendar[unit]"><option value="day">Diário</option><option value="week">Semanal</option><option value="month">Mensal</option></select></div>
<div class="form-group"><label for="recurring-interval">Intervalo</label><input id="recurring-interval" class="form-control" type="number" min="1" max="120" name="calendar[interval]" value="1" required></div>
<div class="form-group"><label for="recurring-first">Primeira data local</label><input id="recurring-first" class="form-control" type="date" min="2000-01-01" max="9999-12-31" name="calendar[first_date]" value="<?= $esc(gmdate('Y-m-d')); ?>" required></div>
<div class="form-group"><label for="recurring-timezone">Fuso horário IANA</label><input id="recurring-timezone" class="form-control" name="calendar[timezone]" value="UTC" list="recurring-timezones" maxlength="100" required><datalist id="recurring-timezones"><option value="UTC"><option value="America/Bahia"><option value="America/Sao_Paulo"><option value="America/Manaus"><option value="Europe/Lisbon"></datalist></div>
<div class="form-group"><label for="recurring-cycles">Quantidade de ciclos</label><input id="recurring-cycles" class="form-control" type="number" min="1" max="120" name="calendar[cycles]" value="3" required></div>
<div class="form-group"><label for="recurring-due">Dias para o prazo de cada tarefa</label><input id="recurring-due" class="form-control" type="number" min="0" max="3650" name="calendar[due_days]" value="1" required></div>
<div class="form-group"><label for="recurring-previous">Condição da tarefa anterior</label><select id="recurring-previous" class="form-control" name="calendar[require_previous_done]"><option value="0">Pode criar sem aguardar conclusão</option><option value="1">Exigir conclusão; modelo no primeiro ciclo</option></select></div>
</div>
<p>No calendário mensal, o dia do modelo é mantido; quando ele não existe no mês, usa-se o último dia do mês. O limite é de 120 ciclos e 500 agendas ativas ou pausadas por empresa.</p>
<button type="submit">Confirmar agenda privada</button><?= form_close(); ?>
<?php } else { ?><p>Para criar a agenda, são necessárias permissões atuais de criação e edição de tarefas e um responsável autorizado na tarefa original.</p><?php } ?>
<?php } elseif ($detail === null) { ?><p>Abra uma tarefa <?= $kind === 'project' ? 'de projeto' : 'avulsa'; ?> e use “Revisar recorrência desta tarefa” para confirmar um modelo.</p><?php } ?>
<h2>Agendas <?= $kind === 'project' ? 'de tarefas de projeto' : 'de tarefas avulsas'; ?></h2>
<div class="table-responsive"><table class="table" id="task-recurring-directory"><thead><tr><th>Agenda</th><th>Estado</th><th>Ciclos</th><th>Próxima data local</th><th>Fuso</th></tr></thead><tbody><?php foreach ($directory['items'] as $row) { ?><tr><td><a href="<?= $esc($url('&schedule='.(int)$row['id'])); ?>"><?= $esc($row['template']['name']); ?> #<?= (int)$row['id']; ?></a></td><td><?= $esc($states[$row['state']]); ?></td><td><?= (int)$row['completed_cycles']; ?>/<?= (int)$row['calendar']['cycles']; ?></td><td><?= $esc($row['next_date'] ?? 'Encerrada'); ?></td><td><?= $esc($row['calendar']['timezone']); ?></td></tr><?php } ?></tbody></table></div>
<?php if ($directory['next'] !== null) { ?><a href="<?= $esc($url('&after='.$directory['next'].($source !== null ? '&source='.(int)$source['id'] : ''))); ?>">Próxima página de agendas</a><?php } ?>
<p>A execução automática depende do agendamento do worker da instalação. Cada tentativa revalida a empresa, o criador, o responsável e a origem da API quando aplicável.</p>
</section>
<?php require APPPATH.'views/admin/includes/native_company_layout_tail.php'; ?>
