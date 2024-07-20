<?php
session_start();
require_once 'db_config.php';

// SQL query for fetching the workshop data with available slots to book
$workshopQuery = "
    SELECT t.topic, 
        GROUP_CONCAT(DISTINCT t.timings SEPARATOR '<br>') AS timings,
        MAX(t.capacity) AS capacity
    FROM trainings t
    LEFT JOIN (
        SELECT topic, timings, COUNT(*) AS bookedSlots
        FROM bookings
        GROUP BY topic, timings
    ) b ON t.topic = b.topic AND t.timings = b.timings
    GROUP BY t.topic
    HAVING SUM(CASE WHEN t.capacity - COALESCE(b.bookedSlots, 0) > 0 THEN 1 ELSE 0 END) > 0
";
$workshopStatement = $conn->query($workshopQuery);
$workshopData = $workshopStatement->fetchAll(PDO::FETCH_ASSOC);

// Referenced from COMP519 Lecture 20 Slide L20 - 7 Arrays,�lect20.pdf

$topicDescriptions = [
    'Word Processing' => 'Session on Basic Word processing.',
    'Spreadsheets' => 'Learn Basics of handling spreadsheets.',
    'Email' => 'Improve the skills of writing good emails.',
    'Presentation Software' => 'Basic knowledge share on presentation software.',
    'Library Use' => 'Know the maximum usage of library and resources.'
];

$errorMessage = '';
$successMessage = '';
$selectedTopic = '';
$slotOptions = '';

// Here we check if the request is a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['topic'])) {
        $selectedTopic = $_POST['topic'];
        $slotOptions = getSlotOptions($selectedTopic, $conn);
    }

    if (isset($_POST['bookSession'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $topic = $_POST['topic'] ?? '';
        $slot = $_POST['slot'] ?? '';

        // Here we Validate the regular expressions for name and email
        // Referenced from https://www.php.net/manual/en/regexp.reference.php
        $nameRegex = '/^[a-zA-Z\'\- ]+$/';
        $consecutiveRegex = '/[\'\-]{2,}/';
        $nameStartRegex = '/^[a-zA-Z\'].+[^\- ]$/';
        $emailRegex = '/^(?:[a-z0-9]+(?:[._-][a-z0-9]+)*|__+(?:[a-z0-9]+)*[a-z0-9])*@[a-z0-9]+(?:[._-][a-z0-9]+)*$/i';

        // Query for fetching the capacity for the selected training workshop topic
        $capacityQuery = "
            SELECT capacity
            FROM trainings
            WHERE topic = :topic
        ";
        $capacityStatement = $conn->prepare($capacityQuery);
        $capacityStatement->bindParam(':topic', $topic);
        $capacityStatement->execute();
        $capacityResult = $capacityStatement->fetch(PDO::FETCH_ASSOC);

        if ($capacityResult) {
            $capacity = $capacityResult['capacity'];

            // Query for fetching the number of booked slots for the selected training workshop topic and timings
            $bookedSlotsQuery = "
                SELECT COUNT(*) AS bookedSlots
                FROM bookings
                WHERE topic = :topic AND timings = :slot
            ";
            $bookedSlotsStatement = $conn->prepare($bookedSlotsQuery);
            $bookedSlotsStatement->bindParam(':topic', $topic);
            $bookedSlotsStatement->bindParam(':slot', $slot);
            $bookedSlotsStatement->execute();
            $bookedSlotsResult = $bookedSlotsStatement->fetch(PDO::FETCH_ASSOC);
            $bookedSlots = $bookedSlotsResult['bookedSlots'];

            $availableSlots = $capacity - $bookedSlots;

            // Validating the checks 
            if ($availableSlots <= 0) {
                $errorMessage = 'Error: All sessions are full. No more bookings can be made.';
            } elseif (empty($name) || empty($email) || empty($topic) || empty($slot)) {
                $errorMessage = 'Please fill in all required fields.';
            } elseif (!preg_match($nameRegex, $name) || preg_match($consecutiveRegex, $name) || !preg_match($nameStartRegex, $name)) {
                $errorMessage = 'Error: Invalid name format. Ensure the name consists of letters (a-z and A-Z), hyphens, apostrophes, and spaces; contains no consecutive hyphens or apostrophes; starts with a letter or an apostrophe; does not end with a hyphen or a space';
            } elseif (!preg_match($emailRegex, $email)) {
                $errorMessage = 'Error: Invalid email format. Ensure the email address entered has one @ symbol, preceded and followed by a non-empty sequence of characters from a-z, dot, underscore, or hyphen, with neither sequence ending in a dot or a hyphen';
            } else {
                try {
                     
                     /*
                     * The code for handling database transactions has been referenced from
                     * the PHP manual
                     * https://www.php.net/manual/en/pdo.transactions.php 
                     */
                    $conn->beginTransaction();

                    // Here we recheck available slots before booking a slot
                    $recheckQuery = "
                        SELECT COUNT(*) AS bookedSlots
                        FROM bookings
                        WHERE topic = :topic AND timings = :slot
                    ";
                    $recheckStatement = $conn->prepare($recheckQuery);
                    $recheckStatement->bindParam(':topic', $topic);
                    $recheckStatement->bindParam(':slot', $slot);
                    $recheckStatement->execute();
                    $recheckResult = $recheckStatement->fetch(PDO::FETCH_ASSOC);
                    $bookedSlots = $recheckResult['bookedSlots'];

                    $availableSlots = $capacity - $bookedSlots;

                    if ($availableSlots > 0) {
                        $insertQuery = "
                            INSERT INTO bookings (name, user_id, timings, date, day, topic)
                            VALUES (:name, :email, :slot, CURDATE(), DAYNAME(CURDATE()), :topic)
                        ";
                        $insertStatement = $conn->prepare($insertQuery);
                        $insertStatement->bindParam(':name', $name);
                        $insertStatement->bindParam(':email', $email);
                        $insertStatement->bindParam(':slot', $slot);
                        $insertStatement->bindParam(':topic', $topic);
                        $insertStatement->execute();
                        $conn->commit();

                        $successMessage = "Slot booked successfully for '$topic' at '$slot'";

                        // Updating $slotOptions after successfully booking a slot 
                        $slotOptions = getSlotOptions($selectedTopic, $conn);
                    } else {
                        $conn->rollback();
                        $errorMessage = 'Error: The selected slot is no longer available. Please choose a different slot.';
                    }
                } catch (PDOException $e) {
                    $conn->rollback();
                    if ($e->getCode() == 23000) {
                        $errorMessage = 'Error: Duplicate booking is not allowed';
                    } else {
                        $errorMessage = 'Error: ' . $e->getMessage();
                    }
                }
            }
        } else {
            $errorMessage = 'Error: Invalid topic selected.';
        }
    }
}

// Here we fetch the available slot options for the given topic

function getSlotOptions($selectedTopic, $conn)
{
    $slotOptions = '';

    $slotsQuery = "
        SELECT
            t.topic,
            t.timings,
            t.capacity,
            COALESCE(b.bookedSlots, 0) AS bookedSlots
        FROM trainings t
        LEFT JOIN (
            SELECT topic, timings, COUNT(*) AS bookedSlots
            FROM bookings
            GROUP BY topic, timings
        ) b ON t.topic = b.topic AND t.timings = b.timings
        WHERE t.topic = :selectedTopic
    ";

    $slotsStatement = $conn->prepare($slotsQuery);
    $slotsStatement->bindParam(':selectedTopic', $selectedTopic);
    $slotsStatement->execute();
    $slots = $slotsStatement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($slots as $slot) {
        $timingsArray = explode('<br>', $slot['timings']);
        $capacity = $slot['capacity'];

        foreach ($timingsArray as $timing) {
            $bookingQuery = "
                SELECT COUNT(*) AS bookedSlots
                FROM bookings
                WHERE topic = :selectedTopic AND timings = :timing
            ";
            $bookingStatement = $conn->prepare($bookingQuery);
            $bookingStatement->bindParam(':selectedTopic', $selectedTopic);
            $bookingStatement->bindParam(':timing', $timing);
            $bookingStatement->execute();
            $bookingResult = $bookingStatement->fetch(PDO::FETCH_ASSOC);
            $bookedSlots = $bookingResult['bookedSlots'];

            $availableSlots = $capacity - $bookedSlots;

            if ($availableSlots > 0) {
                $slotOptions .= '<option value="' . $timing . '">' .
                    $timing . ' - Slots Left: ' . $availableSlots .
                    '</option>';
            }
        }
    }

    return $slotOptions;
}

function hasAvailableSlots($topic, $conn)
{
    $slotOptions = getSlotOptions($topic, $conn);
    return !empty($slotOptions);
}

$remainingSlotsQuery = "
    SELECT SUM(t.capacity) - COALESCE(SUM(b.bookedSlots), 0) AS remainingSlots
    FROM trainings t
    LEFT JOIN (
        SELECT topic, timings, COUNT(*) AS bookedSlots
        FROM bookings
        GROUP BY topic, timings
    ) b ON t.topic = b.topic AND t.timings = b.timings
";
$remainingSlotsResult = $conn->query($remainingSlotsQuery);
$remainingSlots = $remainingSlotsResult->fetchColumn();

$bookingsQuery = "
    SELECT name, user_id, timings, topic 
    FROM bookings 
    ORDER BY topic, timings
";
$bookingsStatement = $conn->query($bookingsQuery);
$bookingData = $bookingsStatement->fetchAll(PDO::FETCH_ASSOC);

// Here we build the workshop table rows 
$workshopTableRows = '';
foreach ($workshopData as $workshop) {
    if (hasAvailableSlots($workshop['topic'], $conn)) {
        $workshopTableRows .= '<tr>';
        $workshopTableRows .= '<td>' . $workshop['topic'] . '</td>';
        $workshopTableRows .= '<td>' . ($topicDescriptions[$workshop['topic']] ?? '') . '</td>';
        $workshopTableRows .= '<td>' . nl2br($workshop['timings']) . '</td>';
        $workshopTableRows .= '<td>' . $workshop['capacity'] . '</td>';
        $workshopTableRows .= '</tr>';
    }
}

// Here we build the booking table rows
$bookingTableRows = '';
if (!empty($bookingData)) {
    foreach ($bookingData as $booking) {
        $bookingTableRows .= '<tr>';
        $bookingTableRows .= '<td>' . $booking['topic'] . '</td>';
        $bookingTableRows .= '<td>' . $booking['timings'] . '</td>';
        $bookingTableRows .= '<td>' . $booking['name'] . '</td>';
        $bookingTableRows .= '<td>' . $booking['user_id'] . '</td>';
        $bookingTableRows .= '</tr>';
    }
}

include 'index1.html';
?>