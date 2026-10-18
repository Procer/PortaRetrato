<?php
// Copiar como backend/mirror.config.php SOLO en la PC del portarretrato.
// Su sola existencia activa el "modo espejo" (el Visor lee todo del disco local).
return [
    'remote' => 'https://fotos.zaratesystemgroup.com.ar/PortaRetrato',  // raíz de la app en el hosting
    'token'  => 'PEGAR_AQUI_EL_DEVICE_TOKEN',                           // el mismo DEVICE_TOKEN del config.php del hosting
    // 'cainfo' => 'C:/laragon/etc/ssl/cacert.pem',                     // solo si curl da error de certificado
];
