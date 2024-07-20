<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "Training";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $bookingsTableQuery = "
        CREATE TABLE IF NOT EXISTS bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            timings VARCHAR(255) NOT NULL,
            date DATE NOT NULL,
            day VARCHAR(255) NOT NULL,
            topic VARCHAR(255) NOT NULL,
            UNIQUE KEY unique_booking (name, user_id, timings, date, day, topic)
        )
    ";
    $conn->exec($bookingsTableQuery);

    $trainingsTableQuery = "
        CREATE TABLE IF NOT EXISTS trainings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            topic VARCHAR(255) NOT NULL,
            timings VARCHAR(255) NOT NULL,
            capacity INT NOT NULL
        )
    ";
    $conn->exec($trainingsTableQuery);
    $trainingsCountQuery = "SELECT COUNT(*) FROM trainings";
    $trainingsCount = $conn->query($trainingsCountQuery)->fetchColumn();

    if ($trainingsCount == 0) {
        $defaultData = [
            ['Word Processing', 'Monday, 10:00 AM<br>Wednesday, 11:00 AM<br>Friday, 12:00 PM', 4],
            ['Spreadsheets', 'Tuesday, 11:00 AM<br>Friday, 12:00 PM', 3],
            ['Email', 'Tuesday, 12:00 PM<br>Wednesday, 10:00 AM', 3],
            ['Presentation Software', 'Monday, 10:00 AM<br>Thursday, 12:00 PM', 2],
            ['Library Use', 'Wednesday, 11:00 AM', 2]
        ];

        $insertTrainingsQuery = "
            INSERT INTO trainings (topic, timings, capacity)
            VALUES (?, ?, ?)
        ";
        $insertTrainingsStmt = $conn->prepare($insertTrainingsQuery);

        foreach ($defaultData as $data) {
            $insertTrainingsStmt->execute($data);
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>