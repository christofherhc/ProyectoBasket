<?php

/**
 * EstadisticasController - Estadísticas básicas de liga
 */
class EstadisticasController extends Controller {
    private Partido $partidoModel;

    public function __construct() {
        require_once '../app/models/Partido.php';
        $this->partidoModel = new Partido();
    }

    public function index(): void {
        $this->requiereLogin();
        
        // Obtener todos los partidos con nombres
        $partidos = $this->partidoModel->obtenerTodos();
        
        // Mapa de equipos: id => nombre
        $equiposMap = [];
        foreach ($partidos as $p) {
            $equiposMap[$p['equipo_local_id']] = $p['nombre_local'];
            $equiposMap[$p['equipo_visitante_id']] = $p['nombre_visitante'];
        }
        
        // Calcular estadísticas por equipo
        $stats = [];
        foreach (array_unique(array_keys($equiposMap)) as $eid) {
            $eidInt = (int)$eid;
            $stats[$eidInt] = [
                'equipo_id' => $eidInt,
                'nombre' => $equiposMap[$eidInt] ?? '',
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
