<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require APP . '/legal.php';
render_legal('refund-policy');
