<?php

require __DIR__ . '/../lib/response.php';

session_start();
session_unset();
session_destroy();

json_response(['success' => true]);
