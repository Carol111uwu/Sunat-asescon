<?php if ($esGlobal && $resumenClientes !== []): ?>
<section class="reference-panel" aria-label="Resultados por empresa">
  <details>
    <summary><strong>Resultados guardados por empresa (<?= count($resumenClientes) ?>)</strong></summary>
    <div class="table-container"><table>
      <thead><tr><th>Empresa</th><th>RUC</th><th>Meses con resultado</th><th>Comprobantes</th></tr></thead>
      <tbody>
        <?php foreach ($resumenClientes as $empresa): ?>
          <tr>
            <td><a href="?<?= $h(http_build_query(array_replace($parametros, ['cliente' => (int)$empresa['id_cliente']]))) ?>"><?= $h($empresa['razon_social']) ?></a></td>
            <td><?= $h($empresa['ruc']) ?></td>
            <td><?= (int)$empresa['periodos_importados'] ?></td>
            <td><?= (int)$empresa['cantidad_registros'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </details>
</section>
<?php endif; ?>
