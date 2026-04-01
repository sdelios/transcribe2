# revisar.py
import sys
import json
import os
import textwrap
from openai import OpenAI

# Inicializar cliente
client = OpenAI(api_key=os.getenv("OPENAI_API_KEY"))

ruta_archivo = sys.argv[1]

with open(ruta_archivo, "r", encoding="utf-8") as f:
    texto = f.read()

# Dividir texto en chunks de máximo ~10,000 caracteres
def dividir_texto(texto, longitud=10000):
    return textwrap.wrap(texto, longitud)

chunks = dividir_texto(texto)

resultado_total = ""

for i, parte in enumerate(chunks, start=1):
    print(f"Procesando fragmento {i}/{len(chunks)}...", file=sys.stderr)
    
    prompt = f"""
    Corrige el texto siguiente en español:
    1. Corrige ortografía y gramática.
    2. Corrige nombres de diputados y funcionarios públicos (si son evidentes).
    3. Convierte el texto a formato taquigráfico, sin cambiar el contenido ni resumir nada.
    4. Mantén la estructura original (no resumas, no elimines párrafos).
    Texto:
    {parte}
    """
    
    response = client.chat.completions.create(
        model="gpt-4o-mini",
        messages=[{"role": "user", "content": prompt}],
        temperature=0.3
    )
    
    resultado_total += response.choices[0].message.content.strip() + "\n"

# Guardar salida
salida = {
    "original": len(texto),
    "corregido": len(resultado_total),
    "texto_corregido": resultado_total
}

print(json.dumps(salida, ensure_ascii=False))
