<?php init_head(); ?>

<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <table class="table dt-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Data de criação</th>
                  <th>Cliente</th>
                  <th>Valor</th>
                  <th>Valor líquido</th>
                  <th>Descrição</th>
                  <th>Tipo de cobrança</th>
                  <th>Permite pagamento após vencimento</th>
                  <th>Situação</th>
                  <th>Vencimento</th>
                  <th>Vencimento original</th>
                  <th>Data de pagamento do cliente</th>
                  <th>Número da parcela</th>
                  <th>Link da fatura</th>
                  <th>Número da fatura</th>
                  <th>Referência externa</th>
                </tr>
              </thead>
              <tbody>
                <?php	 foreach($new_array as $new_row) {  ?>
                <tr>
                  <td><?php echo  $new_row["id"];    ?></td>
                  <td><?php echo  $new_row["dateCreated"];   ?></td>
                  <td><?php echo  $new_row["customer"];      ?></td>
                  <td><?php echo  $new_row ["value"]; ?></td>
                  <td><?php echo  $new_row["netValue"];    ?></td>
                  <td><?php echo   $new_row["description"];  ?></td>
                  <td><?php echo  $new_row["billingType"]; ?></td>
                  <td><?php echo  $new_row["canBePaidAfterDueDate"];    ?></td>
                  <td><?php echo  $new_row["status"]; ?></td>
                  <td><?php echo  $new_row["dueDate"]; ?></td>
                  <td><?php echo  $new_row["originalDueDate"];     ?></td>
                  <td><?php echo  $new_row["clientPaymentDate"];   ?></td>
                  <td><?php echo  $new_row["installmentNumber"];   ?></td>
                  <td><?php echo  $new_row ["invoiceUrl"];     ?></td>
                  <td><?php echo  $new_row["invoiceNumber"];  ?></td>
                  <td><?php echo  $new_row["externalReference"]; ?></td>
                </tr>
                <?php	 }	?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
