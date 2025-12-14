<?php
require_once __DIR__ . '/../../config/init.php';
ensure_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dealer'])) {
    if (!verify_csrf()) die('CSRF validation failed');
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    
    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO dealers (name, contact) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $contact);
        
        if ($stmt->execute()) {
            $success = "Dealer added successfully.";
        } else {
            $error = "Failed to add dealer. Name may already exist.";
        }
    } else {
        $error = "Dealer name is required.";
    }
}

$dealers = $conn->query("
    SELECT d.*, COUNT(a.id) as asset_count 
    FROM dealers d 
    LEFT JOIN assets a ON d.id = a.dealer_id 
    GROUP BY d.id 
    ORDER BY d.name
");

include __DIR__ . '/../partials/header.php';
?>

<div class="container">
    <div class="card">
        <h2 class="card-title">Add New Dealer</h2>
        
        <!-- Add Dealer Form -->
        <div>
            <?php if (isset($success)): ?>
                <div class="alert success"><?= $success ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <?= get_csrf_input() ?>
                <div>
                    <label for="name">Dealer Name *</label>
                    <input type="text" name="name" id="name" class="input" 
                           placeholder="e.g., Tech Solutions Pvt Ltd" required maxlength="255">
                </div>
                
                <div>
                    <label for="contact">Contact Information</label>
                    <input type="text" name="contact" id="contact" class="input" 
                           placeholder="e.g., John Doe - 9876543210" maxlength="255">
                </div>
                
                <div style="margin-top: 1rem;">
                    <button type="submit" name="add_dealer" class="btn">
                        Add Dealer
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Dealers List -->
    <div class="table-card">
        <h3>All Dealers (<?= $dealers->num_rows ?>)</h3>
        
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Assets</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($dealer = $dealers->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($dealer['name']) ?></td>
                            <td>
                                <?php if ($dealer['contact']): ?>
                                    <?= htmlspecialchars($dealer['contact']) ?>
                                <?php else: ?>
                                    <span class="text-muted">No contact info</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $dealer['asset_count'] > 0 ? 'good' : 'na' ?>">
                                    <?= $dealer['asset_count'] ?> assets
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
