<?php

use App\Modules\Identity\Domain\Authorization\PermissionName;

it('uses stable namespaced permission identifiers', function () {
    expect(PermissionName::ReconciliationManage->value)->toBe('reconciliation.manage')->and(PermissionName::AuditView->description())->not->toBeEmpty();
});
