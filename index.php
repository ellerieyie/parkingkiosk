
<?php
include("db.php");

// FUNCTION PARA AUTO-DETECT NG IMAGE EXTENSION (WEBP, JPG, PNG)
function getSlotImage($imageName) {
    $folder = 'parkingslot_image/';
    $extensions = ['webp', 'jpg', 'jpeg', 'png'];

    foreach ($extensions as $ext) {
        if (file_exists($folder . $imageName . '.' . $ext)) {
            return $folder . $imageName . '.' . $ext;
        }
    }

    return $folder . 'default.png';
}

// Auto-complete expired sessions
$conn->query("UPDATE reservations SET Status='Completed'
              WHERE Status='Active' AND EndTime < NOW()");

// Get all parking slots + current occupant info
$sql = "SELECT s.*,
        (SELECT COUNT(*) FROM reservations r
         WHERE r.SlotID = s.SlotID
           AND r.Status = 'Active'
           AND NOW() BETWEEN r.StartTime AND r.EndTime) AS OccupiedNow,

        (SELECT r.EndTime FROM reservations r
         WHERE r.SlotID = s.SlotID
           AND r.Status = 'Active'
           AND NOW() BETWEEN r.StartTime AND r.EndTime
         ORDER BY r.EndTime DESC LIMIT 1) AS OccupiedUntil

        FROM parking_slots s
        ORDER BY s.SlotID ASC";

$result = $conn->query($sql);

if (!$result) {
    die("Error loading slots: " . $conn->error);
}

$slots = [];

while ($row = $result->fetch_assoc()) {
    $slots[] = $row;
}

$status = $_GET['status'] ?? '';

// Group slots by zone
$zoneOrder = ['Zone A', 'Zone B', 'Zone C', 'VIP', 'SPVIP'];

$groupedSlots = [];

foreach ($slots as $index => $row) {
    $zoneName = trim($row['Zone']);

    if (!isset($groupedSlots[$zoneName])) {
        $groupedSlots[$zoneName] = [];
    }

    $groupedSlots[$zoneName][] = [
        'index' => $index,
        'row' => $row
    ];
}

// Preserve predefined order and include other database zones
$orderedZones = $zoneOrder;

foreach (array_keys($groupedSlots) as $zoneName) {
    if (!in_array($zoneName, $orderedZones, true)) {
        $orderedZones[] = $zoneName;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Condo Parking Portal | Get a Slot</title>

    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- EXTERNAL CSS -->
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">

            <a class="navbar-brand" href="index.php">
                <img src="parkingslot_image/logo.jpg" alt="Condo Logo">
                <span>Residences Parking Portal</span>
            </a>

            <button class="navbar-toggler" type="button"
                    data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false"
                    aria-label="Toggle navigation">

                <span class="navbar-toggler-icon"></span>

            </button>

            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">

                <ul class="navbar-nav">

                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Visitor Slots</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#about">About System</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Admin Security</a>
                    </li>

                </ul>

            </div>

        </div>
    </nav>

    <!-- STATUS MESSAGES -->
    <div class="container mt-3">

        <?php if ($status === 'success'): ?>

            <div class="alert alert-success alert-dismissible fade show" role="alert">
                ✅ Parking slot acquired! You may now proceed to your slot. Please display your plate number.

                <button type="button" class="btn-close"
                        data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

        <?php elseif ($status === 'conflict'): ?>

            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                ⚠️ Sorry — that slot was just taken. Please choose another slot.

                <button type="button" class="btn-close"
                        data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

        <?php elseif ($status === 'error'): ?>

            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                ❌ Could not acquire the slot. Please check your details and try again.

                <button type="button" class="btn-close"
                        data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

        <?php endif; ?>

    </div>

    <!-- HEADING + LEGEND -->
    <section class="page-heading">

        <h1>Guest & Visitor Parking</h1>

        <p>Grab a slot instantly — pay for your hours and park right away.</p>

        <div class="slot-legend">

            <div>
                <span class="legend-color available-color"></span>
                Available
            </div>

            <div>
                <span class="legend-color occupied-color"></span>
                Occupied
            </div>

        </div>

    </section>

    <!-- SLOTS GROUPED BY ZONE -->
    <section class="slots-container">

        <?php if (empty($slots)): ?>
            <div class="empty-slots">
                <h4>No parking slots configured.</h4>
                <p>Please contact the property management office.</p>
            </div>
        <?php else: ?>

            <div class="slots-list">

                <?php foreach ($orderedZones as $zoneName): ?>

                    <?php if (!empty($groupedSlots[$zoneName])): ?>

                        <div class="zone-section">

                            <h2 class="zone-heading">
                                <?= htmlspecialchars($zoneName, ENT_QUOTES, 'UTF-8') ?>
                            </h2>

                            <div class="zone-slots">

                                <?php foreach ($groupedSlots[$zoneName] as $item):

                                    $index = $item['index'];
                                    $row = $item['row'];

                                    $slotId = (int)$row['SlotID'];
                                    $slotName = $row['SlotName'];
                                    $zone = $row['Zone'];
                                    $rate = (float)$row['RatePerHour'];

                                    // Get image filename from database
                                    $imageName = $row['Image'] ?? 'default';

                                    $occupied = (int)$row['OccupiedNow'] > 0;

                                    $occupiedUntil = $row['OccupiedUntil']
                                        ? date('g:i A', strtotime($row['OccupiedUntil']))
                                        : '';

                                    $modalId = "slot-modal-" . ($index + 1);

                                ?>

                                    <div class="slot-item">

                                        <div class="slot-card">

                                            <!-- PARKING SLOT IMAGE -->
                                            <div class="slot-image-wrap">

                                                <img
                                                    src="<?= getSlotImage($imageName) ?>"
                                                    alt="<?= htmlspecialchars($slotName, ENT_QUOTES, 'UTF-8') ?>"
                                                    class="slot-image"
                                                    loading="lazy"
                                                    onerror="this.style.display='none';"
                                                >

                                            </div>

                                            <!-- SLOT DETAILS -->
                                            <div class="slot-content">

                                                <div class="slot-details">

                                                    <div class="slot-header-info">

                                                        <h3 class="slot-name">
                                                            <?= htmlspecialchars($slotName, ENT_QUOTES, 'UTF-8') ?>
                                                        </h3>

                                                        <span class="slot-badge <?= $occupied ? 'occupied' : 'available' ?>">
                                                            <?= $occupied ? 'Occupied' : 'Available' ?>
                                                        </span>

                                                    </div>

                                                    <div class="slot-zone">
                                                        Location / Zone:
                                                        <?= htmlspecialchars($zone, ENT_QUOTES, 'UTF-8') ?>
                                                    </div>

                                                    <div class="slot-price">
                                                        ₱<?= number_format($rate, 2) ?> / hr
                                                    </div>

                                                </div>

                                                <?php if ($occupied): ?>

                                                    <button type="button" class="slot-button" disabled>
                                                        Occupied<?= $occupiedUntil ? ' until ' . htmlspecialchars($occupiedUntil) : '' ?>
                                                    </button>

                                                <?php else: ?>

                                                    <button type="button"
                                                            class="slot-button"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#<?= $modalId ?>">
                                                        Get Slot Now
                                                    </button>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    <?php endif; ?>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <!-- RESERVATION MODALS -->
    <?php foreach ($slots as $index => $row):

        $slotId = (int)$row['SlotID'];
        $slotName = $row['SlotName'];
        $zone = $row['Zone'];
        $rate = (float)$row['RatePerHour'];

        // Get image filename from database
        $imageName = $row['Image'] ?? 'default';

        $occupied = (int)$row['OccupiedNow'] > 0;

        $modalId = "slot-modal-" . ($index + 1);

        if ($occupied) continue;

    ?>

        <div class="modal fade" id="<?= $modalId ?>" tabindex="-1"
             aria-labelledby="<?= $modalId ?>-label" aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title" id="<?= $modalId ?>-label">
                            Get Slot <?= htmlspecialchars($slotName, ENT_QUOTES, 'UTF-8') ?>
                        </h5>

                        <button type="button" class="btn-close"
                                data-bs-dismiss="modal" aria-label="Close"></button>

                    </div>

                    <div class="modal-body">

                        <div class="modal-slot-card">

                            <!-- MODAL PARKING IMAGE -->
                            <img
                                src="<?= getSlotImage($imageName) ?>"
                                alt="<?= htmlspecialchars($slotName, ENT_QUOTES, 'UTF-8') ?>"
                                class="modal-slot-image"
                                onerror="this.style.display='none';"
                            >

                            <div class="modal-slot-info">

                                <span class="fs-6 text-muted">
                                    <?= htmlspecialchars($zone, ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <span class="fs-5">
                                    ₱<?= number_format($rate, 2) ?>/hr
                                </span>

                            </div>

                            <div class="notice-now">
                                ⏱ Parking starts immediately upon confirmation.
                            </div>

                            <form action="insert.php" method="POST"
                                  class="parking-form"
                                  data-rate="<?= htmlspecialchars((string)$rate, ENT_QUOTES, 'UTF-8') ?>">

                                <input type="hidden" name="slot_id" value="<?= $slotId ?>">

                                <!-- VISITOR NAME -->
                                <div class="input-group">

                                    <span class="input-group-text">Name</span>

                                    <input type="text"
                                           class="form-control"
                                           name="User"
                                           maxlength="100"
                                           placeholder="Full name of visitor"
                                           required>

                                </div>

                                <!-- UNIT VISITED -->
                                <div class="mb-3">

                                    <label for="UnitNo<?= $slotId ?>" class="form-label">
                                        Unit Visited
                                    </label>

                                    <select class="form-select"
                                            id="UnitNo<?= $slotId ?>"
                                            name="UnitNo"
                                            required>

                                        <option value="">Select Unit</option>

                                        <?php for ($floor = 1; $floor <= 12; $floor++): ?>
                                            <optgroup label="Floor <?= $floor ?>">
                                                <?php for ($unit = 1; $unit <= 8; $unit++): ?>
                                                    <option value="Floor <?= $floor ?> Unit <?= $unit ?>">
                                                        Floor <?= $floor ?> Unit <?= $unit ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </optgroup>
                                        <?php endfor; ?>
                                    </select>

                                </div>

                                <!-- PLATE NUMBER -->
                                <div class="input-group">
                                    <span class="input-group-text">Plate No.</span>
                                    <input type="text"
                                           class="form-control"
                                           name="PlateNo"
                                           maxlength="20"
                                           placeholder="e.g. ABC 1234"
                                           pattern="[A-Za-z0-9\s\-]{3,20}"
                                           style="text-transform: uppercase;"
                                           required>
                                </div>

                                <!-- CONTACT -->
                                <div class="input-group">
                                    <span class="input-group-text">Contact No.</span>
                                    <input type="tel"
                                           class="form-control"
                                           name="Contact"
                                           maxlength="20"
                                           pattern="[0-9+\-\s()]{7,20}"
                                           placeholder="Mobile number"
                                           required>

                                </div>

                                <!-- HOURS -->
                                <div class="input-group">
                                    <span class="input-group-text">Hours</span>
                                    <input type="number"
                                           class="form-control hours-input"
                                           name="Hours"
                                           min="1"
                                           max="12"
                                           step="1"
                                           value="1"
                                           required>
                                </div>

                                <!-- PARKING ENDS -->
                                <div class="input-group">
                                    <span class="input-group-text">Parking Ends</span>
                                    <input type="text"
                                           class="form-control end-display"
                                           value="—"
                                           readonly>
                                </div>

                                <!-- TOTAL FEE -->
                                <div class="input-group">

                                    <span class="input-group-text">Total Fee</span>

                                    <input type="text"
                                           class="form-control total-display"
                                           value="₱<?= number_format($rate, 2) ?>"
                                           readonly>

                                </div>

                                <button type="submit" class="submit-button">
                                    Start Parking
                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    <?php endforeach; ?>

    <!-- ABOUT -->
    <section id="about" class="information-section">

        <h2>About Residential Parking</h2>

        <p>
            This portal manages guest and visitor parking allocations across our condominium property.
            Visitors can immediately acquire an available slot and pay per hour of use.
            All parking must match vehicle plate numbers for security validation at the main entrance gate.
        </p>

    </section>

    <!-- CONTACT -->
    <section id="contact" class="information-section">

        <h2>Security & Property Management</h2>

        <p>
            For extensions, security clearances, or access concerns, contact administration.
        </p>

        <p>
            Email:
            <a href="mailto:admin@condoresidences.com">
                admin@condoresidences.com
            </a>
        </p>

        <p>
            Guard House:
            <a href="tel:+639123456789">
                +63 912 345 6789
            </a>
        </p>

    </section>

    <!-- FOOTER -->
    <footer>

        <p class="mb-2">
            &copy; <?= date("Y") ?> Condominium Parking System. All Rights Reserved.
        </p>

        <p class="mb-0">
            <a href="#about">About System</a> |
            <a href="#contact">Security Desk</a>
        </p>

    </footer>

    <!-- BOOTSTRAP JAVASCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- TOTAL FEE + PARKING END PREVIEW -->
    <script>

        document.querySelectorAll(".parking-form").forEach(function(form) {

            const rate = Number(form.dataset.rate);

            const hoursInput = form.querySelector(".hours-input");
            const endDisplay = form.querySelector(".end-display");
            const totalDisplay = form.querySelector(".total-display");

            function formatDT(date) {

                return date.toLocaleString("en-PH", {
                    month: "short",
                    day: "numeric",
                    year: "numeric",
                    hour: "2-digit",
                    minute: "2-digit",
                    hour12: true
                });

            }

            function recalculate() {

                let hours = parseInt(hoursInput.value, 10);

                if (!Number.isFinite(hours) || hours < 1) {
                    hours = 1;
                }

                if (hours > 12) {
                    hours = 12;
                }

                const now = new Date();

                const endDate = new Date(
                    now.getTime() + hours * 60 * 60 * 1000
                );

                endDisplay.value = formatDT(endDate);

                totalDisplay.value = "₱" + (rate * hours).toLocaleString("en-PH", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

            }

            hoursInput.addEventListener("input", recalculate);

            recalculate();

        });

    </script>

</body>
</html>
