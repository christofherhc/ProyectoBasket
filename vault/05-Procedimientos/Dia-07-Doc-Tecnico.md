# Día 07 - Documento Técnico y vault de aprendizaje

**Fecha:** 2026-10-03
**Rama:** `main`
**Objetivo:** Redactar la documentación del proyecto y montar el vault de conocimiento.
**Temas a comprender:** qué distingue a un documento técnico de un resumen, cómo escribir código autodocumentado, y para qué sirve un vault personal.

## 1. Contexto

El código ya funciona. Falta lo que un proyecto necesita para ser **entregable**: que otra persona (o tú mismo dentro de tres meses) pueda entender qué se hizo y por qué.

**Un documento técnico no es un resumen.** Un resumen cuenta *qué* hay. Un documento técnico cuenta **qué, por qué y con qué consecuencias**.

## 2. Preparación

- Los 6 módulos implementados y verificados.
- El registro de errores con los problemas encontrados.
- Decidir la estructura del vault.

## 3. Pasos realizados

### Paso 7.1: Redactar `doc/practica_liga.md`

Documento con las secciones exigidas por la práctica:

| Sección | Contenido |
|---|---|
| Portada | Título, autor, curso, fecha |
| Introducción | Problema y objetivo |
| Marco teórico | PHP procedural, patrón MVC, PDO, sesiones |
| Desarrollo | Arquitectura, estructura, base de datos, módulos |
| Pruebas | Verificación de cada módulo |
| Conclusiones | Lo aprendido |
| Bibliografía | Manuales de PHP y MySQL/MariaDB |

⚠️ **Un detalle de formato:** el enunciado pedía 500-600 palabras en la parte descriptiva. Escribirlo a la primeradio cuesta más que ajustarlo después, porque contar palabras de forma aproximada obliga a recortar o expandir frases a ciegas. La versión final quedó ajustada a la extensión pedida.

### Paso 7.2: Escribir el documento como ejemplo

Además del documento de la práctica, se creó `doc/ejemplo_documento_tecnico.md`: un ejemplo reducido, bien estructurado, que sirve de plantilla.

**La idea:** cuando tengas que escribir un documento técnico en el futuro, no empieces de cero. Parte de una estructura que ya funciona.

### Paso 7.3: Montar el vault

El vault es la parte personal del proyecto: **todo lo aprendido, explica desde cero, para poder releerlo**.

```
vault/
├── 00-MOC/                 Índice general (Map of Content)
├── 01-Introduccion/        Qué es el proyecto y qué se aprende
├── 02-Fundamentos/        PHP, PDO, MVC, routing, sesiones, Git
├── 03-Arquitectura/       Decisiones de diseño, estructura, diagrama
├── 04-BD/                 Schema, datos de prueba, modelo ER
├── 05-Procedimientos/     Un archivo por día de trabajo
├── 06-Codigo-Explicado/   Cada archivo del proyecto explicado
├── 07-Diario-de-Aprendizaje/  Qué aprendiste cada día
├── 08-Errores-y-Soluciones/   Errores encontrados y sus causas
├── 09-Glosario/           Términos técnicos definidos
└── 10-Recursos/           Enlaces de estudio
```

⚠️ **El MOC (Map of Content) es lo que hace utilizable un vault.** Sin un índice que apunte a todo, un vault es solo una carpeta de archivos sueltos. Está en `00-MOC/Proyecto-Liga-Basquet-MOC.md` y es el archivo al que se entra primero.

### Paso 7.4: Los procedimientos (un archivo por día)

Los 8 archivos de `05-Procedimientos/` usan una plantilla fija (`_Templates/Plantilla-Dia-Procedimiento.md`) con estas secciones:

```
1. Contexto
2. Preparación
3. Pasos realizados (detallados, paso a paso)
4. Archivos creados/modificados
5. Explicación técnica (amplia)
6. Pruebas realizadas
7. Commits realizados
8. Aprendizajes del día
9. Dudas pendientes y aclaraciones
10. Próximos pasos
```

⚠️ **La sección 9 ("Dudas pendientes") es la más valiosa y la que más gente se salta.** Documentar lo que **no** funciona o no está resuelto es más útil que documentar lo que sí:
- Te avisa a ti mismo de lo pendiente.
- Demuestra honestidad técnica en una evaluación.
- Si vuelves al proyecto dentro de seis meses, sabes por dónde seguir.

### Paso 7.5: El código explicado (cada archivo)

`06-Codigo-Explicado/` tiene **un archivo por cada archivo del proyecto**, con el mismo esquema:

```
1. Qué problema resuelve
2. El código completo
3. Explicación línea a línea
4. Errores frecuentes
5. Archivos relacionados
```

⚠️ **Regla que se aplicó:** en cada explicación se incluyen los **errores frecuentes** de esa pieza. Explicar solo cómo funciona algo esútil; explicar **cómo se rompe** es lo que hace que el aprendizaje se quede.

## 4. Archivos creados/modificados

| Archivo | Tipo | Descripción |
|---|---|---|
| `doc/practica_liga.md` | crear | Documento técnico de la práctica (500-600 palabras descriptivas) |
| `doc/ejemplo_documento_tecnico.md` | crear | Ejemplo reducido que sirve de plantilla |
| `vault/00-MOC/Proyecto-Liga-Basquet-MOC.md` | crear | Índice general del vault |
| `vault/01-Introduccion/*` | crear | Objetivo del proyecto y qué se aprende |
| `vault/02-Fundamentos/*` | crear | 6 documentos: PHP, PDO, MVC, routing, sesiones, Git |
| `vault/03-Arquitectura/*` | crear | Decisiones de diseño, diagrama, estructura |
| `vault/04-BD/*` | crear | Schema explicado, datos de prueba, modelo ER |
| `vault/05-Procedimientos/*` | crear | 8 archivos, uno por día |
| `vault/06-Codigo-Explicado/*` | crear | 15 archivos (core, controllers, models, vistas) |
| `vault/07-Diario-de-Aprendizaje/*` | crear | 7 archivos, uno por día |
| `vault/08-Errores-y-Soluciones/*` | crear | Registro de errores con causa y solución |
| `vault/09-Glosario/Terminos-clave.md` | crear | ~30 términos definidos |
| `vault/10-Recursos/Enlaces-de-estudio.md` | crear | Enlaces a documentación oficial |
| `vault/_Templates/*` | crear | Plantillas reutilizables |

## 5. Explicación técnica: qué hace "técnico" a un documento

Comparación de dos frases sobre el mismo código:

**Versión resumen (no es un documento técnico):**
> Usamos PDO para conectar con la base de datos.

**Versión documento técnico:**
> La conexión usa PDO en lugar de `mysqli` porque permite desactivar la emulación de
> prepared statements (`ATTR_EMULATE_PREPARES => false`). Con la emulación activada, PDO
> construye la consulta concatenando los valores escapados y solo luego la envía; al
> desactivarla, la consulta y los valores viajan separados hasta el servidor y es MySQL
> quien la prepara. Eso elimina por completo la clase de ataques por inyección que
> depende de que el escapado falle. El coste es un viaje extra al servidor por consulta
> y algunas restricciones (no se admiten sentencias múltiples ni reutilizar un mismo
> placeholder con dos tipos distintos). Como ventaja, los placeholders con nombre
> (`:id`) siguen funcionando y **se pueden repetir** dentro de la misma consulta, algo que
> la emulación no permite; por eso los 4 modelos del proyecto los usan en lugar de `?`.

**Las diferencias:**
- Explica la **decisión**, no solo el uso.
- Nombra el **trade-off** (un viaje extra al servidor y las restricciones de las sentencias nativas).
- Da el **motivo** (el escapado deja de ser la barrera contra la inyección).
- Dice **qué se ganó** al aplicarlo (placeholders con nombre reutilizables).

⚠️ **Un documento técnico sin decisiones es un manual de uso.** Lo que lo hace técnico es documentar *por qué* se eligió cada cosa, incluidas las cosas que se descartaron.

⚠️ **Y un documento técnico con datos inventados es peor que ninguno.** La primera versión de este párrafo afirmaba que con `ATTR_EMULATE_PREPARES => false` solo se podían usar placeholders `?`. Es falso, y además contradecía el código del proyecto, que usa `:id` en todos los modelos. La moraleja: **cada afirmación técnica del documento tiene que poder comprobarse en el código.**

## 6. Pruebas realizadas

| Prueba | Resultado |
|---|---|
| `doc/practica_liga.md` tiene todas las secciones exigidas | ✅ |
| La extensión de la parte descriptiva está en 500-600 palabras | ✅ |
| Todos los enlaces del MOC apuntan a archivos existentes | ✅ |
| Cada archivo del proyecto tiene su explicación en `06-Codigo-Explicado` | ✅ (15 archivos) |
| Los 8 procedimientos tienen las 10 secciones de la plantilla | ✅ |
| El registro de errores tiene causa y solución en cada entrada | ✅ |
| El glosario define los términos usados en el vault | ✅ |

## 7. Commits realizados

```
docs: añade documento técnico practica_liga.md
docs: crea vault de conocimiento con 10 secciones
```

## 8. Aprendizajes del día

1. **Un documento técnico documenta decisiones, no hechos.** "Qué usé" es un resumen; "qué usé y por qué, y qué descarté" es un documento técnico.
2. **Documentar el error es tan valioso como documentar la solución.** El error explica por qué el código es como es.
3. **Un vault sin índice es una carpeta inútil.** El MOC es lo que lo convierte en knowledge base.
4. **Escribir las limitaciones pending da más valor que ocultarlas.** En una evaluación, reconocer lo que falta es una señal de madurez técnica.
5. **Escribir la explicación de un archivo obliga a entenderlo.** Al escribir `Router-explicado.md` aparecieron preguntas ("¿por qué `ucfirst(strtolower())`?") que en el código no se habían hecho.
6. **Las plantillas hacen que la documentación sea uniforme.** Con `_Templates/`, los 8 procedimientos tienen la misma estructura y se pueden comparar.
7. **Documentar los errores frecuentes de cada pieza multiplica el valor.** La mitad del aprendizaje está en cómo se rompe el código.

## 9. Dudas pendientes

⚠️ **Pendiente de documentación en este día:**

| # | Pendiente | Nota |
|---|---|---|
| 1 | **`06-Codigo-Explicado` incompleto** | Muchos archivos tenían 3-8 líneas. Deben reescribirse a fondo |
| 2 | **`05-Procedimientos` días 04-08 incompletos** | Eran de 2-7 líneas |
| 3 | **`07-Diario` días 04-08 incompletos** | Eran de 2 líneas |
| 4 | **`04-BD/schema-explicado.md` desactualizado** | Describía la versión antigua del schema (con `CREATE INDEX` sueltos) |
| 5 | **`08-Errores-y-Soluciones` incompleto** | Le faltaban los errores del día 8 |
| 6 | Los ejemplos del documento técnico usan `substr($fecha, 0, 16)` **sin** el `str_replace` | Refleja la solución incompleta del bug del `datetime-local` |

⚠️ **El punto 6 es el más delicado:** documentar una solución a medias como si fuera correcta es peor que no documentarlo. Se resolvió en el día 8 al corregir el bug.

## 10. Próximos pasos

**Día 08 - Verificación y corrección:**
- Batería de pruebas funcionales.
- Corrección de los bugs reales detectados.
- Corrección de la documentación desactualizada (los puntos 1-6 de arriba).