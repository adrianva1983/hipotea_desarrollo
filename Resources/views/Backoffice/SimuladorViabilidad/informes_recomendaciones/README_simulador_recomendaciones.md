# Informe de recomendaciones ? Simulador de Viabilidad Hipotecaria

Resumen
-------
Este documento explica de forma clara y entendible las recomendaciones que genera el Simulador de Viabilidad Hipotecaria de Hipotea. Está pensado para entregarlo al cliente junto con la simulación o como documento independiente.

Qué recibe el cliente
---------------------
- Un resumen ejecutivo del estado de viabilidad (Verde / Media / Baja).
- Recomendaciones personalizadas para mejorar la viabilidad (cada recomendación con su justificación y pasos prácticos).
- Valores clave usados en la simulación (porcentaje financiación objetivo, aportación actual, ratio de endeudamiento, precio del inmueble, gastos estimados).
- Contacto del asesor para solicitar recalculado o pasos operativos.

Estructura del informe por recomendación
----------------------------------------
Cada informe específico contiene:
- Título de la recomendación (ej. "Aumentar aportación inicial").
- Resumen ejecutivo con el objetivo (qué se consigue).
- Impacto estimado con cifras (diferencia de aportación, cambio de ratio, etc.).
- Pasos prácticos a seguir (ordenados por prioridad).
- Riesgos o consideraciones adicionales (costes, implicaciones legales).
- Contacto y siguiente acción propuesta (re-simular, tasación, análisis de cotitular).

Recomendaciones habituales (explicadas para el cliente)
-------------------------------------------------------
- Aumentar aportación inicial: reducir la financiación necesaria para acceder a mejores condiciones.
- Reducir deudas activas: prioridad en préstamos con cuotas impagadas; disminuye el ratio de endeudamiento.
- Ampliar plazo: reducir cuota mensual si la edad y normativa lo permiten.
- Esperar mayor antigüedad laboral: aplicable a autónomos o contratos con antigüedad insuficiente.
- Buscar cotitular: incorporar a otra persona con perfil estable para mejorar el riesgo conjunto.
- Aportar vivienda como aval: alternativa cuando no hay ahorro suficiente, con implicaciones legales.
- Mejorar ingresos o reducir precio: opciones para bajar ratio y cuota.

Cómo interpretar las cifras
---------------------------
- `Financiación objetivo (financiacionVerde)`: porcentaje que la entidad consideraría óptimo.
- `Aportación actual` y `Diferencia recomendada`: cuánto sería necesario aportar adicionalmente para alcanzar el objetivo.
- `Ratio de endeudamiento`: porcentaje que compara cuota mensual con ingresos netos; referencias internas: ideal < 35%, atención > 45%.

Entrega y formatos
------------------
- Podemos entregar cada recomendación como: HTML imprimible, PDF listo para enviar, o fichero adjunto al expediente CRM.
- En la implementación actual, el sistema genera `informe_html` y puede convertirlo a PDF para adjuntar al expediente.

Opciones operativas que ofrecemos
---------------------------------
- Re-simulación inmediata con aportación alternativa, cotitular o plazo ampliado.
- Preparación de comparativas de cuotas y entidades para el cliente.
- Coordinación de tasación y asesoría legal si procede (en caso de aval o ejecución hipotecaria).

Privacidad y seguridad
----------------------
Los informes pueden contener datos personales básicos (nombre, DNI, email). Si solicita adjuntar al CRM, verificamos límites de uso por email y guardamos con control de accesos interno.

Siguientes pasos recomendados para el cliente
--------------------------------------------
1. Revisar la/s recomendación/es incluidas en el informe.
2. Contactar al asesor indicado para solicitar re-simulación o iniciar gestiones.
3. Si procede, solicitar que preparemos el PDF oficial para adjuntar al expediente.

Contacto
--------
Asesor asignado: {{ asesor.nombre|default('Tu asesor Hipotea') }} ? {{ asesor.email|default('asesor@hipotea.es') }}

---

Archivo generado automáticamente por el módulo de Simulador de Viabilidad. Para cambios de estilo, contenidos o inclusión de más datos numéricos, solicitar ajuste al equipo técnico.