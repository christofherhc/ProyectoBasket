# EstadisticasController - Explicación

1. Obtiene partidos con nombres
2. Construye equiposMap
3. Inicializa stats [pj,pg,pe,pp,gf,gc,dif]
4. Procesa cada partido: acumula PJ, GF/GC, W/D/L
5. Calcula DIF = GF-GC
6. usort: PG desc, DIF desc
7. Pasa a vista (stats + últimos 10)
