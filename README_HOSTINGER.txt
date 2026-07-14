ALL IN ONE ABROAD - Hostinger Deployment Guide
=============================================

1. Upload all files from this folder to your Hostinger public_html directory.

2. Create a MySQL database in Hostinger hPanel.

3. Update config.php with your Hostinger database credentials:

   $dbHost = 'localhost';
   $dbName = 'your_database_name';
   $dbUser = 'your_database_user';
   $dbPass = 'your_database_password';

4. Open this URL once to create the database tables:
   https://yourdomain.com/setup_db.php

5. Test the backend:
   https://yourdomain.com/api.php?action=health

6. Open the storefront:
   https://yourdomain.com/index.html

7. To view saved orders:
   https://yourdomain.com/orders.php

Notes:
- The site now supports registration/login and order storage.
- If the database is not configured yet, the health endpoint will still respond.
- After the database is configured, checkout orders will be saved into the MySQL orders table.
