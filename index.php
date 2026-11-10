<?php
declare(strict_types=1);
session_start();

/* ---------- Configuration: edit for your environment ---------- */
const DB_HOST = 'localhost';
const DB_NAME = 'lab05_event_registration';
const DB_USER = 'root';
const DB_PASS = '';

/* ---------- Helpers ---------- */
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

function post(string $key): string
{
    return (isset($_POST[$key]) && is_string($_POST[$key])) ? trim($_POST[$key]) : '';
}

function err(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="error" id="' . h($key) . '-error" role="alert">' . h($errors[$key]) . '</p>' : '';
}

function inv(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . h($key) . '-error"' : '';
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ---------- Allowed values ---------- */
$workshops = ['Web Security Basics', 'Introduction to Docker', 'Data Analysis with Python', 'Agile Project Management'];
$modes     = ['In person', 'Online'];

/* ---------- State ---------- */
$errors = [];
$old = ['participant_name' => '', 'email' => '', 'phone' => '', 'workshop' => '', 'mode' => '',
        'seats' => '', 'requirements' => '', 'terms' => ''];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* ---------- Handle submission ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], post('csrf'))) {
        $errors['form'] = 'Your session expired. Reload the page and submit again.';
    }
    foreach ($old as $key => $_) {
        $old[$key] = post($key);
    }
    $old['participant_name'] = preg_replace('/\s+/u', ' ', $old['participant_name']);

    // Name
    $len = mb_strlen($old['participant_name']);
    if ($old['participant_name'] === '') {
        $errors['participant_name'] = 'Enter the participant name.';
    } elseif ($len < 3 || $len > 80) {
        $errors['participant_name'] = 'Name must be 3 to 80 characters.';
    } elseif (!preg_match("/^\p{L}[\p{L} .'\-]*$/u", $old['participant_name'])) {
        $errors['participant_name'] = 'Use letters, spaces, apostrophes, hyphens and full stops only.';
    }

    // Email (required, valid, normalised to lower case)
    $email = mb_strtolower($old['email']);
    if ($email === '') {
        $errors['email'] = 'Enter an email address.';
    } elseif (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address, e.g. name@example.com.';
    }

    // Phone (optional): 9 digits starting with 6 when supplied
    $phone = null;
    if ($old['phone'] !== '') {
        $compact = preg_replace('/[\s\-]/', '', $old['phone']);
        if (preg_match('/^(?:\+?237)?(6\d{8})$/', $compact, $m)) {
            $phone = $m[1];
        } else {
            $errors['phone'] = 'Enter 9 digits starting with 6, or leave this blank.';
        }
    }

    // Workshop and attendance mode
    if ($old['workshop'] === '') {
        $errors['workshop'] = 'Choose a workshop.';
    } elseif (!in_array($old['workshop'], $workshops, true)) {
        $errors['workshop'] = 'Choose a workshop from the list.';
    }
    if ($old['mode'] === '') {
        $errors['mode'] = 'Choose an attendance mode.';
    } elseif (!in_array($old['mode'], $modes, true)) {
        $errors['mode'] = 'Choose one of the listed options.';
    }

    // Seats: integer 1-3
    $seats = filter_var($old['seats'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3]]);
    if ($old['seats'] === '') {
        $errors['seats'] = 'Enter the number of seats.';
    } elseif ($seats === false || !preg_match('/^\d+$/', $old['seats'])) {
        $errors['seats'] = 'Seats must be a whole number from 1 to 3.';
    }

    // Special requirements: max 300 characters
    if (mb_strlen($old['requirements']) > 300) {
        $errors['requirements'] = 'Special requirements must be 300 characters or fewer.';
    }

    // Terms
    if ($old['terms'] !== '1') {
        $errors['terms'] = 'You must accept the event terms.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $dup = $pdo->prepare('SELECT 1 FROM registrations WHERE email = :e AND workshop = :w LIMIT 1');
            $dup->execute([':e' => $email, ':w' => $old['workshop']]);

            if ($dup->fetchColumn()) {
                $errors['email'] = 'This email is already registered for "' . $old['workshop'] . '".';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO registrations (participant_name, email, phone, workshop, attendance_mode, seats, special_requirements, terms_accepted)
                     VALUES (:n, :e, :p, :w, :m, :s, :r, 1)'
                );
                $stmt->execute([
                    ':n' => $old['participant_name'], ':e' => $email, ':p' => $phone, ':w' => $old['workshop'],
                    ':m' => $old['mode'], ':s' => $seats,
                    ':r' => $old['requirements'] !== '' ? $old['requirements'] : null,
                ]);
                if ($stmt->rowCount() === 1) {
                    $_SESSION['flash'] = ['msg' => 'Registration saved (reference ' . $pdo->lastInsertId() . ') for "'
                        . $old['workshop'] . '", ' . $seats . ' seat(s).'];
                    header('Location: index.php', true, 303);
                    exit;
                }
                $errors['form'] = 'The registration could not be saved. Please try again.';
            }
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                $errors['email'] = 'This email is already registered for "' . $old['workshop'] . '".';
            } else {
                error_log('Lab05 insert failed: ' . $e->getMessage());
                $errors['form'] = 'The database is unavailable right now. Please try again later.';
            }
        }
    }
}

/* ---------- Extension: totals per workshop ---------- */
$totals = [];
$listError = null;
try {
    $totals = db()->query(
        'SELECT workshop, COUNT(*) AS registrations, SUM(seats) AS seats
         FROM registrations GROUP BY workshop ORDER BY workshop'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('Lab05 totals failed: ' . $e->getMessage());
    $listError = 'The totals are unavailable (database connection problem).';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Event Registration</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
  <h1 class="page-title">Workshop registration</h1>
  <p class="subtitle">Reserve your place. Fields marked * are required.</p>

  <section class="card" aria-labelledby="form-title">
    <h2 id="form-title">Registration form</h2>

    <?php if ($flash): ?>
      <div class="alert alert-success" role="status"><?= h($flash['msg']) ?></div>
    <?php endif; ?>
    <?php if (isset($errors['form'])): ?>
      <div class="alert alert-error" role="alert"><?= h($errors['form']) ?></div>
    <?php elseif ($errors): ?>
      <div class="alert alert-error" role="alert">Please correct the highlighted fields.</div>
    <?php endif; ?>

    <form method="post" action="index.php" novalidate>
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

      <div class="form-group">
        <label class="required" for="participant_name">Participant name</label>
        <input type="text" id="participant_name" name="participant_name" maxlength="80" autocomplete="name"
               value="<?= h($old['participant_name']) ?>"<?= inv($errors, 'participant_name') ?>>
        <?= err($errors, 'participant_name') ?>
      </div>

      <div class="form-group">
        <label class="required" for="email">Email</label>
        <input type="email" id="email" name="email" maxlength="150" autocomplete="email"
               value="<?= h($old['email']) ?>"<?= inv($errors, 'email') ?>>
        <?= err($errors, 'email') ?>
      </div>

      <div class="form-group">
        <label for="phone">Phone</label>
        <input type="tel" id="phone" name="phone" inputmode="numeric" autocomplete="tel" placeholder="671234567"
               value="<?= h($old['phone']) ?>"<?= inv($errors, 'phone') ?>>
        <p class="hint">Optional. 9 digits starting with 6.</p>
        <?= err($errors, 'phone') ?>
      </div>

      <div class="form-group">
        <label class="required" for="workshop">Workshop</label>
        <select id="workshop" name="workshop"<?= inv($errors, 'workshop') ?>>
          <option value="">Choose an option</option>
          <?php foreach ($workshops as $w): ?>
            <option value="<?= h($w) ?>"<?= $old['workshop'] === $w ? ' selected' : '' ?>><?= h($w) ?></option>
          <?php endforeach; ?>
        </select>
        <?= err($errors, 'workshop') ?>
      </div>

      <fieldset>
        <legend class="required">Attendance mode</legend>
        <?php foreach ($modes as $m): ?>
          <label class="choice">
            <input type="radio" name="mode" value="<?= h($m) ?>"<?= $old['mode'] === $m ? ' checked' : '' ?>>
            <?= h($m) ?>
          </label>
        <?php endforeach; ?>
        <?= err($errors, 'mode') ?>
      </fieldset>

      <div class="form-group">
        <label class="required" for="seats">Number of seats</label>
        <input type="number" id="seats" name="seats" min="1" max="3" step="1"
               value="<?= h($old['seats']) ?>"<?= inv($errors, 'seats') ?>>
        <p class="hint">1 to 3 seats per registration.</p>
        <?= err($errors, 'seats') ?>
      </div>

      <div class="form-group">
        <label for="requirements">Special requirements</label>
        <textarea id="requirements" name="requirements" maxlength="300"<?= inv($errors, 'requirements') ?>><?= h($old['requirements']) ?></textarea>
        <p class="hint">Up to 300 characters.</p>
        <?= err($errors, 'requirements') ?>
      </div>

      <div class="form-group">
        <span class="required" style="font-weight:600">Accept event terms</span><br>
        <label class="checkbox-row" for="terms">
          <input type="checkbox" id="terms" name="terms" value="1"<?= $old['terms'] === '1' ? ' checked' : '' ?><?= inv($errors, 'terms') ?>>
          I agree
        </label>
        <?= err($errors, 'terms') ?>
      </div>

      <div class="actions">
        <button type="submit" class="btn-primary">Submit Form</button>
        <button type="reset" class="btn-secondary">Reset</button>
      </div>
    </form>
  </section>

  <section class="card" aria-labelledby="list-title">
    <h2 id="list-title">Registrations per workshop</h2>
    <?php if ($listError): ?>
      <p class="error"><?= h($listError) ?></p>
    <?php elseif (!$totals): ?>
      <p class="empty">No registrations yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Workshop</th><th>Registrations</th><th>Seats booked</th></tr></thead>
          <tbody>
          <?php foreach ($totals as $t): ?>
            <tr>
              <td><?= h($t['workshop']) ?></td>
              <td><?= h((string)$t['registrations']) ?></td>
              <td><?= h((string)$t['seats']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
