<?php
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode(['ok' => false, 'error' => 'Live location sharing is disabled. Delivery maps use fixed order coordinates.']);
