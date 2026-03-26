<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case OrgAdmin = 'org_admin';
    case HrManager = 'hr_manager';
    case Viewer = 'viewer';
}
