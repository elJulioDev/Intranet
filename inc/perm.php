<?php
// intranet/inc/perm.php (PHP 5.6)

function user_can(PDO $pdo, $funcionarioId, $recursoCodigo, $accionCodigo) {
  if ($funcionarioId <= 0) return false;

  // superadmin bypass
  $st = $pdo->prepare("SELECT is_superadmin FROM funcionarios WHERE id=? LIMIT 1");
  $st->execute(array((int)$funcionarioId));
  if ((int)$st->fetchColumn() === 1) return true;

  $sql = "
    SELECT 1
    FROM funcionario_roles fr
    JOIN rol_permisos rp ON rp.rol_id = fr.rol_id
    JOIN recursos re ON re.id = rp.recurso_id
    JOIN acciones ac ON ac.id = rp.accion_id
    WHERE fr.funcionario_id = ?
      AND re.codigo = ?
      AND re.activo = 1
      AND ac.codigo = ?
      AND rp.permitido = 1
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute(array((int)$funcionarioId, (string)$recursoCodigo, (string)$accionCodigo));
  return (bool)$st->fetchColumn();
}

function require_perm(PDO $pdo, $recursoCodigo, $accionCodigo) {
  $fid = !empty($_SESSION['funcionario_id']) ? (int)$_SESSION['funcionario_id'] : 0;
  if (!user_can($pdo, $fid, $recursoCodigo, $accionCodigo)) {
    http_response_code(403);
    exit('Acceso denegado.');
  }
}
