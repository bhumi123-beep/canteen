<?php
require_once __DIR__ . '/../../config/config.php';
$workspaceRole = 'finance';
requireStaffRole([$workspaceRole]);
require __DIR__ . '/role_workspace.php';
