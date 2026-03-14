// ============================================================
//  src/ts/app.ts — Lógica TypeScript del cliente
//  Compilar: tsc --target ES2020 --outDir ../public/js src/ts/app.ts
// ============================================================

// ── Tipos ───────────────────────────────────────────────────
interface MenuItem {
  id: number;
  nombre: string;
  descripcion: string;
  precio: number;
  categoria: 'comida' | 'bebida' | 'postre' | 'extra';
  emoji: string;
  disponible: boolean;
}

interface CartItem {
  item: MenuItem;
  cantidad: number;
}

interface Pedido {
  id: number;
  numero_orden: number;
  tipo: 'mesa' | 'para_llevar';
  mesa_numero?: number;
  estado: 'pendiente' | 'en_preparacion' | 'listo' | 'entregado' | 'cancelado';
  total: number;
  items: Array<{ nombre: string; cantidad: number; emoji: string; subtotal: number; }>;
  creado_en: string;
}

interface ApiResponse<T> {
  ok: boolean;
  data: T;
  mensaje: string;
}

// ── Estado global ────────────────────────────────────────────
const State = {
  token:   getCookie('cliente_token') || '',
  usuario: JSON.parse(localStorage.getItem('fogon_usuario') || 'null') as { nombre: string; tipo: string; mesa: number } | null,
  carrito: [] as CartItem[],
  menu:    [] as MenuItem[],
  pedidos: [] as Pedido[],
};

// ── Utilidades ───────────────────────────────────────────────
function getCookie(name: string): string {
  const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
  return match ? decodeURIComponent(match[2]) : '';
}

function setCookie(name: string, value: string, days = 1): void {
  const exp = new Date(Date.now() + days * 86400000).toUTCString();
  document.cookie = `${name}=${encodeURIComponent(value)};expires=${exp};path=/;SameSite=Lax`;
}

async function apiFetch<T>(url: string, options?: RequestInit): Promise<ApiResponse<T>> {
  const response = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  });
  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  return response.json();
}

function toast(msg: string, type: 'success' | 'error' | 'info' = 'info'): void {
  const container = document.getElementById('toast-container') || (() => {
    const el = document.createElement('div');
    el.id = 'toast-container';
    document.body.appendChild(el);
    return el;
  })();

  const icons: Record<string, string> = { success: '✅', error: '❌', info: 'ℹ️' };
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<span>${icons[type]}</span><span>${msg}</span>`;
  container.appendChild(t);
  setTimeout(() => { t.style.animation = 'fadeOut .4s forwards'; setTimeout(() => t.remove(), 400); }, 3500);
}

function formatMXN(amount: number): string {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
}

// ── Carrito ──────────────────────────────────────────────────
const Cart = {
  add(item: MenuItem): void {
    const existing = State.carrito.find(c => c.item.id === item.id);
    if (existing) {
      existing.cantidad++;
    } else {
      State.carrito.push({ item, cantidad: 1 });
    }
    Cart.render();
    toast(`${item.emoji} ${item.nombre} añadido`, 'success');
  },

  remove(itemId: number): void {
    const idx = State.carrito.findIndex(c => c.item.id === itemId);
    if (idx === -1) return;
    if (State.carrito[idx].cantidad > 1) {
      State.carrito[idx].cantidad--;
    } else {
      State.carrito.splice(idx, 1);
    }
    Cart.render();
  },

  total(): number {
    return State.carrito.reduce((sum, c) => sum + c.item.precio * c.cantidad, 0);
  },

  count(): number {
    return State.carrito.reduce((sum, c) => sum + c.cantidad, 0);
  },

  render(): void {
    const cartList  = document.getElementById('cart-list');
    const cartTotal = document.getElementById('cart-total');
    const cartBadge = document.getElementById('cart-badge');

    if (cartBadge) cartBadge.textContent = String(Cart.count());
    if (cartTotal) cartTotal.textContent = formatMXN(Cart.total());
    if (!cartList)  return;

    if (State.carrito.length === 0) {
      cartList.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:2rem">Tu carrito está vacío</p>';
      return;
    }

    cartList.innerHTML = State.carrito.map(c => `
      <div class="cart-row" style="display:flex;align-items:center;gap:.75rem;padding:.85rem 0;border-bottom:1px solid var(--border)">
        <span style="font-size:1.5rem">${c.item.emoji}</span>
        <div style="flex:1">
          <div style="font-weight:500">${c.item.nombre}</div>
          <div style="color:var(--gold);font-size:.9rem">${formatMXN(c.item.precio)} c/u</div>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem">
          <button onclick="Cart.remove(${c.item.id})" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);width:28px;height:28px;border-radius:6px;cursor:pointer;font-size:1rem">−</button>
          <span style="min-width:20px;text-align:center;font-weight:700">${c.cantidad}</span>
          <button onclick="Cart.add(${JSON.stringify(c.item).replace(/"/g, '&quot;')})" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);width:28px;height:28px;border-radius:6px;cursor:pointer;font-size:1rem">+</button>
        </div>
        <div style="min-width:70px;text-align:right;font-weight:600">${formatMXN(c.item.precio * c.cantidad)}</div>
      </div>
    `).join('');
  },

  async enviar(): Promise<void> {
    if (State.carrito.length === 0) { toast('Tu carrito está vacío', 'error'); return; }
    if (!State.token) { toast('Inicia sesión primero', 'error'); return; }

    const notas = (document.getElementById('notas-input') as HTMLTextAreaElement)?.value || '';
    const items = State.carrito.map(c => ({ menu_id: c.item.id, cantidad: c.cantidad }));

    try {
      const res = await apiFetch<Pedido>(`/restaurante/api/pedidos.php?accion=crear_pedido&token=${State.token}`, {
        method: 'POST',
        body: JSON.stringify({ items, notas }),
      });

      if (!res.ok) { toast(res.mensaje, 'error'); return; }

      toast(`🎉 ${res.mensaje}`, 'success');
      State.carrito = [];
      Cart.render();

      // Cerrar carrito y mostrar estado
      document.getElementById('cart-sidebar')?.classList.remove('open');
      PedidoStatus.cargar();

    } catch (e) {
      toast('Error al enviar el pedido', 'error');
    }
  }
};

// ── Menú ─────────────────────────────────────────────────────
const MenuUI = {
  async cargar(): Promise<void> {
    try {
      const res = await apiFetch<MenuItem[]>('/restaurante/api/menu.php?accion=lista');
      if (!res.ok) return;
      State.menu = res.data;
      MenuUI.render(res.data);
    } catch {
      toast('No se pudo cargar el menú', 'error');
    }
  },

  render(items: MenuItem[], filtro: string = 'todos'): void {
    const grid = document.getElementById('menu-grid');
    if (!grid) return;

    const filtered = filtro === 'todos' ? items : items.filter(i => i.categoria === filtro);

    grid.innerHTML = filtered.map(item => {
      const enCarrito = State.carrito.find(c => c.item.id === item.id);
      return `
        <div class="menu-item ${enCarrito ? 'selected' : ''}" onclick="Cart.add(${JSON.stringify(item).replace(/"/g,'&quot;')})">
          ${enCarrito ? `<div class="qty-badge">${enCarrito.cantidad}</div>` : ''}
          <span class="emoji">${item.emoji}</span>
          <div class="name">${item.nombre}</div>
          <div class="price">${formatMXN(item.precio)}</div>
          <div style="font-size:.8rem;color:var(--text-muted);margin-top:.25rem">${item.descripcion}</div>
        </div>
      `;
    }).join('');
  },

  filtrar(categoria: string): void {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`[data-cat="${categoria}"]`)?.classList.add('active');
    MenuUI.render(State.menu, categoria);
  }
};

// ── Estado del pedido (cliente) ──────────────────────────────
const PedidoStatus = {
  intervalo: 0,

  async cargar(): Promise<void> {
    if (!State.token) return;
    try {
      const res = await apiFetch<Pedido[]>(`/restaurante/api/pedidos.php?accion=mis_pedidos&token=${State.token}`);
      if (!res.ok) return;
      State.pedidos = res.data;
      PedidoStatus.render();
    } catch {}
  },

  render(): void {
    const container = document.getElementById('mis-pedidos');
    if (!container) return;

    if (State.pedidos.length === 0) {
      container.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:2rem">Aún no has pedido nada</p>';
      return;
    }

    const estadoLabel: Record<string, string> = {
      pendiente: '⏳ Pendiente',
      en_preparacion: '👨‍🍳 En preparación',
      listo: '✅ ¡Listo!',
      entregado: '🎉 Entregado',
      cancelado: '❌ Cancelado',
    };

    container.innerHTML = State.pedidos.map(p => `
      <div class="order-card" style="margin-bottom:1rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
          <div>
            <span style="font-family:'Playfair Display',serif;font-size:1.3rem">Orden #${p.numero_orden}</span>
            <span style="margin-left:.75rem;color:var(--text-muted);font-size:.85rem">${p.tipo === 'mesa' ? `Mesa ${p.mesa_numero}` : 'Para llevar'}</span>
          </div>
          <span class="badge badge-${p.estado}">${estadoLabel[p.estado] || p.estado}</span>
        </div>
        <div style="color:var(--text-muted);font-size:.9rem;margin-bottom:.5rem">
          ${p.items?.map(i => `${i.cantidad}x ${i.emoji} ${i.nombre}`).join(' · ') || ''}
        </div>
        <div style="display:flex;justify-content:flex-end;font-weight:700;color:var(--gold)">${formatMXN(p.total)}</div>
      </div>
    `).join('');
  },

  iniciarPolling(): void {
    PedidoStatus.cargar();
    this.intervalo = window.setInterval(() => PedidoStatus.cargar(), 8000);
  },

  detenerPolling(): void {
    clearInterval(this.intervalo);
  }
};

// ── Registro de cliente ─────────────────────────────────────
async function registrarCliente(e: Event): Promise<void> {
  e.preventDefault();
  const form   = e.target as HTMLFormElement;
  const nombre = (form.querySelector('[name=nombre]') as HTMLInputElement).value.trim();
  const tipo   = (form.querySelector('[name=tipo]') as HTMLSelectElement).value;
  const mesa   = parseInt((form.querySelector('[name=mesa]') as HTMLInputElement)?.value || '0');

  try {
    const res = await apiFetch<{ token: string; usuario: object }>(
      '/restaurante/api/pedidos.php?accion=registrar_cliente',
      { method: 'POST', body: JSON.stringify({ nombre, tipo, mesa }) }
    );
    if (!res.ok) { toast(res.mensaje, 'error'); return; }

    const { token, usuario } = res.data;
    State.token   = token;
    State.usuario = usuario as any;
    setCookie('cliente_token', token, 1);
    localStorage.setItem('fogon_usuario', JSON.stringify(usuario));

    toast(`🎉 ${res.mensaje}`, 'success');
    document.getElementById('registro-modal')?.remove();
    document.getElementById('app-main')?.classList.remove('hidden');
    MenuUI.cargar();
    PedidoStatus.iniciarPolling();
  } catch {
    toast('Error al registrarse', 'error');
  }
}

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Si ya tiene sesión activa
  if (State.token && State.usuario) {
    document.getElementById('registro-modal')?.remove();
    document.getElementById('app-main')?.classList.remove('hidden');
    if (State.usuario) {
      const welEl = document.getElementById('welcome-name');
      if (welEl) welEl.textContent = State.usuario.nombre;
    }
    MenuUI.cargar();
    PedidoStatus.iniciarPolling();
  }

  // Toggle carrito
  document.getElementById('cart-toggle')?.addEventListener('click', () => {
    document.getElementById('cart-sidebar')?.classList.toggle('open');
  });
  document.getElementById('cart-close')?.addEventListener('click', () => {
    document.getElementById('cart-sidebar')?.classList.remove('open');
  });

  // Enviar pedido
  document.getElementById('btn-send-order')?.addEventListener('click', () => Cart.enviar());

  // Form registro
  document.getElementById('form-registro')?.addEventListener('submit', registrarCliente);

  // Filtros de menú
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => MenuUI.filtrar(btn.getAttribute('data-cat') || 'todos'));
  });
});

// Exponer al HTML
(window as any).Cart     = Cart;
(window as any).MenuUI   = MenuUI;
(window as any).formatMXN = formatMXN;