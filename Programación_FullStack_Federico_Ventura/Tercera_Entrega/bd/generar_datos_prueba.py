#!/usr/bin/env python3
"""
bd/generar_datos_prueba.py

Genera bd/sgdm_datos_prueba.sql: datos de prueba CONSISTENTES para todas las
tablas (50+ filas en cada una). No son filas sueltas al azar: el script simula
torneos completos con las mismas reglas que usa la aplicación (liga con el
método del círculo, eliminación directa con byes y suizo sin repetir rival),
asi que fixtures, resultados, tabla de posiciones, ganadores, equipos,
invitaciones e inscripciones cierran entre sí.

Uso:
    pip install bcrypt
    python3 bd/generar_datos_prueba.py            # reescribe bd/sgdm_datos_prueba.sql

El resultado es determinístico (misma semilla = mismo archivo). Para cambiar
la cantidad de datos o los tamaños, tocar las constantes de arriba.
"""

import datetime as dt
import math
import os
import random
import sys
import unicodedata

import bcrypt

# ---------------------------------------------------------------------------
# Configuración
# ---------------------------------------------------------------------------
SEMILLA = 20261007
R = random.Random(SEMILLA)
HOY = dt.datetime(2026, 10, 7, 12, 0, 0)
BASE_ID = 1000                      # los ids de prueba empiezan en 1001
CLAVE = 'Prueba123!'               # contraseña de TODAS las cuentas de prueba
CANTIDAD_PARTICIPANTES = 100
CANTIDAD_ORGANIZADORES = 5
SALIDA = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'sgdm_datos_prueba.sql')

# bcrypt con sal fija para que el archivo sea reproducible. PHP acepta el prefijo $2y$.
HASH = bcrypt.hashpw(CLAVE.encode(), b'$2b$10$abcdefghijklmnopqrstuu').decode().replace('$2b$', '$2y$', 1)

# ---------------------------------------------------------------------------
# Utilidades
# ---------------------------------------------------------------------------


def sin_tildes(texto):
    return ''.join(c for c in unicodedata.normalize('NFD', texto) if unicodedata.category(c) != 'Mn')


def clave_nombre(texto):
    """Orden alfabético parecido al de la base (utf8mb4_unicode_ci): sin tildes ni mayúsculas."""
    return sin_tildes(texto).casefold()


def q(valor):
    if valor is None:
        return 'NULL'
    if isinstance(valor, bool):
        return '1' if valor else '0'
    if isinstance(valor, int):
        return str(valor)
    if isinstance(valor, dt.datetime):
        return "'" + valor.strftime('%Y-%m-%d %H:%M:%S') + "'"
    if isinstance(valor, dt.date):
        return "'" + valor.strftime('%Y-%m-%d') + "'"
    if isinstance(valor, Raw):
        return valor.sql
    assert '\\' not in valor, valor
    return "'" + valor.replace("'", "''") + "'"


class Raw:
    def __init__(self, sql):
        self.sql = sql


def aleatorio_entre(desde, hasta):
    """Datetime aleatorio entre dos datetimes."""
    segundos = int((hasta - desde).total_seconds())
    return desde + dt.timedelta(seconds=R.randint(0, max(0, segundos)))


class Ids:
    def __init__(self):
        self.n = BASE_ID

    def __call__(self):
        self.n += 1
        return self.n


# ---------------------------------------------------------------------------
# Catálogo (igual al de bd/sgdm_migracion_equipos.sql) y variantes de torneo
# ---------------------------------------------------------------------------
# clave de puntaje, codigo del catálogo, nombre, jugadores por equipo
VARIANTES = [
    ('futbol11',     'futbol11',     'Fútbol 11',          11),
    ('futbol5',      'futbol5',      'Fútbol 5',            5),
    ('basquetbol',   'basquetbol',   'Básquetbol',          5),
    ('tenis',        'tenis',        'Tenis',               1),
    ('tenis_dobles', 'tenis_dobles', 'Tenis dobles',        2),
    ('padel',        'padel',        'Pádel',               2),
    ('pingpong',     'pingpong',     'Ping pong',           1),
    ('ajedrez',      'ajedrez',      'Ajedrez',             1),
    ('truco',        'truco',        'Truco',               2),
    ('damas',        'damas',        'Damas',               1),
    ('cs2',          'cs2',          'CS2',                 5),
    ('lol',          'lol',          'League of Legends',   5),
    ('fortnite',     'fortnite',     'Fortnite',            1),
    ('valorant',     'valorant',     'Valorant',            5),
    ('rocketleague', 'rocketleague', 'Rocket League',       3),
    # "Otro" libre: el nombre y el tamaño los puso quien creó el torneo / el equipo
    ('quemada',      'otro',         'Quemada',             6),
    ('mus',          'otro',         'Mus',                 2),
    ('domino',       'otro',         'Dominó',              1),
]

EMPATES = {'futbol11': 0.18, 'futbol5': 0.18, 'ajedrez': 0.25, 'damas': 0.20}   # el resto no admite empate

FORMATOS = ['liga', 'eliminacion', 'suizo']
ESTADOS = ['finalizado', 'en_curso', 'inscripciones_abiertas']

NOMBRES_F = ['Valentina', 'Camila', 'Sofía', 'Martina', 'Lucía', 'Julieta', 'Agustina', 'Florencia', 'Micaela',
             'Catalina', 'Abril', 'Renata', 'Delfina', 'Pilar', 'Josefina', 'Antonella', 'Bianca', 'Luciana',
             'Candela', 'Milagros', 'Rocío', 'Carolina', 'Natalia', 'Paula', 'Romina']
NOMBRES_M = ['Santiago', 'Mateo', 'Martín', 'Joaquín', 'Benjamín', 'Thiago', 'Nicolás', 'Facundo', 'Lucas',
             'Tomás', 'Franco', 'Emiliano', 'Matías', 'Bruno', 'Ignacio', 'Maximiliano', 'Agustín', 'Federico',
             'Gonzalo', 'Sebastián', 'Rodrigo', 'Diego', 'Andrés', 'Gastón', 'Ramiro']
APELLIDOS = ['Pérez', 'González', 'Rodríguez', 'Fernández', 'López', 'Martínez', 'García', 'Silva', 'Sosa',
             'Díaz', 'Castro', 'Rivero', 'Acosta', 'Olivera', 'Techera', 'Suárez', 'Ferreira', 'Cabrera',
             'Núñez', 'Méndez', 'Pereira', 'Rossi', 'Correa', 'Benítez', 'Machado', 'Ramos', 'Vázquez',
             'Cardozo', 'Píriz', 'Giménez', 'Maidana', 'Bentancor', 'Viera', 'Fagúndez', 'Lemos', 'Barrios',
             'Altez', 'Pintos', 'Bonilla', 'Texeira', 'Echeverría', 'Lacuesta', 'Mederos', 'Peralta',
             'Cuello', 'Sanguinetti', 'Rebollo', 'Montero', 'Varela', 'Arrieta']
NOMBRES_EQUIPO = ['Los Tigres', 'Halcones', 'Titanes', 'Los Cuervos', 'Furia Celeste', 'Pumas', 'Dragones',
                  'Los Gladiadores', 'Rayo Verde', 'Lobos', 'Águilas', 'Los Pibes', 'Tormenta', 'Fénix',
                  'Los Charrúas', 'Vikingos', 'Jaguares', 'Los Cóndores', 'Samuráis', 'Centauros',
                  'Los Zorros', 'Cometas', 'Los Bohemios', 'Escorpiones', 'Los Gauchos']
PREFIJOS_TORNEO = ['Copa', 'Torneo', 'Desafío', 'Clásico', 'Abierto', 'Liga']
EDICIONES = {
    'finalizado': ['Verano 2026', 'Otoño 2026', 'Invierno 2026'],
    'en_curso': ['Apertura 2026', 'Primavera 2026'],
    'inscripciones_abiertas': ['Clausura 2026', 'Verano 2027', 'Copa de Cierre 2026'],
}

# ---------------------------------------------------------------------------
# Tablas en memoria
# ---------------------------------------------------------------------------
usuarios, torneos, equipos, miembros, invitaciones = [], [], [], [], []
inscripciones, tabla_pos, rondas, enfrentamientos, resultados, auditoria = [], [], [], [], [], []

id_usuario, id_torneo, id_equipo, id_miembro, id_invitacion = Ids(), Ids(), Ids(), Ids(), Ids()
id_inscripcion, id_tabla, id_ronda, id_enf, id_resultado, id_audit = Ids(), Ids(), Ids(), Ids(), Ids(), Ids()

eventos_audit = []   # (fecha, usuario_id, accion, tabla, registro_id, detalle)


def audit(fecha, usuario_id, accion, tabla, registro_id, detalle):
    eventos_audit.append((fecha, usuario_id, accion, tabla, registro_id, detalle))


# ---------------------------------------------------------------------------
# Usuarios
# ---------------------------------------------------------------------------
def crear_usuario(username, nombre, rol, genero, registro):
    uid = id_usuario()
    celular = '09%d %03d %03d' % (R.randint(1, 9), R.randint(0, 999), R.randint(0, 999))
    usuarios.append(dict(id=uid, username=username, nombre=nombre, email=f'{username}@primecup.test',
                         celular=celular, genero=genero, rol=rol, registro=registro))
    audit(registro, uid, 'ALTA_USUARIO', 'usuarios', uid, f'Alta de usuario {username}')
    return uid


ADMIN = crear_usuario('test_admin', 'Administración PrimeCup (prueba)', 1, 'Femenino', dt.datetime(2025, 10, 20, 9, 0))
ORGANIZADORES = []
for i in range(CANTIDAD_ORGANIZADORES):
    nombre = f"{(NOMBRES_F + NOMBRES_M)[i * 5 % 50]} {APELLIDOS[(i * 11 + 4) % len(APELLIDOS)]}"
    ORGANIZADORES.append(crear_usuario(f'test_organizador{i + 1}', nombre, 2, 'Femenino' if i % 2 == 0 else 'Masculino',
                                       aleatorio_entre(dt.datetime(2025, 10, 21), dt.datetime(2025, 11, 10))))

PARTICIPANTES = []
NOMBRE_DE_USUARIO = {}
for i in range(CANTIDAD_PARTICIPANTES):
    mujer = i % 2 == 0
    lista = NOMBRES_F if mujer else NOMBRES_M
    nombre = lista[(i // 2) % len(lista)]
    apellido = APELLIDOS[(i * 7 + i // len(lista)) % len(APELLIDOS)]
    username = f'test_{sin_tildes(nombre).lower()}{i + 1:02d}'
    uid = crear_usuario(username, f'{nombre} {apellido}', 3, 'Femenino' if mujer else 'Masculino',
                        aleatorio_entre(dt.datetime(2025, 11, 1), dt.datetime(2025, 12, 31)))
    PARTICIPANTES.append(uid)
    NOMBRE_DE_USUARIO[uid] = f'{nombre} {apellido}'

# ---------------------------------------------------------------------------
# Equipos (se arman afuera de los torneos: capitán + invitaciones aceptadas)
# ---------------------------------------------------------------------------
EQUIPOS_POR_VARIANTE = {}      # clave -> {'completos': [...], 'incompletos': [...]}
EQUIPO_POR_ID = {}


def crear_equipos(variante, indice_variante):
    clave, codigo, nombre_deporte, tamano = variante
    if tamano < 2:
        return
    cantidad_completos = 6 if tamano >= 11 else 8
    cantidad_incompletos = 1 if tamano >= 11 else 2
    pool = PARTICIPANTES[:]
    random.Random(SEMILLA + indice_variante).shuffle(pool)
    sobrantes_inicio = cantidad_completos * tamano + cantidad_incompletos * (tamano - 1)
    assert sobrantes_inicio <= len(pool), (nombre_deporte, sobrantes_inicio)
    libres = pool[sobrantes_inicio:]

    completos, incompletos = [], []
    cursor = 0
    for n in range(cantidad_completos + cantidad_incompletos):
        completo = n < cantidad_completos
        cuantos = tamano if completo else tamano - 1
        integrantes = pool[cursor:cursor + cuantos]
        cursor += cuantos
        eid = id_equipo()
        creado = aleatorio_entre(dt.datetime(2026, 1, 5), dt.datetime(2026, 2, 20))
        nombre_equipo = NOMBRES_EQUIPO[(n * 3 + indice_variante) % len(NOMBRES_EQUIPO)]
        equipo = dict(id=eid, nombre=nombre_equipo, capitan=integrantes[0], codigo=codigo, deporte=nombre_deporte,
                      tamano=tamano, creado=creado, miembros=integrantes, clave=clave)
        equipos.append(equipo)
        EQUIPO_POR_ID[eid] = equipo
        audit(creado, integrantes[0], 'CREACION_EQUIPO', 'equipos', eid, f"Equipo '{nombre_equipo}' ({nombre_deporte})")

        for j, uid in enumerate(integrantes):
            ingreso = creado if j == 0 else creado + dt.timedelta(days=R.randint(1, 12), hours=R.randint(0, 20))
            miembros.append(dict(id=id_miembro(), equipo=eid, usuario=uid, ingreso=ingreso))
            if j > 0:   # todo integrante que no es el capitán entró aceptando una invitación
                inv_creada = ingreso - dt.timedelta(hours=R.randint(2, 40))
                inv_id = id_invitacion()
                invitaciones.append(dict(id=inv_id, equipo=eid, usuario=uid, por=integrantes[0], estado='aceptada',
                                         creada=inv_creada, respuesta=ingreso))
                audit(inv_creada, integrantes[0], 'INVITACION_EQUIPO', 'equipos', eid,
                      f"Equipo #{eid}: invitó a '{NOMBRE_DE_USUARIO[uid]}'")
                audit(ingreso, uid, 'RESPUESTA_INVITACION', 'equipos', eid, f'Invitación #{inv_id}: aceptada')

        if not completo:
            # equipos todavía sin completar: les quedan invitaciones pendientes
            faltan = tamano - cuantos
            for _ in range(faltan):
                uid = libres.pop(R.randrange(len(libres)))
                inv_creada = HOY - dt.timedelta(days=R.randint(0, 6), hours=R.randint(0, 20))
                inv_id = id_invitacion()
                invitaciones.append(dict(id=inv_id, equipo=eid, usuario=uid, por=integrantes[0], estado='pendiente',
                                         creada=inv_creada, respuesta=None))
                audit(inv_creada, integrantes[0], 'INVITACION_EQUIPO', 'equipos', eid,
                      f"Equipo #{eid}: invitó a '{NOMBRE_DE_USUARIO[uid]}'")
            incompletos.append(equipo)
        else:
            completos.append(equipo)

    # algunas invitaciones rechazadas y canceladas (de equipos completos, hacia gente que no está en el equipo)
    for equipo in completos[:3]:
        for estado in ('rechazada', 'cancelada'):
            uid = libres.pop(R.randrange(len(libres)))
            creada = equipo['creado'] + dt.timedelta(days=R.randint(1, 8))
            respuesta = creada + dt.timedelta(hours=R.randint(2, 48))
            inv_id = id_invitacion()
            invitaciones.append(dict(id=inv_id, equipo=equipo['id'], usuario=uid, por=equipo['capitan'], estado=estado,
                                     creada=creada, respuesta=respuesta))
            audit(creada, equipo['capitan'], 'INVITACION_EQUIPO', 'equipos', equipo['id'],
                  f"Equipo #{equipo['id']}: invitó a '{NOMBRE_DE_USUARIO[uid]}'")
            if estado == 'rechazada':
                audit(respuesta, uid, 'RESPUESTA_INVITACION', 'equipos', equipo['id'], f'Invitación #{inv_id}: rechazada')
            else:
                audit(respuesta, equipo['capitan'], 'CANCELACION_INVITACION', 'equipos', equipo['id'],
                      f'Invitación #{inv_id} cancelada')

    EQUIPOS_POR_VARIANTE[clave] = dict(completos=completos, incompletos=incompletos)


for indice, variante in enumerate(VARIANTES):
    crear_equipos(variante, indice)


# ---------------------------------------------------------------------------
# Simulación de partidos
# ---------------------------------------------------------------------------
def puntaje(clave, resultado):
    """Marcador (local, visitante) coherente con el resultado 'L', 'V' o 'E'."""
    if resultado == 'E':
        if clave in ('futbol11', 'futbol5'):
            g = R.choice([0, 0, 1, 1, 1, 2, 2, 3])
            return g, g
        return 1, 1            # ajedrez / damas: tablas

    if clave in ('futbol11', 'futbol5'):
        p = R.choice([0, 0, 1, 1, 2]); g = p + R.choice([1, 1, 2, 2, 3])
    elif clave == 'basquetbol':
        p = R.randint(58, 95); g = p + R.randint(1, 18)
    elif clave in ('tenis', 'tenis_dobles', 'padel', 'lol'):
        g = 2; p = R.choice([0, 1])
    elif clave == 'pingpong':
        g = 3; p = R.choice([0, 1, 2])
    elif clave in ('ajedrez', 'damas'):
        g = 1; p = 0
    elif clave == 'truco':
        g = 30; p = R.randint(8, 29)
    elif clave in ('cs2', 'valorant'):
        if R.random() < 0.2:
            g, p = 14, 12
        else:
            g = 13; p = R.randint(2, 11)
    elif clave == 'fortnite':
        p = R.randint(8, 24); g = p + R.randint(1, 12)
    elif clave == 'rocketleague':
        p = R.randint(0, 4); g = p + R.randint(1, 3)
    elif clave == 'quemada':
        g = R.randint(7, 15); p = max(0, g - R.randint(1, 6))
    elif clave == 'mus':
        g = 3; p = R.choice([0, 1, 2])
    elif clave == 'domino':
        g = 100; p = R.randint(35, 95)
    else:
        raise ValueError(clave)
    return (g, p) if resultado == 'L' else (p, g)


class Simulacion:
    """Estado de un torneo: inscripciones, tabla de posiciones y rondas."""

    def __init__(self, clave, formato, inscripciones_ids, nombres):
        self.clave = clave
        self.formato = formato
        self.ids = sorted(inscripciones_ids)
        self.nombre = nombres                      # inscripcion_id -> nombre (para desempatar la tabla)
        self.fuerza = {i: R.random() for i in self.ids}
        self.tabla = {i: [0, 0, 0, 0, 0] for i in self.ids}   # pts, pj, g, e, p
        self.rondas = []                           # dicts: numero, nombre, enfs
        self.posiciones_calculadas = False

    # --- tabla ---
    def orden(self):
        return sorted(self.ids, key=lambda i: (-self.tabla[i][0], -self.tabla[i][2], clave_nombre(self.nombre[i]), i))

    def _sumar(self, insc, resultado):
        fila = self.tabla[insc]
        fila[1] += 1
        if resultado == 'ganado':
            fila[0] += 3; fila[2] += 1
        elif resultado == 'empatado':
            fila[0] += 1; fila[3] += 1
        else:
            fila[4] += 1

    # --- partidos ---
    def nueva_ronda(self, nombre):
        ronda = dict(numero=len(self.rondas) + 1, nombre=nombre, enfs=[])
        self.rondas.append(ronda)
        return ronda

    def partido(self, ronda, local, visitante):
        enf = dict(local=local, visitante=visitante, res=None)
        ronda['enfs'].append(enf)
        return enf

    def bye(self, ronda, insc):
        enf = dict(local=insc, visitante=None, res=(0, 0, insc), bye=True)
        ronda['enfs'].append(enf)
        return enf

    def jugar(self, enf):
        local, visitante = enf['local'], enf['visitante']
        p_local = 1 / (1 + math.exp(-4 * (self.fuerza[local] - self.fuerza[visitante])))
        empate = self.formato != 'eliminacion' and R.random() < EMPATES.get(self.clave, 0)
        if empate:
            resultado = 'E'
        else:
            resultado = 'L' if R.random() < p_local else 'V'
        pl, pv = puntaje(self.clave, resultado)
        ganador = local if resultado == 'L' else visitante if resultado == 'V' else None
        enf['res'] = (pl, pv, ganador)

        if resultado == 'E':
            self._sumar(local, 'empatado'); self._sumar(visitante, 'empatado')
        else:
            self._sumar(ganador, 'ganado'); self._sumar(local if ganador == visitante else visitante, 'perdido')
        self.posiciones_calculadas = True

    def jugar_ronda(self, ronda, cantidad=None):
        """Juega todos los partidos pendientes de la ronda, o solo `cantidad` elegidos al azar."""
        pendientes = [e for e in ronda['enfs'] if e['res'] is None]
        if cantidad is not None:
            pendientes = R.sample(pendientes, cantidad)
        for enf in pendientes:
            self.jugar(enf)

    @staticmethod
    def partidos_de(ronda):
        return [e for e in ronda['enfs'] if e['visitante'] is not None]

    # --- formatos ---
    def liga(self, estado):
        lista = self.ids[:]
        if len(lista) % 2 != 0:
            lista.append(None)
        total = len(lista)
        total_rondas = total - 1
        mitad = total // 2
        for numero in range(1, total_rondas + 1):
            ronda = self.nueva_ronda(f'Fecha {numero}')
            for i in range(mitad):
                a, b = lista[i], lista[total - 1 - i]
                if a is not None and b is not None:
                    self.partido(ronda, a, b)
            fijo, resto = lista[0], lista[1:]
            resto.insert(0, resto.pop())
            lista = [fijo] + resto

        if estado == 'finalizado':
            for ronda in self.rondas:
                self.jugar_ronda(ronda)
            return self.orden()[0]

        completas = R.randint(min(1, total_rondas - 1), total_rondas - 1)
        for ronda in self.rondas[:completas]:
            self.jugar_ronda(ronda)
        actual = self.rondas[completas]
        self.jugar_ronda(actual, R.randint(0, len(self.partidos_de(actual)) - 1))
        return None

    @staticmethod
    def nombre_ronda_elim(clasificados):
        return {1: 'Final', 2: 'Semifinal', 4: 'Cuartos de final', 8: 'Octavos de final'}.get(
            clasificados, f'Ronda (quedan {clasificados})')

    def eliminacion(self, estado):
        n = len(self.ids)
        potencia = 1
        while potencia < n:
            potencia *= 2
        byes = potencia - n
        total_rondas = int(math.log2(potencia))
        ordenados = self.ids[:]
        R.shuffle(ordenados)

        completas = total_rondas if estado == 'finalizado' else R.randint(0, total_rondas - 1)

        ronda = self.nueva_ronda(self.nombre_ronda_elim(potencia // 2))
        indice = 0
        for _ in range(byes):
            self.bye(ronda, ordenados[indice]); indice += 1
        while indice < n:
            self.partido(ronda, ordenados[indice], ordenados[indice + 1]); indice += 2

        while True:
            numero = ronda['numero']
            if numero <= completas:
                self.jugar_ronda(ronda)
            else:
                self.jugar_ronda(ronda, R.randint(0, len(self.partidos_de(ronda)) - 1))
                return None
            ganadores = [e['res'][2] for e in ronda['enfs']]   # primero los byes, después los ganadores (orden de enfrentamiento)
            if len(ganadores) == 1:
                return ganadores[0]
            ronda = self.nueva_ronda(self.nombre_ronda_elim(len(ganadores) // 2))
            for i in range(0, len(ganadores), 2):
                self.partido(ronda, ganadores[i], ganadores[i + 1])

    @staticmethod
    def _clave_par(a, b):
        return (a, b) if a < b else (b, a)

    def _emparejar(self, lista, ya):
        if not lista:
            return []
        primero, resto = lista[0], lista[1:]
        for i, candidato in enumerate(resto):
            if self._clave_par(primero, candidato) in ya:
                continue
            sub = self._emparejar(resto[:i] + resto[i + 1:], ya)
            if sub is not None:
                return [(primero, candidato)] + sub
        return None

    def _armar_ronda_suiza(self, ordenados):
        ya, tuvo_bye = set(), set()
        for r in self.rondas:
            for e in r['enfs']:
                if e['visitante'] is None:
                    tuvo_bye.add(e['local'])
                else:
                    ya.add(self._clave_par(e['local'], e['visitante']))

        bye, parejas = None, None
        if len(ordenados) % 2 == 0:
            parejas = self._emparejar(ordenados, ya)
        else:
            for candidato in reversed(ordenados):
                if candidato in tuvo_bye:
                    continue
                posibles = self._emparejar([x for x in ordenados if x != candidato], ya)
                if posibles is not None:
                    bye, parejas = candidato, posibles
                    break
        if parejas is None:   # plan B de la app: se permite revancha antes que trabar el torneo
            if len(ordenados) % 2 != 0:
                bye, resto = ordenados[-1], ordenados[:-1]
            else:
                resto = ordenados
            parejas = self._emparejar(resto, set())
        return parejas, bye

    def suizo(self, estado):
        total_rondas = max(1, math.ceil(math.log2(len(self.ids))))
        completas = total_rondas if estado == 'finalizado' else R.randint(0, total_rondas - 1)

        for numero in range(1, total_rondas + 1):
            if numero == 1:
                ordenados = self.ids[:]
                R.shuffle(ordenados)
            else:
                ordenados = self.orden()
            parejas, bye = self._armar_ronda_suiza(ordenados)
            ronda = self.nueva_ronda(f'Ronda {numero} de {total_rondas}')
            for local, visitante in parejas:
                self.partido(ronda, local, visitante)
            if bye is not None:
                self.bye(ronda, bye)
                self._sumar(bye, 'ganado')           # en suizo el bye cuenta como victoria
                self.posiciones_calculadas = True

            if numero <= completas:
                self.jugar_ronda(ronda)
            else:
                self.jugar_ronda(ronda, R.randint(0, len(self.partidos_de(ronda)) - 1))
                return None
        return self.orden()[0]

    def correr(self, estado):
        return {'liga': self.liga, 'eliminacion': self.eliminacion, 'suizo': self.suizo}[self.formato](estado)


# ---------------------------------------------------------------------------
# Torneos
# ---------------------------------------------------------------------------
def rondas_totales_aprox(formato, n):
    if formato == 'liga':
        return n if n % 2 else n - 1
    return max(1, math.ceil(math.log2(n)))


def crear_torneo(variante, indice_variante, k):
    clave, codigo, nombre_deporte, tamano = variante
    estado = ESTADOS[k]
    formato = FORMATOS[(indice_variante + k) % 3]
    modalidad = 'equipo' if tamano >= 2 else 'individual'
    tid = id_torneo()
    nombre = f'{PREFIJOS_TORNEO[(indice_variante + k) % len(PREFIJOS_TORNEO)]} {nombre_deporte} ' \
             f'{EDICIONES[estado][(indice_variante // 3 + k) % len(EDICIONES[estado])]}'

    # --- quiénes se inscriben ---
    if modalidad == 'equipo':
        grupo = EQUIPOS_POR_VARIANTE[clave]['completos']
        if estado == 'inscripciones_abiertas':
            cantidad = R.randint(2, max(2, len(grupo) - 1))
        else:
            cantidad = R.randint(4, len(grupo))
        elegidos = R.sample(grupo, cantidad)
        participantes = [dict(equipo=e['id'], usuario=None, nombre=e['nombre']) for e in elegidos]
    else:
        cantidad = R.randint(3, 10) if estado == 'inscripciones_abiertas' else R.randint(6, 12)
        elegidos = R.sample(PARTICIPANTES, cantidad)
        participantes = [dict(equipo=None, usuario=u, nombre=NOMBRE_DE_USUARIO[u]) for u in elegidos]

    # --- los inscriptos reciben su id y se simula la competencia ---
    insc_ids = [id_inscripcion() for _ in participantes]
    for insc_id, p in zip(insc_ids, participantes):
        p['id'] = insc_id
    nombres = {p['id']: p['nombre'] for p in participantes}

    sim = None
    ganador = None
    if estado != 'inscripciones_abiertas':
        sim = Simulacion(clave, formato, insc_ids, nombres)
        ganador = sim.correr(estado)

    # --- fechas coherentes con lo que pasó ---
    if estado == 'inscripciones_abiertas':
        fecha_inicio = (HOY + dt.timedelta(days=R.randint(8, 70))).date()
        creado = HOY - dt.timedelta(days=R.randint(2, 25), hours=R.randint(0, 10))
    elif estado == 'en_curso':
        # la ronda más avanzada con partidos jugados tiene que caer antes de hoy
        con_partidos = [r['numero'] for r in sim.rondas
                        if any(e['res'] is not None and e['visitante'] is not None for e in r['enfs'])]
        ultima_jugada = max(con_partidos, default=1)
        fecha_inicio = (HOY - dt.timedelta(days=7 * (ultima_jugada - 1) + R.randint(0, 5) + 1)).date()
        creado = dt.datetime.combine(fecha_inicio, dt.time(10, 0)) - dt.timedelta(days=R.randint(12, 35))
    else:
        fecha_inicio = (HOY - dt.timedelta(days=7 * len(sim.rondas) + R.randint(7, 90))).date()
        creado = dt.datetime.combine(fecha_inicio, dt.time(10, 0)) - dt.timedelta(days=R.randint(12, 35))

    # --- ventana de inscripción y fin del torneo ---
    apertura = creado.date()
    if estado == 'inscripciones_abiertas':
        if R.random() < 0.25:
            # ya cerró por fecha pero el organizador todavía no generó el fixture
            cierre = max(creado.date() + dt.timedelta(days=1), (HOY - dt.timedelta(days=R.randint(1, 4))).date())
        else:
            cierre = min(fecha_inicio, (HOY + dt.timedelta(days=R.randint(1, 6))).date())
    else:
        cierre = fecha_inicio - dt.timedelta(days=1)
    fecha_fin = fecha_inicio + dt.timedelta(days=7 * max(0, rondas_totales_aprox(formato, len(participantes)) - 1))

    organizador = None
    if estado != 'inscripciones_abiertas' or R.random() < 0.6:
        organizador = ORGANIZADORES[(indice_variante + k) % len(ORGANIZADORES)]
    actor = organizador or ADMIN

    cupos = None
    if estado == 'inscripciones_abiertas':
        # algunos torneos con cupo justo (llenos), otros con lugar de sobra
        r = R.random()
        if r < 0.2:
            cupos = len(participantes)
        elif r < 0.7:
            cupos = len(participantes) + R.randint(1, 6)

    torneo = dict(id=tid, nombre=nombre, deporte=nombre_deporte, formato=formato, modalidad=modalidad, estado=estado,
                  inicio=fecha_inicio, creado_por=ADMIN, organizador=organizador, ganador=ganador, cupos=cupos,
                  tamano=tamano, creado=creado, apertura=apertura, cierre=cierre, fin=fecha_fin)
    torneos.append(torneo)
    audit(creado, ADMIN, 'CREACION_TORNEO', 'torneos', tid, f"Torneo '{nombre}' ({nombre_deporte}, {formato})")

    # --- inscripciones ---
    fin_inscripcion = min(HOY - dt.timedelta(hours=1), dt.datetime.combine(cierre, dt.time(23, 59, 59)))
    for p in participantes:
        fecha_insc = aleatorio_entre(creado + dt.timedelta(hours=3), max(creado + dt.timedelta(hours=4), fin_inscripcion))
        inscripciones.append(dict(id=p['id'], torneo=tid, equipo=p['equipo'], usuario=p['usuario'], estado='activa',
                                  fecha=fecha_insc))
        if p['equipo'] is not None:
            audit(fecha_insc, EQUIPO_POR_ID[p['equipo']]['capitan'], 'INSCRIPCION_EQUIPO', 'inscripciones', p['id'],
                  f"Torneo #{tid}: inscribió al equipo #{p['equipo']}")
        else:
            audit(fecha_insc, p['usuario'], 'INSCRIPCION', 'inscripciones', p['id'], f'Torneo #{tid}: inscripción individual')

    # una baja en algunos torneos con inscripciones abiertas (queda 'retirada')
    if estado == 'inscripciones_abiertas' and R.random() < 0.5:
        if modalidad == 'equipo':
            resto = [e for e in EQUIPOS_POR_VARIANTE[clave]['completos'] if e['id'] not in {p['equipo'] for p in participantes}]
            retirado = dict(equipo=R.choice(resto)['id'], usuario=None) if resto else None
        else:
            resto = [u for u in PARTICIPANTES if u not in {p['usuario'] for p in participantes}]
            retirado = dict(equipo=None, usuario=R.choice(resto))
        if retirado:
            insc_id = id_inscripcion()
            fecha_insc = aleatorio_entre(creado + dt.timedelta(hours=3), min(HOY - dt.timedelta(days=1), fin_inscripcion))
            inscripciones.append(dict(id=insc_id, torneo=tid, equipo=retirado['equipo'], usuario=retirado['usuario'],
                                      estado='retirada', fecha=fecha_insc))
            quien = EQUIPO_POR_ID[retirado['equipo']]['capitan'] if retirado['equipo'] else retirado['usuario']
            audit(fecha_insc, quien, 'INSCRIPCION_EQUIPO' if retirado['equipo'] else 'INSCRIPCION',
                  'inscripciones', insc_id, f'Torneo #{tid}: inscripción')
            audit(fecha_insc + dt.timedelta(days=1), quien, 'BAJA_INSCRIPCION', 'inscripciones', insc_id,
                  f'Torneo #{tid}: baja de la inscripción')

    if sim is None:
        return

    # --- tabla, rondas, enfrentamientos y resultados ---
    audit(dt.datetime.combine(fecha_inicio, dt.time(9, 0)) - dt.timedelta(days=1), actor, 'GENERACION_FIXTURE', 'torneos', tid,
          f'Fixture generado: {formato}, {len(participantes)} inscriptos')

    posicion = {}
    if sim.posiciones_calculadas:
        posicion = {insc: i + 1 for i, insc in enumerate(sim.orden())}
    for insc in sim.ids:
        pts, pj, g, e, pe = sim.tabla[insc]
        tabla_pos.append(dict(id=id_tabla(), insc=insc, pos=posicion.get(insc), pts=pts, pj=pj, g=g, e=e, p=pe))

    for ronda in sim.rondas:
        rid = id_ronda()
        fecha_ronda = fecha_inicio + dt.timedelta(days=7 * (ronda['numero'] - 1))
        rondas.append(dict(id=rid, torneo=tid, numero=ronda['numero'], nombre=ronda['nombre'], fecha=fecha_ronda))
        for i, enf in enumerate(ronda['enfs']):
            eid = id_enf()
            hora = None if enf['visitante'] is None else \
                dt.datetime.combine(fecha_ronda, dt.time(18, 0)) + dt.timedelta(minutes=90 * (i % 4))
            jugado = enf['res'] is not None
            enfrentamientos.append(dict(id=eid, ronda=rid, local=enf['local'], visitante=enf['visitante'], hora=hora,
                                        estado='jugado' if jugado else 'pendiente'))
            if jugado:
                pl, pv, ganador_enf = enf['res']
                if enf['visitante'] is None:
                    carga = dt.datetime.combine(fecha_ronda, dt.time(8, 0))
                else:
                    carga = hora + dt.timedelta(hours=2, minutes=R.randint(0, 40))
                # Un resultado cargado no puede estar en el futuro (el bye de una ronda ya generada
                # pero cuya fecha todavía no llegó se cargó cuando se generó el fixture).
                carga = min(carga, HOY - dt.timedelta(hours=1, minutes=R.randint(0, 300)))
                resultados.append(dict(id=id_resultado(), enf=eid, pl=pl, pv=pv, ganador=ganador_enf, fecha=carga))
                if enf['visitante'] is not None:
                    audit(carga, actor, 'CARGA_RESULTADO', 'enfrentamientos', eid, f'Resultado {pl} - {pv}')

    if estado == 'finalizado':
        ultima_fecha = fecha_inicio + dt.timedelta(days=7 * (len(sim.rondas) - 1))
        audit(dt.datetime.combine(ultima_fecha, dt.time(23, 30)), actor, 'FINALIZACION_TORNEO', 'torneos', tid,
              f'Campeón: inscripción #{ganador}')


for indice, variante in enumerate(VARIANTES):
    for k in range(3):
        crear_torneo(variante, indice, k)

# ---------------------------------------------------------------------------
# Auditoría: algunos inicios de sesión, y todo ordenado por fecha
# ---------------------------------------------------------------------------
for _ in range(120):
    uid = R.choice(PARTICIPANTES + ORGANIZADORES + [ADMIN])
    audit(aleatorio_entre(HOY - dt.timedelta(days=60), HOY - dt.timedelta(minutes=5)), uid, 'LOGIN', 'usuarios', uid,
          'Inicio de sesión')

for fecha, uid, accion, tabla, registro_id, detalle in sorted(eventos_audit, key=lambda e: (e[0], e[2])):
    auditoria.append(dict(id=id_audit(), usuario=uid, accion=accion, tabla=tabla, registro=registro_id, detalle=detalle,
                          ip=R.choice(['127.0.0.1', '192.168.1.%d' % R.randint(2, 60), '10.0.0.%d' % R.randint(2, 60)]),
                          fecha=fecha))

# ---------------------------------------------------------------------------
# Verificaciones (si algo no cierra, el script falla antes de escribir nada)
# ---------------------------------------------------------------------------
def verificar():
    assert len({u['username'] for u in usuarios}) == len(usuarios)
    assert len({u['email'] for u in usuarios}) == len(usuarios)

    pares = set()
    for m in miembros:
        assert (m['equipo'], m['usuario']) not in pares
        pares.add((m['equipo'], m['usuario']))
    for e in equipos:
        assert EQUIPO_POR_ID[e['id']]['capitan'] == e['miembros'][0]
        n_mie = sum(1 for m in miembros if m['equipo'] == e['id'])
        n_pend = sum(1 for i in invitaciones if i['equipo'] == e['id'] and i['estado'] == 'pendiente')
        assert n_mie + n_pend <= e['tamano'], e

    por_torneo = {}
    miembros_de = {}
    for m in miembros:
        miembros_de.setdefault(m['equipo'], set()).add(m['usuario'])
    for i in inscripciones:
        assert (i['equipo'] is None) != (i['usuario'] is None)                 # arco exclusivo
        clave_u = (i['torneo'], i['usuario']) if i['usuario'] else None
        clave_e = (i['torneo'], i['equipo']) if i['equipo'] else None
        for c in (clave_u, clave_e):
            if c:
                assert c not in por_torneo, c
                por_torneo[c] = True
    for t in torneos:
        inscs = [i for i in inscripciones if i['torneo'] == t['id'] and i['estado'] == 'activa']
        if t['modalidad'] == 'equipo':
            vistos = set()
            for i in inscs:
                eq = EQUIPO_POR_ID[i['equipo']]
                assert eq['deporte'] == t['deporte'] and eq['tamano'] == t['tamano'], (t, eq)
                assert len(miembros_de[i['equipo']]) == t['tamano']
                assert not (miembros_de[i['equipo']] & vistos), 'integrante repetido en un torneo'
                vistos |= miembros_de[i['equipo']]
        if t['cupos'] is not None:
            assert len(inscs) <= t['cupos']
        # fechas: apertura <= cierre <= inicio <= fin, y toda inscripción cae dentro de la ventana
        assert t['apertura'] <= t['cierre'] <= t['inicio'] <= t['fin'], t
        for i in [i for i in inscripciones if i['torneo'] == t['id']]:
            assert dt.datetime.combine(t['apertura'], dt.time(0, 0)) <= i['fecha'] <= dt.datetime.combine(t['cierre'], dt.time(23, 59, 59)), (t['nombre'], i)

    # suizo: nadie repite rival; cada inscripto aparece una sola vez por ronda
    enf_por_ronda = {}
    for e in enfrentamientos:
        enf_por_ronda.setdefault(e['ronda'], []).append(e)
    ronda_por_id = {r['id']: r for r in rondas}
    for t in torneos:
        if t['formato'] != 'suizo':
            continue
        vistos = set()
        for r in [r for r in rondas if r['torneo'] == t['id']]:
            en_ronda = []
            for e in enf_por_ronda[r['id']]:
                en_ronda.append(e['local'])
                if e['visitante'] is not None:
                    en_ronda.append(e['visitante'])
                    par = tuple(sorted((e['local'], e['visitante'])))
                    assert par not in vistos, ('rival repetido', t['nombre'])
                    vistos.add(par)
            assert len(en_ronda) == len(set(en_ronda))

    res_por_enf = {r['enf']: r for r in resultados}
    for e in enfrentamientos:
        assert (e['estado'] == 'jugado') == (e['id'] in res_por_enf)

    # la tabla debe coincidir con los resultados cargados
    for t in torneos:
        if t['estado'] == 'inscripciones_abiertas':
            continue
        sumas = {}
        for r in [r for r in rondas if r['torneo'] == t['id']]:
            for e in enf_por_ronda[r['id']]:
                res = res_por_enf.get(e['id'])
                if res is None or e['visitante'] is None:
                    if res is not None and e['visitante'] is None and t['formato'] == 'suizo':
                        s = sumas.setdefault(e['local'], [0, 0, 0, 0, 0]); s[0] += 3; s[1] += 1; s[2] += 1
                    continue
                for insc, resultado in ((e['local'], 'L'), (e['visitante'], 'V')):
                    s = sumas.setdefault(insc, [0, 0, 0, 0, 0])
                    s[1] += 1
                    if res['ganador'] is None:
                        s[0] += 1; s[3] += 1
                    elif res['ganador'] == insc:
                        s[0] += 3; s[2] += 1
                    else:
                        s[4] += 1
        for fila in [f for f in tabla_pos if any(i['id'] == f['insc'] and i['torneo'] == t['id'] for i in inscripciones)]:
            esperado = sumas.get(fila['insc'], [0, 0, 0, 0, 0])
            assert [fila['pts'], fila['pj'], fila['g'], fila['e'], fila['p']] == esperado, (t['nombre'], fila, esperado)
        if t['estado'] == 'finalizado':
            assert t['ganador'] is not None
        else:
            assert t['ganador'] is None

    for nombre, filas in [('usuarios', usuarios), ('torneos', torneos), ('equipos', equipos), ('equipo_miembros', miembros),
                          ('invitaciones_equipo', invitaciones), ('inscripciones', inscripciones),
                          ('tabla_posiciones', tabla_pos), ('rondas', rondas), ('enfrentamientos', enfrentamientos),
                          ('resultados', resultados), ('auditoria', auditoria)]:
        assert len(filas) >= 50, (nombre, len(filas))


verificar()

# ---------------------------------------------------------------------------
# Escritura del SQL
# ---------------------------------------------------------------------------
def insertar(tabla, columnas, filas, trozo=100):
    salida = []
    for i in range(0, len(filas), trozo):
        grupo = filas[i:i + trozo]
        valores = ',\n'.join('    (' + ', '.join(q(v) for v in fila) + ')' for fila in grupo)
        salida.append(f'INSERT INTO {tabla} ({", ".join(columnas)}) VALUES\n{valores};\n')
    return '\n'.join(salida)


def sql_datos():
    partes = []
    partes.append(insertar('usuarios',
        ['id', 'nombre_usuario', 'nombre_completo', 'email', 'celular', 'contrasena_hash', 'genero', 'rol_id', 'activo', 'fecha_registro'],
        [[u['id'], u['username'], u['nombre'], u['email'], u['celular'], HASH, u['genero'], u['rol'], 1, u['registro']] for u in usuarios]))
    partes.append(insertar('torneos',
        ['id', 'nombre', 'deporte', 'formato', 'modalidad', 'estado', 'fecha_inicio', 'creado_por', 'organizador_asignado_id',
         'fecha_apertura_inscripcion', 'fecha_cierre_inscripcion', 'fecha_fin',
         'cupos', 'jugadores_por_equipo', 'fecha_creacion'],
        [[t['id'], t['nombre'], t['deporte'], t['formato'], t['modalidad'], t['estado'], t['inicio'], t['creado_por'],
          t['organizador'], t['apertura'], t['cierre'], t['fin'], t['cupos'], t['tamano'], t['creado']] for t in torneos]))
    partes.append(insertar('equipos',
        ['id', 'nombre_equipo', 'capitan_id', 'deporte_id', 'deporte', 'jugadores_por_equipo', 'fecha_creacion'],
        [[e['id'], e['nombre'], e['capitan'], Raw(f"(SELECT id FROM deportes WHERE codigo = '{e['codigo']}')"),
          e['deporte'], e['tamano'], e['creado']] for e in equipos]))
    partes.append(insertar('equipo_miembros', ['id', 'equipo_id', 'usuario_id', 'fecha_ingreso'],
        [[m['id'], m['equipo'], m['usuario'], m['ingreso']] for m in miembros]))
    partes.append(insertar('invitaciones_equipo',
        ['id', 'equipo_id', 'usuario_id', 'invitado_por', 'estado', 'fecha_creacion', 'fecha_respuesta'],
        [[i['id'], i['equipo'], i['usuario'], i['por'], i['estado'], i['creada'], i['respuesta']] for i in invitaciones]))
    partes.append(insertar('inscripciones',
        ['id', 'torneo_id', 'equipo_id', 'usuario_id', 'estado_inscripcion', 'fecha_inscripcion'],
        [[i['id'], i['torneo'], i['equipo'], i['usuario'], i['estado'], i['fecha']] for i in inscripciones]))
    partes.append(insertar('tabla_posiciones',
        ['id', 'inscripcion_id', 'posicion', 'puntos', 'partidos_jugados', 'partidos_ganados', 'partidos_empatados', 'partidos_perdidos'],
        [[f['id'], f['insc'], f['pos'], f['pts'], f['pj'], f['g'], f['e'], f['p']] for f in tabla_pos]))
    partes.append(insertar('rondas', ['id', 'torneo_id', 'numero', 'nombre', 'fecha'],
        [[r['id'], r['torneo'], r['numero'], r['nombre'], r['fecha']] for r in rondas]))
    partes.append(insertar('enfrentamientos',
        ['id', 'ronda_id', 'inscripcion_local_id', 'inscripcion_visitante_id', 'fecha_hora', 'estado'],
        [[e['id'], e['ronda'], e['local'], e['visitante'], e['hora'], e['estado']] for e in enfrentamientos]))
    partes.append(insertar('resultados',
        ['id', 'enfrentamiento_id', 'puntaje_local', 'puntaje_visitante', 'inscripcion_ganadora_id', 'validado', 'fecha_carga'],
        [[r['id'], r['enf'], r['pl'], r['pv'], r['ganador'], 1, r['fecha']] for r in resultados]))
    ganadores = [t for t in torneos if t['ganador'] is not None]
    partes.append('\n'.join(f"UPDATE torneos SET ganador_inscripcion_id = {t['ganador']} WHERE id = {t['id']};" for t in ganadores) + '\n')
    partes.append(insertar('auditoria',
        ['id', 'usuario_id', 'accion', 'tabla_afectada', 'registro_id', 'detalle', 'ip', 'fecha'],
        [[a['id'], a['usuario'], a['accion'], a['tabla'], a['registro'], a['detalle'], a['ip'], a['fecha']] for a in auditoria]))
    return '\n'.join(partes)


ENCABEZADO = f"""-- ============================================================
-- sgdm_datos_prueba.sql
-- GENERADO por bd/generar_datos_prueba.py (no editar a mano: se
-- vuelve a generar con `python3 bd/generar_datos_prueba.py`).
--
-- Datos de prueba consistentes para TODAS las tablas. No es para
-- producción.
--
-- Requisitos: tener corridas todas las migraciones (la última es
-- sgdm_migracion_fechas.sql). Se puede correr de nuevo cuando
-- quieras: primero borra los datos de prueba anteriores.
--
-- Qué hay (todo cuelga de usuarios cuyo nombre empieza con test_):
--   {len(usuarios):>4} usuarios      {len(torneos):>4} torneos        {len(equipos):>4} equipos
--   {len(miembros):>4} integrantes    {len(invitaciones):>4} invitaciones   {len(inscripciones):>4} inscripciones
--   {len(rondas):>4} rondas         {len(enfrentamientos):>4} enfrentamientos {len(resultados):>4} resultados
--   {len(tabla_pos):>4} filas de tabla de posiciones, {len(auditoria):>4} registros de auditoría
--
-- Cuentas (la contraseña de TODAS es:  {CLAVE} ):
--   test_admin                      Administrador general
--   test_organizador1 .. {CANTIDAD_ORGANIZADORES}        Organizadores (cada uno gestiona torneos asignados)
--   test_<nombre>01 .. {CANTIDAD_PARTICIPANTES}          Participantes (ej: test_valentina01, test_santiago02)
--
-- Torneos: {len(VARIANTES)} deportes/juegos x 3 estados (finalizado, en curso,
-- inscripciones abiertas) y los 3 formatos (liga, eliminación, suizo).
-- Los finalizados tienen campeón; los en curso tienen la ronda actual a
-- medias (para probar la carga de resultados y "avanzar de ronda"); los
-- abiertos tienen inscriptos, equipos incompletos con invitaciones
-- pendientes, bajas y torneos con el cupo lleno. Algunos abiertos ya
-- cerraron sus inscripciones por fecha y esperan que el organizador
-- genere el fixture (la inscripción se rechaza aunque el estado siga abierto).
-- ============================================================

USE sgdm;

-- ------------------------------------------------------------
-- 1) Limpieza de corridas anteriores (solo toca datos de prueba:
--    todo lo que cuelga de usuarios test_*)
-- ------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM resultados WHERE enfrentamiento_id IN (
    SELECT e.id FROM enfrentamientos e
      JOIN rondas r ON r.id = e.ronda_id
      JOIN torneos t ON t.id = r.torneo_id
     WHERE t.creado_por IN (SELECT id FROM usuarios WHERE nombre_usuario LIKE 'test\\_%'));
DELETE FROM enfrentamientos WHERE ronda_id IN (
    SELECT r.id FROM rondas r JOIN torneos t ON t.id = r.torneo_id
     WHERE t.creado_por IN (SELECT id FROM usuarios WHERE nombre_usuario LIKE 'test\\_%'));
DELETE FROM rondas WHERE torneo_id IN (
    SELECT id FROM torneos WHERE creado_por IN (SELECT id FROM usuarios WHERE nombre_usuario LIKE 'test\\_%'));
DELETE FROM tabla_posiciones WHERE inscripcion_id IN (
    SELECT i.id FROM inscripciones i JOIN torneos t ON t.id = i.torneo_id
     WHERE t.creado_por IN (SELECT id FROM usuarios WHERE nombre_usuario LIKE 'test\\_%'));
DELETE FROM inscripciones WHERE torneo_id IN (
    SELECT id FROM torneos WHERE creado_por IN (SELECT id FROM usuarios WHERE nombre_usuario LIKE 'test\\_%'));
DELETE FROM invitaciones_equipo WHERE equipo_id IN (
    SELECT id FROM equipos WHERE capitan_id IN (SELECT id FROM usuarios WHERE nombre_usuario LIKE 'test\\_%'));
DELETE FROM equipo_miembros WHERE equipo_id IN (
    SELECT id FROM equipos WHERE capitan_id IN (SELECT id FROM usuarios WHERE nombre_usuario LIKE 'test\\_%'));
DELETE FROM equipos WHERE capitan_id IN (SELECT id FROM usuarios WHERE nombre_usuario LIKE 'test\\_%');
DELETE FROM torneos WHERE creado_por IN (SELECT id FROM usuarios WHERE nombre_usuario LIKE 'test\\_%');
DELETE FROM auditoria WHERE usuario_id IN (SELECT id FROM usuarios WHERE nombre_usuario LIKE 'test\\_%');
DELETE FROM usuarios WHERE nombre_usuario LIKE 'test\\_%';

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 2) Datos
-- ------------------------------------------------------------
"""


def main():
    solo_datos = '--solo-datos' in sys.argv     # sin limpieza, para validar en SQLite
    with open(SALIDA if not solo_datos else SALIDA + '.datos', 'w', encoding='utf-8') as f:
        if not solo_datos:
            f.write(ENCABEZADO)
        f.write(sql_datos())
    print(f'OK -> {SALIDA}{".datos" if solo_datos else ""}')
    for nombre, filas in [('usuarios', usuarios), ('torneos', torneos), ('equipos', equipos), ('equipo_miembros', miembros),
                          ('invitaciones_equipo', invitaciones), ('inscripciones', inscripciones),
                          ('tabla_posiciones', tabla_pos), ('rondas', rondas), ('enfrentamientos', enfrentamientos),
                          ('resultados', resultados), ('auditoria', auditoria)]:
        print(f'  {nombre:20s} {len(filas):5d}')


main()
