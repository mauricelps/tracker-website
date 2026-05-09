<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP läuft!<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Teste db.php
if (file_exists('db.php')) {
    echo "db.php existiert<br>";
    try {
        require_once 'db.php';
        echo "db.php erfolgreich geladen<br>";
        
        // Teste PDO
        if (isset($pdo)) {
            echo "PDO Instanz existiert<br>";
            $pdo->query("SELECT 1");
            echo "Datenbankverbindung funktioniert!<br>";
        } else {
            echo "FEHLER: \$pdo Variable nicht gefunden<br>";
        }
    } catch (Exception $e) {
        echo "FEHLER: " . $e->getMessage() . "<br>";
    }
} else {
    echo "FEHLER: db.php nicht gefunden<br>";
}
?>