<?php

namespace App\Modules\Identity\Domain\Authorization;

enum PermissionName: string
{
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case ReportsView = 'reports.view';
    case ReconciliationView = 'reconciliation.view';
    case ReconciliationManage = 'reconciliation.manage';
    case AuditView = 'audit.view';

    public function description(): string
    {
        return match ($this) {
            self::UsersView => 'View users', self::UsersManage => 'Create and update users',
            self::RolesManage => 'Manage role and permission assignments', self::ReportsView => 'View reports',
            self::ReconciliationView => 'View reconciliation results', self::ReconciliationManage => 'Run and resolve reconciliations',
            self::AuditView => 'View audit history',
        };
    }
}
