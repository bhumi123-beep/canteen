<?php
require_once __DIR__ . '/../../config/config.php';
$workspaceRole = 'cook';
requireStaffRole([$workspaceRole]);
require __DIR__ . '/role_workspace.php';
