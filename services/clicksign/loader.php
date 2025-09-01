<?php
// Este arquivo centraliza todos os 'require_once' para os serviços da ClickSign.

// Helpers
require_once __DIR__ . '/../../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../../helpers/BitrixContactHelper.php';
require_once __DIR__ . '/../../helpers/ClickSignHelper.php';
require_once __DIR__ . '/../../helpers/LogHelper.php';
require_once __DIR__ . '/../../helpers/UtilHelpers.php';
require_once __DIR__ . '/../../helpers/BitrixHelper.php';

// Enums
require_once __DIR__ . '/../../enums/ClickSignCodes.php';

// Repositories
require_once __DIR__ . '/../../Repositories/ClickSignDAO.php';

// Services
require_once __DIR__ . '/UtilService.php';
