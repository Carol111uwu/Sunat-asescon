import unittest
from categorias import interpretar_tabla
from consultar_casilla_sunafil import parse_date


class CategoriasTest(unittest.TestCase):
    def test_acciones_previas(self):
        headers = ["", "Tipo de Requerimiento", "Registro", "Fecha de Depósito",
                   "Fecha Acuse de Recibo", "Fecha de Notificación", "Plazo",
                   "Fecha Límite de Presentación", "Opción", "Ver Documento"]
        cells = ["", "CARTAS DISUASIVAS GENERAL", "0000066199 - 2026 - SUNAFIL/ILM/SIFS",
                 "24/08/2026 13:33", "31/08/2026 16:30", "31/08/2026", "8",
                 "10/09/2026", "", ""]
        row = interpretar_tabla(headers, [{"cells": cells}], "ACCIONES_PREVIAS", parse_date)[0]
        self.assertEqual(row["fecha_recepcion"], "2026-08-31 00:00:00")
        self.assertEqual(row["campos_oficiales"]["Fecha Límite de Presentación"], "10/09/2026")
        self.assertEqual(row["campos_oficiales"]["Plazo"], "8")
        self.assertEqual(row["campos_oficiales"]["Registro"], cells[2])
        cells[4] = "01/09/2026"
        again = interpretar_tabla(headers, [{"cells": cells}], "ACCIONES_PREVIAS", parse_date)[0]
        self.assertEqual(row["identificador_externo"], again["identificador_externo"])

    def test_rechaza_menu(self):
        self.assertIsNone(interpretar_tabla(["Documento"], [{"cells":["Notificaciones de Orientaciones"]}], "ACCIONES_PREVIAS", parse_date))

    def test_campos_dinamicos(self):
        row = interpretar_tabla(["Orden de Inspección","Intendencia","Estado"], [{"cells":["123","Lima","Pendiente"]}], "FISCALIZACION", parse_date)[0]
        self.assertEqual(row["campos_oficiales"]["Intendencia"], "Lima")
        self.assertNotIn("Plazo", row["campos_oficiales"])


if __name__ == "__main__":
    unittest.main()
