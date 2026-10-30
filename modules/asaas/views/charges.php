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
                  <th>Situação</th>
                  <th>Vencimento</th>
                  <th>Vencimento original</th>
                  <th>Data de pagamento</th>
                  <th>Data de pagamento do cliente</th>
                  <th>Número da fatura</th>
                  <th>Referência externa</th>
                </tr>
              </thead>
              <tbody>
                <?php	 foreach($response as $row) {  ?>
                <tr>
                  <td><?php echo  $row["id"];    ?></td>
                  <td><?php echo  $row["dateCreated"];   ?></td>
                  <td><?php echo  $row["customer"];      ?></td>
                  <td><?php echo  $row ["value"]; ?></td>
                  <td><?php echo  $row["netValue"];    ?></td>
                  <td><?php echo   $row["description"];  ?></td>
                  <td><?php echo  $row["billingType"]; ?></td>
                  <td><?php echo  $row["status"]; ?></td>
                  <td><?php echo  $row["dueDate"]; ?></td>
                  <td><?php echo  $row["originalDueDate"];     ?></td>
                  <td><?php echo  $row["paymentDate"];  ?></td>
                  <td><?php echo  $row["clientPaymentDate"];   ?></td>
                  <td><?php echo  $row["invoiceNumber"];  ?></td>
                  <td><?php echo  $row["externalReference"]; ?></td>
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
