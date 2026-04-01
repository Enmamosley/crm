# Funciones Personalizadas DM Champ

## FUNCIÓN 1: consultar_servicios

```json
{
  "name": "consultar_servicios",
  "description": "Obtiene la lista de servicios disponibles con precios actualizados del CRM. Usar cuando el usuario pregunta qué servicios se ofrecen, cuánto cuesta algo, planes, hosting, dominios, correos, desarrollo web o cualquier producto.",
  "url": "https://app.mosley.digital/api/v1/dmchamp/servicios",
  "method": "POST",
  "ai_action": "Presenta los servicios de forma organizada con nombre, precio con IVA y descripción breve. Agrupa por categoría si hay varios. Comparte el link de compra cuando esté disponible. Invita al usuario a contratar.",
  "headers": [
    {
      "key": "X-DmChamp-Token",
      "value": "c257779fa4139f72b5e0773b0e438fe273d374fb93fcdbbea4e7e586dc83cd76"
    },
    {
      "key": "Content-Type",
      "value": "application/json"
    }
  ],
  "input": [
    {
      "name": "buscar",
      "type": "string",
      "description": "Tipo de servicio que busca el cliente extraído de la conversación: hosting, dominio, correo, desarrollo, etc. Dejar vacío para ver todo el catálogo.",
      "required": false
    }
  ],
  "response_mapping": {
    "encontrados": "Cantidad de servicios encontrados",
    "mensaje": "Mensaje resumen para el cliente",
    "servicios": "Lista de servicios. Cada uno tiene: nombre, descripcion, precio (sin IVA), precio_con_iva (precio final que paga el cliente), categoria, mas_info (enlace con más detalles), comprar (enlace directo para contratar)"
  }
}
```

## FUNCIÓN 2: estado_de_cuenta

```json
{
  "name": "estado_de_cuenta",
  "description": "Consulta el estado de cuenta del cliente: facturas pendientes, montos y últimos pagos. Usar cuando el cliente pregunta si tiene algo pendiente, cuánto debe, sus facturas o su estado de cuenta.",
  "url": "https://app.mosley.digital/api/v1/dmchamp/cliente",
  "method": "POST",
  "ai_action": "Usa el teléfono del contacto automáticamente (no lo pidas). Presenta el resumen de forma clara: nombre del cliente, facturas pendientes con folio y monto, total a pagar. Si está al corriente, felicítalo. Si tiene pendientes, ofrece enviar link de pago.",
  "headers": [
    {
      "key": "X-DmChamp-Token",
      "value": "c257779fa4139f72b5e0773b0e438fe273d374fb93fcdbbea4e7e586dc83cd76"
    },
    {
      "key": "Content-Type",
      "value": "application/json"
    }
  ],
  "input": [],
  "response_mapping": {
    "encontrado": "Si se encontró al cliente en el sistema",
    "nombre": "Nombre del cliente",
    "email": "Email registrado del cliente",
    "facturas_pendientes": "Cantidad de facturas sin pagar",
    "total_pendiente": "Monto total que adeuda el cliente",
    "detalle_pendientes": "Lista de facturas pendientes, cada una con folio, total y fecha",
    "ultimos_pagos": "Últimos 3 pagos realizados con folio, total y fecha de pago",
    "mensaje": "Resumen del estado de cuenta listo para comunicar al cliente"
  }
}
```

## FUNCIÓN 3: registrar_prospecto

```json
{
  "name": "registrar_prospecto",
  "description": "Registra un nuevo prospecto/lead en el CRM. Usar cuando el bot identifica interés genuino del usuario en contratar un servicio, o cuando el usuario quiere que un asesor lo contacte.",
  "url": "https://app.mosley.digital/api/v1/dmchamp/lead",
  "method": "POST",
  "ai_action": "No llames esta función de inmediato. Primero recopila la información posible de la conversación (nombre, servicio de interés, notas). El teléfono y email se envían automáticamente. Solo llámala cuando el usuario confirme que quiere ser contactado o muestre interés claro.",
  "headers": [
    {
      "key": "X-DmChamp-Token",
      "value": "c257779fa4139f72b5e0773b0e438fe273d374fb93fcdbbea4e7e586dc83cd76"
    },
    {
      "key": "Content-Type",
      "value": "application/json"
    }
  ],
  "input": [
    {
      "name": "name",
      "type": "string",
      "description": "Nombre completo del prospecto extraído de la conversación. Si no lo mencionó, dejar vacío.",
      "required": false
    },
    {
      "name": "service",
      "type": "string",
      "description": "Servicio en el que está interesado el prospecto (ej: hosting, dominio, desarrollo web).",
      "required": false
    },
    {
      "name": "notes",
      "type": "string",
      "description": "Resumen breve de la conversación o requerimientos específicos del prospecto.",
      "required": false
    }
  ],
  "response_mapping": {
    "creado": "Si se creó el prospecto exitosamente o ya existía",
    "lead_id": "ID del prospecto en el CRM",
    "mensaje": "Mensaje de confirmación para comunicar al cliente"
  }
}
```

## INSTRUCCIONES DEL BOT (agregar al prompt de la campaña)

```
#custom_functions
Tienes acceso a funciones que conectan directamente con el sistema CRM de Mosley Digital Services. DEBES usarlas en las siguientes situaciones:

1. consultar_servicios — Cuando el prospecto pregunte por servicios, precios, planes, hosting, dominios, correos, desarrollo web o cualquier producto. SIEMPRE consulta esta función antes de responder sobre precios o servicios. NO inventes precios ni respondas de memoria. Los datos reales están en el CRM.

2. estado_de_cuenta — Cuando un cliente existente pregunte por su estado de cuenta, facturas pendientes, cuánto debe o sus pagos. Esta función usa el teléfono del contacto automáticamente, no necesitas pedírselo.

3. registrar_prospecto — Cuando identifiques interés genuino del prospecto en contratar un servicio, o cuando solicite ser contactado por un asesor. Primero recopila nombre y servicio de interés de la conversación. El teléfono se envía automáticamente.

Reglas sobre funciones:
- NUNCA compartas precios sin antes consultar la función consultar_servicios
- Los precios devueltos incluyen precio sin IVA y precio con IVA (precio final)
- Si la función devuelve un link de compra, compártelo al prospecto
- Si la función retorna un campo "mensaje", úsalo textualmente para responder al cliente
- Si la función falla técnicamente, responde: "En este momento no puedo consultar esa información, pero con gusto te atiendo. ¿Me puedes decir qué servicio te interesa?"
```
