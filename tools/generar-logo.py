# -*- coding: utf-8 -*-
"""
Marca del negocio: una gota de aceite dentro de una tuerca hexagonal.
Dos rubros en un solo signo.

Geometria en un lienzo de 100x100 para que el SVG y los mapas de bits
salgan exactamente iguales. La gota se construye con dos rectas tangentes
y un arco -no con curvas libres- para que Pillow pueda reproducirla al
milimetro y para que el trazo se lea tecnico, no organico.
"""
import math, os
from PIL import Image, ImageDraw

AMBAR = (224, 147, 15)      # #e0930f
TINTA = (43, 30, 5)         # #2b1e05

# --- Tuerca: hexagono de caja cuadrada (84x84 centrado en 50,50) ---
HEX = [(50, 8), (92, 29), (92, 71), (50, 92), (8, 71), (8, 29)]

# --- Gota: apice + circulo, unidos por sus tangentes ---
# La gota ocupa cerca del 44% del ancho de la tuerca: a 16 px una gota
# pequena se convierte en un punto y deja de significar nada.
APICE = (50.0, 21.0)
CENTRO = (50.0, 55.0)
RADIO = 18.5

def tangentes(apice, centro, r):
    """Puntos donde las rectas desde el apice tocan el circulo."""
    dx, dy = centro[0] - apice[0], centro[1] - apice[1]
    d = math.hypot(dx, dy)
    theta = math.acos(r / d)                 # angulo entre CA y CT
    ux, uy = (apice[0] - centro[0]) / d, (apice[1] - centro[1]) / d
    pts = []
    for signo in (1, -1):
        c, s = math.cos(signo * theta), math.sin(signo * theta)
        pts.append((centro[0] + r * (ux * c - uy * s),
                    centro[1] + r * (ux * s + uy * c)))
    return pts

T1, T2 = tangentes(APICE, CENTRO, RADIO)

# =====================================================================
# SVG
# =====================================================================
def svg(tam=None):
    attr = f'width="{tam}" height="{tam}" ' if tam else ''
    hexa = ' '.join(f'{x},{y}' for x, y in HEX)
    return f'''<svg xmlns="http://www.w3.org/2000/svg" {attr}viewBox="0 0 100 100" role="img" aria-label="Logo">
  <title>Gota de aceite en tuerca hexagonal</title>
  <polygon points="{hexa}" fill="#e0930f"/>
  <path d="M {APICE[0]} {APICE[1]} L {T1[0]:.2f} {T1[1]:.2f} A {RADIO} {RADIO} 0 1 1 {T2[0]:.2f} {T2[1]:.2f} Z" fill="#2b1e05"/>
</svg>
'''

# =====================================================================
# Mapas de bits
# =====================================================================
def png(lado, escala=8, fondo=None, margen=0.0):
    """Se dibuja en grande y se reduce: asi los bordes salen suaves."""
    g = lado * escala
    img = Image.new('RGBA', (g, g), fondo or (0, 0, 0, 0))
    d = ImageDraw.Draw(img)

    m = margen * 100.0                       # margen en unidades del lienzo
    k = (g / 100.0) * (1 - 2 * margen)
    def p(x, y):
        return (m * g / 100.0 + x * k, m * g / 100.0 + y * k)

    d.polygon([p(x, y) for x, y in HEX], fill=AMBAR)
    d.polygon([p(*APICE), p(*T1), p(*T2)], fill=TINTA)
    cx, cy = p(*CENTRO)
    r = RADIO * k
    d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=TINTA)

    return img.resize((lado, lado), Image.LANCZOS)

base = r'C:\Users\rick\Desktop\Tornillos\saas-ventas-inventario\public'
os.makedirs(os.path.join(base, 'img'), exist_ok=True)

# SVG: la marca escalable
open(os.path.join(base, 'img', 'logo.svg'), 'w', encoding='utf-8').write(svg())
open(os.path.join(base, 'favicon.svg'), 'w', encoding='utf-8').write(svg())

# ICO multitamano para navegadores antiguos
ico = png(64)
ico.save(os.path.join(base, 'favicon.ico'), sizes=[(16, 16), (32, 32), (48, 48)])

# Icono de pantalla de inicio: fondo solido, la transparencia se ve mal ahi
touch = png(180, fondo=(255, 255, 255, 255), margen=0.10)
touch.save(os.path.join(base, 'img', 'icono-180.png'))

# Version grande para documentos, redes y presentaciones
png(512).save(os.path.join(base, 'img', 'logo-512.png'))

print('tangentes  T1={:.2f},{:.2f}  T2={:.2f},{:.2f}'.format(*T1, *T2))
for f in ['img/logo.svg', 'favicon.svg', 'favicon.ico', 'img/icono-180.png', 'img/logo-512.png']:
    ruta = os.path.join(base, f.replace('/', os.sep))
    print('  {:24s} {:>7d} bytes'.format(f, os.path.getsize(ruta)))
