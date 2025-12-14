<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/asset_helper.php';
ensure_role(['student','faculty']);

$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) die('CSRF validation failed');
    $asset_code = trim($_POST['asset_code'] ?? '');
    $asset_name_id = (int)($_POST['asset_name_id'] ?? 0);
    $room_id = (int)($_POST['room_id'] ?? 0);
    $number = (int)($_POST['number'] ?? 1);
    $issue_type = $_POST['issue_type'] ?? 'Damage';
    $urgency_priority = $_POST['urgency_priority'] ?? 'Medium';
    $description = trim($_POST['description'] ?? '');
    $cpu_id = trim($_POST['cpu_id'] ?? '');
    $reported_by = $_SESSION['user']['id'];

    // Generate asset code if not provided but name and room are selected
    if (empty($asset_code) && $asset_name_id > 0 && $room_id > 0) {
        $roomStmt = $conn->prepare("SELECT room_no FROM rooms WHERE id = ?");
        $roomStmt->bind_param("i", $room_id);
        $roomStmt->execute();
        $roomResult = $roomStmt->get_result();
        if ($roomRow = $roomResult->fetch_assoc()) {
            $asset_code = getNextAssetCode($conn, $asset_name_id, $roomRow['room_no']);
            // If we still can't generate (e.g. invalid name), fallback to manual entry requirement
            if (!$asset_code) {
                $error = 'Could not auto-generate asset code. Please enter manually.';
            }
        }
    }

    if (!$error) {
        // Check if asset exists
        $stmt = $conn->prepare("SELECT id, room_id, parent_asset_id, status FROM assets WHERE asset_code = ?");
        $stmt->bind_param("s", $asset_code);
        $stmt->execute();
        $asset = $stmt->get_result()->fetch_assoc();

        // If asset doesn't exist, create it safely
        if (!$asset) {
            // Fetch defaults for required fields
            $catRes = $conn->query("SELECT id FROM categories LIMIT 1");
            $category_id = ($row = $catRes->fetch_assoc()) ? $row['id'] : 0;

            $dealerRes = $conn->query("SELECT id FROM dealers LIMIT 1");
            $dealer_id = ($row = $dealerRes->fetch_assoc()) ? $row['id'] : 0;

            if ($category_id && $dealer_id) {
                try {
                    $newAssetData = [
                        'asset_name_id' => $asset_name_id,
                        'category_id' => $category_id,
                        'room_id' => $room_id,
                        'parent_asset_id' => null,
                        'warranty_end' => date('Y-m-d', strtotime('+2 years')),
                        'dealer_id' => $dealer_id
                    ];
                    $result = insertAssetSafe($conn, $newAssetData);
                    
                    // Use the newly created asset
                    $asset = [
                        'id' => $result['id'],
                        'room_id' => $room_id,
                        'parent_asset_id' => null,
                        'status' => 'Good' // Default status
                    ];
                    $asset_code = $result['code']; // Ensure we use the actual generated code
                } catch (Exception $e) {
                    $error = 'Failed to auto-create asset: ' . $e->getMessage();
                }
            } else {
                $error = 'System configuration error: Missing default category or dealer.';
            }
        }

        if (!$error) {
            if ($asset['status'] === 'Needs Repair') {
                $error = 'DUPLICATE_REPORT';
            } else {
                if ($asset['parent_asset_id'] && empty($cpu_id)) {
                    $error = 'CPU ID is required for computer components to track their physical location.';
                }

                $img_path = null;
                if (!empty($_FILES['image']['name'])) {
                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $error = 'Only JPG/PNG/GIF allowed';
                    } else {
                        $new = 'uploads/' . date('Ymd_His') . '_' . $asset_code . '.' . $ext;
                        if (!move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../../' . $new)) {
                            $error = 'Upload failed';
                        } else {
                            $img_path = '/' . $new;
                        }
                    }
                }
            }
        }

        if (!$error) {
            try {
                $conn->begin_transaction();

                $final_description = $description;
                if ($asset['parent_asset_id'] && !empty($cpu_id)) {
                    $final_description = "(CPU ID: " . $cpu_id . ")\n\n" . $description;
                }

                // Insert damage report with urgency_priority and issue_type
                $stmt = $conn->prepare("INSERT INTO damage_reports (asset_id, reported_by, description, image_path, urgency_priority, issue_type) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissss", $asset['id'], $reported_by, $final_description, $img_path, $urgency_priority, $issue_type);
                $stmt->execute();

                // Update asset status to 'Needs Repair'
                $updateAssetStmt = $conn->prepare("UPDATE assets SET status = 'Needs Repair' WHERE id = ?");
                $updateAssetStmt->bind_param("i", $asset['id']);
                $updateAssetStmt->execute();

                $room_id = (int)$asset['room_id'];

                // Update exam room status using the new normalized structure
                $roomTypeStmt = $conn->prepare("SELECT room_type FROM rooms WHERE id = ?");
                $roomTypeStmt->bind_param("i", $room_id);
                $roomTypeStmt->execute();
                $roomTypeResult = $roomTypeStmt->get_result();
                if ($roomTypeRow = $roomTypeResult->fetch_assoc()) {
                    $room_type = $roomTypeRow['room_type'];

                    // Only update exam_rooms table for classroom/lab types
                    if (in_array(strtolower($room_type), ['classroom', 'lab', 'laboratory'])) {
                        // Calculate the new exam ready status
                        $newExamStatus = isRoomExamReady($conn, $room_id, $room_type);
                        // Update the exam_rooms table
                        $updateExamStmt = $conn->prepare("
                            UPDATE exam_rooms 
                            SET status_exam_ready = ?, updated_at = CURRENT_TIMESTAMP 
                            WHERE room_id = ?
                        ");
                        $updateExamStmt->bind_param("si", $newExamStatus, $room_id);
                        $updateExamStmt->execute();
                    }
                }

                $conn->commit();
                $ok = 'Report submitted for asset: ' . $asset_code;
                if ($asset['parent_asset_id'] && !empty($cpu_id)) {
                    $ok .= ' (CPU ID: ' . $cpu_id . ')';
                }
                $ok .= ' with urgency: ' . $urgency_priority;

                // Get human-readable details for notifications
                $notifyDetailsQuery = $conn->prepare("
                    SELECT an.name as asset_name, r.room_no 
                    FROM assets a 
                    JOIN asset_names an ON a.asset_name_id = an.id 
                    JOIN rooms r ON a.room_id = r.id 
                    WHERE a.id = ?
                ");
                $notifyDetailsQuery->bind_param("i", $asset['id']);
                $notifyDetailsQuery->execute();
                $notifyDetails = $notifyDetailsQuery->get_result()->fetch_assoc();
                
                $n_asset_name = $notifyDetails['asset_name'] ?? 'Asset';
                $n_room_no = $notifyDetails['room_no'] ?? 'Unknown';

                // Notify faculty
                $fac = $conn->query("SELECT faculty_id FROM room_assignments WHERE room_id = $room_id");
                while($f = $fac->fetch_assoc()) {
                    $msg = "⚠️ New Report: $n_asset_name in Room $n_room_no is damaged. Priority: $urgency_priority. Code ($asset_code)";
                    notify_user($conn, (int)$f['faculty_id'], $msg);
                }

                // Notify admin
                $admin_query = $conn->query("SELECT id FROM users WHERE role = 'admin'");
                while($admin = $admin_query->fetch_assoc()) {
                    $msg = "⚠️ Action Required: New $urgency_priority priority report for $n_asset_name in Room $n_room_no";
                    notify_user($conn, (int)$admin['id'], $msg);
                }

            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

$rooms = $conn->query("SELECT id, building, floor, room_no FROM rooms ORDER BY building, floor, room_no");
$assetNames = $conn->query("SELECT id, name FROM asset_names ORDER BY name");

include __DIR__ . '/../partials/header.php';
?>

<script>
function playAchievementSound() {
    const audio = new Audio('<?= BASE_URL ?>sounds/achievement.mp3');
    audio.volume = 0.7;
    audio.play().catch(e => console.log('Audio failed:', e));
}
</script>

<div class="card">
    <h2>Report Asset Damage</h2>
    
    <?php if ($error && $error !== 'DUPLICATE_REPORT'): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if($ok): ?>
        <div class="alert success"><?= htmlspecialchars($ok) ?></div>
        <script>playAchievementSound();</script>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="grid cols-2">
        <?= get_csrf_input() ?>
        <div>
            <label>Asset Code</label>
            <input class="input" name="asset_code" id="asset_code" 
                   placeholder="Enter asset code or use auto-generation below"
                   value="<?= htmlspecialchars($_POST['asset_code'] ?? '') ?>">
        </div>
        
        <div>
            <label>Asset Name</label>
            <select class="input" name="asset_name_id" id="asset_name_id" onchange="generateAssetCode()">
                <option value="">Select Asset Name</option>
                <?php while ($an = $assetNames->fetch_assoc()): ?>
                    <option value="<?= (int)$an['id'] ?>" 
                            <?= (isset($_POST['asset_name_id']) && $_POST['asset_name_id'] == $an['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($an['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <small style="color: #8fa0c9;">Choose from standardized list to avoid typos</small>
        </div>

        <div>
            <label>Room</label>
            <select class="input" name="room_id" id="room_id" onchange="generateAssetCode()">
                <option value="">Select room</option>
                <?php while ($r = $rooms->fetch_assoc()): ?>
                    <option value="<?= (int)$r['id'] ?>" data-room-no="<?= htmlspecialchars($r['room_no']) ?>"
                            <?= (isset($_POST['room_id']) && $_POST['room_id'] == $r['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['building'] . '/' . $r['floor'] . '/' . $r['room_no']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label>Asset Number</label>
            <input class="input" name="number" id="number" type="number" value="1" min="1" onchange="generateAssetCode()">
            <small style="color: #8fa0c9;">If multiple same items exist in room</small>
        </div>

        </div>

        <div>
            <label>Issue Type</label>
            <select class="input" name="issue_type" id="issue_type" required onchange="updateDescriptionPlaceholder()">
                <option value="Damage">Damage (Broken/Malfunction)</option>
                <option value="Missing Sticker">Missing Sticker (No Code)</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div>
            <label>Urgency Priority</label>
            <select class="input" name="urgency_priority" required>
                <option value="Low">Low - Minor cosmetic issues</option>
                <option value="Medium" selected>Medium - Affects functionality</option>
                <option value="High">High - Complete failure</option>
            </select>
        </div>

        <div id="cpu-id-container" class="col-span-2" style="display:none;">
            <label>CPU ID <span class="text-required">*</span></label>
            <input class="input" name="cpu_id" id="cpu_id" 
                   placeholder="Enter the CPU ID of the computer this component belongs to">
            <small class="text-muted">This helps track component location even if parts are swapped between computers</small>
        </div>

        <div class="col-span-2">
            <label>Description</label>
            <textarea class="input" name="description" id="description" rows="4" placeholder="What happened?" required></textarea>
        </div>

        <div class="col-span-2">
            <label>Image (optional)</label>
            <input class="input" type="file" name="image" accept="image/*">
        </div>

        <div class="actions col-span-2 text-center">
            <button class="btn" type="submit" id="submitBtn">Submit Report</button>
        </div>
    </form>
</div>

<!-- Duplicate Report Modal -->
<div id="duplicateModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>Report Already Exists</h3>
        <p>This asset already has an active damage report and is marked as "Needs Repair".</p>
        <p>Only one report per asset is allowed at a time.</p>
        <button onclick="document.getElementById('duplicateModal').style.display='none'" class="btn">OK</button>
    </div>
</div>

<script>
<?php if ($error === 'DUPLICATE_REPORT'): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('duplicateModal').style.display = 'block';
});
<?php endif; ?>

async function generateAssetCode() {
    const assetNameSelect = document.getElementById('asset_name_id');
    const roomSelect = document.getElementById('room_id');
    const numberInput = document.getElementById('number');
    const assetCodeInput = document.getElementById('asset_code');
    const submitBtn = document.getElementById('submitBtn');

    const assetNameId = assetNameSelect.value;
    const selectedRoom = roomSelect.options[roomSelect.selectedIndex];
    const number = numberInput.value || 1;
    const userTypedCode = assetCodeInput.dataset.userTyped === 'true';

    if (assetNameId && selectedRoom && selectedRoom.value && number && !userTypedCode) {
        const roomNo = selectedRoom.getAttribute('data-room-no');
        const assetNameText = assetNameSelect.options[assetNameSelect.selectedIndex].text;
        
        if (roomNo && assetNameText) {
            const cleanName = assetNameText.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
            const assetCode = cleanName + '-' + roomNo + '-' + number;
            assetCodeInput.value = assetCode;
            assetCodeInput.style.backgroundColor = '#e8f5e8';
            assetCodeInput.placeholder = 'Auto-generated asset code';
            checkAssetForCpuId();
        }
    }

    submitBtn.disabled = !assetCodeInput.value.trim();
}

async function checkAssetForCpuId() {
    const assetCode = document.getElementById('asset_code').value.trim();
    const cpuIdContainer = document.getElementById('cpu-id-container');
    const cpuIdInput = document.getElementById('cpu_id');

    if (!assetCode) {
        cpuIdContainer.style.display = 'none';
        cpuIdInput.required = false;
        return;
    }

    try {
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        const response = await fetch('<?= BASE_URL ?>includes/check_asset.php', {            
            method: 'POST',            
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'asset_code=' + encodeURIComponent(assetCode) + '&csrf_token=' + encodeURIComponent(csrfToken)
        });

        if (response.ok) {
            const data = await response.json();
            if (data.success && data.has_parent) {
                cpuIdContainer.style.display = 'block';
                cpuIdInput.required = true;
                cpuIdContainer.style.borderRadius = '4px';
                cpuIdContainer.style.padding = '10px';
                cpuIdContainer.style.marginBottom = '10px';
            } else {
                cpuIdContainer.style.display = 'none';
                cpuIdInput.required = false;
                cpuIdInput.value = '';
            }
        }
    } catch (error) {
        console.error('Error checking asset:', error);
        cpuIdContainer.style.display = 'none';
        cpuIdInput.required = false;
    }
}

document.getElementById('asset_code').addEventListener('input', function() {
    const assetCodeInput = this;
    if (assetCodeInput.value.trim()) {
        assetCodeInput.dataset.userTyped = 'true';
        assetCodeInput.placeholder = 'Manual asset code entry';
        checkAssetForCpuId();
    } else {
        assetCodeInput.dataset.userTyped = 'false';
        assetCodeInput.placeholder = 'Enter asset code or use auto-generation';
        generateAssetCode();
    }
    document.getElementById('submitBtn').disabled = !assetCodeInput.value.trim();
});

function updateDescriptionPlaceholder() {
    const issueType = document.getElementById('issue_type').value;
    const desc = document.getElementById('description');
    if (issueType === 'Missing Sticker') {
        desc.placeholder = "Describe where the sticker was located or where the item is in the room...";
    } else {
        desc.placeholder = "What happened?";
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const assetCodeInput = document.getElementById('asset_code');
    assetCodeInput.dataset.userTyped = 'false';
    generateAssetCode();
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('duplicateModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<style>
@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 200px;
    }
}

.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #040014da;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #ee0000;
    width: 400px;
    border-radius: 8px;
    text-align: center;
}

.modal-content h3 {
    color: #d32f2f;
    margin-bottom: 15px;
}
</style>

<?php include __DIR__ . '/../partials/footer.php'; ?>