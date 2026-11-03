<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $owned=$company_owned_contracts;$labels=['draft'=>'Rascunho','active'=>'Ativo','cancelled'=>'Cancelado']; ?>
<div class="panel_s tw-mt-4" id="company-owned-contracts">
    <div class="panel-body">
        <div class="sm:tw-flex sm:tw-items-center sm:tw-justify-between tw-gap-2">
            <div>
                <h4 class="tw-mt-0 tw-font-semibold">Contratos com portal do cliente</h4>
                <p class="text-muted">Contratos numerados, anexos privados e conversas publicados somente para contatos autorizados.</p>
            </div>
            <?php if($owned['can_create']){ ?>
                <a class="btn btn-primary" href="<?= e(admin_url('contracts/contract?source=company')); ?>">Novo contrato com portal</a>
            <?php } ?>
        </div>
        <form class="form-inline tw-mb-3" method="get" action="<?= e(admin_url('contracts')); ?>">
            <label class="sr-only" for="company-contract-search">Buscar contratos com portal</label>
            <input id="company-contract-search" class="form-control" name="company_search" maxlength="191" value="<?= e($owned['search']); ?>" placeholder="Buscar pelo nome">
            <button class="btn btn-default" type="submit"><?= e(_l('search')); ?></button>
        </form>
        <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Número</th><th>Nome</th><th>Estado</th><th>Vigência</th></tr></thead><tbody>
        <?php foreach($owned['directory']['items'] as$row){$canonical=NativeCompanyResourceId::encode('company_contract',(string)$row['id']); ?>
            <tr><td><?= $row['number']===null?'Rascunho':(int)$row['number']; ?></td><td><a href="<?= e(admin_url('contracts/contract/'.$canonical)); ?>"><?= e($row['name']); ?></a></td><td><?= e($labels[$row['status']]??$row['status']); ?></td><td><?= e($row['due_date']); ?></td></tr>
        <?php } ?>
        </tbody></table></div>
        <?php if($owned['directory']['next']!==null){ ?><a class="btn btn-default" href="<?= e(admin_url('contracts?company_after='.(int)$owned['directory']['next'].'&company_search='.rawurlencode($owned['search']))); ?>">Próxima página</a><?php } ?>
    </div>
</div>
