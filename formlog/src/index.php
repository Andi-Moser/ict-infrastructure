<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// SQLite init
$db = new PDO('sqlite:database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT,
    data TEXT,
    created_at INTEGER
)");

// Delete old entries (>7 days)
$weekAgo = time() - (7 * 24 * 60 * 60);
$stmt = $db->prepare("DELETE FROM posts WHERE created_at < :t");
$stmt->execute([':t' => $weekAgo]);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $data = json_encode($_POST, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $stmt = $db->prepare("INSERT INTO posts (session_id, data, created_at) VALUES (:sid, :data, :ts)");
    $stmt->execute([
        ':sid'  => session_id(),
        ':data' => $data,
        ':ts'   => time()
    ]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Toggle session filter
if (isset($_GET['toggle'])) {
    $_SESSION['own_only'] = !($_SESSION['own_only'] ?? false);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Query entries
if (!empty($_SESSION['own_only'])) {
  $stmt = $db->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 50");
} else {
  $stmt = $db->prepare("SELECT * FROM posts WHERE session_id = :sid ORDER BY created_at DESC LIMIT 50");
  $stmt->execute([':sid' => session_id()]);
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extract the most used fieldnames
$fieldnames = [];
foreach ($rows as $row) {
    $data = json_decode($row['data'], true) ?: [];
    foreach (array_keys($data) as $key) {
        if (in_array(strtolower($key), ['comment', 'kommentar', 'bemerkung'])) {
            continue;
        }
        if (!isset($fieldnames[$key])) {
            $fieldnames[$key] = 0;
        }
        $fieldnames[$key]++;
    }
}
arsort($fieldnames);

// keep the first 3
$fieldnames = array_slice($fieldnames, 0, 3, true);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ICT-BZ Formlog</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style type="text/tailwindcss">

    </style>

</head>
<body class="p-4 bg-gray-200">
<div class="max-w-[1280px] mx-auto my-12 bg-white p-10 rounded-2xl shadow">
        <h2 class="text-2xl font-bold text-gray-800 mb-8 text-center">Gespeicherte Einträge</h2>

        <div class="flex justify-end">
            <a class="underline" href="?toggle=1">Ansicht: <?php echo !empty($_SESSION['own_only']) ? 'Alle Einträge' : 'Eigene Einträge'; ?></a>
        </div>

        <div class="shadow-md sm:rounded-lg">
            <table class="w-full mt-8 text-gray-500">
                <thead class="text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left">ID</th>
                        <?php foreach ($fieldnames as $fieldname => $count): ?>
                            <th class="px-6 py-3 text-left"><?= htmlspecialchars(ucfirst($fieldname)) ?></th>
                        <?php endforeach; ?>
                        <th class="px-6 py-3 text-left">Anzahl Felder</th>
                        <th class="px-6 py-3 text-left">Erstellt am</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $row):
                        $data = json_decode($row['data'], true) ?: [];
                        $name = $data['name'] ?? $data['lastname'] ?? $data['nachname'] ?? $data['Nachname'] ?? $data['Name'] ?? $data['Lastname'] ??   '';
                        $firstname = $data['firstname'] ?? $data['vorname'] ?? $data['Firstname'] ?? $data['Vorname'] ??  '';
                        $email = $data['email'] ?? $data['e-mail'] ?? $data['Email'] ?? $data['E-mail'] ?? $data['E-Mail'] ?? '';
                    ?>
                        <tr class="cursor-pointer <?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-200' ?> border-b border-gray-200" onclick="showDetails(<?= $i ?>)">
                            <td class="px-6 py-3"><?= $row['id'] ?></td>
                            <?php foreach ($fieldnames as $fieldname => $count): ?>
                                <td class="px-6 py-3"><?= htmlspecialchars($data[$fieldname] ?? '') ?></td>
                            <?php endforeach; ?>
                            <td class="px-6 py-3"><?= count($data) ?></td>
                            <td class="px-6 py-3"><?= date('d.m.Y H:i:s', $row['created_at']) ?></td>
                        </tr>
                        <tr class="hidden" data-row="<?= $i ?>">
                            <td colspan="6" class="<?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-200' ?>">
                                <!-- Details -->
                                <div id="details" class="p-4">
                                    <?php
                                    foreach ($data as $key => $value) {
                                        echo '<p><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</p>';
                                    }
                                    echo '<p><strong>Erstellt am:</strong> ' . date('d.m.Y H:i:s', $row['created_at']) . '</p>';
                                    ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="max-w-[1280px] mx-auto my-12 bg-white p-10 rounded-2xl shadow">
  <h2 class="text-2xl font-bold text-gray-800 mb-8 text-center">Neuen Eintrag erstellen</h2>

  <form method="post" class="grid grid-cols-2 gap-6">
    <div class="flex flex-col">
      <label for="name" class="text-sm font-medium text-gray-700 mb-1">Name</label>
      <input 
        id="name"
        type="text" 
        name="name" 
        placeholder="Name"
        class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
      >
    </div>

    <div class="flex flex-col">
      <label for="firstname" class="text-sm font-medium text-gray-700 mb-1">Vorname</label>
      <input 
        id="firstname"
        type="text" 
        name="firstname"
        placeholder="Vorname"
        class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
      >
    </div>

    <div class="flex flex-col">
      <label for="email" class="text-sm font-medium text-gray-700 mb-1">E-Mail</label>
      <input 
        id="email"
        type="email" 
        name="email" 
        placeholder="E-Mail"
        class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
      >
    </div>

    <div class="col-span-2 flex flex-col">
      <label for="comment" class="text-sm font-medium text-gray-700 mb-1">Kommentar</label>
      <textarea 
        id="comment"
        name="comment" 
        placeholder="Kommentar" 
        rows="4"
        class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
      ></textarea>
    </div>

    <div class="col-span-2 flex justify-end">
      <button 
        type="submit"
        class="bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg px-6 py-2 shadow focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        Absenden
      </button>
    </div>
  </form>
</div>
    <script>
        function showDetails(row) {
            const detailRow = document.querySelector(`tr[data-row='${row}']`);
            if (detailRow.classList.contains('hidden')) {
                detailRow.classList.remove('hidden');
            } else {
                detailRow.classList.add('hidden');
            }
        }

    </script>
</body>
</html>
