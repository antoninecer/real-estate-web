<?php
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Realitní AI – Qwen</title>

<style>
:root{
  --bg:#0f172a;
  --card:#111827;
  --line:#1f2937;
  --text:#e5e7eb;
  --muted:#94a3b8;
  --accent:#3b82f6;
  --ok:#22c55e;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:system-ui,-apple-system,Segoe UI,Roboto;
  background:var(--bg);
  color:var(--text);
}

.layout{
  display:grid;
  grid-template-columns: 320px 1fr;
  min-height:100vh;
}

.sidebar{
  border-right:1px solid var(--line);
  padding:20px;
  background:#0b1220;
}

.content{
  padding:24px;
  max-width:1100px;
}

h2{
  margin-top:0;
  font-size:15px;
  letter-spacing:.5px;
  color:#cbd5e1;
}

label{
  font-size:12px;
  color:var(--muted);
  display:block;
  margin-bottom:6px;
}

input, textarea{
  width:100%;
  padding:10px;
  border-radius:10px;
  border:1px solid var(--line);
  background:#0b1220;
  color:var(--text);
}

textarea{
  resize:vertical;
}

button{
  margin-top:10px;
  padding:10px 12px;
  border-radius:10px;
  border:1px solid var(--line);
  background:#0b1220;
  color:var(--text);
  cursor:pointer;
}

button.primary{
  background:var(--accent);
  border-color:var(--accent);
}

button.save{
  background:var(--ok);
  border-color:var(--ok);
}

.output{
  margin-top:20px;
  background:#0b1220;
  border:1px solid var(--line);
  border-radius:12px;
  padding:16px;
  white-space:pre-wrap;
  font-family:monospace;
  font-size:13px;
}

.small{
  font-size:12px;
  color:var(--muted);
}
</style>
</head>
<body>

<div class="layout">

  <!-- SIDEBAR -->
  <div class="sidebar">

    <h2>Nastavení</h2>

    <label>Temperature</label>
    <input type="number" step="0.01" id="temperature">

    <label style="margin-top:14px;">Max tokens</label>
    <input type="number" id="max_tokens">

    <h2 style="margin-top:22px;">System prompt</h2>

    <textarea id="system" rows="12"></textarea>

    <button class="save" onclick="saveSystem()">Uložit system prompt</button>

    <div class="small" style="margin-top:12px;">
      System prompt se ukládá do localStorage.
    </div>

  </div>

  <!-- CONTENT -->
  <div class="content">

    <h2>Inzerát</h2>

    <textarea id="user" rows="6"
      placeholder="Sem vlož text inzerátu..."></textarea>

    <button class="primary" onclick="send()">Vyhodnotit</button>

    <div id="result" class="output" style="display:none;"></div>

  </div>

</div>

<script>

const DEFAULT_SYSTEM = `Jsi přísný realitní analytik.
Vyplň JSON.
Pravidla:
- ai_score = součet všech hodnot v breakdown
- každá položka 0–10
- strengths max 200 znaků
- weaknesses max 200 znaků
- summary max 300 znaků
Nevracej nic jiného než validní JSON.
{
 "ai_score": 0,
 "breakdown": {
   "lokalita": 0,
   "komfort": 0,
   "dispozice": 0,
   "riziko": 0,
   "hodnota": 0
 },
 "verdict": "IGNORE",
 "strengths": "",
 "weaknesses": "",
 "summary": ""
}`;

function init(){
  document.getElementById("temperature").value =
    localStorage.getItem("temperature") || 0.1;

  document.getElementById("max_tokens").value =
    localStorage.getItem("max_tokens") || 400;

  document.getElementById("system").value =
    localStorage.getItem("system") || DEFAULT_SYSTEM;
}

function saveSystem(){
  localStorage.setItem("system",
    document.getElementById("system").value);

  localStorage.setItem("temperature",
    document.getElementById("temperature").value);

  localStorage.setItem("max_tokens",
    document.getElementById("max_tokens").value);

  alert("Uloženo.");
}

async function send(){

  const system = document.getElementById("system").value;
  const user = document.getElementById("user").value;

  if(!user.trim()){
    alert("Chybí text inzerátu.");
    return;
  }

  const payload = {
    temperature: parseFloat(document.getElementById("temperature").value),
    max_tokens: parseInt(document.getElementById("max_tokens").value),
    messages: [
      {role:"system", content: system},
      {role:"user", content: user}
    ]
  };

  const r = await fetch("api.php", {
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body: JSON.stringify(payload)
  });

  const data = await r.json();

  const out = document.getElementById("result");
  out.style.display = "block";
  out.textContent = JSON.stringify(data, null, 2);
}

init();

</script>

</body>
</html>
