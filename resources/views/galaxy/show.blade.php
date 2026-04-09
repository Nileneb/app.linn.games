<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cluster Explorer</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{background:#000;overflow:hidden;font-family:'Courier New',monospace;cursor:none;}
canvas{display:block;}
#hud{position:fixed;top:0;left:0;right:0;pointer-events:none;padding:14px 18px;display:flex;justify-content:space-between;align-items:flex-start;}
#hud-left{color:#6366f1;font-size:11px;line-height:2;text-shadow:0 0 8px #6366f1;}
#hud-right{color:#06b6d4;font-size:11px;line-height:2;text-align:right;text-shadow:0 0 8px #06b6d4;}
#hud-title{font-size:15px;font-weight:bold;color:#a5b4fc;letter-spacing:3px;text-shadow:0 0 12px #6366f1;}
#score{font-size:20px;font-weight:bold;color:#f59e0b;text-shadow:0 0 12px #f59e0b;transition:transform .1s;}
#score.pop{transform:scale(1.3);}
#combo{font-size:13px;color:#f59e0b;text-shadow:0 0 8px #f59e0b;transition:opacity .3s;}
#crosshair{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);pointer-events:none;width:24px;height:24px;}
#crosshair::before,#crosshair::after{content:'';position:absolute;background:rgba(6,182,212,.9);}
#crosshair::before{width:2px;height:100%;left:50%;transform:translateX(-50%);}
#crosshair::after{height:2px;width:100%;top:50%;transform:translateY(-50%);}
#crosshair-dot{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:4px;height:4px;background:#06b6d4;border-radius:50%;pointer-events:none;box-shadow:0 0 6px #06b6d4;}
#info-panel{position:fixed;bottom:68px;left:50%;transform:translateX(-50%);background:rgba(10,10,30,.9);border:1px solid #6366f1;border-radius:8px;padding:10px 20px;color:#a5b4fc;font-size:11px;text-align:center;pointer-events:none;transition:opacity .3s;max-width:420px;box-shadow:0 0 20px rgba(99,102,241,.4);}
.cluster-name{font-size:13px;color:#e0e7ff;font-weight:bold;margin-bottom:3px;}
.cluster-stats{color:#818cf8;}
#controls-hint{position:fixed;bottom:20px;right:20px;color:rgba(99,102,241,.4);font-size:10px;line-height:2;text-align:right;pointer-events:none;}
#wave-banner{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);font-size:32px;font-weight:bold;color:#f59e0b;text-shadow:0 0 30px #f59e0b,0 0 60px #f59e0b;letter-spacing:6px;pointer-events:none;opacity:0;transition:opacity .3s;}
#health-bar{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:8px;pointer-events:none;}
#health-fill{height:6px;width:200px;background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.4);border-radius:3px;overflow:hidden;}
#health-inner{height:100%;background:#ef4444;width:100%;transition:width .3s;box-shadow:0 0 8px #ef4444;}
#flash{position:fixed;inset:0;pointer-events:none;opacity:0;transition:opacity .08s;}
#boss-alert{position:fixed;top:28%;left:50%;transform:translateX(-50%);color:#ef4444;font-size:18px;font-weight:bold;letter-spacing:4px;text-shadow:0 0 20px #ef4444;pointer-events:none;opacity:0;transition:opacity .3s;}
#mute-btn{position:fixed;top:14px;left:50%;transform:translateX(-50%);background:rgba(10,10,30,.7);border:1px solid #6366f1;color:#a5b4fc;font-size:10px;padding:4px 12px;border-radius:4px;cursor:pointer;letter-spacing:2px;transition:background .2s;}
#mute-btn:hover{background:rgba(99,102,241,.3);}
#back-btn{position:fixed;top:14px;left:18px;background:rgba(10,10,30,.7);border:1px solid #6366f1;color:#a5b4fc;font-size:10px;padding:4px 12px;border-radius:4px;cursor:pointer;letter-spacing:2px;text-decoration:none;transition:background .2s;z-index:200;}
#back-btn:hover{background:rgba(99,102,241,.3);}
#start-overlay{position:fixed;inset:0;background:rgba(0,0,10,.85);display:flex;flex-direction:column;align-items:center;justify-content:center;color:#a5b4fc;z-index:100;}
#start-overlay h1{font-size:28px;letter-spacing:6px;color:#e0e7ff;text-shadow:0 0 20px #6366f1;margin-bottom:12px;}
#start-overlay p{font-size:13px;color:#818cf8;margin-bottom:8px;text-align:center;max-width:400px;line-height:1.7;}
#start-btn{margin-top:20px;background:rgba(99,102,241,.2);border:1px solid #6366f1;color:#a5b4fc;font-size:13px;letter-spacing:3px;padding:10px 28px;border-radius:6px;cursor:pointer;font-family:inherit;transition:all .2s;}
#start-btn:hover{background:rgba(99,102,241,.4);box-shadow:0 0 20px rgba(99,102,241,.4);}
#start-btn:disabled{opacity:.4;cursor:not-allowed;}
#loading-hint{font-size:11px;color:#4f46e5;margin-top:8px;letter-spacing:1px;}
</style>
</head>
<body>
<canvas id="c"></canvas>

<a id="back-btn" href="{{ route('recherche.projekt', $projektId) }}">← ZURÜCK</a>

<div id="start-overlay">
  <h1>CLUSTER EXPLORER</h1>
  <p>Du fliegst durch deinen Forschungsraum.<br>
  Outlier-Paper, Cluster-Kollisionen und Dunkle Materie greifen an.<br>
  Klassifiziere sie mit dem Laser — bevor sie dich erreichen.</p>
  <p style="color:#ef4444;font-size:11px;">⚠ Ton wird aktiviert — ggf. Lautstärke anpassen</p>
  <div id="loading-hint">Lade Galaxie-Daten...</div>
  <button id="start-btn" onclick="startGame()" disabled>▶ STARTEN</button>
</div>

<div id="hud">
  <div id="hud-left">
    <div id="hud-title">CLUSTER EXPLORER</div>
    <div>WAVE: <span id="wave-num">1</span></div>
    <div style="color:#ef4444;text-shadow:0 0 8px #ef4444">FEINDE: <span id="enemy-count">0</span></div>
    <div style="color:#10b981;text-shadow:0 0 8px #10b981">KILLS: <span id="kills">0</span></div>
  </div>
  <div id="hud-right">
    <div id="score">0</div>
    <div id="combo" style="opacity:0">COMBO ×<span id="combo-num">1</span></div>
    <div>PRÄZISION: <span id="precision">—</span></div>
    <div style="margin-top:4px;color:#10b981;text-shadow:0 0 8px #10b981">EVIDENZ: <span id="evidence">░░░░░░░░░░</span></div>
  </div>
</div>
<button id="mute-btn" onclick="toggleMute()">🔊 TON</button>
<div id="crosshair"></div><div id="crosshair-dot"></div>
<div id="info-panel" style="opacity:0"><div class="cluster-name" id="panel-name"></div><div class="cluster-stats" id="panel-stats"></div></div>
<div id="wave-banner">WAVE 1</div>
<div id="health-bar">
  <span style="color:#ef4444;font-size:10px;text-shadow:0 0 6px #ef4444">SCHILD</span>
  <div id="health-fill"><div id="health-inner"></div></div>
</div>
<div id="flash"></div>
<div id="boss-alert"></div>
<div id="controls-hint">KLICK: Pointer lock<br>MAUS: Zielen<br>W/S/A/D: Fliegen<br>SHIFT: Boost<br>SPACE/KLICK: Feuer</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script>
const PROJEKT_ID = @json($projektId);
const GALAXY_DATA_URL = '/recherche/' + PROJEKT_ID + '/galaxy-data';

// ── DEMO FALLBACK ─────────────────────────────────────────────────────
const DEMO_CLUSTERS = [
  {label:'Intervention',  desc:'Therapeutische Maßnahmen',    treffer:142,relevanz:.92,pos:[0,0,0],      color:0x6366f1,size:8},
  {label:'Population',    desc:'Zielgruppen & Stichproben',   treffer:89, relevanz:.78,pos:[38,8,-22],   color:0x10b981,size:6},
  {label:'Outcome',       desc:'Primäre Endpunkte',           treffer:115,relevanz:.85,pos:[-32,5,18],   color:0x06b6d4,size:7},
  {label:'Studiendesign', desc:'RCT, Kohorten, Case-Control', treffer:67, relevanz:.65,pos:[22,-15,38],  color:0xf59e0b,size:5},
  {label:'Bias & Qualität',desc:'RoB2, CASP Bewertungen',    treffer:43, relevanz:.71,pos:[-48,-10,-32],color:0xec4899,size:4},
  {label:'Comparator',    desc:'Kontrollgruppen & Vergleich', treffer:38, relevanz:.60,pos:[52,20,12],   color:0xa78bfa,size:4},
  {label:'Setting',       desc:'Klinische Umgebungen',        treffer:29, relevanz:.55,pos:[-18,28,-52], color:0xfb923c,size:3.5},
  {label:'Intervention 2',desc:'Ergänzende Verfahren',        treffer:21, relevanz:.48,pos:[10,-30,60],  color:0x34d399,size:3},
];

function normalizeCluster(c) {
  const relevanzMap = {hoch: 0.9, mittel: 0.7, niedrig: 0.5};
  return {
    label:   c.label,
    desc:    c.desc  || '',
    treffer: c.treffer || 10,
    relevanz: relevanzMap[c.relevanz] ?? 0.7,
    pos:     c.position,
    color:   parseInt((c.color || '#6366f1').slice(1), 16),
    size:    c.size  || 5,
  };
}

let clusters = [];

async function loadClusters() {
  try {
    const res = await fetch(GALAXY_DATA_URL, {headers: {'Accept': 'application/json'}});
    if (!res.ok) throw new Error('no data');
    const data = await res.json();
    if (!data.clusters?.length) throw new Error('empty');
    return data.clusters.map(normalizeCluster);
  } catch (_) {
    return DEMO_CLUSTERS;
  }
}

// ── AUDIO ENGINE ──────────────────────────────────────────────────────
let audioCtx=null, muted=false;
function initAudio(){audioCtx=new(window.AudioContext||window.webkitAudioContext)();}
function toggleMute(){muted=!muted;document.getElementById('mute-btn').textContent=muted?'🔇 STUMM':'🔊 TON';}

function playTone(freq,type='sine',dur=.08,vol=.15,delay=0){
  if(!audioCtx||muted)return;
  const o=audioCtx.createOscillator(),g=audioCtx.createGain();
  o.connect(g);g.connect(audioCtx.destination);
  o.type=type;o.frequency.setValueAtTime(freq,audioCtx.currentTime+delay);
  g.gain.setValueAtTime(vol,audioCtx.currentTime+delay);
  g.gain.exponentialRampToValueAtTime(.0001,audioCtx.currentTime+delay+dur);
  o.start(audioCtx.currentTime+delay);o.stop(audioCtx.currentTime+delay+dur);
}
function playSweep(f1,f2,dur=.15,vol=.1,type='sawtooth'){
  if(!audioCtx||muted)return;
  const o=audioCtx.createOscillator(),g=audioCtx.createGain();
  o.connect(g);g.connect(audioCtx.destination);
  o.type=type;o.frequency.setValueAtTime(f1,audioCtx.currentTime);
  o.frequency.linearRampToValueAtTime(f2,audioCtx.currentTime+dur);
  g.gain.setValueAtTime(vol,audioCtx.currentTime);
  g.gain.exponentialRampToValueAtTime(.0001,audioCtx.currentTime+dur);
  o.start();o.stop(audioCtx.currentTime+dur);
}
function soundLaser(){playSweep(800,200,.1,.08,'sawtooth');}
function soundHit(){playSweep(300,80,.15,.12,'square');playTone(150,'sine',.1,.08);}
function soundExplode(big=false){
  playSweep(big?200:400,20,big?.5:.25,big?.25:.15,'sawtooth');
  if(big){playSweep(180,10,.4,.2,'square');}
}
function soundDamage(){playSweep(120,60,.2,.2,'square');playTone(80,'sine',.3,.1);}
function soundWave(){[0,.1,.2].forEach(d=>playTone(440+d*200,'sine',.15,.12,d));}
function soundCombo(){playTone(660+combo*80,'sine',.08,.1);}
function soundTeleport(){playSweep(200,800,.12,.1,'sine');playSweep(800,200,.12,.1,'sine');}
function startAmbient(){
  if(!audioCtx||muted)return;
  [55,82,110].forEach((f,i)=>{
    const o=audioCtx.createOscillator(),g=audioCtx.createGain();
    o.connect(g);g.connect(audioCtx.destination);
    o.type='sine';o.frequency.value=f;g.gain.value=.018+i*.006;o.start();
  });
}

// ── SCENE ─────────────────────────────────────────────────────────────
const canvas=document.getElementById('c');
const renderer=new THREE.WebGLRenderer({canvas,antialias:true});
renderer.setSize(innerWidth,innerHeight);renderer.setPixelRatio(Math.min(devicePixelRatio,2));
const scene=new THREE.Scene();scene.fog=new THREE.FogExp2(0x000008,.003);
const camera=new THREE.PerspectiveCamera(80,innerWidth/innerHeight,.1,1200);
camera.position.set(0,15,90);

const sv=[];for(let i=0;i<6000;i++)sv.push((Math.random()-.5)*1200,(Math.random()-.5)*1200,(Math.random()-.5)*1200);
const sg=new THREE.BufferGeometry();sg.setAttribute('position',new THREE.Float32BufferAttribute(sv,3));
scene.add(new THREE.Points(sg,new THREE.PointsMaterial({color:0xffffff,size:.35,transparent:true,opacity:.75})));
[[0x4338ca,.1],[0x0e7490,.07],[0x7c3aed,.05]].forEach(([col,op])=>{
  const g=new THREE.BufferGeometry(),v=[];
  for(let i=0;i<600;i++){const r=80+Math.random()*150,a=Math.random()*Math.PI*2;v.push(r*Math.cos(a),(Math.random()-.5)*60,r*Math.sin(a));}
  g.setAttribute('position',new THREE.Float32BufferAttribute(v,3));
  scene.add(new THREE.Points(g,new THREE.PointsMaterial({color:col,size:2,transparent:true,opacity:op})));
});
scene.add(new THREE.AmbientLight(0x111133,.9));
const sun=new THREE.DirectionalLight(0xffffff,.5);sun.position.set(60,80,40);scene.add(sun);

const planetMeshes=[],glowMeshes=[];
function buildPlanets(){
  clusters.forEach((c)=>{
    const m=new THREE.Mesh(new THREE.SphereGeometry(c.size,32,32),
      new THREE.MeshPhongMaterial({color:c.color,emissive:c.color,emissiveIntensity:.25,shininess:90}));
    m.position.set(...c.pos);m.userData={cluster:c};scene.add(m);planetMeshes.push(m);
    const g=new THREE.Mesh(new THREE.SphereGeometry(c.size*1.7,16,16),
      new THREE.MeshBasicMaterial({color:c.color,transparent:true,opacity:.05,side:THREE.BackSide}));
    g.position.set(...c.pos);scene.add(g);glowMeshes.push(g);
    if(c.treffer>70){
      const r=new THREE.Mesh(new THREE.RingGeometry(c.size*1.9,c.size*2.1,64),
        new THREE.MeshBasicMaterial({color:c.color,transparent:true,opacity:.15,side:THREE.DoubleSide}));
      r.position.set(...c.pos);r.rotation.x=Math.PI/2+(Math.random()-.5)*.6;scene.add(r);
    }
    const pl=new THREE.PointLight(c.color,.7,c.size*14);pl.position.set(...c.pos);scene.add(pl);
  });
}

// ── ENEMY AI ──────────────────────────────────────────────────────────
function behaviorAnomaly(e){
  const dist=camera.position.distanceTo(e.position);
  if(dist<60){
    const toPlayer=new THREE.Vector3().subVectors(camera.position,e.position).normalize();
    e.userData.vel.lerp(toPlayer.multiplyScalar(e.userData.speed),0.04);
  } else {
    if(!e.userData.wanderTarget||e.userData.wanderTimer<=0){
      e.userData.wanderTarget=new THREE.Vector3(
        e.position.x+(Math.random()-.5)*60,e.position.y+(Math.random()-.5)*20,e.position.z+(Math.random()-.5)*60);
      e.userData.wanderTimer=120+Math.random()*80;
    }
    e.userData.wanderTimer--;
    const toWander=new THREE.Vector3().subVectors(e.userData.wanderTarget,e.position).normalize();
    e.userData.vel.lerp(toWander.multiplyScalar(e.userData.speed*.5),0.02);
  }
  e.position.add(e.userData.vel);
}
function behaviorCollision(e){
  if(!e.userData.phase)e.userData.phase='flank';
  const toPlayer=new THREE.Vector3().subVectors(camera.position,e.position);
  const dist=toPlayer.length();
  if(e.userData.phase==='flank'&&dist<50)e.userData.phase='dive';
  if(e.userData.phase==='flank'){
    const fwd=new THREE.Vector3();camera.getWorldDirection(fwd);
    const flankerTarget=camera.position.clone().add(fwd.clone().cross(new THREE.Vector3(0,1,0)).multiplyScalar(40));
    flankerTarget.y+=10;
    const toFlank=new THREE.Vector3().subVectors(flankerTarget,e.position).normalize();
    e.userData.vel.lerp(toFlank.multiplyScalar(e.userData.speed*.8),0.03);
  } else {
    e.userData.vel.lerp(toPlayer.normalize().multiplyScalar(e.userData.speed*1.4),0.06);
  }
  e.position.add(e.userData.vel);
}
function behaviorDark(e){
  e.userData.teleportTimer--;
  const dist=camera.position.distanceTo(e.position);
  e.material.opacity=dist>40?0.1:0.85;
  if(e.userData.teleportTimer<=0){
    const fwd=new THREE.Vector3();camera.getWorldDirection(fwd);
    e.position.copy(camera.position).addScaledVector(fwd,-25);
    e.position.x+=(Math.random()-.5)*15;e.position.y+=(Math.random()-.5)*10;
    e.userData.teleportTimer=90+Math.random()*60;
    soundTeleport();
    flashEl.style.background='rgba(139,92,246,0.2)';flashEl.style.opacity='1';
    setTimeout(()=>flashEl.style.opacity='0',80);
  }
  const toPlayer=new THREE.Vector3().subVectors(camera.position,e.position).normalize();
  e.userData.vel.lerp(toPlayer.multiplyScalar(e.userData.speed),0.05);
  e.position.add(e.userData.vel);
}
function behaviorBoss(e){
  if(!e.userData.orbitCenter){
    const c=clusters[Math.floor(Math.random()*clusters.length)];
    e.userData.orbitCenter=new THREE.Vector3(...c.pos);
    e.userData.orbitAngle=Math.random()*Math.PI*2;
    e.userData.chargeTimer=180;e.userData.charging=false;
  }
  e.userData.chargeTimer--;
  if(e.userData.chargeTimer<=0&&!e.userData.charging){
    e.userData.charging=true;e.userData.chargeTimer=120;
    bossAlertEl.textContent='⚠ BOSS LAEDT AUF ⚠';bossAlertEl.style.opacity='1';
    setTimeout(()=>bossAlertEl.style.opacity='0',1500);
    playSweep(100,400,.3,.15,'square');
  }
  if(e.userData.charging){
    const toPlayer=new THREE.Vector3().subVectors(camera.position,e.position).normalize();
    e.userData.vel.lerp(toPlayer.multiplyScalar(e.userData.speed*2.5),0.08);
    if(e.userData.chargeTimer<=0){e.userData.charging=false;e.userData.chargeTimer=200+wave*20;}
  } else {
    e.userData.orbitAngle+=.008;
    const r=35,oc=e.userData.orbitCenter;
    const target=new THREE.Vector3(oc.x+r*Math.cos(e.userData.orbitAngle),oc.y+10*Math.sin(e.userData.orbitAngle*.3),oc.z+r*Math.sin(e.userData.orbitAngle));
    const toTarget=new THREE.Vector3().subVectors(target,e.position).normalize();
    e.userData.vel.lerp(toTarget.multiplyScalar(e.userData.speed),0.05);
  }
  e.position.add(e.userData.vel);
  if(!e.userData.shootTimer)e.userData.shootTimer=0;
  e.userData.shootTimer++;
  if(e.userData.shootTimer%(100-wave*5)===0)fireEnemyProjectile(e.position.clone(),0.8+wave*.1);
}

// ── ENEMY PROJECTILES ─────────────────────────────────────────────────
const enemyProjectiles=[];
function fireEnemyProjectile(from,speed){
  const p=new THREE.Mesh(new THREE.SphereGeometry(.4,6,6),new THREE.MeshBasicMaterial({color:0xff4400}));
  p.position.copy(from);
  const dir=new THREE.Vector3().subVectors(camera.position,from).normalize();
  dir.x+=(Math.random()-.5)*.3;dir.y+=(Math.random()-.5)*.3;dir.normalize();
  p.userData={dir,speed};scene.add(p);enemyProjectiles.push(p);
  playSweep(400,150,.12,.06,'square');
}

// ── ENEMIES ───────────────────────────────────────────────────────────
const enemies=[];
function spawnEnemy(type='anomaly',pos=null){
  let geo,mat,hp,pts,size,speed;
  if(type==='anomaly'){geo=new THREE.OctahedronGeometry(1.4,0);mat=new THREE.MeshPhongMaterial({color:0xef4444,emissive:0xef4444,emissiveIntensity:.8});hp=1;pts=100;size=1.4;speed=.05+wave*.012;}
  else if(type==='collision'){geo=new THREE.TetrahedronGeometry(2.2,0);mat=new THREE.MeshPhongMaterial({color:0xf97316,emissive:0xf97316,emissiveIntensity:.6});hp=2;pts=250;size=2.2;speed=.06+wave*.015;}
  else if(type==='dark'){geo=new THREE.BoxGeometry(1.8,1.8,1.8);mat=new THREE.MeshPhongMaterial({color:0x8b5cf6,emissive:0x8b5cf6,emissiveIntensity:.5,transparent:true,opacity:.85});hp=3;pts=400;size=1.8;speed=.09+wave*.018;}
  else if(type==='boss'){geo=new THREE.IcosahedronGeometry(6,1);mat=new THREE.MeshPhongMaterial({color:0xff2200,emissive:0xff2200,emissiveIntensity:.35,shininess:120});hp=15+wave*5;pts=3000;size=6;speed=.04+wave*.008;}
  const mesh=new THREE.Mesh(geo,mat);
  if(pos)mesh.position.copy(pos);
  else{const r=80+Math.random()*50,a=Math.random()*Math.PI*2,b=(Math.random()-.5)*.8;mesh.position.set(camera.position.x+r*Math.cos(a)*Math.cos(b),camera.position.y+r*Math.sin(b)*20,camera.position.z+r*Math.sin(a)*Math.cos(b));}
  mesh.userData={type,hp,maxHp:hp,pts,size,speed,vel:new THREE.Vector3((Math.random()-.5)*.02,(Math.random()-.5)*.02,(Math.random()-.5)*.02),spinX:(Math.random()-.5)*.07,spinY:(Math.random()-.5)*.08,teleportTimer:type==='dark'?80+Math.random()*40:999999,wanderTarget:null,wanderTimer:0};
  scene.add(mesh);enemies.push(mesh);enemyCountEl.textContent=enemies.length;
}

// ── PARTICLES ─────────────────────────────────────────────────────────
const particles=[];
function explode(pos,color=0xef4444,count=25,big=false){
  for(let i=0;i<count;i++){
    const g=new THREE.SphereGeometry(big?.35:.18,4,4);
    const m=new THREE.MeshBasicMaterial({color,transparent:true,opacity:1});
    const p=new THREE.Mesh(g,m);p.position.copy(pos);
    const d=new THREE.Vector3((Math.random()-.5)*2,(Math.random()-.5)*2,(Math.random()-.5)*2).normalize();
    p.userData={vel:d.multiplyScalar(big?Math.random()*2+.6:Math.random()*1.4+.2),life:1,decay:big?.011:.02};
    scene.add(p);particles.push(p);
  }
  const rg=new THREE.RingGeometry(.1,big?4:2,24);
  const rm=new THREE.MeshBasicMaterial({color,transparent:true,opacity:.9,side:THREE.DoubleSide});
  const ring=new THREE.Mesh(rg,rm);ring.position.copy(pos);ring.lookAt(camera.position);
  ring.userData={vel:new THREE.Vector3(),life:1,decay:.055,ring:true,scale:big?2:.9};
  scene.add(ring);particles.push(ring);
}

// ── LASERS ────────────────────────────────────────────────────────────
const lasers=[];
function fireLaser(){
  if(!gameStarted)return;shots++;soundLaser();
  const g=new THREE.CylinderGeometry(.04,.04,5,5);g.rotateX(Math.PI/2);
  const l=new THREE.Mesh(g,new THREE.MeshBasicMaterial({color:0x00ffff}));
  l.position.copy(camera.position);
  const dir=new THREE.Vector3();camera.getWorldDirection(dir);
  l.userData={dir,speed:5,life:80};l.lookAt(l.position.clone().add(dir));
  scene.add(l);lasers.push(l);
}

// ── CONTROLS ──────────────────────────────────────────────────────────
const keys={};
document.addEventListener('keydown',e=>{keys[e.code]=true;if(e.code==='Space'){e.preventDefault();fireLaser();}});
document.addEventListener('keyup',e=>keys[e.code]=false);
let mouseX=0,mouseY=0,locked=false;
canvas.addEventListener('click',()=>{if(!locked)canvas.requestPointerLock();else fireLaser();});
document.addEventListener('pointerlockchange',()=>locked=document.pointerLockElement===canvas);
document.addEventListener('mousemove',e=>{if(locked){mouseX-=e.movementX*.0022;mouseY-=e.movementY*.0022;mouseY=Math.max(-1.3,Math.min(1.3,mouseY));}});

// ── STATE + HUD ───────────────────────────────────────────────────────
let score=0,kills=0,combo=1,comboTimer=0,shots=0,hits=0;
let health=100,wave=1,waveKills=0,waveTarget=8,gameOver=false,gameStarted=false;
const scoreEl=document.getElementById('score');
const killsEl=document.getElementById('kills');
const comboEl=document.getElementById('combo');
const comboNumEl=document.getElementById('combo-num');
const precisionEl=document.getElementById('precision');
const evidenceEl=document.getElementById('evidence');
const enemyCountEl=document.getElementById('enemy-count');
const waveNumEl=document.getElementById('wave-num');
const healthInner=document.getElementById('health-inner');
const flashEl=document.getElementById('flash');
const bossAlertEl=document.getElementById('boss-alert');
const waveBanner=document.getElementById('wave-banner');
const infoPanelEl=document.getElementById('info-panel');
const panelNameEl=document.getElementById('panel-name');
const panelStatsEl=document.getElementById('panel-stats');
let closestCluster=null;

function showWaveBanner(txt){waveBanner.textContent=txt;waveBanner.style.opacity='1';setTimeout(()=>waveBanner.style.opacity='0',2200);}
function updateEvidence(){const b=Math.min(10,Math.floor(kills/3));evidenceEl.textContent='▓'.repeat(b)+'░'.repeat(10-b);evidenceEl.style.color=b>6?'#10b981':b>3?'#f59e0b':'#ef4444';}
function addScore(pts){score+=pts*combo;scoreEl.textContent=score.toLocaleString();scoreEl.classList.add('pop');setTimeout(()=>scoreEl.classList.remove('pop'),120);}
function bumpCombo(){combo=Math.min(combo+1,8);comboTimer=130;comboNumEl.textContent=combo;comboEl.style.opacity='1';soundCombo();}
function takeDamage(amt){health=Math.max(0,health-amt);healthInner.style.width=health+'%';soundDamage();flashEl.style.background='rgba(239,68,68,.35)';flashEl.style.opacity='1';setTimeout(()=>flashEl.style.opacity='0',100);if(health<=0&&!gameOver){gameOver=true;setTimeout(()=>alert('GAME OVER\nScore: '+score.toLocaleString()+'\nKills: '+kills),300);}}
function startWave(n){
  wave=n;waveKills=0;waveTarget=6+n*4;waveNumEl.textContent=n;soundWave();
  showWaveBanner('— WAVE '+n+' —');
  const total=Math.min(3+n*2,14);
  for(let i=0;i<total;i++){setTimeout(()=>{const r=Math.random();spawnEnemy(n>=4&&r<.08?'boss':r<.2?'dark':r<.45?'collision':'anomaly');},i*350);}
}

// Pre-load data so start button is ready when user clicks
loadClusters().then(loaded=>{
  clusters=loaded;
  document.getElementById('loading-hint').textContent=
    (loaded===DEMO_CLUSTERS)?'⚠ Demo-Modus (keine DB-Daten)':'✓ '+loaded.length+' Cluster geladen';
  document.getElementById('start-btn').disabled=false;
});

async function startGame(){
  document.getElementById('start-overlay').style.display='none';
  initAudio();startAmbient();buildPlanets();
  gameStarted=true;canvas.requestPointerLock();startWave(1);
}

// ── MAIN LOOP ─────────────────────────────────────────────────────────
let t=0,spawnTimer=0,autoFireTimer=0;
function animate(){
  requestAnimationFrame(animate);if(gameOver)return;t+=.008;
  camera.rotation.order='YXZ';camera.rotation.y=mouseX;camera.rotation.x=mouseY;
  const fwd=new THREE.Vector3(),rgt=new THREE.Vector3();
  camera.getWorldDirection(fwd);rgt.crossVectors(fwd,camera.up).normalize();
  const spd=(keys['ShiftLeft']||keys['ShiftRight'])?1.5:.75;
  if(keys['KeyW'])camera.position.addScaledVector(fwd,spd);
  if(keys['KeyS'])camera.position.addScaledVector(fwd,-spd);
  if(keys['KeyA'])camera.position.addScaledVector(rgt,-spd);
  if(keys['KeyD'])camera.position.addScaledVector(rgt,spd);
  if(keys['KeyQ'])camera.position.y+=spd*.5;
  if(keys['KeyE'])camera.position.y-=spd*.5;
  if(keys['Space']){autoFireTimer++;if(autoFireTimer%7===0)fireLaser();}else autoFireTimer=0;
  if(gameStarted){
    planetMeshes.forEach((m,i)=>{m.rotation.y+=.003;glowMeshes[i].material.opacity=.04+.025*Math.sin(t*1.5+i);});
    spawnTimer++;const interval=Math.max(45,150-wave*15);
    if(spawnTimer>interval){spawnTimer=0;const r=Math.random();spawnEnemy(r<.15?'dark':r<.4?'collision':'anomaly');if(enemies.length<4)spawnEnemy('anomaly');}
    for(let i=enemies.length-1;i>=0;i--){
      const e=enemies[i];e.rotation.x+=e.userData.spinX;e.rotation.y+=e.userData.spinY;
      if(e.userData.type==='anomaly')behaviorAnomaly(e);
      else if(e.userData.type==='collision')behaviorCollision(e);
      else if(e.userData.type==='dark')behaviorDark(e);
      else if(e.userData.type==='boss')behaviorBoss(e);
      if(e.userData.type==='anomaly')e.material.emissiveIntensity=.5+.5*Math.abs(Math.sin(t*5+i));
      if(e.position.distanceTo(camera.position)<(e.userData.size+2.5)){
        const dmg=e.userData.type==='boss'?18:e.userData.type==='dark'?9:e.userData.type==='collision'?6:4;
        takeDamage(dmg);explode(e.position,e.material.color.getHex(),8);
        scene.remove(e);enemies.splice(i,1);enemyCountEl.textContent=enemies.length;combo=1;comboEl.style.opacity='0';
      }
    }
    for(let i=enemyProjectiles.length-1;i>=0;i--){
      const p=enemyProjectiles[i];p.position.addScaledVector(p.userData.dir,p.userData.speed);
      if(p.position.distanceTo(camera.position)<2){takeDamage(8);explode(p.position,0xff4400,6);scene.remove(p);enemyProjectiles.splice(i,1);continue;}
      if(p.position.distanceTo(camera.position)>200){scene.remove(p);enemyProjectiles.splice(i,1);}
    }
    for(let i=lasers.length-1;i>=0;i--){
      const l=lasers[i];l.position.addScaledVector(l.userData.dir,l.userData.speed);l.userData.life--;
      if(l.userData.life<=0){scene.remove(l);lasers.splice(i,1);continue;}
      let removed=false;
      for(let j=enemies.length-1;j>=0;j--){
        if(removed)break;
        if(l.position.distanceTo(enemies[j].position)<(enemies[j].userData.size+1.5)){
          hits++;const e=enemies[j];e.userData.hp--;
          const origIntens=e.material.emissiveIntensity;
          e.material.emissiveIntensity=3;setTimeout(()=>{if(e.material)e.material.emissiveIntensity=origIntens;},80);
          explode(l.position,0x00ffff,5);
          if(e.userData.hp<=0){
            const big=e.userData.type==='boss';
            explode(e.position,e.material.color.getHex(),big?60:24,big);
            soundExplode(big);flashEl.style.background=big?'rgba(255,34,0,.4)':'rgba(6,182,212,.12)';
            flashEl.style.opacity='1';setTimeout(()=>flashEl.style.opacity='0',100);
            soundHit();addScore(e.userData.pts);kills++;waveKills++;killsEl.textContent=kills;
            bumpCombo();if(shots>0)precisionEl.textContent=Math.round(hits/shots*100)+'%';
            updateEvidence();
            if(big){for(let b=0;b<5;b++)spawnEnemy('anomaly');}
            scene.remove(e);enemies.splice(j,1);enemyCountEl.textContent=enemies.length;
            if(waveKills>=waveTarget)setTimeout(()=>startWave(wave+1),2500);
          } else soundHit();
          scene.remove(l);lasers.splice(i,1);removed=true;
        }
      }
    }
    if(comboTimer>0){comboTimer--;if(comboTimer===0){combo=1;comboEl.style.opacity='0';}}
    for(let i=particles.length-1;i>=0;i--){
      const p=particles[i];p.userData.life-=p.userData.decay;
      if(p.userData.life<=0){scene.remove(p);particles.splice(i,1);continue;}
      p.material.opacity=p.userData.life;
      if(p.userData.ring)p.scale.setScalar(1+(1-p.userData.life)*p.userData.scale*8);
      else{p.position.addScaledVector(p.userData.vel,1);p.userData.vel.multiplyScalar(.93);}
    }
    let near=null,nearD=Infinity;
    planetMeshes.forEach(m=>{const d=camera.position.distanceTo(m.position);if(d<nearD){nearD=d;near=m;}});
    if(near&&nearD<30){
      const c=near.userData.cluster;
      if(closestCluster!==c.label){closestCluster=c.label;panelNameEl.textContent='🪐 '+c.label;panelStatsEl.textContent=c.desc+' · '+c.treffer+' Treffer · Relevanz '+Math.floor(c.relevanz*100)+'%';}
      infoPanelEl.style.opacity=Math.max(0,1-nearD/30).toFixed(2);
    }else{infoPanelEl.style.opacity='0';closestCluster=null;}
  }
  renderer.render(scene,camera);
}
window.addEventListener('resize',()=>{camera.aspect=innerWidth/innerHeight;camera.updateProjectionMatrix();renderer.setSize(innerWidth,innerHeight);});
animate();
</script>
</body>
</html>
