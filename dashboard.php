<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
// Verificar que la sesión no lleve más de 8 horas
if (!empty($_SESSION['login_time']) && time() - $_SESSION['login_time'] > 28800) {
    session_destroy();
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
$admin = $_SESSION['admin'];

// Obtener nombre del restaurante
$stmt = db()->prepare("SELECT nombre FROM restaurantes WHERE id = ?");
$stmt->execute([$admin['restaurante_id']]);
$restaurante = $stmt->fetch();
$admin['restaurante_nombre'] = $restaurante['nombre'] ?? 'Mi Restaurante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>MexxicanMx <?= htmlspecialchars($admin['restaurante_nombre'] ?? 'Mi Restaurante') ?></title>
  <link rel="icon" href="favicon.ico">
  <style>
    /* ── Google Fonts ── */
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=DM+Sans:wght@300;400;500;700&display=swap');

    /* ── Variables globales ── */
    :root {
      --gold:       #e8c07d;
      --gold-dark:  #c9993a;
      --bg:         #0f0f0f;
      --bg2:        #1a1a1a;
      --bg3:        #242424;
      --border:     #2e2e2e;
      --text:       #f0ece4;
      --text-muted: #888;
      --green:      #4caf7a;
      --red:        #e05c5c;
      --blue:       #5c9ce0;
      --orange:     #e09a5c;
      --radius:     14px;
      --shadow:     0 8px 32px rgba(0,0,0,.45);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; scroll-behavior: smooth; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
    }

    h1, h2, h3 { font-family: 'Playfair Display', serif; }
    a { color: var(--gold); text-decoration: none; }

    /* ── Scrollbar ── */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--bg); }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

    /* ── Buttons ── */
    .btn {
      display: inline-flex; align-items: center; gap: .5rem;
      padding: .65rem 1.4rem; border-radius: 10px; border: none;
      font-family: 'DM Sans', sans-serif; font-weight: 500; font-size: .9rem;
      cursor: pointer; transition: all .2s; letter-spacing: .02em;
    }
    .btn-gold  { background: linear-gradient(135deg, var(--gold), var(--gold-dark)); color: #111; }
    .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text); }
    .btn-green { background: rgba(76,175,122,.15); border: 1px solid var(--green); color: var(--green); }
    .btn-red   { background: rgba(224,92,92,.15);  border: 1px solid var(--red);   color: var(--red); }
    .btn-blue  { background: rgba(92,156,224,.15); border: 1px solid var(--blue);  color: var(--blue); }
    .btn:hover  { opacity: .85; transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }

    /* ── Card ── */
    .card {
      background: var(--bg2); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 1.5rem;
      box-shadow: var(--shadow);
    }

    /* ── Badge ── */
    .badge { display: inline-block; padding: .2rem .65rem; border-radius: 99px; font-size: .75rem; font-weight: 500; letter-spacing: .04em; }
    .badge-pendiente      { background: rgba(224,154,92,.15); color: var(--orange); border: 1px solid var(--orange); }
    .badge-en_preparacion { background: rgba(92,156,224,.15); color: var(--blue);   border: 1px solid var(--blue); }
    .badge-listo          { background: rgba(76,175,122,.15); color: var(--green);  border: 1px solid var(--green); }
    .badge-entregado      { background: rgba(100,100,100,.15); color: #888;         border: 1px solid #444; }
    .badge-cancelado      { background: rgba(224,92,92,.15);  color: var(--red);    border: 1px solid var(--red); }

    /* ── Form ── */
    .form-control {
      width: 100%; padding: .8rem 1rem; background: var(--bg);
      border: 1px solid var(--border); border-radius: 10px;
      color: var(--text); font-size: .95rem; outline: none;
      font-family: 'DM Sans', sans-serif; transition: border-color .2s;
    }
    .form-control:focus { border-color: var(--gold); }
    .form-label { display: block; margin-bottom: .4rem; font-size: .8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; }

    /* ── Table ── */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th { background: var(--bg3); color: var(--text-muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; padding: .75rem 1rem; text-align: left; }
    td { padding: .85rem 1rem; border-bottom: 1px solid var(--border); font-size: .9rem; }
    tr:hover td { background: rgba(255,255,255,.02); }

    /* ── Stats ── */
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 1.2rem; }
    .stat-card { background: var(--bg2); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; }
    .stat-card .stat-value { font-family: 'Playfair Display', serif; font-size: 2.2rem; color: var(--gold); }
    .stat-card .stat-label { color: var(--text-muted); font-size: .85rem; margin-top: .25rem; }

    /* ── Toast ── */
    #toast-container { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: .6rem; }
    .toast {
      background: var(--bg2); border: 1px solid var(--border); border-radius: 12px;
      padding: 1rem 1.4rem; display: flex; align-items: center; gap: .75rem;
      box-shadow: 0 8px 32px rgba(0,0,0,.5);
      animation: slideIn .3s ease; min-width: 280px; max-width: 380px;
    }
    .toast.success { border-color: var(--green); }
    .toast.error   { border-color: var(--red); }
    .toast.info    { border-color: var(--blue); }
    @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes fadeOut { to { opacity: 0; transform: translateX(60%); } }

    /* ── Order card ── */
    .order-card { background: var(--bg2); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem; transition: border-color .3s; }
    .order-card.nuevo { border-color: var(--orange); animation: pulse 1.5s ease infinite; }
    @keyframes pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(224,154,92,.3); } 50% { box-shadow: 0 0 0 8px rgba(224,154,92,0); } }

    /* ── Filter buttons ── */
    .filter-btn { padding: .5rem 1.2rem; border-radius: 99px; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; font-size: .85rem; transition: all .2s; }
    .filter-btn.active, .filter-btn:hover { background: var(--gold); color: #111; border-color: var(--gold); }

    /* ── Admin layout ── */
    .admin-layout { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
    .sidebar {
      background: var(--bg2); border-right: 1px solid var(--border);
      padding: 1.5rem; position: sticky; top: 0; height: 100vh;
      overflow-y: auto; display: flex; flex-direction: column;
    }
    .sidebar-logo { font-family: 'Playfair Display', serif; color: var(--gold); font-size: 1.5rem; margin-bottom: 2rem; text-align: center; }
    .nav-item {
      display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem;
      border-radius: 10px; color: var(--text-muted); cursor: pointer;
      transition: all .2s; margin-bottom: .25rem; border: none;
      background: none; width: 100%; text-align: left; font-size: .95rem;
      font-family: 'DM Sans', sans-serif;
    }
    .nav-item:hover, .nav-item.active { background: rgba(232,192,125,.1); color: var(--gold); }
    .main-area { padding: 2rem; overflow-y: auto; }

    /* ── Sections ── */
    .page-section { display: none; }
    .page-section.active { display: block; }
    .orders-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px,1fr)); gap: 1rem; }
    .order-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: 1rem; }

    /* ── Estado dots ── */
    .estado-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: .4rem; }
    .dot-pendiente      { background: var(--orange); }
    .dot-en_preparacion { background: var(--blue); }
    .dot-listo          { background: var(--green); }
    .dot-entregado      { background: #666; }

    /* ── Live indicator ── */
    #live-indicator { display: flex; align-items: center; gap: .5rem; color: var(--green); font-size: .85rem; }
    .pulse-dot { width: 8px; height: 8px; background: var(--green); border-radius: 50%; animation: pulse 1.5s ease infinite; }

    /* ── Corte / print ── */
    .summary-row { display: flex; justify-content: space-between; padding: .5rem 0; border-bottom: 1px solid var(--border); }
    @media print {
      .admin-layout, .sidebar { display: none !important; }
    }

    /* ── Modals ── */
    .modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,.8);
      z-index: 400; display: none; align-items: center;
      justify-content: center; padding: 1rem;
    }
    .modal-overlay.show { display: flex; }

    /* ── Responsive ── */
    @media (max-width: 768px) {
      .admin-layout { grid-template-columns: 1fr; }
      .sidebar { height: auto; position: static; flex-direction: row; flex-wrap: wrap; gap: .5rem; padding: 1rem; }
      .stat-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 480px) {
      .stat-grid { grid-template-columns: 1fr; }
      .main-area { padding: 1rem; }
    }
  </style>
</head>
<body>

<div class="admin-layout">

  <!-- ═══ SIDEBAR ════════════════════════════════════════════ -->
  <aside class="sidebar">
    <div class="sidebar-logo" style="display:flex;align-items:center;justify-content:center;gap:.5rem;flex-wrap:wrap">
  <span id="logo-restaurante"></span>
  <span><?= htmlspecialchars($admin['restaurante_nombre'] ?? 'Mi Restaurante') ?></span>
  <button onclick="showModal('modal-editar-restaurante')" style="background:rgba(232,192,125,.15);border:1px solid rgba(232,192,125,.3);color:var(--gold);border-radius:6px;padding:.2rem .45rem;font-size:.7rem;cursor:pointer" title="Editar">✏️</button>
</div>
    <button class="nav-item active" onclick="showSection('pedidos',this)"> Pedidos en vivo</button>
    <button class="nav-item" onclick="showSection('empleados',this)"> Asistencia</button>
    <button class="nav-item" onclick="showSection('menu',this)"> Menú</button>
    <button class="nav-item" onclick="showSection('corte',this)"> Corte de caja</button>
    <button class="nav-item" onclick="window.location.href='checador.html'">
      Checador
      </button>

    <div style="margin-top:auto;padding-top:1.5rem;border-top:1px solid var(--border)">
      <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.5rem"><?= htmlspecialchars($admin['nombre']) ?></div>
      <a href="logout.php" class="btn btn-ghost" style="width:100%;justify-content:center;font-size:.85rem">Cerrar sesión</a>
    </div>
  </aside>

  <!-- ═══ MAIN ════════════════════════════════════════════════ -->
  <main class="main-area">

    <!-- HEADER -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem">
      <div>
        <h1 style="font-size:1.8rem" id="page-title">Pedidos en vivo</h1>
        <div id="live-indicator"><div class="pulse-dot"></div> Actualización automática cada 5s</div>
      </div>
      <div style="display:flex;gap:.75rem;align-items:center">
        <span id="fecha-display" style="color:var(--text-muted);font-size:.9rem"></span>
        <button class="btn btn-ghost" onclick="cargarPedidos()">🔄 Actualizar</button>
      </div>
    </div>

    <!-- ── SECCIÓN PEDIDOS ──────────────────────────────────── -->
    <section class="page-section active" id="section-pedidos">
      <div class="stat-grid" style="margin-bottom:2rem" id="stats-bar">
        <div class="stat-card"><div class="stat-value" id="stat-pendientes">0</div><div class="stat-label">⏳ Pendientes</div></div>
        <div class="stat-card"><div class="stat-value" id="stat-prep">0</div><div class="stat-label">👨‍🍳 En preparación</div></div>
        <div class="stat-card"><div class="stat-value" id="stat-listos">0</div><div class="stat-label">✅ Listos</div></div>
        <div class="stat-card"><div class="stat-value" id="stat-total-dia" style="font-size:1.5rem">$0</div><div class="stat-label">💰 Total del día</div></div>
      </div>

      <div style="display:flex;gap:.6rem;margin-bottom:1.5rem;flex-wrap:wrap" id="filter-estados">
        <button class="filter-btn active" data-estado="" onclick="filtrarEstado('',this)">Todos</button>
        <button class="filter-btn" data-estado="pendiente" onclick="filtrarEstado('pendiente',this)"><span class="estado-dot dot-pendiente"></span>Pendientes</button>
        <button class="filter-btn" data-estado="en_preparacion" onclick="filtrarEstado('en_preparacion',this)"><span class="estado-dot dot-en_preparacion"></span>En preparación</button>
        <button class="filter-btn" data-estado="listo" onclick="filtrarEstado('listo',this)"><span class="estado-dot dot-listo"></span>Listos</button>
        <button class="filter-btn" data-estado="entregado" onclick="filtrarEstado('entregado',this)">Entregados</button>
      </div>

      <div id="orders-grid" class="orders-grid">
        <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--text-muted)">Cargando pedidos...</div>
      </div>
    </section>

    <!-- ── SECCIÓN EMPLEADOS ────────────────────────────────── -->
    <section class="page-section" id="section-empleados">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
        <div style="display:flex;gap:.75rem;align-items:center">
          <input type="date" id="fecha-asistencia" class="form-control" style="width:auto" value="<?= date('Y-m-d') ?>">
          <button class="btn btn-gold" onclick="cargarAsistencia()">Ver asistencia</button>
        </div>
        <button class="btn btn-ghost" onclick="showModal('modal-empleado')">➕ Nuevo empleado</button>
      </div>

      <div class="card" style="margin-bottom:1.5rem">
        <div class="table-wrap">
          <table id="tabla-asistencia">
            <thead><tr><th>Empleado</th><th>Puesto</th><th>Entrada</th><th>Salida</th><th>Horas</th><th>H. Extra</th><th>Pago Total</th><th>Estado</th></tr></thead>
            <tbody id="asistencia-body"><tr><td colspan="8" style="text-align:center;color:var(--text-muted)">Selecciona una fecha</td></tr></tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <h3 style="margin-bottom:1.2rem">📊 Reporte semanal</h3>
        <div class="table-wrap">
          <table id="tabla-semanal">
            <thead><tr><th>Empleado</th><th>Puesto</th><th>Días trabajados</th><th>Total horas</th><th>H. Extra</th><th>Pago total</th></tr></thead>
            <tbody id="semanal-body"></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ── SECCIÓN MENÚ ─────────────────────────────────────── -->
    <section class="page-section" id="section-menu">
      <div style="display:flex;justify-content:flex-end;margin-bottom:1.5rem">
        <button class="btn btn-gold" onclick="showModal('modal-menu')">➕ Agregar platillo</button>
      </div>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Emoji</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Disponible</th><th>Acciones</th></tr></thead>
            <tbody id="menu-admin-body"></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ── SECCIÓN QRs ──────────────────────────────────────── -->
    <section class="page-section" id="section-qrs">
      <div class="card" style="margin-bottom:1.5rem">
        <h3 style="margin-bottom:1.5rem">📱 Generador de Códigos QR</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem">

          <!-- QR 1: Mesa -->
          <div style="background:var(--bg3);border-radius:16px;padding:1.5rem;text-align:center">
            <div style="font-size:2.5rem;margin-bottom:.75rem">🪑</div>
            <h4 style="color:var(--gold);margin-bottom:.5rem">QR Mesa (Interior)</h4>
            <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">El cliente escanea, se registra y ordena desde su mesa</p>
            <div id="qr-mesa-container" style="background:#fff;border-radius:12px;padding:1rem;margin-bottom:1rem;display:inline-block"></div>
            <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap">
              <input type="number" id="qr-mesa-num" placeholder="# Mesa" min="1" max="30" class="form-control" style="width:100px">
              <button class="btn btn-gold" onclick="generarQRMesa()">Generar</button>
              <button class="btn btn-ghost" onclick="imprimirQR('mesa')">🖨️</button>
            </div>
          </div>

          <!-- QR 2: Para llevar -->
          <div style="background:var(--bg3);border-radius:16px;padding:1.5rem;text-align:center">
            <div style="font-size:2.5rem;margin-bottom:.75rem">🥡</div>
            <h4 style="color:var(--gold);margin-bottom:.5rem">QR Para llevar (Exterior)</h4>
            <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">Afuera del restaurante — cliente ordena sin entrar</p>
            <div id="qr-llevar-container" style="background:#fff;border-radius:12px;padding:1rem;margin-bottom:1rem;display:inline-block"></div>
            <div style="display:flex;gap:.5rem;justify-content:center">
              <button class="btn btn-gold" onclick="generarQRLlevar()">Generar</button>
              <button class="btn btn-ghost" onclick="imprimirQR('llevar')">🖨️</button>
            </div>
          </div>

          <!-- QR 3: Empleados -->
          <!-- <div style="background:var(--bg3);border-radius:16px;padding:1.5rem;text-align:center">
            <div style="font-size:2.5rem;margin-bottom:.75rem">👤</div>
            <h4 style="color:var(--gold);margin-bottom:.5rem">QR Checador (Empleados)</h4>
            <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">Entrada/salida del personal — soporta horas extra</p>
            <div id="qr-emp-container" style="background:#fff;border-radius:12px;padding:1rem;margin-bottom:1rem;display:inline-block"></div>
            <div style="display:flex;gap:.5rem;justify-content:center">
              <button class="btn btn-gold" onclick="generarQREmp()">Generar</button>
              <button class="btn btn-ghost" onclick="imprimirQR('emp')">🖨️</button>
            </div>
          </div> -->
        </div>
      </div>
    </section>

    <!-- ── SECCIÓN CORTE ────────────────────────────────────── -->
    <section class="page-section" id="section-corte">
      <div style="display:flex;gap:.75rem;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap">
        <input type="date" id="fecha-corte" class="form-control" style="width:auto" value="<?= date('Y-m-d') ?>">
        <button class="btn btn-gold" onclick="cargarCorte()">Ver corte</button>
        <button class="btn btn-ghost" onclick="imprimirCorte()">🖨️ Imprimir corte</button>
      </div>

      <div id="corte-content">
        <div style="text-align:center;color:var(--text-muted);padding:3rem">Selecciona una fecha y genera el corte</div>
      </div>
    </section>

  </main><!-- /main-area -->
</div><!-- /admin-layout -->

<!-- ═══ MODAL NUEVO EMPLEADO ══════════════════════════════════ -->
<div class="modal-overlay" id="modal-empleado">
  <div class="card" style="max-width:480px;width:100%">
    <h3 style="margin-bottom:1.5rem">➕ Nuevo empleado</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
      <div><label class="form-label">Nombre</label><input id="emp-nombre" class="form-control" placeholder="Carlos"></div>
      <div><label class="form-label">Apellido</label><input id="emp-apellido" class="form-control" placeholder="Ramírez"></div>
      <div><label class="form-label">Puesto</label><input id="emp-puesto" class="form-control" placeholder="Mesero"></div>
      <div><label class="form-label">PIN (4-6 dígitos)</label><input id="emp-pin" class="form-control" placeholder="1234" maxlength="6"></div>
      <div><label class="form-label">Sueldo/hora ($MXN)</label><input id="emp-sueldo" class="form-control" type="number" value="80" min="0"></div>
      <div><label class="form-label">Mult. hora extra</label><input id="emp-mult" class="form-control" type="number" value="1.5" step=".1" min="1"></div>
    </div>
    <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem">
      <button class="btn btn-ghost" onclick="hideModal('modal-empleado')">Cancelar</button>
      <button class="btn btn-gold" onclick="crearEmpleado()">Guardar empleado</button>
    </div>
  </div>
</div>

<!-- ═══ MODAL NUEVO PLATILLO ══════════════════════════════════ -->
<div class="modal-overlay" id="modal-menu">
  <div class="card" style="max-width:480px;width:100%">
    <h3 style="margin-bottom:1.5rem">🍽️ Nuevo platillo</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
      <div style="grid-column:1/-1"><label class="form-label">Nombre</label><input id="mi-nombre" class="form-control" placeholder="Tacos de birria"></div>
      <div style="grid-column:1/-1"><label class="form-label">Descripción</label><input id="mi-desc" class="form-control" placeholder="Con consomé incluido"></div>
      <div><label class="form-label">Precio ($MXN)</label><input id="mi-precio" class="form-control" type="number" min="0" value="0"></div>
      <div><label class="form-label">Emoji</label><input id="mi-emoji" class="form-control" placeholder="🌮" maxlength="4"></div>
      <div style="grid-column:1/-1">
        <label class="form-label">Foto del platillo (opcional)</label>
        <input type="file" id="mi-imagen" accept="image/*" class="form-control" style="padding:.5rem">
        <div id="mi-imagen-preview" style="margin-top:.5rem;display:none">
          <img id="mi-imagen-img" style="width:100%;max-height:160px;object-fit:cover;border-radius:10px;border:1px solid var(--border)">
        </div>
      </div>
      <div><label class="form-label">Categoría</label>
        <select id="mi-cat" class="form-control">
          <option value="comida">🍽️ Comida</option>
          <option value="bebida">🥤 Bebida</option>
          <option value="postre">🍮 Postre</option>
          <option value="extra">➕ Extra</option>
        </select>
      </div>
    </div>
    <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem">
      <button class="btn btn-ghost" onclick="hideModal('modal-menu')">Cancelar</button>
      <button class="btn btn-gold" onclick="crearPlatillo()">Guardar platillo</button>
    </div>
  </div>
</div>
<!-- MODAL EDITAR RESTAURANTE -->
<div class="modal-overlay" id="modal-editar-restaurante">
  <div class="card" style="max-width:480px;width:100%;max-height:90vh;overflow-y:auto">
    <h3 style="margin-bottom:1.5rem"> Personalizar restaurante</h3>

    <!-- Logo imagen -->
    <!-- <div style="margin-bottom:1.5rem">
      <label class="form-label">Logo del restaurante (PNG, JPG)</label>
      <div style="display:flex;gap:1rem;align-items:center">
        <div id="logo-img-preview" style="width:64px;height:64px;border-radius:12px;border:2px solid var(--border);background:var(--bg3);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
          <span style="font-size:1.8rem">🍽️/</span>
        </div>
        <div style="flex:1">
          <input type="file" id="logo-img-file" accept="image/*" class="form-control" style="padding:.5rem">
          <div style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">Se mostrará en el sidebar del dashboard</div>
        </div>
      </div>
    </div> -->

    <!-- Imagen de fondo del menú -->
    <div style="margin-bottom:1.5rem">
      <label class="form-label">Imagen de fondo del menú (banner)</label>
      <div id="hero-preview-box" style="width:100%;height:100px;border-radius:10px;border:2px solid var(--border);background:var(--bg3);background-size:cover;background-position:center;margin-bottom:.5rem;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:.85rem">
        Sin imagen
      </div>
      <input type="file" id="hero-img-file" accept="image/*" class="form-control" style="padding:.5rem">
      <div style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">Aparece como fondo del header en el menú del cliente</div>
    </div>

    <!-- Slogan -->
    <div style="margin-bottom:1.5rem">
      <label class="form-label">Slogan (texto debajo del nombre)</label>
      <input id="edit-slogan" class="form-control" placeholder="Bienvenido · Ordena desde tu mesa">
    </div>

    <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem">
      <button class="btn btn-ghost" onclick="hideModal('modal-editar-restaurante')">Cancelar</button>
      <button class="btn btn-gold" onclick="guardarPersonalizacion()">💾 Guardar</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<!-- QR library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
const BASE = '/tienda';
const fmt = n => new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'}).format(n);
let todosLosPedidos = [];
let estadoFiltro = '';

function toast(msg,type='info'){
  let c=document.getElementById('toast-container');
  const icons={success:'✅',error:'❌',info:'ℹ️'};
  const t=document.createElement('div');t.className=`toast ${type}`;
  t.innerHTML=`<span>${icons[type]}</span><span>${msg}</span>`;c.appendChild(t);
  setTimeout(()=>{t.style.animation='fadeOut .4s forwards';setTimeout(()=>t.remove(),400);},3500);
}

function showSection(name,btn){
  document.querySelectorAll('.page-section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(b=>b.classList.remove('active'));
  document.getElementById('section-'+name).classList.add('active');
  btn.classList.add('active');
  const titles={pedidos:'📋 Pedidos en vivo',empleados:'👥 Asistencia del personal',menu:'🍲 Gestión de menú',qrs:'📱 Códigos QR',corte:'💰 Corte de caja'};
  document.getElementById('page-title').textContent=titles[name]||name;
  if(name==='empleados'){cargarAsistencia();cargarReporteSemanal();}
  if(name==='menu')cargarMenuAdmin();
}

function showModal(id){document.getElementById(id).classList.add('show');}
function hideModal(id){document.getElementById(id).classList.remove('show');}

// ── Fecha ────────────────────────────────────────────────────
document.getElementById('fecha-display').textContent = new Date().toLocaleDateString('es-MX',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

// ── PEDIDOS ──────────────────────────────────────────────────
let prevCount = 0;
async function cargarPedidos(){
  try{
    const r=await fetch(`${BASE}/pedidos.php?accion=todos_pedidos&fecha=${(()=>{const d=new Date();return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')})()}`,{credentials:'include'});
    const res=await r.json();
    if(!res.ok){
      document.getElementById('orders-grid').innerHTML =
        '<div style="grid-column:1/-1;text-align:center;padding:3rem;color:#e05c5c">'
        + '❌ ' + (res.mensaje || 'Error de sesión')
        + ' — <a href="login.php" style="color:#e8c07d">Volver a iniciar sesión</a>'
        + '</div>';
      return; 
    }
    todosLosPedidos=res.data;

    // Notificación sonora si hay pedidos nuevos
    if(res.data.length>prevCount && prevCount>0){
      new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAA...').play().catch(()=>{});
      toast('🔔 ¡Nuevo pedido recibido!','info');
    }
    prevCount=res.data.length;

    actualizarStats(res.data);
    renderPedidos(res.data,estadoFiltro);
  }catch(e){console.error(e);}
}

function actualizarStats(pedidos){
  document.getElementById('stat-pendientes').textContent=pedidos.filter(p=>p.estado==='pendiente').length;
  document.getElementById('stat-prep').textContent=pedidos.filter(p=>p.estado==='en_preparacion').length;
  document.getElementById('stat-listos').textContent=pedidos.filter(p=>p.estado==='listo').length;
  const total=pedidos.filter(p=>p.estado!=='cancelado').reduce((s,p)=>s+parseFloat(p.total),0);
  document.getElementById('stat-total-dia').textContent=fmt(total);
}

function filtrarEstado(estado,btn){
  estadoFiltro=estado;
  document.querySelectorAll('#filter-estados .filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  renderPedidos(todosLosPedidos,estado);
}

const estadoLabels={pendiente:'⏳ Pendiente',en_preparacion:'👨‍🍳 En preparación',listo:'✅ Listo',entregado:'🎉 Entregado',cancelado:'❌ Cancelado'};
const siguienteEstado={pendiente:'en_preparacion',en_preparacion:'listo',listo:'entregado'};

function renderPedidos(pedidos,filtro){
  const grid=document.getElementById('orders-grid');
  const filtered=filtro?pedidos.filter(p=>p.estado===filtro):pedidos.filter(p=>p.estado!=='entregado'&&p.estado!=='cancelado');
  if(!filtered.length){grid.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--text-muted)">No hay pedidos con este estado</div>';return;}
  grid.innerHTML=filtered.map(p=>`
    <div class="order-card ${p.estado==='pendiente'?'nuevo':''}" id="order-${p.id}">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem">
        <div>
          <div style="font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--gold)">
            #${p.numero_orden}
            <span style="font-size:.9rem;color:var(--text-muted);font-family:'DM Sans',sans-serif;margin-left:.4rem">${p.tipo==='mesa'?`Mesa ${p.mesa_numero}`:'Para llevar'}</span>
          </div>
          <div style="font-size:.9rem;color:var(--text-muted)">${p.cliente} · ${new Date(p.creado_en).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'})}</div>
        </div>
        <span class="badge badge-${p.estado}">${estadoLabels[p.estado]}</span>
      </div>
      <div style="background:var(--bg3);border-radius:8px;padding:.75rem;margin-bottom:.75rem;white-space:pre-line;font-size:.875rem;line-height:1.6">${p.items_texto||''}</div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
        <span style="color:var(--text-muted);font-size:.85rem">${p.total_items} ítems</span>
        <span style="font-weight:700;color:var(--gold)">${fmt(p.total)}</span>
      </div>
      <div class="order-actions">
        ${siguienteEstado[p.estado]?`<button class="btn btn-green" onclick="cambiarEstado(${p.id},'${siguienteEstado[p.estado]}')">→ ${estadoLabels[siguienteEstado[p.estado]]}</button>`:''}
        ${p.estado!=='cancelado'&&p.estado!=='entregado'?`<button class="btn btn-red" onclick="cambiarEstado(${p.id},'cancelado')">Cancelar</button>`:''}
      </div>
    </div>`).join('');
}

async function cambiarEstado(id,estado){
  try{
    const r=await fetch(`${BASE}/pedidos.php?accion=actualizar_estado`,{method:'PUT',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({pedido_id:id,estado})});
    const res=await r.json();
    if(!res.ok){toast(res.mensaje,'error');return;}
    toast('Estado actualizado ✓','success');
    cargarPedidos();
  }catch{toast('Error','error');}
}

// ── ASISTENCIA ───────────────────────────────────────────────
async function cargarAsistencia(){
  const fecha=document.getElementById('fecha-asistencia').value;
  const r=await fetch(`${BASE}/empleados.php?accion=asistencia&fecha=${fecha}`);
  const res=await r.json();
  const tbody=document.getElementById('asistencia-body');
  if(!res.data||!res.data.length){tbody.innerHTML='<tr><td colspan="8" style="text-align:center;color:var(--text-muted)">Sin registros</td></tr>';return;}
  tbody.innerHTML=res.data.map(e=>`
    <tr>
      <td style="font-weight:500">${e.nombre} ${e.apellido}</td>
      <td>${e.puesto}</td>
      <td style="color:var(--green)">${e.hora_entrada?new Date(e.hora_entrada).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'}):'—'}</td>
      <td style="color:var(--red)">${e.hora_salida?new Date(e.hora_salida).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'}):'—'}</td>
      <td>${e.horas_trabajadas||'—'}</td>
      <td style="color:${e.horas_extra>0?'var(--gold)':'var(--text-muted)'}">${e.horas_extra||0}</td>
      <td style="font-weight:700;color:var(--gold)">${e.pago_total?fmt(e.pago_total):'—'}</td>
      <td><span class="badge ${e.hora_salida?'badge-entregado':'badge-en_preparacion'}">${e.hora_salida?'Completo':'En turno'}</span></td>
    </tr>`).join('');
}

async function cargarReporteSemanal(){
  const r=await fetch(`${BASE}/empleados.php?accion=reporte_semana`);
  const res=await r.json();
  if(!res.data)return;
  document.getElementById('semanal-body').innerHTML=res.data.map(e=>`
    <tr>
      <td style="font-weight:500">${e.nombre} ${e.apellido}</td>
      <td>${e.puesto}</td>
      <td>${e.dias_trabajados||0}</td>
      <td>${parseFloat(e.total_horas||0).toFixed(1)} h</td>
      <td style="color:var(--gold)">${parseFloat(e.total_extra||0).toFixed(1)} h</td>
      <td style="font-weight:700;color:var(--gold)">${fmt(e.total_pago||0)}</td>
    </tr>`).join('');
}

async function crearEmpleado(){
  const data={nombre:document.getElementById('emp-nombre').value,apellido:document.getElementById('emp-apellido').value,puesto:document.getElementById('emp-puesto').value,pin:document.getElementById('emp-pin').value,sueldo_hora:document.getElementById('emp-sueldo').value,hora_extra_mult:document.getElementById('emp-mult').value};
  const r=await fetch(`${BASE}/empleados.php?accion=crear`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
  const res=await r.json();
  if(!res.ok){toast(res.mensaje,'error');return;}
  toast('Empleado creado ✓ Token QR: '+res.data.token_qr,'success');
  hideModal('modal-empleado');cargarAsistencia();
}

// ── MENÚ ADMIN ───────────────────────────────────────────────
async function cargarMenuAdmin(){
  const r=await fetch(`${BASE}/menu.php?accion=lista`);
  const res=await r.json();
  if(!res.data)return;
  const cats={comida:'🍽️',bebida:'🥤',postre:'🍮',extra:'➕'};
  document.getElementById('menu-admin-body').innerHTML=res.data.map(m=>`
    <tr>
      <td style="font-size:1.5rem">${m.emoji}</td>
      <td style="font-weight:500">${m.nombre}<div style="font-size:.8rem;color:var(--text-muted)">${m.descripcion||''}</div></td>
      <td>${cats[m.categoria]||''} ${m.categoria}</td>
      <td style="font-weight:700;color:var(--gold)">${fmt(m.precio)}</td>
      <td><span class="badge ${m.disponible?'badge-listo':'badge-cancelado'}">${m.disponible?'Activo':'Inactivo'}</span></td>
      <td>
        <button class="btn btn-ghost" style="padding:.3rem .75rem;font-size:.8rem" onclick="toggleDisponible(${m.id},${m.disponible})">${m.disponible?'Desactivar':'Activar'}</button>
      </td>
    </tr>`).join('');
}

async function crearPlatillo(){
  const nombre  = document.getElementById('mi-nombre').value;
  const archivo = document.getElementById('mi-imagen').files[0];
  let imagenUrl = null;

  if (archivo) {
    const fd = new FormData();
    fd.append('imagen', archivo);
    const ru = await fetch(`${BASE}/menu.php?accion=subir_imagen`, { method:'POST', body: fd });
    const ju = await ru.json();
    if (ju.ok) imagenUrl = ju.data.url;
  }

  const data = {
    nombre,
    descripcion: document.getElementById('mi-desc').value,
    precio:      document.getElementById('mi-precio').value,
    emoji:       document.getElementById('mi-emoji').value,
    categoria:   document.getElementById('mi-cat').value,
    imagen:      imagenUrl
  };
  const r = await fetch(`${BASE}/menu.php?accion=crear`, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body:JSON.stringify(data) });
  const res = await r.json();
  if (!res.ok){ toast(res.mensaje,'error'); return; }
  toast('Platillo creado ✓','success');
  hideModal('modal-menu');
  document.getElementById('mi-imagen').value = '';
  document.getElementById('mi-imagen-preview').style.display = 'none';
  cargarMenuAdmin();
}

async function toggleDisponible(id,actual){
  await fetch(`${BASE}/menu.php?accion=toggle`,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({id,disponible:!actual})});
  cargarMenuAdmin();
}

// ── QR GENERATOR ─────────────────────────────────────────────
const APP_URL=window.location.origin+window.location.pathname.replace(/\/[^\/]*$/, '');
function generarQR(containerId,url){
  const c=document.getElementById(containerId);c.innerHTML='';
  new QRCode(c,{text:url,width:180,height:180,colorDark:'#000',colorLight:'#fff'});
}
function generarQRMesa(){
  const n=document.getElementById('qr-mesa-num').value||'1';
  generarQR('qr-mesa-container',`${APP_URL}/order.html?tipo=mesa&mesa=${n}`);
}
function generarQRLlevar(){generarQR('qr-llevar-container',`${APP_URL}/order.html?tipo=para_llevar`);}
function generarQREmp(){generarQR('qr-emp-container',`${APP_URL}/checador.html`);}
function imprimirQR(tipo){window.print();}

// ── CORTE ────────────────────────────────────────────────────
async function cargarCorte(){
  const fecha=document.getElementById('fecha-corte').value;
  const r=await fetch(`${BASE}/pedidos.php?accion=corte_caja&fecha=${fecha}`);
  const res=await r.json();
  if(!res.ok||!res.data){toast(res.mensaje,'error');return;}
  const {resumen,pedidos,top}=res.data;
  document.getElementById('corte-content').innerHTML=`
    <div class="stat-grid" style="margin-bottom:2rem">
      <div class="stat-card"><div class="stat-value">${resumen.total_pedidos||0}</div><div class="stat-label">Total pedidos</div></div>
      <div class="stat-card"><div class="stat-value" style="font-size:1.4rem">${fmt(resumen.total_mesa||0)}</div><div class="stat-label">💰 Ventas en mesa</div></div>
      <div class="stat-card"><div class="stat-value" style="font-size:1.4rem">${fmt(resumen.total_llevar||0)}</div><div class="stat-label">🥡 Para llevar</div></div>
      <div class="stat-card" style="border-color:var(--gold)"><div class="stat-value" style="font-size:1.8rem">${fmt(resumen.gran_total||0)}</div><div class="stat-label">🏆 GRAN TOTAL</div></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
      <div class="card">
        <h3 style="margin-bottom:1rem">📋 Detalle de pedidos — ${new Date(fecha+'T12:00:00').toLocaleDateString('es-MX',{day:'numeric',month:'long',year:'numeric'})}</h3>
        ${pedidos.map(p=>`
          <div class="summary-row">
            <div><span style="color:var(--gold);font-weight:700">#${p.numero_orden}</span> <span style="font-size:.85rem;color:var(--text-muted)">${p.cliente} · ${p.tipo==='mesa'?`Mesa ${p.mesa_numero}`:'Para llevar'}</span><br><span style="font-size:.8rem;color:var(--text-muted)">${p.items}</span></div>
            <div style="font-weight:700;color:var(--gold);white-space:nowrap">${fmt(p.total)}</div>
          </div>`).join('')}
        <div style="display:flex;justify-content:flex-end;margin-top:1rem;font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--gold)">Total: ${fmt(resumen.gran_total||0)}</div>
      </div>
      <div class="card">
        <h3 style="margin-bottom:1rem">🏆 Top platillos del día</h3>
        ${top.map((t,i)=>`
          <div class="summary-row">
            <div>${['🥇','🥈','🥉','4️⃣','5️⃣'][i]} ${t.emoji} ${t.nombre}</div>
            <div style="text-align:right"><span style="color:var(--gold);font-weight:700">${t.vendidos} uds</span><br><span style="font-size:.8rem;color:var(--text-muted)">${fmt(t.ingresos)}</span></div>
          </div>`).join('')}
      </div>
    </div>`;
}

function imprimirCorte(){
  const corteHTML=document.getElementById('corte-content').innerHTML;
  const win=window.open('','_blank');
  win.document.write(`<!DOCTYPE html><html><head><title>Corte — <?= addslashes(htmlspecialchars($admin['restaurante_nombre'] ?? 'Mi Restaurante')) ?></title>
  <style>body{font-family:Arial,sans-serif;padding:2rem;color:#000}h3{margin-bottom:1rem}.summary-row{display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid #ddd}.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem}.stat-card{border:1px solid #ddd;padding:1rem;border-radius:8px}.stat-value{font-size:1.5rem;font-weight:bold}.stat-label{font-size:.8rem;color:#666}.card{border:1px solid #ddd;padding:1.5rem;border-radius:8px;margin-bottom:1rem}</style>
  </head><body>${corteHTML}</body></html>`);
  win.document.close();win.print();
}

// ── Auto refresh ─────────────────────────────────────────────
cargarPedidos();
setInterval(cargarPedidos,15000);

document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('mi-imagen').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('mi-imagen-img').src = e.target.result;
      document.getElementById('mi-imagen-preview').style.display = '';
    };
    reader.readAsDataURL(file);
  });
});

// ── PERSONALIZAR RESTAURANTE ─────────────────────────────────
const logoFileEl = document.getElementById('logo-img-file');
if (logoFileEl) {
  logoFileEl.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      const preview = document.getElementById('logo-img-preview');
      if (preview) preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover">`;
      window._logoImgData = e.target.result;
    };
    reader.readAsDataURL(file);
  });
}

document.getElementById('hero-img-file').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const box = document.getElementById('hero-preview-box');
    box.style.backgroundImage = `url(${e.target.result})`;
    box.textContent = '';
    window._heroBgData = e.target.result;
  };
  reader.readAsDataURL(file);
});

function guardarPersonalizacion() {
  if (window._logoImgData) {
    localStorage.setItem(KEY_LOGO, window._logoImgData);
    aplicarLogoImg(window._logoImgData);
  }
  if (window._heroBgData) {
    localStorage.setItem(KEY_HERO, window._heroBgData);
    localStorage.setItem('ros_hero_bg', window._heroBgData);
  }
  const slogan = document.getElementById('edit-slogan').value;
  if (slogan) localStorage.setItem(KEY_SLOGAN, slogan);

  hideModal('modal-editar-restaurante');
  toast('✅ Cambios guardados', 'success');
}

function aplicarLogoImg(src) {
  const el = document.getElementById('logo-restaurante');
  if (!el) return;
  el.innerHTML = `<img src="${src}" style="width:36px;height:36px;border-radius:8px;object-fit:cover;vertical-align:middle">`;
}

// Clave única por restaurante
const RID = '<?= $admin["restaurante_id"] ?>';
const KEY_LOGO = 'ros_logo_' + RID;
const KEY_HERO = 'ros_hero_' + RID;
const KEY_SLOGAN = 'ros_slogan_' + RID;

// Aplicar al cargar la página
(function() {
  const logoImg = localStorage.getItem(KEY_LOGO);
  if (logoImg) aplicarLogoImg(logoImg);

  const heroBg = localStorage.getItem(KEY_HERO);
  if (heroBg) {
    const box = document.getElementById('hero-preview-box');
    if (box) { box.style.backgroundImage = `url(${heroBg})`; box.textContent = ''; }
  }
  const slogan = localStorage.getItem(KEY_SLOGAN);
  if (slogan) {
    const el = document.getElementById('edit-slogan');
    if (el) el.value = slogan;
  }
})();
</script>
</body>
</html>