-- CodGuard Extension - OpenCart 4.x Permission Fix
-- This adds permissions to the JSON permission field in oc_user_group table

-- First, let's see the current permissions (for reference)
-- SELECT user_group_id, name, permission FROM oc_user_group;

-- Update Administrator group (usually user_group_id = 1)
-- This adds both access and modify permissions for the CodGuard extension
UPDATE oc_user_group
SET permission = JSON_SET(
    permission,
    '$.access[999]', 'extension/codguard/module/codguard',
    '$.modify[999]', 'extension/codguard/module/codguard'
)
WHERE user_group_id = 1;

-- If you have other user groups, run this for each group:
-- UPDATE oc_user_group
-- SET permission = JSON_SET(
--     permission,
--     '$.access[999]', 'extension/codguard/module/codguard',
--     '$.modify[999]', 'extension/codguard/module/codguard'
-- )
-- WHERE user_group_id = 2;

-- Verify the permissions were added
SELECT
    user_group_id,
    name,
    JSON_EXTRACT(permission, '$.access') as access_permissions,
    JSON_EXTRACT(permission, '$.modify') as modify_permissions
FROM oc_user_group
WHERE user_group_id = 1;
