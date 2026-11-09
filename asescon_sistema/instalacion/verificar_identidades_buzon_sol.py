                                                                    
import sys
import unittest
from unittest.mock import patch
from pathlib import Path

sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'python'/'sunat'))
from consultar_buzon_sol import identificador, read_inbox, desambiguar_notificaciones, identificador_desambiguado
from obtener_pdf_sol import _identidad_fila_tabla, _identidad_fila_generica
from acceso_sol import ErrorAccesoSol


class IdentidadesBuzonSol(unittest.TestCase):
    def test_id_oficial_no_depende_de_fecha_o_asunto(self):
        enlace=[{'href':'https://e-menu.sunat.gob.pe/lectura?idNotificacion=N-12345'}]
        primero,confirmado=identificador('Notificación original','COACTIVA','2026-10-29 12:00:00',enlace)
        despues,_=identificador('Asunto corregido','COACTIVA','2026-10-29 12:04:00',enlace)
        self.assertTrue(confirmado)
        self.assertEqual(primero,despues)

    def test_distintas_notificaciones_no_se_confunden(self):
        a,ca=identificador('Asunto común','SUNAT','2026-10-29 12:00:00',[{'href':'https://sunat.gob.pe/?idMensaje=MSG-111'}])
        b,cb=identificador('Asunto común','SUNAT','2026-10-29 12:00:00',[{'href':'https://sunat.gob.pe/?idMensaje=MSG-222'}])
        self.assertTrue(ca and cb)
        self.assertNotEqual(a,b)

    def test_atributo_identidad_de_fila(self):
        clave,confirmado=identificador('Notificación','SUNAT','2026-10-29',atributos={'data-id-notificacion':'871234'})
        self.assertTrue(confirmado)
        self.assertTrue(clave.startswith('SUNAT2-'))

    def test_descarga_no_es_identidad_de_notificacion(self):
        _,confirmado=identificador('Notificación','SUNAT','2026-10-29',[
            {'href':'javascript:goArchivoDescarga(1141596363,0,1097506321)'}])
        self.assertFalse(confirmado)

    def test_respaldo_compatible(self):
        a,confirmado=identificador('Mismo asunto','SUNAT','2026-10-29 01:02:03')
        b,_=identificador('Mismo asunto','SUNAT','2026-10-29 01:02:03',[])
        self.assertFalse(confirmado)
        self.assertEqual(a,b)

    def test_no_fusionar_filas_sin_identificador_oficial(self):
        identidad,_=identificador('Mismo asunto','SUNAT','2026-10-29 01:02:03')
        filas=[{'identificador_externo':identidad,'clave_oficial':False,
                'asunto':'Mismo asunto','fecha_recepcion':'2026-10-29 01:02:03'} for _ in range(2)]
        with patch('consultar_buzon_sol._registros_pagina',return_value=filas), \
             patch('consultar_buzon_sol._total_visible',return_value=2), \
             patch('consultar_buzon_sol._siguiente_pagina',return_value=None):
            with self.assertRaises(ErrorAccesoSol):read_inbox(None)

    def test_dos_avisos_similares_con_contenido_distinto_se_guardan_separados(self):
        identidad,_=identificador('Aviso tributario','Notificación SOL','2026-10-30 08:00:00')
        base={'identificador_externo':identidad,'clave_oficial':False,
              'asunto':'Aviso tributario','tipo_documento':'Notificación SOL',
              'fecha_recepcion':'2026-10-30 08:00:00'}
        a=dict(base,observaciones='Aviso tributario | 30/10/2026 | Resolución 001')
        b=dict(base,observaciones='Aviso tributario | 30/10/2026 | Resolución 002')
        resultados=desambiguar_notificaciones([a,b])
        self.assertEqual(len(resultados),2)
        self.assertTrue(all(r['identificador_externo'].startswith('SUNAT3-') for r in resultados))
        self.assertNotEqual(resultados[0]['identificador_externo'],resultados[1]['identificador_externo'])
        invertidos=desambiguar_notificaciones([b,a])
        self.assertEqual({r['identificador_externo'] for r in resultados},
                         {r['identificador_externo'] for r in invertidos})
        self.assertEqual(resultados[0]['identificador_externo'], identificador_desambiguado(identidad,a))

    def test_una_notificacion_sin_colision_mantiene_su_identidad_historica(self):
        identidad,_=identificador('Otra notificación','Notificación SOL','2026-10-30 08:01:00')
        fila={'identificador_externo':identidad,'clave_oficial':False,'asunto':'Otra notificación',
              'tipo_documento':'Notificación SOL','fecha_recepcion':'2026-10-30 08:01:00',
              'observaciones':'Aviso único'}
        self.assertEqual(desambiguar_notificaciones([fila])[0]['identificador_externo'],identidad)

    def test_paginacion_permite_claves_base_iguales_si_el_contenido_es_distinto(self):
        identidad,_=identificador('Aviso coincidente','Notificación SOL','2026-10-30 08:00:00')
        filas=[{'identificador_externo':identidad,'clave_oficial':False,
                'asunto':'Aviso coincidente','tipo_documento':'Notificación SOL',
                'fecha_recepcion':'2026-10-30 08:00:00','observaciones':'Aviso número '+s}
               for s in ('A','B')]
        with patch('consultar_buzon_sol._registros_pagina',return_value=filas), \
             patch('consultar_buzon_sol._total_visible',return_value=2), \
             patch('consultar_buzon_sol._siguiente_pagina',return_value=None):
            self.assertEqual(len(read_inbox(None)),2)

    def test_descarga_recupera_la_misma_identidad_desambiguada(self):
        class FilaTabla:
            def evaluate(self,_):
                return {'headers':['Asunto','Tipo','Fecha recepción','Documento'],
                        'cells':['Aviso tributario','Notificación SOL','30/10/2026 08:00','Resolución 001'],
                        'atributos':{},'links':[]}
        fila=FilaTabla()
        base=_identidad_fila_tabla(fila)
        esperado=identificador_desambiguado(base,{
            'asunto':'Aviso tributario','tipo_documento':'Notificación SOL',
            'fecha_recepcion':'2026-10-30 08:00:00',
            'observaciones':'Aviso tributario | Notificación SOL | 30/10/2026 08:00 | Resolución 001'})
        self.assertEqual(_identidad_fila_tabla(fila,True),esperado)
        self.assertNotEqual(_identidad_fila_tabla(fila,True),base)

    def test_descarga_tarjeta_con_id_desambiguado(self):
        class FilaTarjeta:
            def evaluate(self,_):
                return {'texto':'Notificación SOL · Resolución 001 · 30/10/2026',
                        'atributos':{},'links':[]}
        fila=FilaTarjeta()
        self.assertTrue(_identidad_fila_generica(fila).startswith('SUNAT2-'))
        self.assertTrue(_identidad_fila_generica(fila,True).startswith('SUNAT3-'))


if __name__=='__main__':unittest.main()
