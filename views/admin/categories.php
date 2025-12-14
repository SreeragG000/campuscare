<?php
require_once __DIR__ . '/../../config/init.php';
ensure_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    if (!verify_csrf()) die('CSRF validation failed');
    $name = trim($_POST['name']);
    
    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        
        if ($stmt->execute()) {
            $success = "Category added successfully.";
        } else {
            $error = "Category already exists or invalid name.";
        }
    } else {
        $error = "Category name is required.";
    }
}

$categories = $conn->query("
    SELECT c.*, COUNT(a.id) as asset_count 
    FROM categories c 
    LEFT JOIN assets a ON c.id = a.category_id 
    GROUP BY c.id 
    ORDER BY c.name
");

include __DIR__ . '/../partials/header.php';
?>

<div class="container">
    <div class="card">
        <h2 class="card-title">Add Category</h2>    
        <div class="card-body">
            <?php if (isset($success)): ?>
                <div class="alert success"><?= $success ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <?= get_csrf_input() ?>
                <div class="form-group">
                    <input class="input" type="text" name="name" class="form-control" 
                           placeholder="Category Name" required maxlength="100">
                </div>
                <div class="text-center" style="margin-top: 1rem;">
                    <button type="submit" name="add_category" class="btn">
                        Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-card">
        <h3 class="card-title">Assets by Priority</h3>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Assets</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td>
                                <span class="badge badge-info"><?= $cat['asset_count'] ?></span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
