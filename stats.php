<?php
// stats.php - Statistics page (uses includes/header.php + includes/footer.php)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

$page_title = 'Stats';
include __DIR__ . '/includes/header.php';

function createRankingTable(array $rows, string $title, array $headers): void {
    echo "<h2>" . htmlspecialchars($title) . "</h2>";
    if (empty($rows)) {
        echo "<p>No data available for this statistic.</p>";
        return;
    }
    echo '<table class="stats-table" role="table">';
    echo "<thead><tr>";
    foreach ($headers as $header) {
        echo "<th>" . htmlspecialchars($header) . "</th>";
    }
    echo "</tr></thead>";
    echo "<tbody>";
    $rank = 1;
    foreach ($rows as $row) {
        echo "<tr>";
        echo "<td>" . $rank++ . "</td>";
        foreach ($row as $cell) {
            echo "<td>" . htmlspecialchars($cell ?? 'N/A') . "</td>";
        }
        echo "</tr>";
    }
    echo "</tbody></table>";
}

// Inline CSS for tables (placed after includes/header - valid and effective)
?>
<style>
/* Table styling for stats page (dark mode) */
.stats-table { width: 90%; margin: 18px auto 36px; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
.stats-table thead { background: #2c3e50; }
.stats-table th, .stats-table td { padding: 10px 12px; border: 1px solid #2a2f36; text-align: left; color: #e6f7ea; }
.stats-table th { color: #bde5c8; font-weight: 700; }
.stats-table tbody tr:nth-child(even) { background: #242831; }
.stats-table tbody tr:hover { background: #2f3339; }
h1 { color: #4CAF50; }
h2 { color: #4CAF50; border-bottom: 1px solid rgba(76,175,80,0.12); padding-bottom: 6px; margin-top: 22px; }
.container { padding-bottom: 30px; }
</style>

<?php
// Fetch data
try {
    // Total KM per driver (delivered)
    $km_sql = "SELECT u.username, ROUND(SUM(j.driven_distance_km)) AS total_km
               FROM jobs j
               JOIN users u ON j.driver_steam_id = u.steamId
               WHERE j.status = 'delivered'
               GROUP BY u.username
               ORDER BY total_km DESC";
    $km_rows = $pdo->query($km_sql)->fetchAll(PDO::FETCH_ASSOC);

    // Most used truck per driver (we aggregate and pick top per driver in PHP)
    $truck_sql = "SELECT u.username, j.truck, COUNT(*) AS cnt
                  FROM jobs j
                  JOIN users u ON j.driver_steam_id = u.steamId
                  WHERE j.status = 'delivered' AND j.truck IS NOT NULL
                  GROUP BY u.username, j.truck
                  ORDER BY u.username, cnt DESC";
    $truck_rows = $pdo->query($truck_sql)->fetchAll(PDO::FETCH_ASSOC);

    // Top cargo types overall (top 10)
    $cargo_sql = "SELECT j.cargo, COUNT(*) AS cnt
                  FROM jobs j
                  WHERE j.status = 'delivered' AND j.cargo IS NOT NULL
                  GROUP BY j.cargo
                  ORDER BY cnt DESC
                  LIMIT 10";
    $cargo_rows = $pdo->query($cargo_sql)->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 start cities
    $start_city_sql = "SELECT source_city, COUNT(*) AS cnt
                       FROM jobs
                       WHERE status = 'delivered' AND source_city IS NOT NULL
                       GROUP BY source_city
                       ORDER BY cnt DESC
                       LIMIT 10";
    $start_city_rows = $pdo->query($start_city_sql)->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 start companies
    $start_company_sql = "SELECT source_company, COUNT(*) AS cnt
                          FROM jobs
                          WHERE status = 'delivered' AND source_company IS NOT NULL
                          GROUP BY source_company
                          ORDER BY cnt DESC
                          LIMIT 10";
    $start_company_rows = $pdo->query($start_company_sql)->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 destination cities
    $dest_city_sql = "SELECT destination_city, COUNT(*) AS cnt
                      FROM jobs
                      WHERE status = 'delivered' AND destination_city IS NOT NULL
                      GROUP BY destination_city
                      ORDER BY cnt DESC
                      LIMIT 10";
    $dest_city_rows = $pdo->query($dest_city_sql)->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 destination companies
    $dest_company_sql = "SELECT destination_company, COUNT(*) AS cnt
                         FROM jobs
                         WHERE status = 'delivered' AND destination_company IS NOT NULL
                         GROUP BY destination_company
                         ORDER BY cnt DESC
                         LIMIT 10";
    $dest_company_rows = $pdo->query($dest_company_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div style='color:#ffb3b3;padding:12px;border-radius:6px;background:#3a1b1b;'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    include __DIR__ . '/includes/footer.php';
    exit;
}
?>

<h1>Statistics</h1>

<?php
createRankingTable($km_rows, "Total KM per Driver", ["Rank", "Driver", "KM"]);

// Process trucks to top per driver
$top_trucks_by_user = [];
foreach ($truck_rows as $r) {
    $user = $r['username'];
    if (!isset($top_trucks_by_user[$user])) {
        $top_trucks_by_user[$user] = $r; // first row per user is highest due to ORDER BY
    }
}
$truck_table_rows = [];
foreach ($top_trucks_by_user as $t) {
    $truck_table_rows[] = ['username' => $t['username'], 'truck' => $t['truck'], 'count' => $t['cnt']];
}
createRankingTable($truck_table_rows, "Most Used Truck per Driver (one per driver)", ["Rank", "Driver", "Truck", "Trips"]);
createRankingTable($cargo_rows, "Top 10 Cargo Types (overall)", ["Rank", "Cargo", "Count"]);
createRankingTable($start_city_rows, "Top 10 Start Cities", ["Rank", "City", "Count"]);
createRankingTable($start_company_rows, "Top 10 Start Companies", ["Rank", "Company", "Count"]);
createRankingTable($dest_city_rows, "Top 10 Destination Cities", ["Rank", "City", "Count"]);
createRankingTable($dest_company_rows, "Top 10 Destination Companies", ["Rank", "Company", "Count"]);
?>

<?php include __DIR__ . '/includes/footer.php'; ?>