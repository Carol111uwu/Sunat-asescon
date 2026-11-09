import assert from 'node:assert/strict';
import {readFileSync,existsSync} from 'node:fs';
import {createRequire} from 'node:module';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const require = createRequire(import.meta.url);
const base = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const selector = readFileSync(path.join(base,'publico/js/clientes/selector_clientes.js'),'utf8');
const periodos = readFileSync(path.join(base,'publico/js/interfaz/periodos_2021_actual.js'),'utf8');
const servidor = readFileSync(path.join(base,'servicios/sunat/PeriodosSire.php'),'utf8');
assert.match(selector,/select\.dispatchEvent\(new Event\('change'/);
assert.match(selector,/select\.value = opcion\.value/);
assert.match(selector,/select\[name="id_cliente"\]/);
assert.match(selector,/select\[name="cliente"\]/);
assert.match(selector,/limit[e]? = 80/);
assert.match(periodos,/Otros años/);
assert.match(periodos,/timeZone:'America\/Lima'/);
assert.match(servidor,/ANIO_INICIAL = 2021/);
assert.match(servidor,/permitido\(string \$periodo/);
let chromium;
try { chromium=require('playwright').chromium; } catch {                                                  }
if(!chromium || !existsSync(chromium.executablePath())){
  console.log('OK: verificaciones de código; prueba visual omitida (Chromium no está instalado aquí).');
  process.exit(0);
}
const navegador = await chromium.launch({headless:true,executablePath:chromium.executablePath()});
try {
  const pagina = await navegador.newPage({viewport:{width:1024,height:780}});
  const opciones = Array.from({length:130},(_,i)=>{
    const id=i+1, ruc=String(20000000000+i);
    return `<option value="${id}" data-telefono="9${String(i).padStart(8,'0')}" data-nombre="Comercial ${id}">${ruc} — Empresa ${id}</option>`;
  }).join('');
  await pagina.setContent(`<!doctype html><html lang="es"><head><meta charset="utf-8">
  <style>body{font:14px system-ui;margin:30px;background:#f3f7fb}form{width:360px;padding:20px;background:#fff}
  label{display:block;margin:15px 0}select{display:block;width:100%}</style></head><body>
  <form id="prueba"><label>Cliente<select name="cliente" id="cliente"><option value="0">Todos los clientes</option>${opciones}</select></label>
  <label>Año<select name="anio" id="sireAnio"><option value="2027">2027</option><option value="2026" selected>2026</option><option value="2025">2025</option></select></label>
  <label>Mes<select name="mes" id="sireMes"><option value="00">Todos los meses</option>${Array.from({length:12},(_,i)=>`<option value="${String(i+1).padStart(2,'0')}">${i+1}</option>`).join('')}</select></label>
  <label>Sin todos<select name="id_cliente" required id="unico"><option value="">Seleccionar cliente</option><option value="54">Empresa 54</option></select></label>
  <input type="date" name="inicio"><button>Filtrar</button></form></body></html>`);
  await pagina.addStyleTag({path:path.join(base,'publico/css/interfaz/selector_clientes.css')});
  await pagina.addScriptTag({path:path.join(base,'publico/js/clientes/selector_clientes.js')});
  await pagina.addScriptTag({path:path.join(base,'publico/js/interfaz/periodos_2021_actual.js')});
  const actual = Number(new Intl.DateTimeFormat('en-US',{timeZone:'America/Lima',year:'numeric'}).format(new Date()));
  const mes = Number(new Intl.DateTimeFormat('en-US',{timeZone:'America/Lima',month:'numeric'}).format(new Date()));
  assert.equal(await pagina.locator('select#cliente').isVisible(),false,'El select original permanece pero no se duplica');
  assert.equal(await pagina.locator('.asescon-cliente-boton').count(),2);
  await pagina.locator('.asescon-cliente-boton').first().click();
  assert.match(await pagina.locator('.asescon-cliente-nota').first().textContent(),/80 de 131/);
  await pagina.locator('.asescon-cliente-entrada').first().fill('900000078');
  assert.equal(await pagina.locator('.asescon-cliente-lista button').first().textContent(), '20000000078 — Empresa 79');
  await pagina.locator('.asescon-cliente-lista button').first().click();
  assert.equal(await pagina.locator('#cliente').inputValue(),'79','Debe mantener el id_cliente exacto');
  await pagina.locator('.asescon-cliente-boton').first().click();
  await pagina.getByRole('option',{name:'Todos los clientes'}).click();
  assert.equal(await pagina.locator('#cliente').inputValue(),'0','Todos se conserva cuando el módulo lo permite');
  await pagina.locator('.asescon-cliente-boton').last().click();
  assert.equal(await pagina.getByRole('option',{name:'Todos los clientes'}).count(),0,'No inventar "Todos" en un formulario individual');
  await pagina.locator('.asescon-cliente-entrada').last().fill('Empresa 54');
  await pagina.getByRole('option',{name:'Empresa 54'}).click();
  assert.equal(await pagina.locator('#unico').inputValue(),'54');
  const anios = await pagina.locator('#sireAnio option').evaluateAll(o=>o.map(x=>x.value));
  assert.deepEqual(anios,[String(actual),...Array.from({length:actual-2021},(_,i)=>String(actual-1-i))]);
  assert.equal(await pagina.locator('#sireAnio optgroup').count(),2);
  if(mes<12)assert.equal(await pagina.locator('#sireMes option').nth(mes+1).isDisabled(),true);
  await pagina.locator('#sireAnio').selectOption(String(actual-1));
  assert.equal(await pagina.locator('#sireMes option[value="12"]').isDisabled(),false);
  assert.equal(await pagina.locator('input[name="inicio"]').getAttribute('min'),'2021-01-01');
  await pagina.screenshot({path:'/tmp/asescon_selector_qa.png',fullPage:true});
  console.log('OK: selección exacta, 130 empresas, Todos opcional, años 2021–actual, meses válidos, fechas.');
} finally {await navegador.close();}
