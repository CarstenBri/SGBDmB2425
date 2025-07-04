<?php
// --- Debugging-Funktion ---
// Schreibt Nachrichten in eine Log-Datei, um Fehler zu finden.
function log_debug($message) {
    // Erstellt einen Zeitstempel für den Log-Eintrag.
    $timestamp = date('Y-m-d H:i:s');
    // Formatiert die Nachricht. is_string prüft, ob die Nachricht Text ist.
    // Wenn nicht (z.B. ein Array), wird sie mit print_r für die Ausgabe formatiert.
    $log_entry = $timestamp . " | " . (is_string($message) ? $message : print_r($message, true)) . "\n";
    // Schreibt die Nachricht in die Datei 'debug_log.txt'. FILE_APPEND sorgt dafür, dass neue Einträge hinzugefügt werden.
    file_put_contents('debug_log.txt', $log_entry, FILE_APPEND);
}

// Start des Debuggings für jede Anfrage.
log_debug("--- Neue Anfrage erhalten ---");
log_debug("Aktion (aus GET): " . ($_GET['action'] ?? 'nicht gesetzt'));
log_debug("Request-Methode: " . $_SERVER['REQUEST_METHOD']);

// Set the correct header to output JSON.
header('Content-Type: application/json');

// Define the name of our database file.
$db_file = 'handball_data.json';

// --- Pre-flight Check: Ensure the database file is usable ---
if (!file_exists($db_file)) {
    if (@file_put_contents($db_file, '[]') === false) {
        $error_msg = 'Server-Fehler: Die Datenbank-Datei konnte nicht erstellt werden. Überprüfen Sie die Schreibrechte für den Ordner auf dem Webserver.';
        log_debug($error_msg);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $error_msg]);
        exit;
    }
}

if (!is_writable($db_file)) {
    $error_msg = 'Server-Fehler: Die Datenbank-Datei ist nicht beschreibbar. Überprüfen Sie die Dateiberechtigungen (z.B. auf 664 oder 777 setzen).';
    log_debug($error_msg);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $error_msg]);
    exit;
}

$action = $_GET['action'] ?? '';

// --- Utility Functions ---
function get_all_games_map() {
    global $db_file;
    $json_data = file_get_contents($db_file);
    $games = json_decode($json_data, true) ?: [];
    $games_map = [];
    foreach ($games as $game) {
        if (isset($game['id'])) {
            $games_map[$game['id']] = $game;
        }
    }
    return $games_map;
}

function save_all_games($games_map) {
    global $db_file;
    $indexed_games = array_values($games_map);
    file_put_contents($db_file, json_encode($indexed_games, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// --- Action Handling ---
$request_payload = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_data = file_get_contents('php://input');
    log_debug("Empfangene Roh-POST-Daten: " . $post_data);
    $request_payload = json_decode($post_data, true);

    // Detaillierte Prüfung auf JSON-Fehler
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_debug("JSON-Dekodierungsfehler: " . json_last_error_msg());
    }
}

switch ($action) {
    case 'load':
        log_debug("Aktion 'load' wird ausgeführt.");
        $games_map = get_all_games_map();
        echo json_encode(array_values($games_map));
        break;

    case 'save':
        log_debug("Aktion 'save' wird ausgeführt.");
        $new_game = $request_payload;

        if (!$new_game || !isset($new_game['id'])) {
            $error_msg = 'Ungültige Spieldaten erhalten oder ID fehlt.';
            log_debug($error_msg);
            log_debug("Payload-Inhalt: " . print_r($new_game, true));
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $error_msg]);
            exit;
        }

        log_debug("Speichere Spiel mit ID: " . $new_game['id']);
        $games_map = get_all_games_map();
        $games_map[$new_game['id']] = $new_game;
        save_all_games($games_map);
        log_debug("Speichern erfolgreich.");
        echo json_encode(['status' => 'success', 'message' => 'Spiel erfolgreich gespeichert.']);
        break;

    case 'delete':
        log_debug("Aktion 'delete' wird ausgeführt.");
        $data_to_delete = $request_payload;

        if (!$data_to_delete || !isset($data_to_delete['id'])) {
            $error_msg = 'Ungültige Spiel-ID zum Löschen erhalten.';
            log_debug($error_msg);
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $error_msg]);
            exit;
        }

        $game_id_to_delete = $data_to_delete['id'];
        log_debug("Lösche Spiel mit ID: " . $game_id_to_delete);
        $games_map = get_all_games_map();
        if (isset($games_map[$game_id_to_delete])) {
            unset($games_map[$game_id_to_delete]);
            save_all_games($games_map);
            log_debug("Löschen erfolgreich.");
        } else {
            log_debug("Spiel-ID zum Löschen nicht gefunden.");
        }
        echo json_encode(['status' => 'success', 'message' => 'Spiel erfolgreich gelöscht.']);
        break;

    default:
        $error_msg = 'Keine gültige Aktion angegeben.';
        log_debug($error_msg);
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $error_msg]);
        break;
}

?>
