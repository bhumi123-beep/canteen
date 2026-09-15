<?php
require_once __DIR__ . '/../../config/config.php';
$workspaceRole = 'waiter';
requireStaffRole([$workspaceRole]);
require __DIR__ . '/role_workspace.php';
