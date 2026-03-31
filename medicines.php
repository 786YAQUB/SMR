<?php
// medicines.php - CRUD for medicines + logs + stats
require_once 'config.php';

$uid    = requireLogin();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$data   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'list':      getMedicines($uid);          break;
    case 'add':       addMedicine($uid, $data);    break;
    case 'update':    updateMedicine($uid, $data); break;
    case 'delete':    deleteMedicine($uid);        break;
    case 'toggle':    toggleMedicine($uid, $data); break;
    case 'log':       logDose($uid, $data);        break;
    case 'logs':      getLogs($uid);               break;
    case 'stats':     getStats($uid);              break;
    case 'today':     getTodaySchedule($uid);      break;
    case 'notes':     handleNotes($uid, $data);    break;
    case 'note_add':  addNote($uid, $data);        break;
    default: jsonResponse(['error' => 'Invalid action'], 400);
}

// ── List medicines ─────────────────────────────────────────
function getMedicines($uid) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM medicines WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $meds = $stmt->fetchAll();
    foreach ($meds as &$m) $m['times'] = json_decode($m['times'], true);
    jsonResponse(['medicines' => $meds]);
}

// ── Add medicine ───────────────────────────────────────────
function addMedicine($uid, $d) {
    $db = getDB();
    $name  = trim($d['name'] ?? '');
    $dose  = trim($d['dosage'] ?? '');
    $freq  = $d['frequency'] ?? '';
    $times = $d['times'] ?? [];
    $start = $d['start_date'] ?? date('Y-m-d');

    if (!$name || !$dose || !$freq || empty($times))
        jsonResponse(['error' => 'Name, dosage, frequency and times are required'], 400);

    $colors = ['#ff6b6b','#4ecdc4','#45b7d1','#96ceb4','#feca57','#ff9ff3','#54a0ff','#5f27cd'];
    $color  = $d['color'] ?? $colors[array_rand($colors)];

    $stmt = $db->prepare("INSERT INTO medicines (user_id,name,dosage,medicine_type,frequency,times,start_date,end_date,instructions,color) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $uid, $name, $dose,
        $d['medicine_type'] ?? 'tablet',
        $freq,
        json_encode($times),
        $start,
        $d['end_date'] ?: null,
        $d['instructions'] ?? '',
        $color
    ]);
    jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Medicine added']);
}

// ── Update medicine ────────────────────────────────────────
function updateMedicine($uid, $d) {
    $db = getDB();
    $id = (int)($d['id'] ?? 0);

    $stmt = $db->prepare("SELECT id FROM medicines WHERE id=? AND user_id=?");
    $stmt->execute([$id, $uid]);
    if (!$stmt->fetch()) jsonResponse(['error' => 'Not found'], 404);

    $db->prepare("UPDATE medicines SET name=?,dosage=?,medicine_type=?,frequency=?,times=?,start_date=?,end_date=?,instructions=?,color=? WHERE id=? AND user_id=?")
       ->execute([
           $d['name'], $d['dosage'], $d['medicine_type'], $d['frequency'],
           json_encode($d['times']), $d['start_date'], $d['end_date'] ?: null,
           $d['instructions'] ?? '', $d['color'], $id, $uid
       ]);
    jsonResponse(['success' => true, 'message' => 'Medicine updated']);
}

// ── Delete medicine ────────────────────────────────────────
function deleteMedicine($uid) {
    $db = getDB();
    $id = (int)($_GET['id'] ?? 0);
    $db->prepare("DELETE FROM medicines WHERE id=? AND user_id=?")->execute([$id, $uid]);
    jsonResponse(['success' => true, 'message' => 'Medicine deleted']);
}

// ── Toggle active/inactive ─────────────────────────────────
function toggleMedicine($uid, $d) {
    $db = getDB();
    $id = (int)($d['id'] ?? 0);
    $db->prepare("UPDATE medicines SET is_active = NOT is_active WHERE id=? AND user_id=?")->execute([$id, $uid]);
    jsonResponse(['success' => true]);
}

// ── Log a dose (taken/skipped) ─────────────────────────────
function logDose($uid, $d) {
    $db = getDB();
    $mid    = (int)($d['medicine_id'] ?? 0);
    $status = $d['status'] ?? 'taken'; // taken | skipped
    $sched  = $d['scheduled_time'] ?? date('Y-m-d H:i:s');
    $notes  = $d['notes'] ?? '';

    $stmt = $db->prepare("SELECT id FROM medicines WHERE id=? AND user_id=?");
    $stmt->execute([$mid, $uid]);
    if (!$stmt->fetch()) jsonResponse(['error' => 'Not found'], 404);

    // Check for existing log for same medicine+scheduled_time
    $check = $db->prepare("SELECT id FROM reminder_logs WHERE medicine_id=? AND scheduled_time=? AND user_id=?");
    $check->execute([$mid, $sched, $uid]);
    if ($check->fetch()) {
        $db->prepare("UPDATE reminder_logs SET status=?,taken_at=?,notes=? WHERE medicine_id=? AND scheduled_time=? AND user_id=?")
           ->execute([$status, $status==='taken'?date('Y-m-d H:i:s'):null, $notes, $mid, $sched, $uid]);
    } else {
        $db->prepare("INSERT INTO reminder_logs (medicine_id,user_id,scheduled_time,status,taken_at,notes) VALUES (?,?,?,?,?,?)")
           ->execute([$mid, $uid, $sched, $status, $status==='taken'?date('Y-m-d H:i:s'):null, $notes]);
    }
    jsonResponse(['success' => true, 'status' => $status]);
}

// ── Get recent logs ────────────────────────────────────────
function getLogs($uid) {
    $db = getDB();
    $days = (int)($_GET['days'] ?? 7);
    $stmt = $db->prepare("
        SELECT rl.*, m.name AS medicine_name, m.color, m.medicine_type
        FROM reminder_logs rl
        JOIN medicines m ON rl.medicine_id = m.id
        WHERE rl.user_id=? AND rl.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY rl.scheduled_time DESC
        LIMIT 100
    ");
    $stmt->execute([$uid, $days]);
    jsonResponse(['logs' => $stmt->fetchAll()]);
}

// ── Stats / adherence ──────────────────────────────────────
function getStats($uid) {
    $db = getDB();

    // Total active medicines
    $s = $db->prepare("SELECT COUNT(*) AS cnt FROM medicines WHERE user_id=? AND is_active=1");
    $s->execute([$uid]); $totalMeds = $s->fetch()['cnt'];

    // Last 30 days logs
    $s = $db->prepare("SELECT status, COUNT(*) AS cnt FROM reminder_logs WHERE user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY status");
    $s->execute([$uid]);
    $logs = ['taken'=>0,'skipped'=>0,'missed'=>0];
    foreach ($s->fetchAll() as $row) $logs[$row['status']] = (int)$row['cnt'];
    $total = array_sum($logs);
    $adherence = $total > 0 ? round(($logs['taken']/$total)*100) : 0;

    // Streak (consecutive days taken)
    $s = $db->prepare("SELECT DATE(scheduled_time) AS day, SUM(status='taken') AS taken, COUNT(*) AS total FROM reminder_logs WHERE user_id=? AND scheduled_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY day ORDER BY day DESC");
    $s->execute([$uid]);
    $streak = 0;
    foreach ($s->fetchAll() as $row) {
        if ($row['taken'] > 0 && $row['taken'] == $row['total']) $streak++;
        else break;
    }

    jsonResponse([
        'total_medicines' => (int)$totalMeds,
        'adherence'       => $adherence,
        'streak'          => $streak,
        'logs_summary'    => $logs
    ]);
}

// ── Today's schedule ───────────────────────────────────────
function getTodaySchedule($uid) {
    $db   = getDB();
    $today= date('Y-m-d');

    $stmt = $db->prepare("SELECT * FROM medicines WHERE user_id=? AND is_active=1 AND start_date<=? AND (end_date IS NULL OR end_date>=?)");
    $stmt->execute([$uid, $today, $today]);
    $meds = $stmt->fetchAll();

    $schedule = [];
    foreach ($meds as $m) {
        $times = json_decode($m['times'], true);
        foreach ($times as $t) {
            $sched_dt = $today . ' ' . $t . ':00';
            // Check if logged
            $ls = $db->prepare("SELECT status FROM reminder_logs WHERE medicine_id=? AND scheduled_time=? AND user_id=?");
            $ls->execute([$m['id'], $sched_dt, $uid]);
            $log = $ls->fetch();
            $schedule[] = [
                'medicine_id'    => $m['id'],
                'medicine_name'  => $m['name'],
                'dosage'         => $m['dosage'],
                'medicine_type'  => $m['medicine_type'],
                'color'          => $m['color'],
                'time'           => $t,
                'scheduled_time' => $sched_dt,
                'status'         => $log ? $log['status'] : 'pending',
                'instructions'   => $m['instructions'],
            ];
        }
    }
    usort($schedule, fn($a,$b) => strcmp($a['time'], $b['time']));
    jsonResponse(['schedule' => $schedule, 'date' => $today]);
}

// ── Health notes ───────────────────────────────────────────
function handleNotes($uid, $d) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM health_notes WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$uid]);
    jsonResponse(['notes' => $stmt->fetchAll()]);
}

function addNote($uid, $d) {
    $db   = getDB();
    $note = trim($d['note'] ?? '');
    $mood = $d['mood'] ?? 'okay';
    if (!$note) jsonResponse(['error' => 'Note text required'], 400);
    $db->prepare("INSERT INTO health_notes (user_id,note,mood) VALUES (?,?,?)")->execute([$uid,$note,$mood]);
    jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
}
?>