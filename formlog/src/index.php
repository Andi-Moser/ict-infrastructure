<?php
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
    $stmt = $db->prepare("SELECT * FROM posts WHERE session_id = :sid ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([':sid' => session_id()]);
} else {
    $stmt = $db->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 50");
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <h2 class="text-2xl font-bold text-gray-800 mb-8 text-center">Geispeicherte Einträge</h2>

        <div class="flex justify-end">
            <a class="underline" href="?toggle=1">Ansicht: <?php echo !empty($_SESSION['own_only']) ? 'Eigene Einträge' : 'Alle Einträge'; ?></a>
        </div>

        <div class="shadow-md sm:rounded-lg">
            <table class="w-full mt-8 text-gray-500">
                <thead class="text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left">ID</th>
                        <th class="px-6 py-3 text-left">Name</th>
                        <th class="px-6 py-3 text-left">Vorname</th>
                        <th class="px-6 py-3 text-left">E-Mail</th>
                        <th class="px-6 py-3 text-left">Anzahl Felder</th>
                        <th class="px-6 py-3 text-left">Erstellt am</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): 
                        $data = json_decode($row['data'], true) ?: [];
                        $name = $data['name'] ?? '';
                        $firstname = $data['firstname'] ?? data['vorname'] ?? '';
                        $email = $data['email'] ?? '';
                        $comment = $data['comment'] ?? '';
                    ?>
                        <tr class="cursor-pointer odd:bg-white even:bg-gray-50 border-b border-gray-200" onclick="showDetails(this)" data-json='<?= str_replace("\\r\\n", "<br>", nl2br(json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT))) ?>' data-created='<?= date('d.m.Y H:i:s', $row['created_at']) ?>'>
                            <td class="px-6 py-3"><?= $row['id'] ?></td>
                            <td class="px-6 py-3"><?= htmlspecialchars($name) ?></td>
                            <td class="px-6 py-3"><?= htmlspecialchars($firstname) ?></td>
                            <td class="px-6 py-3"><?= htmlspecialchars($email) ?></td>
                            <td class="px-6 py-3"><?= count($data) ?></td>
                            <td class="px-6 py-3"><?= date('d.m.Y H:i:s', $row['created_at']) ?></td>
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


    <dialog id="modal" class="rounded-2xl shadow-xl p-6 w-128 backdrop:bg-black/50 mx-auto mt-24">
        <div class="modal-box">
            <h2 class="text-xl font-semibold mb-2">Details</h2>
            <div id="details"></div>
            <div class="flex justify-end space-x-2">
            <button id="close" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-3 py-1 rounded-lg">Close</button>
            </div>
        </div>
    </dialog>
    <script>
        function showDetails(row) {
            const data = JSON.parse(row.dataset.json);
            let html = '';
            for (let key in data) {
                html += '<p><strong>' + key + ':</strong> ' + data[key] + '</p>';
            }
            html += '<p><strong>Erstellt am:</strong> ' + row.dataset.created + '</p>';
            document.getElementById('details').innerHTML = html;
            document.getElementById('modal').showModal();
        }

        document.getElementById('close').addEventListener('click', function() {
            document.getElementById('modal').close();
        })
        const modal = document.getElementById('modal');
        const box = modal.querySelector('.modal-box');

        // Close on backdrop click
        modal.addEventListener('click', e => {
        const rect = box.getBoundingClientRect();
        if (
            e.clientX < rect.left ||
            e.clientX > rect.right ||
            e.clientY < rect.top ||
            e.clientY > rect.bottom
        ) {
            modal.close();
        }
        });

    </script>
</body>
</html>
