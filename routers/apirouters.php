<?php

// Este arquivo retorna um array mapeando URIs para suas ações de controller.
// Formato: '/uri' => ['ControllerName', 'methodName', 'HTTP_METHOD']
return [
    // Company
    '/company/criar' => ['CompanyController', 'criar', 'POST'],
    '/company/consultar' => ['CompanyController', 'consultar', 'GET'],
    '/company/editar' => ['CompanyController', 'editar', 'POST'],

    // ClickSign
    '/clicksign/new' => ['ClickSignController', 'GerarAssinatura', 'POST'],
    '/clicksign/up' => ['ClickSignController', 'atualizarDocumentoClickSign', 'POST'],
    '/clicksign/retorno' => ['ClickSignController', 'retornoClickSign', 'POST'], // Rota pública

    // Deal
    '/deal/criar' => ['DealController', 'criar', 'POST'],
    '/deal/consultar' => ['DealController', 'consultar', 'GET'],
    '/deal/editar' => ['DealController', 'editar', 'POST'],

    // Outras rotas
    '/disk/rename' => ['DiskController', 'RenomearPasta', 'POST'],
    '/extenso' => ['ExtensoController', 'executar', 'GET'],
    '/geraroptnd' => ['GeraroptndController', 'executar', 'POST'],
    '/mediahora' => ['MediaHoraController', 'executar', 'POST'],
    '/scheduler' => ['SchedulerController', 'executar', 'POST'],
    '/importar' => ['']
];
