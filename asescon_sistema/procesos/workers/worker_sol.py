import os
import time
import subprocess
import redis

conexion = redis.Redis(
    host=os.getenv("ASESCON_REDIS_HOST", "redis"),
    port=int(os.getenv("ASESCON_REDIS_PORT", "6379")),
    decode_responses=True,
    socket_connect_timeout=5,
    socket_timeout=15
)

proyecto = "/var/www/html/asescon_sistema"
almacenamiento = "/var/www/html/asescon_datos_privados/almacenamiento"

activo = os.getenv("ASESCON_WORKER_SOL_ACTIVO", "0") == "1"

entorno = os.environ.copy()
entorno["ASESCON_PYTHON_CLI"] = "/opt/asescon-venv/bin/python"
entorno["ASESCON_PLAYWRIGHT_BROWSERS_PATH"] = "/ms-playwright"
entorno["ASESCON_WORKER_SOLO_PENDIENTES"] = "1"


def procesar_buzon():
    os.makedirs(
        almacenamiento + "/sincronizacion",
        exist_ok=True
    )

    resultado = subprocess.run(
        [
            "flock", "-n", "-E", "75",
            almacenamiento + "/sincronizacion/worker_chromium.lock",
            "bash",
            proyecto + "/procesos/buzon_sol/procesar_cola.sh",
            "0"
        ],
        env=entorno
    )

    if resultado.returncode not in (0, 75):
        print("Error del procesador SOL:", resultado.returncode)


print("Worker SOL iniciado")
print("Consultas reales habilitadas:", activo)

while True:
    try:
        conexion.blpop("asescon:sol:despertar", timeout=10)
    except redis.RedisError:
        time.sleep(5)

    if activo:
        procesar_buzon()
