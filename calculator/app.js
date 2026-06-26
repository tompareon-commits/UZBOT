
const AppState = {
    activeTrainNum: null,
    activeInterval: null,
    interpolatedRoute: [],
    delays: {},
    stations: {},
    timeOffsetMs: 0
};

const StorageManager = {
    save: function(k, v) { try { sessionStorage.setItem(k, JSON.stringify(v)); } catch(e) { this.warn(); } },
    load: function(k, d) { try { const i = sessionStorage.getItem(k); return i ? JSON.parse(i) : d; } catch(e) { this.warn(); return d; } },
    remove: function(k) { try { sessionStorage.removeItem(k); } catch(e) { this.warn(); } },
    hasWarned: false,
    warn: function() {
        if (!this.hasWarned) {
            console.warn("Storage restricted.");
            const t = document.createElement('div');
            t.style.cssText = "position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#334155;color:#fff;padding:10px 20px;border-radius:20px;font-size:0.8rem;z-index:10000;box-shadow:0 4px 6px rgba(0,0,0,0.1);";
            t.innerText = "⚠️ Режим інкогніто. Налаштування не збережуться.";
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 4000);
            this.hasWarned = true;
        }
    }
};

function toggleTheme() {
    const isDark = document.body.classList.toggle('dark-theme');
    StorageManager.save('radar_dark_theme', isDark);
    document.getElementById('btnTheme').innerText = isDark ? "☀️" : "🌙";
}
if (StorageManager.load('radar_dark_theme', false)) {
    document.body.classList.add('dark-theme');
    window.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('btnTheme');
        if (btn) btn.innerText = "☀️";
    });
}

AppState.delays = StorageManager.load('radar_delays', {});
AppState.stations = StorageManager.load('radar_stations', {});
let rawOffset = null;
try { rawOffset = sessionStorage.getItem('radar_time_offset'); } catch(e) {}
AppState.timeOffsetMs = rawOffset ? parseInt(rawOffset) : 0;
if (isNaN(AppState.timeOffsetMs)) AppState.timeOffsetMs = 0;

function getNow() { return new Date(Date.now() + AppState.timeOffsetMs); }
function showLoader() { document.getElementById('loadingOverlay').classList.add('active'); }
function hideLoader() { document.getElementById('loadingOverlay').classList.remove('active'); }

function initMapSettings() {
    if (sessionStorage.getItem('radar_expand_kyiv') === null) {
        sessionStorage.setItem('radar_expand_kyiv', 'true');
    }
    if (sessionStorage.getItem('radar_hide_minor') === null) {
        sessionStorage.setItem('radar_hide_minor', 'true');
    }

    const isExpanded = sessionStorage.getItem('radar_expand_kyiv') === 'true';
    const btnKyiv = document.getElementById('btnToggleKyiv');
    if (isExpanded) {
        document.body.classList.add('expanded-kyiv');
        if (btnKyiv) {
            btnKyiv.innerText = "🗺️ Згорнути до Конотопа";
            btnKyiv.style.background = "#e2e8f0";
            btnKyiv.style.color = "#1e293b";
        }
    } else {
        document.body.classList.remove('expanded-kyiv');
        if (btnKyiv) {
            btnKyiv.innerText = "🗺️ Розгорнути до Києва";
            btnKyiv.style.background = "";
            btnKyiv.style.color = "";
        }
    }

    const isMinorHidden = sessionStorage.getItem('radar_hide_minor') === 'true';
    const btnMinor = document.getElementById('btnToggleMinor');
    if (isMinorHidden) {
        document.body.classList.add('hide-minor');
        if (btnMinor) {
            btnMinor.innerText = "🚂 Показати дрібні станції";
            btnMinor.style.background = "#e2e8f0";
            btnMinor.style.color = "#1e293b";
        }
    } else {
        document.body.classList.remove('hide-minor');
        if (btnMinor) {
            btnMinor.innerText = "🚂 Приховати дрібні станції";
            btnMinor.style.background = "";
            btnMinor.style.color = "";
        }
    }
}

function toggleKyivMap() {
    showLoader();
    setTimeout(() => {
        try {
            const isExpanded = sessionStorage.getItem('radar_expand_kyiv') === 'true';
            sessionStorage.setItem('radar_expand_kyiv', !isExpanded);
        } catch(e) { StorageManager.warn(); }
        initMapSettings();
        if (AppState.activeTrainNum) updateTrainPosition(); 
        hideLoader();
    }, 200);
}

function toggleMinorStations() {
    showLoader();
    setTimeout(() => {
        try {
            const isMinorHidden = sessionStorage.getItem('radar_hide_minor') === 'true';
            sessionStorage.setItem('radar_hide_minor', !isMinorHidden);
        } catch(e) { StorageManager.warn(); }
        initMapSettings();
        if (AppState.activeTrainNum) updateTrainPosition(); 
        hideLoader();
    }, 200);
}

let dateClickCount = 0;
let dateClickTimer = null;
document.getElementById('dateDisplay').addEventListener('click', () => {
    dateClickCount++;
    clearTimeout(dateClickTimer);
    dateClickTimer = setTimeout(() => { dateClickCount = 0; }, 1000); 
    if (dateClickCount >= 3) {
        dateClickCount = 0;
        let userInput = prompt("🕒 МАШИНА ЧАСУ (Дебаг)\\nДД ГГ:ХХ (наприклад: 24 15:30)", "");
        if (userInput === null) return;
        if (userInput.trim() === "") {
            StorageManager.remove('radar_time_offset');
            location.reload(); return;
        }
        let parts = userInput.trim().split(/[ \t]+/);
        if (parts.length >= 2) {
            let now = new Date();
            let day = parseInt(parts[0]);
            let timeParts = parts[1].split(':');
            if (timeParts.length >= 2) {
                let h = parseInt(timeParts[0]);
                let m = parseInt(timeParts[1]);
                if (!isNaN(day) && !isNaN(h) && !isNaN(m)) {
                    let targetDate = new Date(now.getFullYear(), now.getMonth(), day, h, m, 0);
                    AppState.timeOffsetMs = targetDate.getTime() - Date.now();
                    try { sessionStorage.setItem('radar_time_offset', AppState.timeOffsetMs); } catch(e){}
                    location.reload();
                    return;
                }
            }
        }
        alert("Невірний формат. Використовуйте: ДД ГГ:ХХ (наприклад: 24 15:30)");
    }
});

function updateClockAndButtons() {
    const now = getNow();
    const options = { day: '2-digit', month: '2-digit', year: 'numeric' };
    const dateStr = now.toLocaleDateString('uk-UA', options);
    const timeStr = now.toTimeString().substring(0, 8); 
    let extra = AppState.timeOffsetMs !== 0 ? ' <span style="color:#ef4444;">[МАШИНА ЧАСУ]</span>' : '';
    document.getElementById('dateDisplay').innerHTML = `🗓️ ${dateStr} | 🕒 ${timeStr}${extra}`;
    
    const isEvenDay = now.getDate() % 2 === 0;
    document.querySelectorAll('.train-pair-card').forEach(card => {
        const tNum = card.getAttribute('data-train-card');
        if (tNum === '143' || tNum === '144') {
            if (!isEvenDay) {
                card.classList.add('disabled-train');
                card.querySelectorAll('button').forEach(b => b.disabled = true);
            } else {
                card.classList.remove('disabled-train');
                card.querySelectorAll('button').forEach(b => b.disabled = false);
            }
        }
    });
}
// Миттєве завантаження годинника
updateClockAndButtons();
setInterval(updateClockAndButtons, 1000);

let currentModalStationId = null;
let currentModalSchedTime = null;

function timeToMins(timeStr) {
    if(!timeStr) return null;
    const [h, m] = timeStr.split(':').map(Number);
    return h * 60 + m;
}

function formatTime(mins) {
    let h = Math.floor(mins / 60) % 24; let m = Math.floor(mins % 60);
    if (h < 0) h += 24;
    return (h < 10 ? '0'+h : h) + ':' + (m < 10 ? '0'+m : m);
}

function formatDelayText(totalMins) {
    const m = Math.round(totalMins); if (m < 60) return m + ' хв';
    const h = Math.floor(m / 60); const rem = m % 60;
    return h + ' год ' + (rem < 10 ? '0' : '') + rem + ' хв';
}

function clearSession() {
    showLoader();
    StorageManager.remove('radar_delays'); StorageManager.remove('radar_stations');
    location.reload();
}

function shareStatus() {
    if (!AppState.activeTrainNum || AppState.interpolatedRoute.length < 2) {
        alert('Спочатку оберіть потяг для відстеження!');
        return;
    }
    const effectiveDelayMins = getEffectiveDelay(AppState.activeTrainNum);
    const projectedArrival = AppState.interpolatedRoute[AppState.interpolatedRoute.length - 1].time + effectiveDelayMins; 
    const finalStationName = AppState.interpolatedRoute[AppState.interpolatedRoute.length - 1].name;
    const isDelayed = effectiveDelayMins > 0;
    
    let delayStr = isDelayed ? `Затримка: +${formatDelayText(effectiveDelayMins)}.` : `Слідує за графіком.`;
    let arrivalStr = `Орієнтовно в ${finalStationName} о ${formatTime(projectedArrival)}.`;
    let url = window.location.href.split('?')[0] + '?dir=' + currentDir + '&train=' + AppState.activeTrainNum;
    
    let text = `🚂 Потяг ${AppState.activeTrainNum}. ${delayStr} ${arrivalStr} Слідкуй тут: ${url}`;
    
    if (navigator.share) {
        navigator.share({ title: `Статус потяга ${AppState.activeTrainNum}`, text: text })
            .catch(err => console.log('Share failed', err));
    } else {
        navigator.clipboard.writeText(text).then(() => {
            alert('Текст статусу скопійовано в буфер обміну!');
        }).catch(() => {
            alert('Не вдалося скопіювати текст. Ось він:\n\n' + text);
        });
    }
}

function buildInterpolatedRoute(trainNum) {
    const sched = schedules[trainNum]; if(!sched) return;
    const majorPoints = [];
    
    let lastMins = -1;
    let dayOffset = 0;

    for (const [stName, timeStr] of Object.entries(sched)) {
        const physicalSt = allStations.find(s => s.name.replace(/['’]/g, '').toUpperCase() === stName.replace(/['’]/g, '').toUpperCase());
        if (physicalSt) {
            let currentMins = timeToMins(timeStr);
            if (lastMins !== -1 && currentMins < lastMins - 600) { 
                dayOffset += 1440;
            }
            const absoluteMins = currentMins + dayOffset;
            
            majorPoints.push({ id: physicalSt.id, name: physicalSt.name, km: physicalSt.global_km, time: absoluteMins });
            lastMins = currentMins;
        }
    }
    
    if(majorPoints.length < 2) return;
    const dir = (majorPoints[majorPoints.length-1].km > majorPoints[0].km) ? 1 : -1;

    AppState.interpolatedRoute = [];
    for(const st of allStations) {
        let exact = majorPoints.find(m => m.id === st.id); let timeMins = null;
        if (exact) { timeMins = exact.time; } else {
            let p1 = null, p2 = null;
            for (let i = 0; i < majorPoints.length - 1; i++) {
                const m1 = majorPoints[i], m2 = majorPoints[i+1];
                if ((dir === 1 && st.global_km >= m1.km && st.global_km <= m2.km) ||
                    (dir === -1 && st.global_km <= m1.km && st.global_km >= m2.km)) {
                    p1 = m1; p2 = m2; break;
                }
            }
            if (p1 && p2) {
                const distTotal = Math.abs(p2.km - p1.km); const distPass = Math.abs(st.global_km - p1.km);
                const progress = distTotal === 0 ? 0 : distPass / distTotal;
                timeMins = p1.time + progress * (p2.time - p1.time);
            }
        }
        if (timeMins !== null) { AppState.interpolatedRoute.push({ id: st.id, name: st.name, km: st.global_km, time: timeMins }); }
    }
    
    AppState.interpolatedRoute.sort((a,b) => a.time - b.time);
}

function getEffectiveDelay(trainNum) {
    let inheritedDelay = 0;
    if (turnarounds[trainNum]) {
        const feederTrain = turnarounds[trainNum]; 
        const feederDelay = AppState.delays[feederTrain] || 0;
        if (feederDelay > 0) {
            const feederSched = schedules[feederTrain];
            if (feederSched) {
                let fLastMins = -1; let fDayOffset = 0; let arrivalMinsAbs = 0;
                for (const timeStr of Object.values(feederSched)) {
                    let curMins = timeToMins(timeStr);
                    if (fLastMins !== -1 && curMins < fLastMins - 600) fDayOffset += 1440;
                    arrivalMinsAbs = curMins + fDayOffset; fLastMins = curMins;
                }
                const readyMins = arrivalMinsAbs + feederDelay + 30; 
                const mySched = schedules[trainNum];
                if (mySched) {
                    let myStartMins = timeToMins(Object.values(mySched)[0]);
                    while (myStartMins < arrivalMinsAbs - 720) { myStartMins += 1440; }
                    if (readyMins > myStartMins) { inheritedDelay = readyMins - myStartMins; }
                }
            }
        }
    }
    return AppState.delays.hasOwnProperty(trainNum) ? AppState.delays[trainNum] : inheritedDelay;
}

function trackTrain(trainNum) {
    const card = document.querySelector(`.train-pair-card[data-train-card="${trainNum}"]`);
    if (card && card.classList.contains('disabled-train')) { alert(`Потяг ${trainNum} не курсує.`); return; }
    
    showLoader();
    setTimeout(() => {
        document.querySelectorAll('.train-pair-card').forEach(c => c.classList.remove('active'));
        if (card) card.classList.add('active');
        AppState.activeTrainNum = trainNum;
        buildInterpolatedRoute(trainNum);
        if (AppState.activeInterval) clearInterval(AppState.activeInterval);
        updateTrainPosition(); AppState.activeInterval = setInterval(updateTrainPosition, 5000);
        hideLoader();
    }, 200);
}

function openTimeModal(stationId) {
    if (!AppState.activeTrainNum) return;
    const routePoint = AppState.interpolatedRoute.find(p => p.id === stationId);
    if (!routePoint) { alert('Ця зупинка не входить у маршрут.'); return; }
    currentModalStationId = stationId; currentModalSchedTime = routePoint.time;
    
    const currentDelay = getEffectiveDelay(AppState.activeTrainNum);
    let projectedMins = (routePoint.time + currentDelay) % (24 * 60); 
    
    document.getElementById('modalStationName').innerText = routePoint.name;
    document.getElementById('manualTimeInput').value = formatTime(projectedMins);
    document.getElementById('timeModal').classList.add('active');
}

function closeTimeModal() {
    document.getElementById('timeModal').classList.remove('active');
    currentModalStationId = null; currentModalSchedTime = null;
}

function applyManualTime() {
    const timeVal = document.getElementById('manualTimeInput').value; if (!timeVal) return;
    let eventMins = timeToMins(timeVal);
    
    let candidates = [eventMins - 1440, eventMins, eventMins + 1440];
    let bestDelay = null;
    for(let c of candidates) {
        let d = c - currentModalSchedTime;
        if (d >= 0) {
            if (bestDelay === null || d < bestDelay) bestDelay = d;
        }
    }
    
    let delay = bestDelay;
    if (delay === null) {
        alert(`Час раніший за графік (${formatTime(currentModalSchedTime)}). Затримку скинуто до 0.`); 
        delay = 0; 
    }
    
    let savedStationId = currentModalStationId;
    
    closeTimeModal();
    showLoader();
    setTimeout(() => {
        if (delay === 0) { 
            delete AppState.delays[AppState.activeTrainNum]; 
            delete AppState.stations[AppState.activeTrainNum]; 
        } else { 
            AppState.delays[AppState.activeTrainNum] = delay; 
            AppState.stations[AppState.activeTrainNum] = savedStationId; 
        }
        StorageManager.save('radar_delays', AppState.delays);
        StorageManager.save('radar_stations', AppState.stations);
        updateTrainPosition();
        hideLoader();
    }, 200);
}

function updateTrainPosition() {
    document.querySelectorAll('.station').forEach(el => {
        el.classList.remove('has-train', 'manual', 'passed-station');
        const delaySpan = el.querySelector('.inline-delay');
        if (delaySpan) { delaySpan.innerHTML = ''; delaySpan.className = 'inline-delay'; delaySpan.style.display = 'none'; }
        const dot = el.querySelector('.blinking-dot');
        if (dot) dot.style.transform = '';
    });
    
    const statusEl = document.getElementById('statusText');
    const blockerEl = document.getElementById('blockerBox');
    blockerEl.classList.remove('active'); blockerEl.innerHTML = '';
    if (AppState.interpolatedRoute.length < 2) return;

    const now = getNow(); const currentMins = now.getHours() * 60 + now.getMinutes() + (now.getSeconds() / 60);
    const effectiveDelayMins = getEffectiveDelay(AppState.activeTrainNum);
    const isInherited = !AppState.delays.hasOwnProperty(AppState.activeTrainNum) && effectiveDelayMins > 0 && turnarounds[AppState.activeTrainNum];
    let manualSetStationId = AppState.stations[AppState.activeTrainNum] || null;

    if (isInherited) {
        manualSetStationId = AppState.interpolatedRoute[0].id;
        const feederTrain = turnarounds[AppState.activeTrainNum]; const feederDelay = AppState.delays[feederTrain] || 0;
        const feederSched = schedules[feederTrain];
        let fLastMins = -1; let fDayOffset = 0; let arrivalMinsAbs = 0;
        for (const timeStr of Object.values(feederSched)) {
            let curMins = timeToMins(timeStr);
            if (fLastMins !== -1 && curMins < fLastMins - 600) fDayOffset += 1440;
            arrivalMinsAbs = curMins + fDayOffset; fLastMins = curMins;
        }
        const readyMins = arrivalMinsAbs + feederDelay + 30;
        let absCurrentMins = currentMins;
        while (absCurrentMins < readyMins - 720) { absCurrentMins += 1440; }
        const isDeparted = absCurrentMins >= readyMins;
        const reverseDir = (currentDir === 'reverse') ? 'forward' : 'reverse';
        const statusMsg = isDeparted ? `Орієнтовно, потяг вже в дорозі (відправився о ≈${formatTime(readyMins)}), якщо не було інших затримок` : `Орієнтовна відправка: ≈${formatTime(readyMins)}`;
        blockerEl.innerHTML = `
            <div style="font-weight:900; color:#c2410c; margin-bottom:5px; text-transform:uppercase;">🚧 Оборотний рейс затримується</div>
            <div style="font-size:0.85rem; color:#9a3412; margin-bottom:10px; line-height:1.4;">
                Склад для <b>${AppState.activeTrainNum}</b> прив'язаний до рейсу <b>${feederTrain}</b> (затримка +${formatDelayText(feederDelay)}).<br>Враховано 30 хв на розворот.
            </div>
            <div style="font-weight:bold; color:#b45309; font-size:1.1rem;">${statusMsg}</div>
            <button onclick="window.location.href='?dir=${reverseDir}&train=${feederTrain}'" class="btn-blocker-jump">📍 Відкрити карту потяга ${feederTrain}</button>
        `;
        blockerEl.classList.add('active');
    }

    let absCurrentMins = currentMins;
    const routeStartTime = AppState.interpolatedRoute[0].time;
    while (absCurrentMins < routeStartTime - 720) { absCurrentMins += 1440; }

    const effectiveMins = absCurrentMins - effectiveDelayMins;
    const projectedArrival = AppState.interpolatedRoute[AppState.interpolatedRoute.length - 1].time + effectiveDelayMins; 
    let isDelayed = effectiveDelayMins > 0;
    
    const mySched = schedules[AppState.activeTrainNum];
    const schedStations = Object.keys(mySched);
    const rawStartStation = schedStations[0];
    const rawStartTime = mySched[rawStartStation];
    const rawEndStation = schedStations[schedStations.length - 1];
    const rawEndTime = mySched[rawEndStation];

    let uiText = '';
    if (isDelayed) {
        statusEl.classList.add('delayed');
        uiText = `<span>🚂 ПОТЯГ №${AppState.activeTrainNum} | 🔴 ЗАПІЗНЕННЯ: +${formatDelayText(effectiveDelayMins)}</span>`;
        uiText += `<span style="font-size: 0.8rem; margin-top:5px; color:#b91c1c;">Формування: <b>${rawStartStation}</b> (${rawStartTime}) ➔ Орієнтовно в <b>${rawEndStation}</b> о <b>${formatTime(projectedArrival)}</b></span>`;
    } else {
        statusEl.classList.remove('delayed');
        uiText = `<span>🚂 ПОТЯГ №${AppState.activeTrainNum} | 🟢 ГРАФІК</span>`;
        uiText += `<span style="font-size: 0.8rem; margin-top:5px; color:#047857;">Формування: <b>${rawStartStation}</b> (${rawStartTime}) ➔ Кінцева: <b>${rawEndStation}</b> (${rawEndTime})</span>`;
    }
    
    if (blockerEl.classList.contains('active')) { statusEl.style.display = 'none'; } 
    else { statusEl.style.display = 'flex'; statusEl.innerHTML = uiText; }

    AppState.interpolatedRoute.forEach(p => {
        const stationEl = document.querySelector(`.station[data-id="${p.id}"]`);

        if (stationEl) {
            if (p.time <= effectiveMins) { stationEl.classList.add('passed-station'); }
            const span = stationEl.querySelector('.inline-delay'); span.style.display = 'inline-block'; 
            if (effectiveDelayMins === 0) {
                span.innerText = `🟢 ${formatTime(p.time)}`; span.classList.add('time-ideal');
            } else {
                const clickedPoint = AppState.interpolatedRoute.find(x => x.id === manualSetStationId);
                if (clickedPoint) {
                    if (p.id === manualSetStationId) {
                        span.innerText = `🔴 Fact: ${formatTime(p.time + effectiveDelayMins)} (+${formatDelayText(effectiveDelayMins)})`;
                        span.classList.add('time-delayed-current');
                    } else if (p.time > clickedPoint.time) {
                        span.innerText = `🟠 ≈ ${formatTime(p.time + effectiveDelayMins)}`;
                        span.classList.add('time-delayed-future');
                    } else {
                        span.innerText = `⚪ ${formatTime(p.time)}`; span.classList.add('time-passed');
                    }
                }
            }
        }
    });

    let visibleRoute = AppState.interpolatedRoute;
    if (sessionStorage.getItem('radar_hide_minor') === 'true') {
        visibleRoute = AppState.interpolatedRoute.filter(st => {
            const el = document.querySelector(`.station[data-id="${st.id}"]`);
            return el && !el.classList.contains('minor');
        });
    }

    if (effectiveMins < visibleRoute[0].time) { placeDotOnStation(visibleRoute[0].id, isDelayed); } 
    else if (effectiveMins >= visibleRoute[visibleRoute.length - 1].time) { placeDotOnStation(visibleRoute[visibleRoute.length - 1].id, isDelayed); } 
    else {
        for (let i = 0; i < visibleRoute.length - 1; i++) {
            const p1 = visibleRoute[i], p2 = visibleRoute[i+1];
            if (effectiveMins >= p1.time && effectiveMins < p2.time) {
                const progress = (effectiveMins - p1.time) / (p2.time - p1.time);
                placeDotBetweenStations(p1.id, p2.id, progress, isDelayed);
                break;
            }
        }
    }
    
    AppState.manualSetStationId = manualSetStationId;
    AppState.effectiveMins = effectiveMins;
}

function placeDotBetweenStations(id1, id2, progress, isManual) {
    const el1 = document.querySelector(`.station[data-id="${id1}"]`);
    const el2 = document.querySelector(`.station[data-id="${id2}"]`);
    if (el1 && el2) {
        el1.classList.add('has-train');
        if (isManual) el1.classList.add('manual');
        const dot = el1.querySelector('.blinking-dot');
        if (dot) {
            const rect1 = el1.getBoundingClientRect();
            const rect2 = el2.getBoundingClientRect();
            const dist = rect2.top - rect1.top;
            const yOffset = dist * Math.max(0, Math.min(1, progress));
            dot.style.transform = `translateY(${yOffset}px)`;
        }
    } else if (el1) {
        placeDotOnStation(id1, isManual);
    }
}

function placeDotOnStation(stationId, isManual) {
    const el = document.querySelector(`.station[data-id="${stationId}"]`);
    if (el) {
        el.classList.add('has-train'); if (isManual) el.classList.add('manual');
    }
}

function setOnlineStatus(isOnline) {
    const banner = document.getElementById('offlineBanner');
    const statusEl = document.getElementById('connectionStatus');
    const statusText = statusEl ? statusEl.querySelector('.conn-text') : null;
    if (isOnline) {
        banner.classList.remove('active');
        if (statusEl) {
            statusEl.classList.remove('offline');
            statusEl.classList.add('online');
            statusText.innerText = 'Онлайн';
        }
    } else {
        banner.classList.add('active');
        if (statusEl) {
            statusEl.classList.remove('online');
            statusEl.classList.add('offline');
            statusText.innerText = 'Офлайн';
        }
    }
}

function checkConnection() {
    if (!navigator.onLine) { setOnlineStatus(false); return; }
    fetch('../logo.png?ping=' + Date.now(), { method: 'HEAD', cache: 'no-store' })
    .then(r => { setOnlineStatus(r.ok); })
    .catch(() => { setOnlineStatus(false); });
}
setInterval(checkConnection, 60000);
window.addEventListener('online', checkConnection);
window.addEventListener('offline', checkConnection);
setTimeout(checkConnection, 3000);

window.addEventListener('DOMContentLoaded', () => {
    initMapSettings(); 
    const urlParams = new URLSearchParams(window.location.search);
    const autoTrain = urlParams.get('train');
    const autoDir = urlParams.get('dir');

    if (!autoTrain && !autoDir) {
        const savedTrain = StorageManager.load('radar_last_train', null);
        const savedDir = StorageManager.load('radar_last_dir', null);
        if (savedTrain && savedDir) {
            window.location.replace('?dir=' + savedDir + '&train=' + savedTrain);
            return;
        }
    }

    if (autoTrain) { 
        StorageManager.save('radar_last_train', autoTrain);
        if (autoDir) StorageManager.save('radar_last_dir', autoDir);
        setTimeout(() => { trackTrain(autoTrain); }, 50); 
    } else if (autoDir) {
        StorageManager.save('radar_last_dir', autoDir);
    }
});

let lastScreenshotBase64 = null;

function captureAndSendToGroup() {
    if (!lastScreenshotBase64) {
        alert('Помилка: Скріншот ще не згенеровано.');
        return;
    }
    
    const comment = document.getElementById('screenshotComment').value.trim();
    document.getElementById('screenshotModal').classList.remove('active');
    showLoader();
    
    fetch('share_group.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            image: lastScreenshotBase64,
            comment: comment,
            targetChatId: document.getElementById('targetGroupSelect').value
        })
    })
    .then(res => res.json())
    .then(data => {
        hideLoader();
        if (data.success) {
            alert('Скріншот успішно відправлено в групу!');
            document.getElementById('screenshotComment').value = '';
            lastScreenshotBase64 = null;
        } else {
            alert('Помилка відправки: ' + (data.error || 'Невідома помилка'));
        }
    })
    .catch(err => {
        hideLoader();
        alert('Помилка мережі при відправці.');
        console.error(err);
    });
}

function toggleSecretShare() {
    const btn = document.getElementById('btnSecretShare');
    if (btn) {
        btn.style.display = btn.style.display === 'none' ? '' : 'none';
    }
}

function openScreenshotModal() {
    if (!AppState.activeTrainNum) {
        alert('Спочатку оберіть потяг!');
        return;
    }
    
    showLoader();
    
    const container = document.querySelector('.container');
    container.classList.add('screenshot-mode');
    
    // Determine the threshold time for stations to display
    let startTimeLimit = -1;
    if (AppState.manualSetStationId) {
        const clickedPoint = AppState.interpolatedRoute.find(x => x.id === AppState.manualSetStationId);
        if (clickedPoint) {
            startTimeLimit = clickedPoint.time;
        }
    } else {
        // Find the last station the train has reached or passed based on current effectiveMins
        let currentPassedPoint = null;
        AppState.interpolatedRoute.forEach(p => {
            if (p.time <= AppState.effectiveMins) {
                if (!currentPassedPoint || p.time > currentPassedPoint.time) {
                    currentPassedPoint = p;
                }
            }
        });
        if (currentPassedPoint) {
            startTimeLimit = currentPassedPoint.time;
        }
    }
    
    // Hide passed stations that are before the startTimeLimit
    const stationsToHide = [];
    if (startTimeLimit !== -1) {
        AppState.interpolatedRoute.forEach(p => {
            if (p.time < startTimeLimit) {
                const el = container.querySelector(`.station[data-id="${p.id}"]`);
                if (el) {
                    el.style.display = 'none';
                    stationsToHide.push(el);
                }
            }
        });
    }
    
    // Hide railway sections that have no visible stations left
    const sections = container.querySelectorAll('.railway-section');
    const sectionsToHide = [];
    sections.forEach(sec => {
        const visibleStations = Array.from(sec.querySelectorAll('.station')).filter(el => el.style.display !== 'none');
        if (visibleStations.length === 0) {
            sec.style.display = 'none';
            sectionsToHide.push(sec);
        }
    });
    
    // Set placeholder text in preview
    const previewContainer = document.getElementById('screenshotPreviewContainer');
    if (previewContainer) {
        previewContainer.innerHTML = '<div style="text-align:center; padding: 15px; color: #94a3b8; font-size: 0.8rem;">Генерація передперегляду...</div>';
    }
    
    // Allow the browser to repaint with the screenshot-mode styles active
    setTimeout(() => {
        html2canvas(container, {
            useCORS: true,
            scale: 1.5,
            backgroundColor: document.body.classList.contains('dark-theme') ? '#0f172a' : '#f8fafc'
        }).then(canvas => {
            // Restore visibility of elements
            container.classList.remove('screenshot-mode');
            stationsToHide.forEach(el => el.style.display = '');
            sectionsToHide.forEach(sec => sec.style.display = '');
            
            lastScreenshotBase64 = canvas.toDataURL('image/png');
            
            // Set the generated image as a preview in the modal
            if (previewContainer) {
                previewContainer.innerHTML = `<img src="${lastScreenshotBase64}" alt="Передперегляд скріншоту">`;
            }
            
            const trainLabels = {
                '779': 'СУМИ - КИЇВ',
                '66': 'КИЇВ - СУМИ',
                '775': 'СУМИ - КИЇВ',
                '776': 'КИЇВ - СУМИ',
                '143': 'СУМИ - РАХІВ',
                '144': 'РАХІВ - СУМИ'
            };
            
            const dirLabel = trainLabels[AppState.activeTrainNum] ? `(${trainLabels[AppState.activeTrainNum]})` : '';
            const defaultText = `🚂 Згідно з інформацією по останньому місцезнаходженню потяга №${AppState.activeTrainNum} ${dirLabel}, наступні станції він прослідує орієнтовно за таким графіком. (За умови, що затримка в дорозі не збільшиться).`;
            document.getElementById('screenshotComment').value = defaultText;
            
            hideLoader();
            document.getElementById('screenshotModal').classList.add('active');
        }).catch(err => {
            // Restore visibility in case of error
            container.classList.remove('screenshot-mode');
            stationsToHide.forEach(el => el.style.display = '');
            sectionsToHide.forEach(sec => sec.style.display = '');
            hideLoader();
            alert('Помилка створення скріншоту.');
            console.error(err);
        });
    }, 100);
}

function fetchGroupMessages() {
    const list = document.getElementById('chatList');
    if (!list) return;

    fetch('get_messages.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.messages.length === 0) {
                    list.innerHTML = '<div style="text-align:center; padding: 20px; color: #94a3b8;">Повідомлень поки немає</div>';
                    return;
                }
                
                list.innerHTML = '';
                data.messages.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = 'chat-msg';
                    
                    const safeText = msg.text.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                    const safeName = msg.user_name.replace(/</g, "&lt;").replace(/>/g, "&gt;");

                    div.innerHTML = `
                        <div class="chat-msg-header">
                            <span class="chat-msg-author">${safeName}</span>
                            <span class="chat-msg-time">${msg.time}</span>
                        </div>
                        <div class="chat-msg-text">${safeText}</div>
                    `;
                    list.appendChild(div);
                });
                
                list.scrollTop = list.scrollHeight;
            }
        })
        .catch(err => console.error('Error fetching messages:', err));
}

function initChatSettings() {
    const isCollapsed = StorageManager.load('radar_chat_collapsed', false);
    const container = document.getElementById('groupMessages');
    const btn = document.getElementById('btnToggleChat');
    if (container && btn) {
        if (isCollapsed) {
            container.classList.add('collapsed');
            btn.innerHTML = '▼ Розгорнути';
        } else {
            container.classList.remove('collapsed');
            btn.innerHTML = '▲ Згорнути';
        }
    }
}

function toggleChatCollapse() {
    const container = document.getElementById('groupMessages');
    const btn = document.getElementById('btnToggleChat');
    if (!container || !btn) return;
    
    const isCollapsed = container.classList.toggle('collapsed');
    StorageManager.save('radar_chat_collapsed', isCollapsed);
    btn.innerHTML = isCollapsed ? '▼ Розгорнути' : '▲ Згорнути';
}

function clearChatMessages() {
    if (!confirm('Ви впевнені, що хочете очистити історію повідомлень для всіх користувачів?')) {
        return;
    }
    
    showLoader();
    fetch('clear_messages.php', {
        method: 'POST'
    })
    .then(res => res.json())
    .then(data => {
        hideLoader();
        if (data.success) {
            const list = document.getElementById('chatList');
            if (list) {
                list.innerHTML = '<div style="text-align:center; padding: 20px; color: #94a3b8;">Повідомлень поки немає</div>';
            }
        } else {
            alert('Помилка очищення: ' + (data.error || 'Невідома помилка'));
        }
    })
    .catch(err => {
        hideLoader();
        alert('Помилка мережі при очищенні.');
        console.error(err);
    });
}

window.addEventListener('DOMContentLoaded', () => {
    initChatSettings();
    fetchGroupMessages();
    setInterval(fetchGroupMessages, 15000);
});
