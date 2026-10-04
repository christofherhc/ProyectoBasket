# Diario de Aprendizaje - Día 07

**Fecha:** 2026-10-03
**Módulo:** Documento técnico + vault

## Aprendido hoy

**Qué hace "técnico" a un documento**
Comparando dos frases sobre la misma conexión:
- ❌ "Usamos PDO para conectar con la base de datos." → un resumen.
- ✔️ "PDO con `ATTR_EMULATE_PREPARES => false` porque así la consulta y los valores viajan separados hasta el servidor y es MySQL quien la prepara; el coste es un viaje extra por consulta, y como ventaja los placeholders con nombre se pueden repetir en la misma consulta." → un documento técnico.

La diferencia no es la extensión: es que documenta **la decisión, el motivo, el coste y la ganancia**.

**El MOC (Map of Content)**
`vault/00-MOC/Proyecto-Liga-Basquet-MOC.md` es el índice que hace utilizable el vault. Sin él, un vault es una carpeta de archivos sueltos.

**Documentar lo que NO funciona**
La sección 9 de cada procedimiento ("Dudas pendientes y aclaraciones") resultó ser la más valiosa. Documentar las limitaciones:
- Te avisa a ti mismo de lo pendiente.
- Demuestra honestidad técnica.
- Si vuelves en seis meses, sabes por dónde seguir.

**Escribir la explicación obliga a entender**
Al escribir `Router-explicado.md` aparecieron preguntas que en el código no se habían hecho: "¿por qué `ucfirst(strtolower())`?" y "¿debería `Router` validar el login?". Ninguna era un bug, pero las dos son reales.

## Lo que más me costó

⚠️ **Ajustar el documento a 500-600 palabras.** Escribirlo a la primera cuesta más que ajustarlo después: contar palabras de forma aproximada obliga a recortar o expandir frases a ciegas.

⚠️ **Ser honesto sobre las carencias del vault.** Al montar la estructura se vio que muchos archivos de `06-Codigo-Explicado` tenían 3-8 líneas, y que `04-BD/schema-explicado.md` describía una versión del schema que ya no existía. Se anotó como pendiente del día 7 y se resolvió en el día 8.

⚠️ **Escribir las limitaciones sin sonar a excusa.** La diferencia está en dar el número: "no hay protección CSRF" es una limitación; "no hay protección CSRF, y se arregla con un token por sesión en el formulario + comparación en el controlador" es una limitación con solución.

## Dudas aclaradas
- ¿El vault dentro del proyecto o fuera? Dentro: viaja con el código y queda versionado.
- ¿Un `.md` por archivo de código o uno grande? Uno por archivo, porque se busca por archivo concreto.
- ¿Documentar los errores frecuentes en cada explicación? Sí, es la mitad del aprendizaje.

## Errores de hoy
- Documento de 900 palabras en la parte descriptiva → recortado a la extensión pedida.
- `schema-explicado.md` describiendo `CREATE INDEX` sueltos que ya no existen en el schema → marcado para el día 8.

## Decisiones tomadas
- Plantillas reutilizables en `vault/_Templates/` para que los 8 procedimientos tengan la misma estructura.
- Los procedimientos usan "Paso 6.1", "Paso 6.2"... porque permiten retomar un día a mitad.
- El glosario se limita a los términos realmente usados en el proyecto, no a todo el vocabulario de PHP.

## Próximo
**Día 08:** verificación funcional completa, corrección de bugs y corrección de la documentación desactualizada.