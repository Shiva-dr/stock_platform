<?php
// This is a one-time script to populate our database with manual historical data.
// Run it by visiting http://localhost/stock_platform/seed_data.php in your browser.

require_once 'php/config.php'; // Use our main config file for the database connection

echo "<h1>Seeding Historical Stock Data...</h1>";

// Manual data similar to our Python dictionary
$manual_data = [
    "NABIL" => [550, 552, 551, 555, 553, 558, 560, 559, 562, 565, 563, 568, 570],
    "NIMB"  => [450, 451, 449, 453, 452, 455, 457, 456, 458, 460, 459, 462, 465],
    "HDL"   => [1800, 1805, 1802, 1810, 1808, 1815, 1820, 1818, 1825, 1830, 1828, 1835, 1840],
    "NRIC"  => [900, 902, 901, 905, 903, 908, 910, 909, 912, 915, 913, 918, 920],
    "SHIVM" => [700, 701, 699, 703, 702, 705, 707, 706, 708, 710, 709, 712, 715],
    "NTC"   => [1200, 1205, 1202, 1210, 1208, 1215, 1220, 1218, 1225, 1230, 1228, 1235, 1240]
];

try {
    // First, clear any old data to prevent duplicates if you run this script multiple times
    $conn->query("DELETE FROM historical_data");
    echo "<p style='color:orange;'>Cleared old historical data.</p>";

    // Prepare the statement for inserting data
    $stmt_insert = $conn->prepare("INSERT INTO historical_data (company_id, price_date, close_price) VALUES (?, ?, ?)");

    // Loop through each company in our manual data
    foreach ($manual_data as $ticker => $prices) {
        // Find the company's ID from the database
        $stmt_company = $conn->prepare("SELECT id FROM companies WHERE ticker = ?");
        $stmt_company->bind_param("s", $ticker);
        $stmt_company->execute();
        $result = $stmt_company->get_result();

        if ($result->num_rows > 0) {
            $company = $result->fetch_assoc();
            $company_id = $company['id'];
            
            echo "<p>Processing data for <strong>$ticker</strong> (ID: $company_id)...</p>";

            // Loop through the prices for the current company
            $day_counter = 0;
            foreach ($prices as $price) {
                // We'll create fake dates, counting backwards from today
                $date = new DateTime();
                $date->modify("-$day_counter days");
                $formatted_date = $date->format('Y-m-d');
                
                // Bind the values and execute the insert statement
                $stmt_insert->bind_param("isd", $company_id, $formatted_date, $price);
                $stmt_insert->execute();
                $day_counter++;
            }
            echo "<p style='color:green;'>Successfully inserted " . count($prices) . " price points for $ticker.</p>";
        } else {
            echo "<p style='color:red;'>Could not find company with ticker $ticker in the database.</p>";
        }
        $stmt_company->close();
    }
    $stmt_insert->close();
    echo "<h2>Data seeding complete!</h2>";
    echo "<p>You can now delete this 'seed_data.php' file.</p>";

} catch (Exception $e) {
    die("An error occurred: " . $e->getMessage());
}
?>