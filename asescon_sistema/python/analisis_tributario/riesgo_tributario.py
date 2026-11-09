import mysql.connector
import json
import os
from datetime import datetime

from conexion_mysql import conectar

def calcular_riesgo(
    obligaciones_vencidas,
    obligaciones_pendientes,
    alertas_sunat=0,
    alertas_sunafil=0
):

    puntaje = 0

    if obligaciones_vencidas >= 3:
        puntaje += 4
    elif obligaciones_vencidas >= 1:
        puntaje += 2

    if obligaciones_pendientes >= 3:
        puntaje += 2
    elif obligaciones_pendientes >= 1:
        puntaje += 1

    if alertas_sunat >= 2:
        puntaje += 2
    elif alertas_sunat >= 1:
        puntaje += 1

    if alertas_sunafil >= 1:
        puntaje += 2

    if puntaje >= 7:
        nivel = "ALTO"
    elif puntaje >= 4:
        nivel = "MEDIO"
    else:
        nivel = "BAJO"

    return nivel, puntaje

def obtener_clientes(cursor):

    consulta = """
        SELECT
            id_cliente,
            ruc,
            razon_social,
            estado
        FROM clientes
        WHERE estado = 'ACTIVO'
        ORDER BY razon_social
    """

    cursor.execute(consulta)

    return cursor.fetchall()

def obtener_obligaciones(cursor, id_cliente):

    consulta = """
        SELECT
            estado_pago,
            fecha_vencimiento
        FROM declaraciones_sunat
        WHERE id_cliente = %s
    """

    cursor.execute(consulta, (id_cliente,))

    return cursor.fetchall()

def analizar_cliente(cursor, cliente):

    obligaciones = obtener_obligaciones(
        cursor,
        cliente["id_cliente"]
    )

    vencidas = 0
    pendientes = 0

    fecha_actual = datetime.now().date()

    for obligacion in obligaciones:

        estado = str(
            obligacion.get("estado_pago") or ""
        ).upper()

        fecha_vencimiento = obligacion.get(
            "fecha_vencimiento"
        )

        if estado in ["VENCIDO", "VENCIDA"]:

            vencidas += 1

        elif (
            estado in ["PENDIENTE", "PENDIENTES"]
            and fecha_vencimiento
            and fecha_vencimiento < fecha_actual
        ):

            vencidas += 1

        elif estado in ["PENDIENTE", "PENDIENTES"]:

            pendientes += 1

    alertas_sunat = 0
    alertas_sunafil = 0

    nivel, puntaje = calcular_riesgo(
        vencidas,
        pendientes,
        alertas_sunat,
        alertas_sunafil
    )

    return {

        "id_cliente": cliente["id_cliente"],

        "ruc": cliente["ruc"],

        "razon_social": cliente["razon_social"],

        "estado_cliente": cliente["estado"],

        "obligaciones_vencidas": vencidas,

        "obligaciones_pendientes": pendientes,

        "alertas_sunat": alertas_sunat,

        "alertas_sunafil": alertas_sunafil,

        "puntaje": puntaje,

        "nivel": nivel
    }

def guardar_json(resultados):

    carpeta_proyecto = os.path.abspath(
        os.path.join(
            os.path.dirname(__file__),
            "..",
            ".."
        )
    )

    ruta_storage = os.path.join(
        os.path.dirname(carpeta_proyecto),
        "asescon_datos_privados",
        "almacenamiento"
    )

    os.makedirs(
        ruta_storage,
        exist_ok=True
    )

    ruta_json = os.path.join(
        ruta_storage,
        "riesgo_tributario.json"
    )

    datos = {

        "fecha_analisis":
            datetime.now().strftime(
                "%Y-%m-%d %H:%M:%S"
            ),

        "clientes": resultados
    }

    with open(
        ruta_json,
        "w",
        encoding="utf-8"
    ) as archivo:

        json.dump(
            datos,
            archivo,
            ensure_ascii=False,
            indent=4
        )

    print()
    print("Resultado guardado en:")
    print(ruta_json)

def main():

    conexion = conectar()

    if not conexion:
        return

    try:

        cursor = conexion.cursor(
            dictionary=True
        )

        clientes = obtener_clientes(
            cursor
        )

        resultados = []

        for cliente in clientes:

            resultado = analizar_cliente(
                cursor,
                cliente
            )

            resultados.append(
                resultado
            )

        guardar_json(
            resultados
        )

        print()
        print("==========================================")
        print("       ANÁLISIS TRIBUTARIO ASESCON")
        print("==========================================")

        for resultado in resultados:

            print(
                f"{resultado['razon_social']} | "
                f"RUC: {resultado['ruc']} | "
                f"Riesgo: {resultado['nivel']} | "
                f"Puntaje: {resultado['puntaje']}"
            )

        print("==========================================")

        cursor.close()

    except mysql.connector.Error as error:

        print("Error al consultar MySQL:")
        print(error)

    finally:

        conexion.close()

if __name__ == "__main__":
    main()
