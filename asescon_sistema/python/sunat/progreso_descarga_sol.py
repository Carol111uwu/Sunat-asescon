                                                                             
from __future__ import annotations
import json
import os
import time
from pathlib import Path

class ProgresoDescargaSol:
    def __init__(self, ruta: str, id_trabajo: str):
        self.ruta=Path(ruta) if ruta else None
        self.id_trabajo=id_trabajo
        self.ultima_escritura=0.0
        self.etapa_previa=''

    def informar(self, etapa: str, descargados: int=0, total: int=0, forzar: bool=False)->None:
        if not self.ruta or not self.id_trabajo:
            return
        if etapa not in {'acceso','busqueda','documento','descarga','guardado'}:
            return
        ahora=time.monotonic()
        if not forzar and self.etapa_previa==etapa and ahora-self.ultima_escritura<2.0:
            return
        self.ultima_escritura=ahora
        self.etapa_previa=etapa
        temporal=self.ruta.with_name(self.ruta.name+'.tmp.'+str(os.getpid()))
        try:
            self.ruta.parent.mkdir(parents=True,exist_ok=True,mode=0o700)
            with temporal.open('w',encoding='utf-8') as archivo:
                os.chmod(temporal,0o600)
                json.dump({'id_trabajo':self.id_trabajo,'etapa':etapa,
                           'bytes_descargados':max(0,int(descargados)),
                           'bytes_totales':max(0,int(total))},archivo)
            os.replace(temporal,self.ruta)
        except OSError:
            try:temporal.unlink(missing_ok=True)
            except OSError:pass
