<?php
// index.php
?><!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Qwen3 4B – Chat Frontend</title>
  <style>
    :root{
      --bg:#0b0f19;
      --card:#0f172a;
      --muted:#94a3b8;
      --text:#e5e7eb;
      --line:#1f2a44;
      --accent:#60a5fa;
      --accent2:#34d399;
      --danger:#fb7185;
      --warn:#fbbf24;
      --ok:#22c55e;
      --mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      --sans: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
      --radius: 16px;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family:var(--sans);
      background: radial-gradient(1200px 800px at 20% -10%, #172554 0%, transparent 55%),
                  radial-gradient(900px 700px at 90% 0%, #064e3b 0%, transparent 55%),
                  var(--bg);
      color:var(--text);
    }
    .wrap{max-width:1150px;margin:0 auto;padding:28px 18px 60px}
    header{
      display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;
      margin-bottom:18px;
    }
    h1{margin:0;font-size:20px;letter-spacing:.3px}
    .sub{margin:6px 0 0;color:var(--muted);font-size:13px}
    .row{display:flex;gap:14px;flex-wrap:wrap}
    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.02));
      border:1px solid rgba(148,163,184,.16);
      border-radius:var(--radius);
      padding:16px;
      box-shadow: 0 10px 28px rgba(0,0,0,.30);
    }
    .card h2{margin:0 0 10px;font-size:14px;color:#cbd5e1}
    label{display:block;font-size:12px;color:var(--muted);margin:0 0 6px}
    input[type="text"], input[type="number"], select, textarea{
      width:100%;
      background: rgba(2,6,23,.55);
      color: var(--text);
      border: 1px solid rgba(148,163,184,.18);
      border-radius: 12px;
      padding: 10px 11px;
      outline: none;
    }
    textarea{min-height:92px;resize:vertical}
    input:focus, select:focus, textarea:focus{border-color: rgba(96,165,250,.55); box-shadow: 0 0 0 3px rgba(96,165,250,.15)}
    .grid{
      display:grid;
      grid-template-columns: 1.1fr .5fr .5fr;
      gap:12px;
    }
    .grid2{
      display:grid;
      grid-template-columns: 1.1fr 1fr;
      gap:12px;
    }
    .btns{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
    button{
      border:1px solid rgba(148,163,184,.18);
      background: rgba(2,6,23,.35);
      color: var(--text);
      padding: 10px 12px;
      border-radius: 12px;
      cursor:pointer;
      font-weight: 600;
      transition: transform .03s ease, border-color .15s ease, background .15s ease;
    }
    button:hover{border-color: rgba(96,165,250,.50); background: rgba(2,6,23,.55)}
    button:active{transform: translateY(1px)}
    .primary{
      border-color: rgba(96,165,250,.55);
      background: rgba(96,165,250,.12);
    }
    .primary:hover{background: rgba(96,165,250,.18)}
    .ghost{opacity:.95}
    .danger{
      border-color: rgba(251,113,133,.55);
      background: rgba(251,113,133,.10);
    }
    .danger:hover{background: rgba(251,113,133,.16)}
    .ok{
      border-color: rgba(34,197,94,.55);
      background: rgba(34,197,94,.10);
    }
    .ok:hover{background: rgba(34,197,94,.16)}
    .pill{
      display:inline-flex;align-items:center;gap:8px;
      padding:7px 10px;border-radius:999px;
      border:1px solid rgba(148,163,184,.16);
      background: rgba(2,6,23,.35);
      color: var(--muted);
      font-size:12px;
    }
    .status-dot{width:9px;height:9px;border-radius:50%;background:rgba(148,163,184,.55)}
    .status-dot.ok{background:rgba(34,197,94,.85)}
    .status-dot.bad{background:rgba(251,113,133,.85)}
    .status-dot.warn{background:rgba(251,191,36,.9)}
    .messages{
      display:flex;flex-direction:column;gap:10px;
    }
    .msg{
      border:1px solid rgba(148,163,184,.16);
      border-radius: 14px;
      padding: 12px;
      background: rgba(2,6,23,.30);
    }
    .msg-head{
      display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px
    }
    .msg-controls{display:flex;gap:8px;align-items:center}
    .small{font-size:12px;color:var(--muted)}
    .mono{font-family:var(--mono)}
    pre{
      margin:0;
      white-space:pre-wrap;
      word-break:break-word;
      background: rgba(2,6,23,.55);
      border:1px solid rgba(148,163,184,.16);
      border-radius: 14px;
      padding: 12px;
      font-family: var(--mono);
      font-size: 12.5px;
      line-height: 1.4;
    }
    .split{
      display:grid;
      grid-template-columns: 1.15fr .85fr;
      gap:14px;
      align-items:start;
    }
    @media (max-width: 980px){
      .split{grid-template-columns:1fr}
      .grid{grid-template-columns:1fr}
      .grid2{grid-template-columns:1fr}
    }
    .hint{color:var(--muted);font-size:12px;margin-top:8px}
    .kpi{
      display:flex;gap:10px;flex-wrap:wrap;margin-top:10px
    }
    .kpi strong{color:#e2e8f0}
    .right-top{
      display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end
    }
    .linkish{
      border:0;background:transparent;color:var(--accent);padding:0;font-weight:700;cursor:pointer
    }
  </style>
</head>
<body>
<div class="wrap">
  <header>
    <div>
      <h1>Frontend pro Qwen3 4B (OpenAI kompatibilní /v1/chat/completions)</h1>
      <div class="sub">Nastavení se pamatuje průběžně (localStorage). „Uložit jako výchozí“ ukládá i do cookie.</div>
    </div>
    <div class="right-top">
      <span class="pill" id="connPill"><span class="status-dot" id="connDot"></span><span id="connText">Nezkoušeno</span></span>
      <button class="ghost" id="btnPing" type="button">Otestovat spojení</button>
    </div>
  </header>

  <div class="split">
    <div class="card">
      <h2>Nastavení dotazu</h2>

      <div class="grid">
        <div>
          <label for="model">Model</label>
          <input id="model" type="text" value="qwen/qwen3-4b-2507" />
        </div>
        <div>
          <label for="temperature">Temperature</label>
          <input id="temperature" type="number" min="0" max="2" step="0.01" value="0.10" />
        </div>
        <div>
          <label for="max_tokens">max_tokens</label>
          <input id="max_tokens" type="number" min="1" max="8000" step="1" value="250" />
        </div>
      </div>

      <div class="btns">
        <button class="ok" id="btnSaveDefault" type="button">Uložit jako výchozí</button>
        <button id="btnReset" type="button">Reset na tovární</button>
        <button class="ghost" id="btnExport" type="button">Export nastavení (JSON)</button>
        <button class="ghost" id="btnImport" type="button">Import nastavení</button>
      </div>

      <div class="hint">
        Poznámka: Výchozí uložené nastavení se bere (v pořadí) z <span class="mono">localStorage</span> → <span class="mono">cookie</span> → tovární hodnoty.
      </div>

      <div style="height:12px"></div>
      <h2>Messages (role + content)</h2>

      <div class="messages" id="messages"></div>

      <div class="btns">
        <button id="btnAddMsg" type="button">+ Přidat message</button>
        <button class="primary" id="btnSend" type="button">Odeslat</button>
      </div>

      <div class="kpi">
        <span class="pill"><strong>Prompt</strong>&nbsp;<span id="kpiPrompt">—</span></span>
        <span class="pill"><strong>Completion</strong>&nbsp;<span id="kpiCompletion">—</span></span>
        <span class="pill"><strong>Total</strong>&nbsp;<span id="kpiTotal">—</span></span>
        <span class="pill"><strong>Finish</strong>&nbsp;<span id="kpiFinish">—</span></span>
      </div>
    </div>

    <div class="card">
      <h2>Odpověď</h2>

      <div class="btns" style="margin-top:0">
        <button class="ghost" id="btnCopyRaw" type="button">Kopírovat RAW</button>
        <button class="ghost" id="btnCopyContent" type="button">Kopírovat content</button>
        <button class="ghost" id="btnPrettyContent" type="button">Zkusit zformátovat JSON</button>
      </div>

      <div style="height:10px"></div>
      <div class="small">RAW odpověď z backendu:</div>
      <pre id="outRaw" class="mono">—</pre>

      <div style="height:10px"></div>
      <div class="small">Vytažené <span class="mono">choices[0].message.content</span> (pokud existuje):</div>
      <pre id="outContent" class="mono">—</pre>

      <div style="height:10px"></div>
      <div class="small">Poznámka k validitě JSON:</div>
      <pre id="outJsonNote" class="mono">—</pre>
    </div>
  </div>
</div>

<script>
(() => {
  const FACTORY = {
    model: "qwen/qwen3-4b-2507",
    temperature: 0.10,
    max_tokens: 250,
    messages: [
      {
        role: "system",
        content: "Jsi přísný realitní analytik. Vyplň JSON. Pravidla: ai_score = součet všech hodnot v breakdown. Každá položka 0–10. Strengths max 200 znaků. Weaknesses max 200 znaků. Summary max 300 znaků. Nevracej nic jiného než validní JSON. { \"ai_score\": 0, \"breakdown\": { \"lokalita\": 0, \"komfort\": 0, \"dispozice\": 0, \"riziko\": 0, \"hodnota\": 0 }, \"verdict\": \"IGNORE\", \"strengths\": \"\", \"weaknesses\": \"\", \"summary\": \"\" }"
      },
      {
        role: "user",
        content: "Byt 4+1, 89m2, Praha Chodov, cena 9 999 000 Kč, 4. patro s výtahem, metro 820m, stav dobrý, panelová stavba."
      }
    ]
  };

  const els = {
    model: document.getElementById("model"),
    temperature: document.getElementById("temperature"),
    max_tokens: document.getElementById("max_tokens"),
    messages: document.getElementById("messages"),
    outRaw: document.getElementById("outRaw"),
    outContent: document.getElementById("outContent"),
    outJsonNote: document.getElementById("outJsonNote"),
    kpiPrompt: document.getElementById("kpiPrompt"),
    kpiCompletion: document.getElementById("kpiCompletion"),
    kpiTotal: document.getElementById("kpiTotal"),
    kpiFinish: document.getElementById("kpiFinish"),
    connDot: document.getElementById("connDot"),
    connText: document.getElementById("connText"),
    connPill: document.getElementById("connPill"),
  };

  const LS_KEY = "qwen_frontend_settings_v1";
  const CK_KEY = "qwen_frontend_default_v1";

  function setConn(state, text){
    // state: ok | bad | warn | idle
    els.connDot.classList.remove("ok","bad","warn");
    if (state === "ok") els.connDot.classList.add("ok");
    else if (state === "bad") els.connDot.classList.add("bad");
    else if (state === "warn") els.connDot.classList.add("warn");
    els.connText.textContent = text;
  }

  function getCookie(name){
    const m = document.cookie.match(new RegExp("(^| )" + name.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&") + "=([^;]+)"));
    return m ? decodeURIComponent(m[2]) : null;
  }

  function setCookie(name, value, days){
    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
  }

  function safeParse(json, fallback){
    try { return JSON.parse(json); } catch { return fallback; }
  }

  function loadSettings(){
    const fromLS = safeParse(localStorage.getItem(LS_KEY) || "", null);
    if (fromLS) return fromLS;

    const ck = getCookie(CK_KEY);
    const fromCK = ck ? safeParse(ck, null) : null;
    if (fromCK) return fromCK;

    return structuredClone(FACTORY);
  }

  function saveToLS(settings){
    localStorage.setItem(LS_KEY, JSON.stringify(settings));
  }

  function saveToCookie(settings){
    setCookie(CK_KEY, JSON.stringify(settings), 180);
  }

  function currentSettingsFromUI(){
    const settings = {
      model: els.model.value.trim() || FACTORY.model,
      temperature: Number(els.temperature.value),
      max_tokens: Number(els.max_tokens.value),
      messages: readMessagesFromUI()
    };
    if (!Number.isFinite(settings.temperature)) settings.temperature = FACTORY.temperature;
    if (!Number.isFinite(settings.max_tokens)) settings.max_tokens = FACTORY.max_tokens;
    return settings;
  }

  function writeUIFromSettings(s){
    els.model.value = s.model ?? FACTORY.model;
    els.temperature.value = s.temperature ?? FACTORY.temperature;
    els.max_tokens.value = s.max_tokens ?? FACTORY.max_tokens;
    renderMessages(s.messages ?? FACTORY.messages);
  }

  function renderMessages(msgs){
    els.messages.innerHTML = "";
    msgs.forEach((m, idx) => addMessageRow(m.role, m.content, false));
    if (msgs.length === 0) addMessageRow("system", "", false);
  }

  function addMessageRow(role="user", content="", shouldPersist=true){
    const wrap = document.createElement("div");
    wrap.className = "msg";

    wrap.innerHTML = `
      <div class="msg-head">
        <div class="msg-controls">
          <label class="small" style="margin:0">role</label>
          <select class="role">
            <option value="system">system</option>
            <option value="user">user</option>
            <option value="assistant">assistant</option>
          </select>
          <span class="small">|</span>
          <button type="button" class="ghost up" title="Posunout nahoru">↑</button>
          <button type="button" class="ghost down" title="Posunout dolů">↓</button>
          <button type="button" class="danger del" title="Smazat">Smazat</button>
        </div>
        <div class="small">Tip: nastav si system prompt a pak už jen vkládej inzeráty jako user.</div>
      </div>
      <textarea class="content" placeholder="content..."></textarea>
    `;

    const sel = wrap.querySelector("select.role");
    const ta = wrap.querySelector("textarea.content");
    sel.value = role;
    ta.value = content;

    // wire
    wrap.querySelector("button.del").addEventListener("click", () => {
      wrap.remove();
      persistNow();
    });
    wrap.querySelector("button.up").addEventListener("click", () => {
      const prev = wrap.previousElementSibling;
      if (prev) els.messages.insertBefore(wrap, prev);
      persistNow();
    });
    wrap.querySelector("button.down").addEventListener("click", () => {
      const next = wrap.nextElementSibling;
      if (next) els.messages.insertBefore(next, wrap);
      persistNow();
    });

    sel.addEventListener("change", persistNow);
    ta.addEventListener("input", debounce(persistNow, 250));

    els.messages.appendChild(wrap);
    if (shouldPersist) persistNow();
  }

  function readMessagesFromUI(){
    const rows = [...els.messages.querySelectorAll(".msg")];
    return rows.map(r => ({
      role: r.querySelector("select.role").value,
      content: r.querySelector("textarea.content").value
    })).filter(m => (m.content ?? "").trim().length > 0);
  }

  function debounce(fn, ms){
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
  }

  function persistNow(){
    const s = currentSettingsFromUI();
    saveToLS(s);
  }

  function setOutput(rawObj){
    els.outRaw.textContent = JSON.stringify(rawObj, null, 2);

    const content = rawObj?.choices?.[0]?.message?.content ?? "";
    els.outContent.textContent = content || "—";

    // KPIs
    const usage = rawObj?.usage || {};
    els.kpiPrompt.textContent = usage.prompt_tokens ?? "—";
    els.kpiCompletion.textContent = usage.completion_tokens ?? "—";
    els.kpiTotal.textContent = usage.total_tokens ?? "—";
    els.kpiFinish.textContent = rawObj?.choices?.[0]?.finish_reason ?? "—";

    // JSON validity note for content
    const note = analyzeContentJSON(content);
    els.outJsonNote.textContent = note;
  }

  function analyzeContentJSON(content){
    if (!content || !content.trim()) return "Žádný content k analýze.";
    // model někdy vrátí JSON s příměsí textu – zkusíme nejdřív čistý parse
    try {
      const obj = JSON.parse(content);
      const verdict = obj?.verdict;
      const score = obj?.ai_score;
      let msg = "content je validní JSON ✅";
      if (typeof score === "number" && obj?.breakdown && typeof obj.breakdown === "object"){
        const sum = Object.values(obj.breakdown).reduce((a,b)=>a+(Number(b)||0),0);
        msg += `\nKontrola: ai_score=${score}, součet breakdown=${sum}.`;
        if (sum !== score) msg += " ⚠️ Nesedí součet (model nedodržel pravidlo).";
      }
      if (verdict) msg += `\nverdict: ${verdict}`;
      return msg;
    } catch (e){
      // zkus „vyříznout“ první {...} blok (pragmaticky)
      const first = content.indexOf("{");
      const last = content.lastIndexOf("}");
      if (first >= 0 && last > first){
        const slice = content.slice(first, last+1);
        try {
          JSON.parse(slice);
          return "content není čistý JSON ❗ Ale uvnitř je pravděpodobně validní JSON blok (vyříznutelný) ⚠️\nDoporučení: zvyšit max_tokens nebo zpřísnit instrukci „Neukončuj uprostřed“.";
        } catch {}
      }
      return "content není validní JSON ❌ (nebo je uříznutý). Tip: zvyšit max_tokens a snížit teplotu.";
    }
  }

  async function postJSON(url, payload){
    const r = await fetch(url, {
      method: "POST",
      headers: {"Content-Type": "application/json"},
      body: JSON.stringify(payload)
    });
    const txt = await r.text();
    let data;
    try { data = JSON.parse(txt); } catch { data = { error: { message: "Backend nevrátil JSON", raw: txt } }; }
    if (!r.ok) throw data;
    return data;
  }

  async function ping(){
    setConn("warn", "Testuji…");
    try{
      const data = await postJSON("api.php", { ping: true });
      setConn("ok", data?.ok ? "OK" : "Odpověď");
    }catch(e){
      setConn("bad", (e?.error?.message || "Chyba spojení"));
    }
  }

  async function send(){
    persistNow();
    setConn("warn", "Volám model…");
    els.outRaw.textContent = "—";
    els.outContent.textContent = "—";
    els.outJsonNote.textContent = "—";
    els.kpiPrompt.textContent = els.kpiCompletion.textContent = els.kpiTotal.textContent = els.kpiFinish.textContent = "—";

    const settings = currentSettingsFromUI();

    // minimální disciplína: ať tam je aspoň jeden user
    const hasUser = settings.messages.some(m => m.role === "user" && (m.content||"").trim());
    if (!hasUser){
      setConn("bad", "Chybí user message");
      els.outJsonNote.textContent = "Doplň aspoň jeden inzerát jako user message.";
      return;
    }

    const payload = {
      model: settings.model,
      temperature: settings.temperature,
      max_tokens: settings.max_tokens,
      messages: settings.messages
    };

    try{
      const data = await postJSON("api.php", payload);
      setConn("ok", "Hotovo");
      setOutput(data);
    }catch(e){
      setConn("bad", "Chyba");
      setOutput(e);
    }
  }

  function exportSettings(){
    const s = currentSettingsFromUI();
    const blob = new Blob([JSON.stringify(s, null, 2)], {type:"application/json"});
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "qwen-settings.json";
    a.click();
    URL.revokeObjectURL(a.href);
  }

  function importSettings(){
    const inp = document.createElement("input");
    inp.type = "file";
    inp.accept = "application/json";
    inp.onchange = async () => {
      const f = inp.files?.[0];
      if (!f) return;
      const txt = await f.text();
      const s = safeParse(txt, null);
      if (!s) return alert("Neplatný JSON.");
      writeUIFromSettings({
        model: s.model ?? FACTORY.model,
        temperature: s.temperature ?? FACTORY.temperature,
        max_tokens: s.max_tokens ?? FACTORY.max_tokens,
        messages: Array.isArray(s.messages) ? s.messages : FACTORY.messages
      });
      persistNow();
    };
    inp.click();
  }

  async function copyText(text){
    try{
      await navigator.clipboard.writeText(text);
    }catch{
      // fallback
      const ta = document.createElement("textarea");
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      ta.remove();
    }
  }

  function prettyContent(){
    const content = els.outContent.textContent;
    try{
      const obj = JSON.parse(content);
      els.outContent.textContent = JSON.stringify(obj, null, 2);
      els.outJsonNote.textContent = "Přeformátováno jako validní JSON ✅";
    }catch{
      els.outJsonNote.textContent = "Nelze přeformátovat: content není čistý JSON.";
    }
  }

  // init
  const initSettings = loadSettings();
  writeUIFromSettings(initSettings);

  // persist on top inputs
  ["input","change"].forEach(ev => {
    els.model.addEventListener(ev, persistNow);
    els.temperature.addEventListener(ev, persistNow);
    els.max_tokens.addEventListener(ev, persistNow);
  });

  // buttons
  document.getElementById("btnAddMsg").addEventListener("click", () => addMessageRow("user", "", true));
  document.getElementById("btnSend").addEventListener("click", send);
  document.getElementById("btnPing").addEventListener("click", ping);

  document.getElementById("btnSaveDefault").addEventListener("click", () => {
    const s = currentSettingsFromUI();
    saveToCookie(s);
    saveToLS(s);
    setConn("ok", "Uloženo jako default");
  });

  document.getElementById("btnReset").addEventListener("click", () => {
    writeUIFromSettings(structuredClone(FACTORY));
    saveToLS(structuredClone(FACTORY));
    setConn("warn", "Resetováno");
  });

  document.getElementById("btnExport").addEventListener("click", exportSettings);
  document.getElementById("btnImport").addEventListener("click", importSettings);

  document.getElementById("btnCopyRaw").addEventListener("click", () => copyText(els.outRaw.textContent));
  document.getElementById("btnCopyContent").addEventListener("click", () => copyText(els.outContent.textContent));
  document.getElementById("btnPrettyContent").addEventListener("click", prettyContent);

  // quick ping on load (nepřehánět – ale ať víš hned, jestli to dýchá)
  ping();

})();
</script>
</body>
</html>
