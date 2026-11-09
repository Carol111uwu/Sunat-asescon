import mysql.connector

from configuracion_bd import obtener_configuracion_bd


def conectar():
    configuracion = obtener_configuracion_bd()
    return mysql.connector.connect(
        host=configuracion["host"],
        user=configuracion["usuario"],
        password=configuracion["password"],
        database=configuracion["base_datos"],
        port=configuracion["puerto"],
    )
