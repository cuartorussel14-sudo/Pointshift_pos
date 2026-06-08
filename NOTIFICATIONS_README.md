Notifications integration

What I added:
- SQL migration: `add_notifications_table.sql` to create the `notifications` table.
- `classes/Notification.php` helper with create/fetchRecent/markRead methods.
- `tools/expiry_notifications.php` script to scan for products expiring within 30 days and create expiry notifications.
- Hooked notification creation into:
  - `staff/manage_product.php` (when stock is updated)
  - `staff/process_order.php` (after order items decrement stock and on success/failure)
- UI: Bell icon + dropdown added to `staff/views/layout.php` and `views/layouts/main.php` showing recent notifications.

How to run:
1. Run the SQL migration (mysql client or phpMyAdmin):

```sql
-- from repo root
source add_notifications_table.sql;
```

2. To create expiry notifications manually (or add to cron):

```powershell
php tools/expiry_notifications.php
```

Notes:
- The notification table has fields: id, message, type, status, product_id, created_at.
- The scanner avoids duplicate expiry notifications within a 7-day window.
- If you'd like unread/unread toggles or a dedicated notifications page, I can add that next.
