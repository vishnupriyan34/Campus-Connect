<?php
// One-time schema import script
// Visit this once to create your tables, then use seed.php as normal.

require_once __DIR__ . '/../config/db.php';

echo "<!DOCTYPE html><html><head><title>Schema Import</title>";
echo "<style>body{font-family:sans-serif;background:#0b0f19;color:#f8fafc;padding:2rem;}pre{background:#131b2e;padding:1rem;border-radius:8px;border:1px solid rgba(255,255,255,0.08);white-space:pre-wrap;}</style>";
echo "</head><body><h1>Campus Connect Schema Import</h1><pre>";

$schemaFile = __DIR__ . '/../schema.sql';

if (!file_exists($schemaFile)) {
    echo "ERROR: schema.sql not found at $schemaFile\n";
} else {
    try {
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
        echo "Schema imported successfully! Tables have been created.\n";
        echo "\nYou can now run seed.php to populate sample data.\n";
    } catch (PDOException $e) {
        echo "Schema import failed: " . $e->getMessage() . "\n";
    }
}

echo "</pre><a href='../scratch/seed.php' style='color:#6366f1;text-decoration:none;font-weight:bold;'>&rarr; Go to Seeder</a></body></html>";
?>
