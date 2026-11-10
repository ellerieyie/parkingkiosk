<?php
include("db.php");

// Make mysqli throw on errors so nothing fails silently
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$slotId  = filter_input(INPUT_POST, "slot_id", FILTER_VALIDATE_INT);
$hours   = filter_input(INPUT_POST, "Hours",   FILTER_VALIDATE_INT);
$plateNo = strtoupper(trim($_POST["PlateNo"] ?? ""));
$user    = trim($_POST["User"]    ?? "");
$contact = trim($_POST["Contact"] ?? "");
$unitNo  = trim($_POST["UnitNo"]  ?? "");

// Validate types + formats (client-side pattern attr is not enough)
$valid =
    $slotId && $slotId > 0 &&
    $hours  && $hours >= 1 && $hours <= 12 &&
    preg_match('/^[A-Z0-9\s\-]{3,20}$/', $plateNo) &&
    mb_strlen($user)    >= 2 && mb_strlen($user)    <= 100 &&
    mb_strlen($contact) >= 7 && mb_strlen($contact) <= 20 &&
    preg_match('/^[0-9+\-\s()]{7,20}$/', $contact) &&
    $unitNo !== "";

if (!$valid) {
    header("Location: index.php?status=error");
    exit;
}

try {
    $conn->begin_transaction();

    // 1. Lock this slot row — serializes concurrent bookings for this SlotID
    $stmt = $conn->prepare(
        "SELECT RatePerHour FROM parking_slots WHERE SlotID = ? FOR UPDATE"
    );
    $stmt->bind_param("i", $slotId);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$slot) {
        $conn->rollback();
        header("Location: index.php?status=error");
        exit;
    }

    // 2. Conflict check — now safe, because we hold the slot row lock
    $stmt = $conn->prepare(
        "SELECT 1 FROM reservations
         WHERE SlotID = ?
           AND Status = 'Active'
           AND NOW() BETWEEN StartTime AND EndTime
         LIMIT 1"
    );
    $stmt->bind_param("i", $slotId);
    $stmt->execute();
    $conflict = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();

    if ($conflict) {
        $conn->rollback();
        header("Location: index.php?status=conflict");
        exit;
    }

    // 3. Insert — rate comes from the DB, not the client
    $total = (float)$slot["RatePerHour"] * $hours;

    $stmt = $conn->prepare(
        "INSERT INTO reservations
         (SlotID, PlateNo, CustomerName, Contact, UnitNo,
          StartTime, EndTime, Hours, Total, Status)
         VALUES (?, ?, ?, ?, ?,
                 NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR), ?, ?, 'Active')"
    );
    $stmt->bind_param(
        "issssiid",
        $slotId, $plateNo, $user, $contact, $unitNo,
        $hours, $hours, $total
    );
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    header("Location: index.php?status=success");
    exit;

} catch (Throwable $e) {
    if ($conn->errno || $conn->ping()) {
        @$conn->rollback();
    }
    error_log("Reservation failed: " . $e->getMessage());
    header("Location: index.php?status=error");
    exit;
}