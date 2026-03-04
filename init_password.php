<?php
require_once 'db.php';

$users = [
    ['username' => 'admin',        'password' => 'amine123456789'],
    ['username' => 'asma.beghdad', 'password' => 'asma2026'],
    ['username' => 'b.bouzouad',   'password' => 'aymen2022'],
    ['username' => 's.meziane',    'password' => 'sara123'],
];

$pdo = getDB();

foreach ($users as $u) {
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE utilisateurs SET password_hash = :h WHERE username = :u');
    $stmt->execute([':h' => $hash, ':u' => $u['username']]);
    echo "✅ " . $u['username'] . " mis à jour !<br>";
}

echo "<br><b>Terminé ! Vous pouvez vous connecter maintenant.</b>";
?>
```


http://localhost/suivi_factures/init_password.php