<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/asset_helper.php';
ensure_role(['admin', 'faculty']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) die('CSRF validation failed');
    $asset_name_id = (int)($_POST['asset_name_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $room_id = (int)($_POST['room_id'] ?? 0);
    $parent_asset_id = $_POST['parent_asset_id'] ? (int)$_POST['parent_asset_id'] : null;
    $warranty_end = !empty($_POST['warranty_end']) ? $_POST['warranty_end'] : null;
    $dealer_id = (int)($_POST['dealer_id'] ?? 0);

    // Validation
    if ($asset_name_id && $room_id && $category_id && $dealer_id && !empty($warranty_end)) {
        // Validate date format
        $date = DateTime::createFromFormat('Y-m-d', $warranty_end);
        if (!$date || $date->format('Y-m-d') !== $warranty_end) {
            set_flash('err', 'Invalid warranty end date format');
        } else {
            // Validate category exists
            $categoryCheck = $conn->prepare("SELECT id FROM categories WHERE id = ?");
            $categoryCheck->bind_param("i", $category_id);
            $categoryCheck->execute();
            if (!$categoryCheck->get_result()->fetch_assoc()) {
                set_flash('err', 'Invalid category selected');
            } else {
                // Validate dealer exists
                $dealerCheck = $conn->prepare("SELECT id FROM dealers WHERE id = ?");
                $dealerCheck->bind_param("i", $dealer_id);
                $dealerCheck->execute();
                if (!$dealerCheck->get_result()->fetch_assoc()) {
                    set_flash('err', 'Invalid dealer selected');
                } else {
                    // Validate asset name exists
                    $assetNameCheck = $conn->prepare("SELECT name FROM asset_names WHERE id = ?");
                    $assetNameCheck->bind_param("i", $asset_name_id);
                    $assetNameCheck->execute();
                    if (!$assetNameResult = $assetNameCheck->get_result()->fetch_assoc()) {
                        set_flash('err', 'Invalid asset name selected');
                    } else {
                        try {
                            $data = [
                                'asset_name_id' => $asset_name_id,
                                'category_id' => $category_id,
                                'room_id' => $room_id,
                                'parent_asset_id' => $parent_asset_id,
                                'warranty_end' => $warranty_end,
                                'dealer_id' => $dealer_id
                            ];
                            $result = insertAssetSafe($conn, $data);
                            set_flash('ok', 'Asset added with code: ' . $result['code']);
                            $_POST = [];
                        } catch (Exception $e) {
                            set_flash('err', $e->getMessage());
                        }
                    }
                }
            }
        }
    } else {
        set_flash('err', 'Fill required fields: Asset Name, Category, Room, Dealer, and Warranty End Date');
    }
}

// Pagination & Search Logic
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$whereSQL = "";
$params = [];
$types = "";

if ($search) {
    $searchTerm = "%$search%";
    $whereSQL = "WHERE a.asset_code LIKE ? OR an.name LIKE ? OR r.room_no LIKE ?";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    $types = "sss";
}

// Count Total
$countQuery = "
    SELECT COUNT(*) as total 
    FROM assets a
    JOIN asset_names an ON an.id = a.asset_name_id
    JOIN rooms r ON r.id = a.room_id
    $whereSQL
";
$stmt = $conn->prepare($countQuery);
if ($search) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalAssets = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalAssets / $limit);

// Fetch Data
$dataQuery = "
    SELECT 
        a.id, 
        a.asset_code, 
        a.status,
        an.name AS asset_name, 
        c.name AS category_name, 
        r.building, 
        r.floor, 
        r.room_no,
        d.name AS dealer_name, 
        d.contact AS dealer_contact,
        p.asset_code AS parent_code
    FROM assets a
    JOIN asset_names an ON an.id = a.asset_name_id
    JOIN categories c ON c.id = a.category_id
    JOIN rooms r ON r.id = a.room_id
    JOIN dealers d ON d.id = a.dealer_id
    LEFT JOIN assets p ON p.id = a.parent_asset_id
    $whereSQL
    ORDER BY a.asset_code ASC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($dataQuery);
if ($search) {
    $stmt->bind_param($types . "ii", ...array_merge($params, [$limit, $offset]));
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$assets = $stmt->get_result();

$rooms = $conn->query("SELECT id, building, floor, room_no FROM rooms ORDER BY building, floor, room_no");
$asset_list = $conn->query("SELECT id, asset_code FROM assets ORDER BY asset_code");

include __DIR__ . '/../partials/header.php';
?>

<div class="container">
    <div class="card">
        <h2 class="card-title">Asset Management</h2>
        
        <div style="margin-bottom: 1rem;">
            <a href="<?= BASE_URL ?>views/admin/asset_names.php" class="btn outline small">Manage Asset Names</a>
        </div>
        
        <?php if ($m = flash('ok')): ?>
            <div class="alert success"><?= htmlspecialchars($m) ?></div>
        <?php endif; ?>
        <?php if ($m = flash('err')): ?>
            <div class="alert error"><?= htmlspecialchars($m) ?></div>
        <?php endif; ?>
        
        <form method="post" class="grid cols-3">
            <?= get_csrf_input() ?>
            <div>
                <label>Asset Name <span class="text-danger">*</span></label>
                <select class="input" name="asset_name_id" id="asset_name_id" required onchange="generateCode()">
                    <option value="">Select Asset Name</option>
                    <?php
                    $assetNames = $conn->query("SELECT id, name FROM asset_names ORDER BY name");
                    while ($an = $assetNames->fetch_assoc()):
                        $selected = (isset($_POST['asset_name_id']) && $_POST['asset_name_id'] == $an['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= (int)$an['id'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($an['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small style="color: #8fa0c9;">
                    Don't see your asset name? <a href="<?= BASE_URL ?>views/admin/asset_names.php" style="color: #6ea8fe;">Add it here</a>
                </small>
            </div>
            
            <div>
                <label>Category <span class="text-danger">*</span></label>
                <select class="input" name="category_id" id="category_id" required>
                    <option value="">Select Category</option>
                    <?php
                    $category_query = "SELECT id, name FROM categories ORDER BY name";
                    $category_result = $conn->query($category_query);
                    while ($c = $category_result->fetch_assoc()):
                        $selected = (isset($_POST['category_id']) && $_POST['category_id'] == $c['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label>Room <span class="text-danger">*</span></label>
                <select class="input" name="room_id" id="room_id" required onchange="generateCode()">
                    <option value="">Select room</option>
                    <?php $rooms->data_seek(0); while ($r = $rooms->fetch_assoc()):
                        $selected = (isset($_POST['room_id']) && $_POST['room_id'] == $r['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= (int)$r['id'] ?>" <?= $selected ?> data-room-no="<?= htmlspecialchars($r['room_no']) ?>">
                            <?= htmlspecialchars($r['building'] . '/' . $r['floor'] . '/' . $r['room_no']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label>Dealer <span class="text-danger">*</span></label>
                <select class="input" name="dealer_id" id="dealer_id" required>
                    <option value="">Select Dealer</option>
                    <?php
                    $dealer_query = "SELECT id, name, contact FROM dealers ORDER BY name";
                    $dealer_result = $conn->query($dealer_query);
                    while ($d = $dealer_result->fetch_assoc()):
                        $selected = (isset($_POST['dealer_id']) && $_POST['dealer_id'] == $d['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($d['name']) ?>
                            <?php if ($d['contact']): ?>
                                - <?= htmlspecialchars($d['contact']) ?>
                            <?php endif; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label>Warranty End Date <span class="text-danger">*</span></label>
                <input class="input" type="date" name="warranty_end" id="warranty_end"
                       value="<?= htmlspecialchars($_POST['warranty_end'] ?? '') ?>" 
                       min="<?= date('Y-m-d') ?>" required>
                <small style="color: #8fa0c9;">Select warranty expiration date</small>
            </div>
            
            <div>
                <label>Parent Asset (optional)</label>
                <select class="input" name="parent_asset_id">
                    <option value="">None</option>
                    <?php while ($p = $asset_list->fetch_assoc()):
                        $selected = (isset($_POST['parent_asset_id']) && $_POST['parent_asset_id'] == $p['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($p['asset_code']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label>Asset Code</label>
                <input class="input" name="asset_code" id="asset_code" placeholder="Auto-generated" readonly>
                <small style="color: #8fa0c9;">Auto-generated based on asset name and room</small>
            </div>
            
            <div class="actions" style="text-align: center;">
                <button class="btn" type="submit">Add Asset</button>
            </div>
        </form>
    </div>

    <div class="table-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 class="card-title" style="margin: 0;">All Assets</h3>
            <form method="get" style="display: flex; gap: 0.5rem;">
                <input class="input" name="search" placeholder="Search code, name, room..." value="<?= htmlspecialchars($search) ?>" style="width: 250px;">
                <button class="btn small" type="submit">Search</button>
                <?php if ($search): ?>
                    <a href="assets.php" class="btn outline small">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Room</th>
                        <th>Dealer</th>
                        <th>Parent</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($assets->num_rows > 0): ?>
                        <?php while ($a = $assets->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['asset_code']) ?></td>
                                <td><?= htmlspecialchars($a['asset_name']) ?></td>
                                <td><?= htmlspecialchars($a['category_name']) ?></td>
                                <td><?= htmlspecialchars($a['building'] . '/' . $a['floor'] . '/' . $a['room_no']) ?></td>
                                <td>
                                    <?= htmlspecialchars($a['dealer_name']) ?>
                                    <?php if ($a['dealer_contact']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($a['dealer_contact']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($a['parent_code'] ?? '-') ?></td>
                                <td><span class="badge <?= $a['status'] == 'Good' ? 'good' : 'bad' ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No assets found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1rem;">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="btn outline small">Previous</a>
        <?php endif; ?>
        
        <span style="align-self: center;">Page <?= $page ?> of <?= $totalPages ?></span>
        
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="btn outline small">Next</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Debug function to check date value
function checkDateValue() {
    const dateInput = document.getElementById('warranty_end');
    console.log('Date input value:', dateInput.value);
    return dateInput.value;
}

// Add event listener to check date changes
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('warranty_end');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            console.log('Date changed to:', this.value);
            if (!this.value) {
                this.setCustomValidity('Please select a warranty end date');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Ensure date input works on mobile
        dateInput.addEventListener('blur', function() {
            if (!this.value) {
                this.focus();
            }
        });
    }
});

async function generateCode() {
    const assetNameSelect = document.getElementById('asset_name_id');
    const roomSelect = document.getElementById('room_id');
    const assetCodeInput = document.getElementById('asset_code');
    
    const assetNameId = assetNameSelect.value;
    const selectedRoom = roomSelect.options[roomSelect.selectedIndex];
    
    if (assetNameId && selectedRoom && selectedRoom.value) {
        const roomNo = selectedRoom.getAttribute('data-room-no');
        if (roomNo) {
            try {
                // Get asset name text for code generation
                const assetNameText = assetNameSelect.options[assetNameSelect.selectedIndex].text;
                const cleanName = assetNameText.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
                const suggestedCode = cleanName + '-' + roomNo + '-1';
                assetCodeInput.placeholder = 'Will be: ' + suggestedCode + ' (or next available)';
            } catch (error) {
                console.error('Error generating code:', error);
            }
        }
    } else {
        assetCodeInput.placeholder = 'Auto-generated';
    }
}
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>