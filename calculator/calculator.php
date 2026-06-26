<?php
// Версія: v069
// Зміни: Темна тема, Share, Пам'ять вибору (Видалено Відстань).
// route.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../schedules.php';

$stations = [
    ['name' => 'СУМИ', 'type' => 'major', 'km' => 59, 'base' => 'Ворожби'],
    ['name' => 'Оп-52км', 'type' => 'minor', 'km' => 52, 'base' => 'Ворожби'],
    ['name' => 'Торопилівка', 'type' => 'major', 'km' => 49, 'base' => 'Ворожби'],
    ['name' => 'Оп-40км', 'type' => 'minor', 'km' => 40, 'base' => 'Ворожби'],
    ['name' => 'Головашівка', 'type' => 'major', 'km' => 35, 'base' => 'Ворожби'],
    ['name' => 'Лікарське', 'type' => 'minor', 'km' => 31, 'base' => 'Ворожби'],
    ['name' => 'Амбари', 'type' => 'major', 'km' => 28, 'base' => 'Ворожби'],
    ['name' => 'Оп-24', 'type' => 'minor', 'km' => 24, 'base' => 'Ворожби'],
    ['name' => 'Вири', 'type' => 'major', 'km' => 20, 'base' => 'Ворожби'],
    ['name' => 'Ульянівка', 'type' => 'minor', 'km' => 17, 'base' => 'Ворожби'],
    ['name' => 'Оп-15км', 'type' => 'minor', 'km' => 15, 'base' => 'Ворожби'],
    ['name' => 'Торохтяний', 'type' => 'minor', 'km' => 11, 'base' => 'Ворожби'],
    ['name' => 'БІЛОПІЛЛЯ', 'type' => 'major', 'km' => 5, 'base' => 'Ворожби'],
    
    ['name' => 'ВОРОЖБА', 'type' => 'major', 'km' => 74, 'base' => 'Конотопа'],
    ['name' => 'Бабаківка', 'type' => 'minor', 'km' => 70, 'base' => 'Конотопа'],
    ['name' => 'Кошари', 'type' => 'major', 'km' => 64, 'base' => 'Конотопа'],
    ['name' => 'Карпилівка', 'type' => 'minor', 'km' => 59, 'base' => 'Конотопа'],
    ['name' => 'Клепали', 'type' => 'minor', 'km' => 54, 'base' => 'Конотопа'],
    ['name' => 'ПУТИВЛЬ', 'type' => 'major', 'km' => 48, 'base' => 'Конотопа'],
    ['name' => 'Степанівка', 'type' => 'minor', 'km' => 44, 'base' => 'Конотопа'],
    ['name' => 'Путійська', 'type' => 'major', 'km' => 39, 'base' => 'Конотопа'],
    ['name' => 'Грузьке', 'type' => 'major', 'km' => 33, 'base' => 'Конотопа'],
    ['name' => 'Зафатівка', 'type' => 'minor', 'km' => 28, 'base' => 'Конотопа'],
    ['name' => 'Дубинка', 'type' => 'minor', 'km' => 25, 'base' => 'Конотопа'],
    ['name' => 'В’язове', 'type' => 'minor', 'km' => 22, 'base' => 'Конотопа'],
    ['name' => 'Дубов’язівка', 'type' => 'major', 'km' => 17, 'base' => 'Конотопа'],
    ['name' => 'Джигаївка', 'type' => 'minor', 'km' => 13, 'base' => 'Конотопа'],
    ['name' => 'Калинівка', 'type' => 'minor', 'km' => 9, 'base' => 'Конотопа'],
    ['name' => 'Лобківка', 'type' => 'minor', 'km' => 6, 'base' => 'Конотопа'],
    ['name' => 'Залізобетонний', 'type' => 'minor', 'km' => 3, 'base' => 'Конотопа'],
    ['name' => 'КОНОТОП', 'type' => 'major', 'km' => 0, 'base' => 'Конотопа'],

    ['name' => 'БАХМАЧ', 'type' => 'major', 'km' => 25, 'base' => 'Київська'],
    ['name' => 'НІЖИН', 'type' => 'major', 'km' => 85, 'base' => 'Київська'],
    ['name' => 'ДАРНИЦЯ', 'type' => 'major', 'km' => 200, 'base' => 'Київська'],
    ['name' => 'КИЇВ', 'type' => 'major', 'km' => 214, 'base' => 'Київська']
];

foreach ($stations as &$st) {
    if ($st['base'] === 'Ворожби') {
        $st['global_km'] = 59 - $st['km'];
    } elseif ($st['base'] === 'Конотопа') {
        $st['global_km'] = 59 + (74 - $st['km']);
    } else {
        $st['global_km'] = 133 + $st['km'];
    }
    $st['id'] = 'st_' . crc32($st['name'] . $st['km']);
}
unset($st);

$allowed_dirs = ['forward', 'reverse'];
$dir = isset($_GET['dir']) && in_array($_GET['dir'], $allowed_dirs) ? $_GET['dir'] : 'forward';
$display_stations = ($dir === 'reverse') ? array_reverse($stations) : $stations;
$js_schedules = $schedules; 

$train_pairs = [
    ['f' => '779', 'r' => '66', 'label_f' => 'СУМИ ➔ КИЇВ', 'label_r' => 'КИЇВ ➔ СУМИ'],
    ['f' => '775', 'r' => '776', 'label_f' => 'СУМИ ➔ КИЇВ', 'label_r' => 'КИЇВ ➔ СУМИ'],
    ['f' => '143', 'r' => '144', 'label_f' => 'СУМИ ➔ РАХІВ', 'label_r' => 'РАХІВ ➔ СУМИ']
];
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ДЕ МІЙ ПОТЯГ?</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div id="offlineBanner" class="offline-banner">⚠️ Немає підключення до Інтернету</div>
<div id="loadingOverlay" class="top-loader"></div>

<div class="container">
    <div class="app-header">
        <img src="../logo.png" alt="Логотип" class="app-logo">
        <div class="app-title-group">
            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <h1 class="app-title">ДЕ МІЙ ПОТЯГ?</h1>
                <a href="https://t.me/depotyaguz" class="btn-tg" target="_blank" style="padding: 2px 6px; font-size: 0.65rem; border-radius: 6px;" title="Спільнота в Telegram">@depotyaguz</a>
            </div>
            <span class="app-subtitle">Інтерактивний калькулятор затримок</span>
        </div>
        <div class="header-actions">
            <div id="connectionStatus" class="conn-status online" title="Стан підключення до мережі" style="display: none;">
                <div class="conn-dot"></div><span class="conn-text">Онлайн</span>
            </div>
            <button id="btnTheme" class="btn-theme-mini" onclick="toggleTheme()" title="Перемкнути тему">🌙</button>
            <button class="btn-help" onclick="document.getElementById('helpModal').classList.add('active')" title="Відкрити інструкцію">❓ Інструкція</button>
        </div>
    </div>


    <div class="action-group">
        <button class="btn-action-small" onclick="document.getElementById('scaleModal').classList.add('active')" title="Масштаб графіка">📐 Масштаб графіка</button>
        <button class="btn-action-small" onclick="shareStatus()" title="Поділитися поточним статусом">📤 Поділитися</button>
        <button id="btnSecretShare" class="btn-action-small" style="display: none;" onclick="openScreenshotModal()" title="Відправити скріншот в групу">📸 В групу</button>
        <button class="btn-action-small" onclick="clearSession()" title="Видалити всі ручні налаштування">🔄 Очистити</button>
    </div>

    <div id="dateDisplay" class="date-display" title="Поточний час. Потрійний клік для налаштування">🗓️ Завантаження...</div>

    <div class="dir-group">
        <a href="?dir=forward" class="btn-dir <?= $dir === 'forward' ? 'active' : '' ?>" onclick="showLoader()" title="СУМИ ➔ КИЇВ"><span class='arr-red'>▲</span> СУМИ ➔ КИЇВ</a>
        <a href="?dir=reverse" class="btn-dir <?= $dir === 'reverse' ? 'active' : '' ?>" onclick="showLoader()" title="КИЇВ ➔ СУМИ"><span class='arr-red'>▼</span> КИЇВ ➔ СУМИ</a>
    </div>

    <div class="trains-grid">
        <?php foreach ($train_pairs as $pair): 
            if ($dir === 'forward') {
                $main_t = $pair['f']; $sub_t = $pair['r'];
                $main_content = "{$main_t} <span class='arr-red'>▲</span>";
                $sub_content = "<span class='arr-red'>▼</span> {$sub_t}";
                $sub_dir = 'reverse';
                $main_label = $pair['label_f'];
                $sub_label = $pair['label_r'];
            } else {
                $main_t = $pair['r']; $sub_t = $pair['f'];
                $main_content = "<span class='arr-red'>▼</span> {$main_t}";
                $sub_content = "{$sub_t} <span class='arr-red'>▲</span>";
                $sub_dir = 'forward';
                $main_label = $pair['label_r'];
                $sub_label = $pair['label_f'];
            }
        ?>
            <div class="train-pair-card" data-train-card="<?= $main_t ?>">
                <button class="train-main-btn" onclick="trackTrain('<?= $main_t ?>')" title="Відстежувати <?= $main_t ?>">
                    <div><?= $main_content ?></div>
                    <div style="font-size: 0.6rem; font-weight: bold; color: #64748b; margin-top:2px;"><?= $main_label ?></div>
                </button>
                <button class="train-sub-btn" onclick="showLoader(); window.location.href='?dir=<?= $sub_dir ?>&train=<?= $sub_t ?>'" title="Оборотний рейс">
                    <div><?= $sub_content ?></div>
                    <div style="font-size: 0.55rem; font-weight: bold; color: #64748b; margin-top:1px;"><?= $sub_label ?></div>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="statusText" class="status-bar" title="Головна панель статусу">
        <span style="font-size: 1.15rem;">Оберіть потяг для трекінгу</span>
        <span style="font-size: 0.85rem; font-weight: normal; margin-top: 6px; color: #64748b;">Клікніть на станцію для вказання фактичного часу.</span>
    </div>
    <div id="blockerBox" class="blocker-box"></div>

    <div class="timeline">
        <?php
        $current_railway = '';
        foreach ($display_stations as $idx => $st) {
            $railway_name = ($st['base'] === 'Ворожби') ? 'ПІВДЕННА ЗАЛІЗНИЦЯ' : (($st['base'] === 'Конотопа') ? 'ПІВДЕННО-ЗАХІДНА ЗАЛІЗНИЦЯ' : 'ПІВДЕННО-ЗАХІДНА ЗАЛІЗНИЦЯ (КИЇВСЬКИЙ ХІД)');
            $railway_class = ($st['base'] === 'Ворожби') ? 'south' : (($st['base'] === 'Конотопа') ? 'southwest' : 'kyiv');

            if ($railway_name !== $current_railway) {
                if ($idx > 0) echo "</div>\n"; 
                echo "<div class=\"railway-section {$railway_class}\">\n";
                if ($idx === 0) echo "  <div class=\"route-start-icon\"></div>\n";
                echo "  <div class=\"railway-label\">{$railway_name}</div>\n";
                $current_railway = $railway_name;
            }

            $class = ($st['type'] === 'major') ? 'major' : 'minor';
            $safe_html_name = htmlspecialchars($st['name'], ENT_QUOTES);
            $station_id = $st['id'];
            
            echo "  <div class=\"station {$class}\" data-id=\"{$station_id}\" onclick=\"openTimeModal('{$station_id}')\" title=\"Натисніть для вказання часу на {$safe_html_name}\">\n";
            echo "    <div class=\"blinking-dot\">🚂</div>\n";
            echo "    <p class=\"station-name\">{$safe_html_name}<span class=\"inline-delay\"></span></p>\n";
            echo "  </div>\n";
        }
        if (count($display_stations) > 0) {
            echo "  <div class=\"route-end-icon\"></div>\n";
            echo "</div>\n";
        }
        ?>
    </div>

    <div id="groupMessages" class="chat-container">
        <div class="chat-header">
            <span>💬 Останні повідомлення з групи</span>
            <div class="chat-header-actions">
                <button id="btnToggleChat" class="btn-chat-action" onclick="toggleChatCollapse()" title="Згорнути/Розгорнути повідомлення">▲ Згорнути</button>
                <button id="btnClearChat" class="btn-chat-action btn-chat-clear" onclick="clearChatMessages()" title="Очистити історію повідомлень">🗑️ Очистити</button>
            </div>
        </div>
        <div id="chatList" class="chat-list">
            <div style="text-align:center; padding: 20px; color: #94a3b8;">Завантаження повідомлень...</div>
        </div>
    </div>

</div>    
    <div class="app-footer" style="text-align: center; font-size: 0.75rem; color: #64748b; margin-top: 30px; margin-bottom: 10px; font-weight: 500; padding: 0 10px; line-height: 1.5;">
        Якщо помітили баги в роботі, або є пропозиції по функціоналу, звертайтесь до <a href="https://t.me/sarmakey" target="_blank" style="color: #0ea5e9; text-decoration: none; font-weight: bold; display: inline-block;">@sarmakey</a>
    </div>
</div>

<div id="helpModal" class="modal-overlay">
    <div class="modal-content help-content">
        <h3 class="modal-title">👵 Як користуватися</h3>
        <div class="help-text">
            <p><b>1. Куди їдемо?</b> Натисніть велику кнопку зверху: "СУМИ ➔ КИЇВ" або навпаки.</p>
            <p><b>2. Який поїзд?</b> Натисніть на цифри вашого поїзда (наприклад, 779). Якщо кнопка бліда і не натискається — значить, сьогодні цей поїзд не ходить.</p>
            <p><b>3. Де він зараз?</b> Ви побачите список зупинок. Шукайте значок паровозика 🚂 — там зараз знаходиться поїзд. Зелені цифри 🟢 означають, що все йде чітко за розкладом.</p>
            <p><b>4. Що робити, якщо поїзд запізнюється?</b> <br>
            Наприклад, знайомі дзвонять з поїзда і кажуть: <i>"Ми щойно приїхали в Путивль, а мали бути тут годину тому!"</i>. Зробіть так:<br>
            • Знайдіть слово "Путивль" у списку і <b>натисніть прямо на нього</b> пальцем.<br>
            • Введіть час, коли вони вам подзвонили.<br>
            • Готово! Калькулятор сам перерахує, о котрій поїзд тепер приїде до вас.</p>
            <p><b>5. Що значать кольори:</b><br>
            🔴 <b>Червоний:</b> Станція, де ви самі вказали, що поїзд спізнився.<br>
            🟠 <b>Оранжевий:</b> Це новий приблизний час, коли поїзд приїде до вас (з урахуванням запізнення).<br>
            ⚪ <b>Сірий:</b> Ці зупинки поїзд вже проїхав.</p>
            <p><b>6. Якщо ви помилилися:</b> Натисніть кнопку <b>"Очистити розрахунки"</b>. Усе повернеться як було.</p>
            <p><b>7. Оборотні рейси:</b> Якщо потяг, яким ви збираєтесь їхати (наприклад, 775), ще не приїхав на кінцеву станцію як попередній рейс (776), калькулятор <b>автоматично</b> зрозуміє це і покаже затримку. Ви побачите помаранчеве попередження з розрахунком часу на розворот складу.</p>
        </div>
        <button class="btn-modal btn-apply" onclick="document.getElementById('helpModal').classList.remove('active')">Зрозуміло</button>
    </div>
</div>

<div id="timeModal" class="modal-overlay">
    <div class="modal-content">
        <h3 id="modalStationName" class="modal-title">Станція</h3>
        <p class="modal-subtitle">О котрій годині потяг був тут?</p>
        <div class="time-picker-wrapper">
            <input type="time" id="manualTimeInput" class="time-input" required>
        </div>
        <div class="modal-buttons">
            <button class="btn-modal btn-cancel" onclick="closeTimeModal()">Скасувати</button>
            <button class="btn-modal btn-apply" onclick="applyManualTime()">Зафіксувати</button>
        </div>
    </div>
</div>

<div id="scaleModal" class="modal-overlay">
    <div class="modal-content">
        <h3 class="modal-title">📐 Масштаб графіка</h3>
        <p class="modal-subtitle">Налаштуйте вигляд маршруту</p>
        <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
            <button id="btnToggleKyiv" class="btn-action-small" style="width: 100%; padding: 12px; font-size: 1rem; border-radius: 8px;" onclick="toggleKyivMap()">🗺️ Розгорнути до Києва</button>
            <button id="btnToggleMinor" class="btn-action-small" style="width: 100%; padding: 12px; font-size: 1rem; border-radius: 8px;" onclick="toggleMinorStations()">🚂 Приховати дрібні станції</button>
        </div>
        <button class="btn-modal btn-cancel" style="width: 100%;" onclick="document.getElementById('scaleModal').classList.remove('active')">Закрити</button>
    </div>
</div>

<div id="screenshotModal" class="modal-overlay">
    <div class="modal-content">
        <h3 class="modal-title">📸 Відправити в групу</h3>
        <p class="modal-subtitle">Додайте коментар та оберіть групу</p>
        
        <div style="margin-bottom: 15px;">
            <label style="font-size: 0.85rem; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">Передперегляд зображення:</label>
            <div id="screenshotPreviewContainer" class="screenshot-preview-box">
                <div style="text-align:center; padding: 15px; color: #94a3b8; font-size: 0.8rem;">Генерація передперегляду...</div>
            </div>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="font-size: 0.85rem; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">Куди відправляємо?</label>
            <select id="targetGroupSelect" style="width: 100%; padding: 8px; border-radius: 8px; border: 2px solid #e2e8f0; font-family: inherit; font-size: 0.9rem; background: #f8fafc; color: #0f172a; outline: none;">
                <option value="-1003941419523">🟢 Основна група</option>
                <option value="-1004459944074">🟡 Тестова група</option>
            </select>
        </div>

        <div style="margin-bottom: 20px;">
            <textarea id="screenshotComment" rows="4" style="width: 100%; padding: 10px; border-radius: 8px; border: 2px solid #e2e8f0; font-family: inherit; font-size: 0.9rem;" placeholder="Наприклад: Потяг затримується на 20 хв..."></textarea>
        </div>
        <div class="modal-buttons">
            <button class="btn-modal btn-cancel" onclick="document.getElementById('screenshotModal').classList.remove('active')">Скасувати</button>
            <button class="btn-modal btn-apply" onclick="captureAndSendToGroup()">Відправити</button>
        </div>
    </div>
</div>

<div class="app-version" onclick="toggleSecretShare()" style="cursor: pointer; pointer-events: auto;">v069</div>

    <script>
        const allStations = <?= json_encode($stations) ?>;
        const schedules = <?= json_encode($js_schedules) ?>;
        const currentDir = "<?= $dir ?>";
        const turnarounds = { '779': '66', '775': '776', '143': '144' };
    </script>
    <script src="app.js"></script>
</body>
</html>