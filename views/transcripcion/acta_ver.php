<?php
$srcdoc = htmlspecialchars($html ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if (!$srcdoc) {
  echo '<div class="alert alert-warning">No hay Acta guardada para esta transcripción.</div>';
} else {
  echo '<iframe class="acta-frame" style="width:100%;min-height:900px;border:1px solid #e5e7eb;background:#fff;" srcdoc="'.$srcdoc.'"></iframe>';
}
?>
