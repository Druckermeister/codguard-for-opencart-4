-- CodGuard Extension - Manual Permission Fix
-- Run this SQL script to manually add permissions for the CodGuard extension
-- This will allow all user groups to access and modify the extension

-- Add access permissions for all user groups
INSERT IGNORE INTO `oc_user_group_permission` (`user_group_id`, `type`, `route`)
SELECT `user_group_id`, 'access', 'extension/codguard/module/codguard'
FROM `oc_user_group`;

-- Add modify permissions for all user groups
INSERT IGNORE INTO `oc_user_group_permission` (`user_group_id`, `type`, `route`)
SELECT `user_group_id`, 'modify', 'extension/codguard/module/codguard'
FROM `oc_user_group`;

-- Verify the permissions were added
SELECT
    ug.name as 'User Group',
    ugp.type as 'Permission Type',
    ugp.route as 'Route'
FROM `oc_user_group_permission` ugp
JOIN `oc_user_group` ug ON ug.user_group_id = ugp.user_group_id
WHERE ugp.route = 'extension/codguard/module/codguard'
ORDER BY ug.name, ugp.type;

-- Note: If your database uses a different prefix than 'oc_',
-- replace 'oc_' with your prefix throughout this script
