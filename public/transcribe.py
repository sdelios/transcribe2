import sys
import os
import warnings
import contextlib
import traceback

# Silenciar warnings (incluido FP16 on CPU)
warnings.filterwarnings("ignore")

def suppress_output():
    @contextlib.contextmanager
    def _s():
        with open(os.devnull, "w") as devnull:
            old_out, old_err = sys.stdout, sys.stderr
            sys.stdout, sys.stderr = devnull, devnull
            try:
                yield
            finally:
                sys.stdout, sys.stderr = old_out, old_err
    return _s()

def download_youtube_to_mp3(url: str, ruta_mp3_final: str):
    """
    Descarga audio desde YouTube con yt-dlp y lo convierte a MP3 (FFmpeg).
    ruta_mp3_final: ruta ABSOLUTA al MP3 de salida.
    """
    import yt_dlp

    outdir = os.path.dirname(os.path.normpath(ruta_mp3_final))
    base   = os.path.splitext(os.path.basename(ruta_mp3_final))[0]
    outtpl = os.path.join(outdir, base + ".%(ext)s")  # NO fijar .mp3 aquí

    os.makedirs(outdir, exist_ok=True)

    class _Logger:
        def debug(self, msg): pass
        def warning(self, msg): pass
        def error(self, msg): print(msg)

    common_opts = {
        "logger": _Logger(),
        "quiet": True,
        "no_warnings": True,
        "noplaylist": True,
        "outtmpl": outtpl,
        # a veces ayuda con vídeos "raros"
        "extractor_args": {"youtube": {"player_client": ["android", "web"]}},
        "postprocessors": [{
            "key": "FFmpegExtractAudio",
            "preferredcodec": "mp3",
            "preferredquality": "192",
        }],
        # Si necesitas ubicar ffmpeg manualmente:
        # "ffmpeg_location": r"C:\ffmpeg\bin",
    }

    # 1er intento: cascada muy tolerante
    opts1 = dict(common_opts)
    opts1["format"] = "ba[ext=m4a]/ba/b/bestaudio/best"

    # 2º intento (fallback)
    opts2 = dict(common_opts)
    opts2["format"] = "bestaudio/best"

    def _run(ydl_opts):
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            ydl.download([url])

    try:
        _run(opts1)
    except Exception:
        _run(opts2)

    # yt-dlp habrá dejado el .mp3 con nombre base + ".mp3"
    ruta_convertida = os.path.join(outdir, base + ".mp3")
    if not os.path.isfile(ruta_convertida):
        raise RuntimeError("No se generó el MP3 esperado: " + ruta_convertida)

    # Si no coincide con el nombre final que queremos, renombra:
    if os.path.normpath(ruta_convertida) != os.path.normpath(ruta_mp3_final):
        if os.path.exists(ruta_mp3_final):
            os.remove(ruta_mp3_final)
        os.replace(ruta_convertida, ruta_mp3_final)

def transcribir_mp3(ruta_mp3: str):
    import whisper
    model = whisper.load_model("base")
    sys.stdout.reconfigure(encoding="utf-8")
    result = model.transcribe(ruta_mp3, language="es")
    print(result["text"])

def main():
    try:
        if len(sys.argv) < 2:
            raise SystemExit(
                "Uso:\n"
                "  youtube: python transcribe.py \"<url>\" \"<ruta_salida>.mp3\"\n"
                "  local:   python transcribe.py local \"<ruta_audio_existente>\""
            )

        src = sys.argv[1]

        if src.lower() == "local":
            if len(sys.argv) < 3:
                raise SystemExit("Falta ruta de audio local.")
            ruta_local = sys.argv[2]
            if not os.path.isfile(ruta_local):
                raise SystemExit("No existe el archivo local: " + ruta_local)
            transcribir_mp3(ruta_local)
            return

        # Modo YouTube
        if len(sys.argv) < 3:
            raise SystemExit("Falta ruta de salida MP3 para YouTube.")
        url = src
        ruta_mp3_final = sys.argv[2]

        os.makedirs(os.path.dirname(os.path.normpath(ruta_mp3_final)), exist_ok=True)

        # Descarga & convierte
        with suppress_output():
            download_youtube_to_mp3(url, ruta_mp3_final)

        # Transcribe
        transcribir_mp3(ruta_mp3_final)

    except Exception:
        traceback.print_exc()
        sys.exit(1)

if __name__ == "__main__":
    main()
