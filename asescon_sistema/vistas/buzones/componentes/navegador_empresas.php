<?php
// Vista compartida. $clientes_disponibles respeta los permisos de la sesión.
$clientesNavegables=array_values($clientes_disponibles??[]);
$nombreNav=($buzonCodigo??'SOL')==='SUNAFIL'?'sunafil':'sol';
?>
<?php if((int)$idCliente===0 && $clientesNavegables!==[]): ?>
<div class="buzon-navegador-busqueda" data-buzon-navegar
     data-clientes="<?=htmlspecialchars(json_encode(array_map(static fn(array $c,int $i):array=>[
       'id'=>(int)$c['id_cliente'],'nombre'=>(string)$c['razon_social'],'ruc'=>(string)$c['ruc'],'pagina'=>$i+1
     ],$clientesNavegables,array_keys($clientesNavegables)),JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP),ENT_QUOTES,'UTF-8')?>">
  <label for="buscarEmpresaBuzon_<?=$nombreNav?>"><strong>Buscar empresa dentro de todas</strong></label>
  <div class="buzon-navegador-input">
    <input type="search" id="buscarEmpresaBuzon_<?=$nombreNav?>"
       list="listaEmpresaBuzon_<?=$nombreNav?>" placeholder="Nombre o RUC de la empresa" autocomplete="off">
    <datalist id="listaEmpresaBuzon_<?=$nombreNav?>">
      <?php foreach($clientesNavegables as $empresaNav): ?>
      <option value="<?=htmlspecialchars((string)$empresaNav['ruc'].' · '.(string)$empresaNav['razon_social'],ENT_QUOTES,'UTF-8')?>"></option>
      <?php endforeach; ?>
    </datalist>
    <button type="button" class="reference-btn" data-ir-empresa-buzon>Buscar</button>
  </div>
  <small role="status" data-mensaje-navegador>Las flechas y esta búsqueda cambian la empresa usando únicamente los datos guardados.</small>
</div>
<?php endif; ?>
