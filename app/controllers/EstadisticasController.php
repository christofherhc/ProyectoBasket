<?php

/**
 * EstadisticasController - Estadísticas básicas de liga
 */
class EstadisticasController extends Controller {
    private Partido $partidoModel;
    private Equipo $equipoModel;

    public function __construct() {
        require_once __DIR__ . '/../models/Partido.php';
        require_once __DIR__ . '/../models/Equipo.php';
        $this->partidoModel = new Partido();
        $this->equipoModel = new Equipo();
    }

    public function index(): void {
        $this->requiereLogin();

        $partidos = $this->partidoModel->obtenerTodos();

        // Se parte de TODOS los equipos, no solo de los que aparecen en un
        // partido. Antes el mapa se armaba desde los partidos, así que un
        // equipo recién creado y sin partidos desaparecía de la tabla.
        $stats = [];
        foreach ($this->equipoModel->obtenerTodos() as $equipo) {
            $eid = (int)$equipo['id'];
            $stats[$eid] = [
                'equipo_id' => $eid,
                'nombre'    => $equipo['nombre'],
                'pj' => 0,
                'pg' => 0,
                'pe' => 0,
                'pp' => 0,
                'gf' => 0,
                'gc' => 0,
                'dif' => 0
            ];
        }

        // Procesar partidos
        foreach ($partidos as $p) {
            $el = (int)$p['equipo_local_id'];
            $ev = (int)$p['equipo_visitante_id'];
            $pl = (int)$p['puntos_local'];
            $pv = (int)$p['puntos_visitante'];

            if (!isset($stats[$el]) || !isset($stats[$ev])) {
                continue;
            }

            // PJ
            $stats[$el]['pj']++;
            $stats[$ev]['pj']++;
            // GF/GC
            $stats[$el]['gf'] += $pl;
            $stats[$el]['gc'] += $pv;
            $stats[$ev]['gf'] += $pv;
            $stats[$ev]['gc'] += $pl;

            // Resultado
            if ($pl > $pv) {
                $stats[$el]['pg']++;
                $stats[$ev]['pp']++;
            } elseif ($pv > $pl) {
                $stats[$ev]['pg']++;
                $stats[$el]['pp']++;
            } else {
                $stats[$el]['pe']++;
                $stats[$ev]['pe']++;
            }
        }

        // Calcular diferencia
        foreach ($stats as $k => $v) {
            $stats[$k]['dif'] = $v['gf'] - $v['gc'];
        }

        // Ordenar por PG desc, DIF desc
        usort($stats, function ($a, $b) {
            if ($a['pg'] !== $b['pg']) {
                return $b['pg'] <=> $a['pg'];
            }
            return $b['dif'] <=> $a['dif'];
        });

        $this->view('estadisticas/index', [
            'titulo' => 'Estadísticas de la Liga',
            'stats' => $stats,
            'partidos' => $partidos
        ]);
    }
}