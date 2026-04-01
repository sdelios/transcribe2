import sys
import json
import language_tool_python
import io
import os

if len(sys.argv) < 2:
    print(json.dumps({"error": "No se proporcionó archivo"}))
    sys.exit(1)

ruta_archivo = sys.argv[1]

try:
    with open(ruta_archivo, "r", encoding="utf-8") as f:
        texto = f.read()
except Exception as e:
    print(json.dumps({"error": f"No se pudo leer el archivo: {str(e)}"}))
    sys.exit(1)

tool = language_tool_python.LanguageTool('es')
matches = tool.check(texto)

sugerencias = []
for match in matches:
    sugerencias.append({
        "offset": match.offset,
        "length": match.errorLength,
        "mensaje": match.message,
        "reemplazos": match.replacements,
        "texto": texto[match.offset:match.offset + match.errorLength]
    })

# 👇 FORZAR salida en UTF-8 para Windows (clave)
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
print(json.dumps(sugerencias, ensure_ascii=False))
