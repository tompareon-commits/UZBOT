# 🚂 Project Context & Memory for Antigravity

This file serves as a persistent memory module. When starting a new session or switching PCs, **tell the AI assistant to read this file first** to immediately gain complete context on the project structure, codebase, and state.

---

## 📌 Project Overview
* **Name:** UZ Train Delay Tracker & Telegram Bot (`uzbot`)
* **Main Target:** An interactive web-based train delay calculator and tracker integrated with a Telegram bot for sharing updates and viewing group chat history.
* **Production Web Path:** `https://sarmak.pp.ua/tlgbot/uzbot/calculator/calculator.php`

---

## 🛠️ Technology Stack
1. **Frontend:** HTML5, Vanilla CSS3 (custom dark/light themes), JavaScript (with `html2canvas` for screenshot generation).
2. **Backend:** PHP (handling endpoints, schedules, and Telegram Bot API requests).
3. **Storage:** `.env` config file, local JSON databases (`group_messages.json`), and `sessionStorage` for frontend state.

---

## 📂 Key Codebase Components
* [calculator/calculator.php](file:///d:/GitHUB/uzbot/calculator/calculator.php) — The primary interactive UI container holding the train grid, timeline, and Telegram chat feed widget.
* [calculator/app.js](file:///d:/GitHUB/uzbot/calculator/app.js) — Core logic including real-time train tracking, delay calculation, DOM manipulation, and interactive screenshotting features.
* [calculator/styles.css](file:///d:/GitHUB/uzbot/calculator/styles.css) — Stylesheets with responsive design, premium styling, dark theme variables, and screenshot-mode layouts.
* [cron_daily.php](file:///d:/GitHUB/uzbot/cron_daily.php) — A cron-run script (executes daily at 00:00) that posts the active schedule list for the day to the Telegram group with the calculator URL link.
* [share_group.php](file:///d:/GitHUB/uzbot/calculator/share_group.php) — Endpoint to receive base64 screenshots and comments from the web app and dispatch them via curl to the Telegram Bot API.
* [index.php](file:///d:/GitHUB/uzbot/index.php) — The primary Webhook handler for the Telegram bot.

---

## 🚀 Key Features Implemented

### 1. Telegram Feed Widget
* Renders the last 10 messages from the group chat in the bottom drawer.
* Features **Expand/Collapse** toggles (persisted via `sessionStorage`) and a **Clear** button (executes `clear_messages.php` which truncates history for all users).

### 2. Premium Selective Screenshotting
* Users can click **📸 В групу** to generate an image report of the train's delay status.
* **Branding:** Keeps the header/logo (`.app-header`) visible for promotional/advertising value.
* **Timeline Cropping:** Instead of showing all stations, the script hides all stations preceding the **threshold station**.
  * The threshold is either the station where a manual position check/override was made (`manualSetStationId` / shown as `🔴 Fact: ...`) or the current location of the train dot (`🚂`).
  * The threshold station and all downstream stations remain fully visible.
* **Railway Sections:** If all stations under a railway header (e.g., "Південна залізниця") are hidden, the header label itself is hidden to keep the layout neat.

### 3. Modal Image Preview
* Before sending, a loading spinner is shown, and the cropped image is compiled into a base64 string.
* The image is rendered inside `#screenshotPreviewContainer` inside the modal so the user can verify exactly what they are broadcasting before adding comments and hitting **Send**.

---

## 📝 How to resume work (instructions for the next AI session)
1. **To start:** Ask the user if they want to modify the web calculator interface or the bot backend.
2. **Key State Variables (`app.js`):**
   * `AppState.manualSetStationId` stores the ID of the manually altered station.
   * `AppState.effectiveMins` holds the current computed train progress minutes.
   * `lastScreenshotBase64` holds the generated base64 image ready for transmission.
3. Read [calculator/app.js](file:///d:/GitHUB/uzbot/calculator/app.js) to locate these variables and update/extend frontend features.
