<?php

require_once __DIR__ . '/../lib/response.php';

$configPath = __DIR__ . '/../config.php';
$installed = file_exists($configPath);
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
    $dbPort = (int) ($_POST['db_port'] ?? 3306);
    $dbName = trim((string) ($_POST['db_name'] ?? ''));
    $dbUser = trim((string) ($_POST['db_user'] ?? ''));
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $siteName = trim((string) ($_POST['site_name'] ?? 'XUNX AdLink'));
    $siteUrl = trim((string) ($_POST['site_url'] ?? ''));
    $adminName = trim((string) ($_POST['admin_name'] ?? 'Администратор'));
    $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');

    if ($dbName === '' || $dbUser === '' || $adminEmail === '' || $adminPassword === '') {
        $errors[] = 'Заполните обязательные поля.';
    }

    if ($adminEmail !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email администратора.';
    }

    if ($adminPassword !== '' && mb_strlen($adminPassword) < 6) {
        $errors[] = 'Пароль администратора должен быть не короче 6 символов.';
    }

    if ($errors === []) {
        $conn = new mysqli($dbHost, $dbUser, $dbPass, '', $dbPort);
        if ($conn->connect_error) {
            $errors[] = 'Не удалось подключиться к базе данных.';
        } else {
            $conn->set_charset('utf8mb4');
            $dbNameEsc = $conn->real_escape_string($dbName);
            if (!$conn->query("CREATE DATABASE IF NOT EXISTS `{$dbNameEsc}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
                $errors[] = 'Не удалось создать базу данных.';
            } else {
                $conn->select_db($dbName);
                $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
                $statements = preg_split('/;\s*[\r\n]+/', $schema);
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if ($statement === '') {
                        continue;
                    }
                    if (!$conn->query($statement)) {
                        $errors[] = 'Ошибка выполнения SQL: ' . $conn->error;
                        break;
                    }
                }

                if ($errors === []) {
                    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare(
                        'INSERT INTO users (role, name, email, password_hash, status)
                         VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE role = VALUES(role), status = VALUES(status)'
                    );
                    $role = 'admin';
                    $status = 'active';
                    $stmt->bind_param('sssss', $role, $adminName, $adminEmail, $passwordHash, $status);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare(
                        'INSERT INTO settings (setting_key, setting_value)
                         VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                    );
                    $settings = [
                        'site.name' => $siteName,
                        'site.url' => $siteUrl,
                        'fraud.max_clicks_per_ip_campaign_10min' => '5',
                        'fraud.max_clicks_per_fingerprint_10min' => '8',
                        'fraud.min_time_on_page_ms' => '2000',
                        'finance.commission_rate' => '0.2',
                        'finance.currency' => 'RUB',
                    ];
                    foreach ($settings as $key => $value) {
                        $stmt->bind_param('ss', $key, $value);
                        $stmt->execute();
                    }
                    $stmt->close();

                    $config = [
                        'db' => [
                            'host' => $dbHost,
                            'user' => $dbUser,
                            'pass' => $dbPass,
                            'name' => $dbName,
                            'port' => $dbPort,
                        ],
                        'geo' => [
                            'allowed_regions' => [
                                'MOW',
                                'SPE',
                                'NIZ',
                                'SVE',
                                'KDA',
                                'TAT',
                                'ROS',
                                'SAM',
                                'KGD',
                                'KHA',
                                'PER',
                                'VLA',
                                'KEM',
                                'KIR',
                                'KRS',
                                'IRK',
                                'KLU',
                                'TVE',
                                'VOR',
                                'LEN',
                                'OMS',
                                'ALR',
                                'YAN',
                                'CHE',
                                'BEL',
                                'BRY',
                                'TUL',
                                'SAR',
                                'VOL',
                                'ORL',
                                'PNZ',
                                'KHM',
                                'TYU',
                                'KOS',
                                'SVE',
                                'ALT',
                                'KYA',
                                'KAM',
                                'SAK',
                                'MSK',
                                'IVA',
                            ],
                        ],
                        'fraud' => [
                            'max_clicks_per_ip_campaign_10min' => 5,
                            'max_clicks_per_fingerprint_10min' => 8,
                            'min_time_on_page_ms' => 2000,
                        ],
                        'finance' => [
                            'commission_rate' => 0.2,
                            'currency' => 'RUB',
                        ],
                    ];

                    $configExport = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($config, true) . ';' . PHP_EOL;
                    if (!file_put_contents($configPath, $configExport)) {
                        $errors[] = 'Не удалось записать config.php.';
                    } else {
                        $success = true;
                        $installed = true;
                    }
                }
            }
        }
    }
}
?>

<!doctype html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Установка — XUNX AdLink</title>
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body>
    <main class="section">
      <div class="container">
        <div class="card">
          <h2>Установка XUNX AdLink</h2>
          <p class="muted">
            Заполните данные базы, параметры сайта и доступ администратора.
          </p>
        </div>

        <?php if ($installed && !$success): ?>
          <div class="card notice notice--warn">
            Установка уже выполнена. Удалите install.php для безопасности.
          </div>
        <?php endif; ?>

        <?php if ($errors): ?>
          <div class="card notice notice--warn">
            <?php foreach ($errors as $error): ?>
              <div><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="card notice">
            Установка завершена! Перейдите в <a href="auth.html">кабинет</a> или
            <a href="admin.html">админпанель</a>.
          </div>
        <?php endif; ?>

        <div class="card">
          <form class="form" method="post">
            <h3>База данных</h3>
            <div>
              <label>Хост</label>
              <input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1') ?>" />
            </div>
            <div>
              <label>Порт</label>
              <input name="db_port" type="number" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" />
            </div>
            <div>
              <label>Название БД *</label>
              <input name="db_name" required value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" />
            </div>
            <div>
              <label>Пользователь БД *</label>
              <input name="db_user" required value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" />
            </div>
            <div>
              <label>Пароль БД</label>
              <input name="db_pass" type="password" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" />
            </div>

            <h3>Параметры сайта</h3>
            <div>
              <label>Название сайта</label>
              <input name="site_name" value="<?= htmlspecialchars($_POST['site_name'] ?? 'XUNX AdLink') ?>" />
            </div>
            <div>
              <label>URL сайта</label>
              <input name="site_url" value="<?= htmlspecialchars($_POST['site_url'] ?? '') ?>" />
            </div>

            <h3>Администратор</h3>
            <div>
              <label>Имя администратора</label>
              <input name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Администратор') ?>" />
            </div>
            <div>
              <label>Email администратора *</label>
              <input name="admin_email" type="email" required value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" />
            </div>
            <div>
              <label>Пароль администратора *</label>
              <input name="admin_password" type="password" required />
            </div>

            <button class="button" type="submit">Установить</button>
          </form>
        </div>
      </div>
    </main>
  </body>
</html>
