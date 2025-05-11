# GeoBloqueo By Daz

## Descripción
Plugin de WordPress que bloquea contenido dependiendo de la distancia del usuario a una ubicación específica. Ideal para asegurar que solo usuarios físicamente presentes en un lugar determinado puedan acceder a ciertos contenidos.

## Características
- Restricción de acceso basada en geolocalización
- Radio configurable (200 metros por defecto)
- Detección básica de VPN
- Registro detallado de accesos y dispositivos
- Panel de administración para ver y exportar registros
- Integración con formularios de Gravity Forms

## Instalación
1. Descarga el plugin y sube la carpeta `geobloqueo-by-daz` a tu directorio `/wp-content/plugins/`
2. Activa el plugin a través del menú 'Plugins' en WordPress
3. El plugin comenzará a funcionar automáticamente

## Configuración
El plugin viene preconfigurado con las siguientes coordenadas:
- Ubicación: Vicuña Mackenna Poniente 6482, La Florida, Santiago de Chile
- Coordenadas: Latitud -33.5209, Longitud -70.5855
- Radio: 200 metros

## Uso
Una vez activado, el plugin:
1. Bloqueará el acceso al formulario de consentimiento con un overlay negro
2. Solicitará permiso para acceder a la ubicación del usuario
3. Verificará si el usuario está dentro del radio permitido
4. Registrará el intento de acceso con información detallada del dispositivo
5. Permitirá o denegará el acceso según la ubicación

## Registros
El plugin mantiene un registro detallado de todos los intentos de acceso, incluyendo:
- Dirección IP
- Coordenadas geográficas
- Distancia al estudio
- Información del dispositivo
- Nombre del dispositivo
- Red utilizada
- Fecha y hora
- Resultado del intento

Los registros pueden exportarse a CSV desde el panel de administración.

## Autor
DazTheLine (Hola@Daz.cl)