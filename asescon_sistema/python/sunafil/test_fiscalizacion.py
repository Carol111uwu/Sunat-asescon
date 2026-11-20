import unittest
from fiscalizacion import documentos_fiscalizacion

class FiscalizacionTest(unittest.TestCase):
    def test_documentos_por_orden(self):
        headers = ["Tipo Documento", "Nro. de Documento", "Fecha Depósito en la Casilla Electrónica",
                   "Fecha de Notificación al Empleador", "Fecha Comparecencia", "Fecha Límite de Respuesta",
                   "Ver Documentos", "Envío de Respuesta"]
        rows = [{"cells": ["REQUERIMIENTO DE INFORMACIÓN", "001", "20/11/2026",
                            "", "", "25/11/2026", "", "Pendiente"]}]
        a = documentos_fiscalizacion(headers, rows, "ORD-2026-1", "Lima", "Abierto", lambda s:s)[0]
        b = documentos_fiscalizacion(headers, rows, "ORD-2026-2", "Lima", "Abierto", lambda s:s)[0]
        self.assertNotEqual(a["identificador_externo"], b["identificador_externo"])
        self.assertEqual(a["campos_oficiales"]["Fecha límite de respuesta"], "25/11/2026")
        self.assertEqual(a["fecha_recepcion"], "20/11/2026")
        self.assertEqual(a["archivo"], "")
    def test_no_confunde_orden_con_documento(self):
        self.assertIsNone(documentos_fiscalizacion(["Orden de Inspección", "Estado"], [], "1", "", "", lambda s:s))

if __name__ == "__main__":
    unittest.main()
