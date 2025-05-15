<?php
// [Previous PHP code remains exactly the same until the HTML starts]
session_start();

// Configuration
$upload_dir = "uploads/";
$admin_user = "admin";
$admin_pass = "password123"; // Change this in production
$max_file_size = 20 * 1024 * 1024; // 5MB
$allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt'];

// Security settings
ini_set('display_errors', 0);
error_reporting(0);

// Create upload directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle Login
if (isset($_POST['login'])) {
    $username = htmlspecialchars(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    if ($username === $admin_user && $password === $admin_pass) {
        $_SESSION['admin'] = true;
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        header("Location: " . strtok($_SERVER['PHP_SELF'], '?'));
        exit;
    } else {
        $error = "Invalid login credentials.";
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: " . strtok($_SERVER['PHP_SELF'], '?'));
    exit;
}

// Verify session security
if (isset($_SESSION['admin'])) {
    if ($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR'] || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_unset();
        session_destroy();
        header("Location: " . strtok($_SERVER['PHP_SELF'], '?'));
        exit;
    }
}

// Handle Multiple File Upload (Admin only)
if (isset($_POST['upload']) && isset($_SESSION['admin']) && !empty($_FILES['files']['name'][0])) {
    $uploaded_files = [];
    $errors = [];
    
    foreach ($_FILES['files']['name'] as $key => $name) {
        $file = [
            'name' => $name,
            'type' => $_FILES['files']['type'][$key],
            'tmp_name' => $_FILES['files']['tmp_name'][$key],
            'error' => $_FILES['files']['error'][$key],
            'size' => $_FILES['files']['size'][$key]
        ];
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\-\._]/', '', basename($file['name']));
        $target = $upload_dir . $filename;
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading {$file['name']}: " . $file['error'];
            continue;
        }
        
        if (!in_array($ext, $allowed_types)) {
            $errors[] = "{$file['name']}: Invalid file type.";
            continue;
        }
        
        if ($file['size'] > $max_file_size) {
            $errors[] = "{$file['name']}: File too large (max 5MB).";
            continue;
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $uploaded_files[] = $filename;
        } else {
            $errors[] = "Failed to upload {$file['name']}.";
        }
    }
    
    if (!empty($uploaded_files)) {
        $message = "Successfully uploaded " . count($uploaded_files) . " file(s).";
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// Handle File Deletion (Admin only)
if (isset($_GET['delete']) && isset($_SESSION['admin'])) {
    $file_to_delete = $upload_dir . basename($_GET['delete']);
    if (file_exists($file_to_delete) && is_file($file_to_delete)) {
        if (unlink($file_to_delete)) {
            $message = "File deleted successfully.";
        } else {
            $error = "Failed to delete file.";
        }
        header("Location: " . strtok($_SERVER['PHP_SELF'], '?'));
        exit;
    }
}

// Get uploaded files
$files = [];
if (is_dir($upload_dir)) {
    $items = array_diff(scandir($upload_dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $upload_dir . $item;
        if (is_file($path)) {
            $files[] = [
                'name' => $item,
                'path' => $path,
                'size' => filesize($path),
                'date' => filemtime($path),
                'ext' => strtolower(pathinfo($item, PATHINFO_EXTENSION))
            ];
        }
    }
    
    // Sort by upload date (newest first)
    usort($files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Handle File View/Download
if (isset($_GET['view']) && file_exists($upload_dir . basename($_GET['view']))) {
    $file_path = $upload_dir . basename($_GET['view']);
    $mime_type = mime_content_type($file_path);
    
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
    readfile($file_path);
    exit;
}

function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 bytes';
    }
}

function getFileIcon($ext) {
    $icons = [
        'pdf' => 'fa-file-pdf',
        'jpg' => 'fa-file-image',
        'jpeg' => 'fa-file-image',
        'png' => 'fa-file-image',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'ppt' => 'fa-file-powerpoint',
        'pptx' => 'fa-file-powerpoint',
        'txt' => 'fa-file-alt'
    ];
    return $icons[$ext] ?? 'fa-file';
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>File Sharing Portal</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #4361ee;
      --primary-light: #4895ef;
      --secondary: #3f37c9;
      --accent: #f72585;
      --light: #f8f9fa;
      --dark: #212529;
      --gray: #6c757d;
      --light-gray: #e9ecef;
      --success: #4cc9f0;
      --danger: #f72585;
      --warning: #f8961e;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #dfe7f1 100%);
      min-height: 100vh;
      padding: 2rem;
      color: var(--dark);
    }
    
    .portal-container {
      max-width: 900px;
      margin: 0 auto;
      animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .portal-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      overflow: hidden;
      margin-bottom: 2rem;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .portal-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
    }
    
    .portal-header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      color: white;
      padding: 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .portal-header h2 {
      font-weight: 600;
      font-size: 1.8rem;
      margin: 0;
    }
    
    .portal-header i {
      margin-right: 10px;
    }
    
    .portal-body {
      padding: 2rem;
    }
    
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.7rem 1.2rem;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      gap: 8px;
    }
    
    .btn-primary {
      background: var(--primary);
      color: white;
    }
    
    .btn-primary:hover {
      background: var(--secondary);
      transform: translateY(-2px);
    }
    
    .btn-outline {
      background: transparent;
      border: 2px solid var(--primary);
      color: var(--primary);
    }
    
    .btn-outline:hover {
      background: var(--primary);
      color: white;
    }
    
    .btn-danger {
      background: var(--danger);
      color: white;
    }
    
    .btn-danger:hover {
      background: #d1145a;
      transform: translateY(-2px);
    }
    
    .btn-sm {
      padding: 0.4rem 0.8rem;
      font-size: 0.85rem;
    }
    
    .form-group {
      margin-bottom: 1.2rem;
    }
    
    .form-control {
      width: 100%;
      padding: 0.8rem 1rem;
      border: 2px solid var(--light-gray);
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.3s ease;
    }
    
    .form-control:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
    }
    
    .alert {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .alert-success {
      background: rgba(76, 201, 240, 0.1);
      color: #0a7a96;
      border-left: 4px solid var(--success);
    }
    
    .alert-danger {
      background: rgba(247, 37, 133, 0.1);
      color: var(--danger);
      border-left: 4px solid var(--danger);
    }
    
    .file-list {
      list-style: none;
      margin-top: 1.5rem;
    }
    
    .file-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem;
      border-radius: 8px;
      transition: all 0.3s ease;
      margin-bottom: 0.8rem;
      background: var(--light);
    }
    
    .file-item:hover {
      background: white;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }
    
    .file-info {
      display: flex;
      align-items: center;
      flex-grow: 1;
      gap: 12px;
    }
    
    .file-icon {
      color: var(--primary);
      font-size: 1.4rem;
      min-width: 36px;
      text-align: center;
    }
    
    .file-name {
      flex-grow: 1;
      word-break: break-word;
      font-weight: 500;
    }
    
    .file-meta {
      display: flex;
      flex-direction: column;
      font-size: 0.85rem;
      color: var(--gray);
    }
    
    .file-actions {
      display: flex;
      gap: 8px;
    }
    
    .action-btn {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      transition: all 0.3s ease;
    }
    
    .action-btn:hover {
      transform: scale(1.1);
    }
    
    .view-btn {
      background: var(--success);
    }
    
    .download-btn {
      background: var(--primary);
    }
    
    .delete-btn {
      background: var(--danger);
    }
    
    .login-container {
      max-width: 450px;
      margin: 3rem auto;
    }
    
    .login-logo {
      text-align: center;
      margin-bottom: 2rem;
    }
    
    .login-logo i {
      font-size: 4rem;
      color: var(--primary);
      margin-bottom: 1rem;
    }
    
    .login-logo h1 {
      font-weight: 700;
      color: var(--dark);
    }
    
    .file-upload-area {
      border: 2px dashed var(--light-gray);
      border-radius: 12px;
      padding: 2rem;
      text-align: center;
      margin-bottom: 1.5rem;
      position: relative;
      transition: all 0.3s ease;
    }
    
    .file-upload-area:hover {
      border-color: var(--primary);
      background: rgba(67, 97, 238, 0.03);
    }
    
    .file-upload-area i {
      font-size: 2.5rem;
      color: var(--primary);
      margin-bottom: 1rem;
    }
    
    .file-upload-area p {
      margin-bottom: 1rem;
      color: var(--gray);
    }
    
    .file-upload-area input[type="file"] {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      cursor: pointer;
    }
    
    .admin-badge {
      display: inline-block;
      background: var(--warning);
      color: white;
      padding: 0.3rem 0.8rem;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      margin-left: 1rem;
    }
    
    @media (max-width: 768px) {
      body {
        padding: 1rem;
      }
      
      .file-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
      }
      
      .file-actions {
        width: 100%;
        justify-content: flex-end;
      }
    }
  </style>
</head>
<body>
  <div class="portal-container">
    <?php if (!isset($_SESSION['admin'])): ?>
      <?php if (isset($_GET['login'])): ?>
        <div class="login-container">
          <div class="login-logo">
            <i class="fas fa-lock"></i>
            <h1>Admin Portal</h1>
          </div>
          
          <div class="portal-card">
            <div class="portal-header">
              <h2><i class="fas fa-sign-in-alt"></i> Admin Login</h2>
            </div>
            <div class="portal-body">
              <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                  <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
              <?php endif; ?>
              
              <form method="post">
                <div class="form-group">
                  <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="form-group">
                  <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary">
                  <i class="fas fa-sign-in-alt"></i> Login
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="portal-card">
          <div class="portal-header">
            <h2><i class="fas fa-file-alt"></i> File Sharing Portal</h2>
            <a href="?login=1" class="btn btn-outline">
              <i class="fas fa-lock"></i> Admin Login
            </a>
          </div>
          <div class="portal-body">
            <!-- Public file listing -->
            <h3><i class="fas fa-folder-open"></i> Available Files</h3>
            <?php if (empty($files)): ?>
              <div class="alert alert-danger">
                <i class="fas fa-info-circle"></i> No files available yet.
              </div>
            <?php else: ?>
              <ul class="file-list">
                <?php foreach ($files as $file): ?>
                  <li class="file-item">
                    <div class="file-info">
                      <i class="fas <?= getFileIcon($file['ext']) ?> file-icon"></i>
                      <div>
                        <div class="file-name"><?= htmlspecialchars($file['name']) ?></div>
                        <div class="file-meta">
                          <span><?= formatSizeUnits($file['size']) ?></span>
                          <span>•</span>
                          <span><?= date('M d, Y H:i', $file['date']) ?></span>
                        </div>
                      </div>
                    </div>
                    <div class="file-actions">
                      <a href="?view=<?= urlencode($file['name']) ?>" class="action-btn view-btn" target="_blank" title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                      <a href="<?= $file['path'] ?>" download class="action-btn download-btn" title="Download">
                        <i class="fas fa-download"></i>
                      </a>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="portal-card">
        <div class="portal-header">
          <h2><i class="fas fa-file-alt"></i> File Sharing Portal <span class="admin-badge">ADMIN</span></h2>
          <a href="?logout=1" class="btn btn-danger">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
        </div>
        <div class="portal-body">
          <?php if (isset($message)): ?>
            <div class="alert alert-success">
              <i class="fas fa-check-circle"></i> <?= $message ?>
            </div>
          <?php endif; ?>
          <?php if (isset($error)): ?>
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
          <?php endif; ?>
          
          <h3><i class="fas fa-cloud-upload-alt"></i> Upload Files</h3>
          <form method="post" enctype="multipart/form-data">
            <div class="file-upload-area">
              <i class="fas fa-cloud-upload-alt"></i>
              <p>Drag & drop files here or click to browse</p>
              <p class="text-muted">Supports: PDF, Images, Word, PowerPoint, Text</p>
              <input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.ppt,.pptx,.txt">
            </div>
            <button type="submit" name="upload" class="btn btn-primary">
              <i class="fas fa-upload"></i> Upload Files
            </button>
          </form>
          
          <h3 style="margin-top: 2rem;"><i class="fas fa-folder-open"></i> File Manager</h3>
          <?php if (empty($files)): ?>
            <div class="alert alert-danger">
              <i class="fas fa-info-circle"></i> No files uploaded yet.
            </div>
          <?php else: ?>
            <ul class="file-list">
              <?php foreach ($files as $file): ?>
                <li class="file-item">
                  <div class="file-info">
                    <i class="fas <?= getFileIcon($file['ext']) ?> file-icon"></i>
                    <div>
                      <div class="file-name"><?= htmlspecialchars($file['name']) ?></div>
                      <div class="file-meta">
                        <span><?= formatSizeUnits($file['size']) ?></span>
                        <span>•</span>
                        <span><?= date('M d, Y H:i', $file['date']) ?></span>
                      </div>
                    </div>
                  </div>
                  <div class="file-actions">
                    <a href="?view=<?= urlencode($file['name']) ?>" class="action-btn view-btn" target="_blank" title="View">
                      <i class="fas fa-eye"></i>
                    </a>
                    <a href="<?= $file['path'] ?>" download class="action-btn download-btn" title="Download">
                      <i class="fas fa-download"></i>
                    </a>
                    <a href="?delete=<?= urlencode($file['name']) ?>" class="action-btn delete-btn" title="Delete" onclick="return confirm('Are you sure you want to delete this file?')">
                      <i class="fas fa-trash"></i>
                    </a>
                  </div>
                </li>
              <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </body>
</html>