<?php
// intranet/admin/marcaciones/_helpers.php (PHP 5.6)

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function clean_rut_base($s){
  // deja solo dígitos (sin DV), ej "5.679.268-3" -> "5679268"
  $s = preg_replace('/[^0-9]/', '', (string)$s);
  return ltrim($s, '0'); // por si viene con ceros
}

function parse_fecha($txt){
  $txt = trim((string)$txt);

  $fmts = array('d-m-Y', 'd/m/Y', 'Y-m-d', 'Y/m/d');
  foreach ($fmts as $f) {
    $dt = DateTime::createFromFormat($f, $txt);
    if ($dt) {
      // Validación estricta: que al formatear vuelva igual
      if ($dt->format($f) === $txt) {
        return $dt->format('Y-m-d');
      }
    }
  }
  return null;
}

function find_ref_by_rut_base(PDO $pdo, $rutBase){
  $rutBase = preg_replace('/\D+/', '', (string)$rutBase);
  $rutBase = ltrim($rutBase, '0');
  if ($rutBase === '') return array(null, 'RUT base vacío');

  $st = $pdo->prepare("
    SELECT id, rut
    FROM funcionarios_ref
    WHERE rut_base = ?
    LIMIT 2
  ");
  $st->execute(array($rutBase));
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  if (count($rows) === 1) return array((int)$rows[0]['id'], null);
  if (count($rows) > 1) return array(null, 'Match ambiguo');
  return array(null, 'Sin match en funcionarios_ref');
}

function parse_hora($txt){
  $txt = trim((string)$txt);
  // permite "7:48:41" (G) y "07:48:41" (H)
  $fmts = array('G:i:s', 'H:i:s', 'G:i', 'H:i');
  foreach ($fmts as $f){
    $dt = DateTime::createFromFormat($f, $txt);
    if ($dt){
      $out = $dt->format('H:i:s');
      return $out;
    }
  }
  return null;
}

function detect_delimiter($line){
  $candidates = array("\t", ";", ",");
  $best = "\t"; $bestCount = -1;
  foreach($candidates as $d){
    $count = substr_count($line, $d);
    if ($count > $bestCount){ $bestCount = $count; $best = $d; }
  }
  return $best;
}

/**
 * Match por número base (sin DV):
 * - Compara LEFT(rut_clean, len(base)) = base
 * - Si 1 match => ok
 * - Si 0 => null
 * - Si >1 => ambiguo
 */
function find_funcionario_by_rut_base(PDO $pdo, $rutBase){
  $rutBase = clean_rut_base($rutBase);
  if ($rutBase === '') return array(null, 'RUT base vacío');

  $sql = "
    SELECT id, rut
    FROM funcionarios
    WHERE activo=1
      AND LEFT(REPLACE(REPLACE(rut,'.',''),'-',''), LENGTH(?)) = ?
    LIMIT 2
  ";
  $st = $pdo->prepare($sql);
  $st->execute(array($rutBase, $rutBase));
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  if (count($rows) === 1){
    return array((int)$rows[0]['id'], null);
  }
  if (count($rows) === 0){
    return array(null, 'Sin match en funcionarios');
  }
  return array(null, 'Match ambiguo (más de 1)');
}
