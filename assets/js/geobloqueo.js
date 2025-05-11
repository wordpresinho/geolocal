(function($) {
    'use strict';

    // Check if geolocation is supported
    function isGeolocationSupported() {
        return 'geolocation' in navigator;
    }

    // Get device name and info
    function getDeviceInfo() {
        let deviceInfo = {};
        
        // Get OS info
        const userAgent = navigator.userAgent;
        let os = "Unknown OS";
        
        if (userAgent.indexOf("Win") !== -1) os = "Windows";
        if (userAgent.indexOf("Mac") !== -1) os = "MacOS";
        if (userAgent.indexOf("Linux") !== -1) os = "Linux";
        if (userAgent.indexOf("Android") !== -1) os = "Android";
        if (userAgent.indexOf("iOS") !== -1 || userAgent.indexOf("iPhone") !== -1 || userAgent.indexOf("iPad") !== -1) os = "iOS";
        
        // Get browser info
        let browser = "Unknown Browser";
        
        if (userAgent.indexOf("Chrome") !== -1) browser = "Chrome";
        if (userAgent.indexOf("Firefox") !== -1) browser = "Firefox";
        if (userAgent.indexOf("Safari") !== -1 && userAgent.indexOf("Chrome") === -1) browser = "Safari";
        if (userAgent.indexOf("Edge") !== -1) browser = "Edge";
        if (userAgent.indexOf("MSIE") !== -1 || userAgent.indexOf("Trident") !== -1) browser = "Internet Explorer";
        
        // Device type
        let deviceType = "Desktop";
        
        if (/Mobi|Android|iPhone|iPad|iPod/i.test(userAgent)) {
            deviceType = "Mobile";
        } else if (/Tablet|iPad/i.test(userAgent)) {
            deviceType = "Tablet";
        }
        
        deviceInfo.os = os;
        deviceInfo.browser = browser;
        deviceInfo.deviceType = deviceType;
        
        return deviceType + " - " + os + " - " + browser;
    }
    
    // Get device name if available
    function getDeviceName() {
        // Try to get device name
        let deviceName = "";
        
        // For modern browsers
        if (navigator.userAgentData && navigator.userAgentData.platform) {
            deviceName = navigator.userAgentData.platform;
        }
        
        // For older browsers or fallback
        if (!deviceName && navigator.platform) {
            deviceName = navigator.platform;
        }
        
        // Check for mobile devices
        if (/iPhone/.test(navigator.userAgent)) {
            if (/iPhone/.test(navigator.platform)) {
                // Try to detect specific iPhone model
                const match = navigator.userAgent.match(/iPhone\s+OS\s+(\d+)_(\d+)/i);
                if (match) {
                    deviceName = "iPhone (iOS " + match[1] + "." + match[2] + ")";
                } else {
                    deviceName = "iPhone";
                }
            }
        }
        
        if (/iPad/.test(navigator.userAgent)) {
            deviceName = "iPad";
        }
        
        if (/Android/.test(navigator.userAgent)) {
            // Try to get Android device model
            const match = navigator.userAgent.match(/Android\s+([0-9.]+);\s+(.*?(?=\s+Build|\s+[;)]))/i);
            if (match) {
                deviceName = match[2] + " (Android " + match[1] + ")";
            } else {
                deviceName = "Android Device";
            }
        }
        
        // Fallback if we couldn't determine a good name
        if (!deviceName || deviceName === "") {
            deviceName = "Dispositivo desconocido";
        }
        
        return deviceName;
    }

    // Formatear distancia
    function formatDistance(meters) {
        if (meters >= 1000) {
            return (meters / 1000).toFixed(2) + ' kilómetros';
        }
        return Math.round(meters) + ' metros';
    }

    // Reemplazar etiquetas dinámicas en el texto
    function replaceMessageTags(text, data) {
        return text
            .replace('{dispositivo}', data.deviceName)
            .replace('{browser}', data.browser)
            .replace('{distancia}', data.distance)
            .replace('{os}', data.os);
    }

    // Verificar si la página debe ser bloqueada
    function shouldBlockPage() {
        // Obtener la URL actual
        const currentUrl = window.location.href;
        
        // Verificar si es la página de consentimiento o tiene un formulario de Gravity Forms
        return currentUrl.includes('consentimiento-tatuaje') || 
               document.querySelector('.gform_wrapper') !== null;
    }

    // Redirigir al usuario
    function redirectUser() {
        window.location.href = geobloqueo_vars.home_url;
    }

    // Handle geolocation permission
    function handleGeolocation() {
        const $overlay = $('#geobloqueo-overlay');
        const $errorMsg = $('#geobloqueo-error');
        
        if (!isGeolocationSupported()) {
            $errorMsg.text('Tu navegador no soporta geolocalización. Por favor, utiliza un navegador moderno.').show();
            setTimeout(redirectUser, 3000);
            return;
        }
        
        // Add checking message with spinner
        const $buttonContainer = $('#geobloqueo-activate').parent();
        $('#geobloqueo-activate').hide();
        $buttonContainer.append('<div class="checking-location"><div class="location-spinner"></div><span>Verificando ubicación...</span></div>');
        
        // Get current position with high accuracy
        navigator.geolocation.getCurrentPosition(
            // Success callback
            function(position) {
                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;
                
                const deviceInfo = getDeviceInfo();
                const deviceName = getDeviceName();
                
                // Make AJAX request to check location
                $.ajax({
                    url: geobloqueo_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'check_location',
                        nonce: geobloqueo_vars.nonce,
                        lat: latitude,
                        lng: longitude,
                        device_info: deviceInfo,
                        device_name: deviceName,
                        accuracy: position.coords.accuracy
                    },
                    success: function(response) {
                        $('.checking-location').remove();
                        $('#geobloqueo-activate').show();
                        
                        if (response.success) {
                            // Location is valid but keep overlay for next check
                            $errorMsg.hide();
                            sessionStorage.setItem('last_check', Date.now());
                        } else {
                            // Location is invalid
                            const messageData = {
                                deviceName: deviceName,
                                browser: deviceInfo.split(' - ')[2],
                                distance: formatDistance(response.data.distance),
                                os: deviceInfo.split(' - ')[1]
                            };
                            
                            let errorMessage = response.data.using_vpn ? 
                                geobloqueo_vars.messages.error_vpn :
                                replaceMessageTags(response.data.message, messageData);
                            
                            $errorMsg.html(errorMessage).show();
                            
                            // Redirect to home if not in permitted radius
                            setTimeout(redirectUser, 3000);
                        }
                    },
                    error: function() {
                        $('.checking-location').remove();
                        $('#geobloqueo-activate').show();
                        $errorMsg.text('Error al verificar la ubicación. Por favor, intente nuevamente.').show();
                        setTimeout(redirectUser, 3000);
                    }
                });
            },
            // Error callback
            function(error) {
                $('.checking-location').remove();
                $('#geobloqueo-activate').show();
                
                let errorMessage = '';
                
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage = 'Acceso a la ubicación denegado. Por favor, permita el acceso a su ubicación e intente nuevamente.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage = 'La información de ubicación no está disponible. Por favor, intente en un área con mejor señal.';
                        break;
                    case error.TIMEOUT:
                        errorMessage = 'Tiempo de espera agotado al obtener la ubicación. Por favor, intente nuevamente.';
                        break;
                    default:
                        errorMessage = 'Error desconocido al obtener la ubicación. Por favor, intente nuevamente.';
                        break;
                }
                
                $errorMsg.text(errorMessage).show();
                setTimeout(redirectUser, 3000);
            },
            // Options for high accuracy
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }

    // Verificar ubicación periódicamente
    function startLocationCheck() {
        const CHECK_INTERVAL = 10 * 60 * 1000; // 10 minutos
        
        setInterval(function() {
            if (shouldBlockPage()) {
                handleGeolocation();
            }
        }, CHECK_INTERVAL);
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Verificar si la página debe ser bloqueada
        if (shouldBlockPage()) {
            // Si no hay overlay, crearlo
            if ($('#geobloqueo-overlay').length === 0) {
                const overlay = `
                    <div id="geobloqueo-overlay">
                        <div class="geobloqueo-container">
                            <h2>${geobloqueo_vars.messages.title}</h2>
                            <p>${geobloqueo_vars.messages.description}</p>
                            <button id="geobloqueo-activate">${geobloqueo_vars.messages.button}</button>
                            <div id="geobloqueo-error" style="display: none;"></div>
                        </div>
                    </div>
                `;
                $('body').append(overlay);
            }

            // Bind click event to geolocation button
            $('#geobloqueo-activate').on('click', function(e) {
                e.preventDefault();
                handleGeolocation();
            });

            // Iniciar verificación periódica
            startLocationCheck();

            // Mostrar overlay siempre
            $('#geobloqueo-overlay').show();
        }
    });

})(jQuery);