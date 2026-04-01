import sys
import whisper
import time
import os
import warnings
import io

# Forzar salida estándar en UTF-8 para Windows
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

# Silenciar warnings innecesarios
warnings.filterwarnings("ignore", category=UserWarning)

ruta = sys.argv[1]
modelo = sys.argv[2]

start = time.time()

model = whisper.load_model(modelo)
result = model.transcribe(ruta, language="es")

end = time.time()
tiempo = round(end - start, 2)

# Mostrar duración solo para debugging (stderr)
print(f"<b>Tiempo total:</b> {tiempo} segundos<br><br>", file=sys.stderr)

# Imprimir solo la transcripción limpia
print(result["text"])
