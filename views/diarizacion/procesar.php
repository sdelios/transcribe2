<?php
if (isset($_GET['idAudio'])) {
    $id = intval($_GET['idAudio']);
    header("Location: controllers/ejecutar_diarizacion.php?idAudio=$id");
    exit();
} else {
    echo "<div class='alert alert-danger'>ID de audio no especificado.</div>";
}
