import sys
import json
import os
from pyannote.audio import Pipeline
from pydub import AudioSegment

# Validar argumento
if len(sys.argv) < 2:
    print(json.dumps({"error": "No se especificó el archivo de audio"}))
    sys.exit(1)

# Ruta al archivo de audio
archivo = sys.argv[1]

# Cargar pipeline (requiere token válido de Hugging Face)
pipeline = Pipeline.from_pretrained(
    "pyannote/speaker-diarization",
    use_auth_token="TU_TOKEN_HF_AQUI"  # reemplaza con tu token real
)

# Convertir a .wav si no lo es
if not archivo.endswith(".wav"):
    audio = AudioSegment.from_file(archivo)
    archivo_wav = archivo.rsplit(".", 1)[0] + "_convertido.wav"
    audio.set_frame_rate(16000).set_channels(1).export(archivo_wav, format="wav")
else:
    archivo_wav = archivo

# Ejecutar diarización
diarization = pipeline(archivo_wav)

# Extraer segmentos
resultados = []
for turno in diarization.itertracks(yield_label=True):
    inicio = round(turno[0].start, 2)
    fin = round(turno[0].end, 2)
    speaker = turno[2]
    resultados.append({
        "inicio": inicio,
        "fin": fin,
        "speaker": speaker,
        "texto": ""  # Puedes usar Whisper más adelante para rellenarlo
    })

# Devolver como JSON
print(json.dumps(resultados, ensure_ascii=False))
