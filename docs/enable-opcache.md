Enable PHP OPcache (XAMPP on Windows)

This guide shows the recommended, low-risk steps to enable and verify OPcache in your XAMPP environment.

1) Locate the php.ini used by XAMPP

- Typical path: C:\\xampp\\php\\php.ini
- You can confirm the loaded php.ini with PHP: run `php --ini` on CLI or use `phpinfo()` in the browser.
- We've added a small helper at `scripts/check_opcache.php` which prints the loaded php.ini and OPcache status.

2) Edit php.ini (recommended settings)

Open `C:\\xampp\\php\\php.ini` in a text editor (Notepad or VS Code) and add/ensure the following values under the OPcache section (create them if missing):

opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.validate_timestamps=1  ; set to 0 in production deploys once you have an atomic deploy process

Optional for assemblies with many files: increase memory_consumption to 256.

3) Restart Apache

Use the XAMPP Control Panel to stop and start Apache, or run (PowerShell):

C:\\xampp\\apache\\bin\\httpd.exe -k restart

If you installed Apache as a Windows service, you can also use:

net stop Apache2.4
net start Apache2.4

(Replace service name if different.)

4) Verify OPcache

Open in the browser:

http://localhost/nacos/scripts/check_opcache.php

Or run in CLI from project root:

php scripts/check_opcache.php

The script prints whether OPcache is loaded and lists key settings and a short status.

5) Production recommendations

- In production, once you have a deployment process, set `opcache.validate_timestamps=0` and `opcache.revalidate_freq=0` for best performance (this avoids filesystem checks) and clear OPcache at deploy time using `opcache_invalidate()` or restarting the PHP process.
- Monitor memory usage and `num_cached_scripts` using the script above; increase `memory_consumption` if the cache is frequently full.

6) Next steps (optional)

- Install APCu if you want a simple in-memory key-value cache for transient data.
- Add small `scripts/check_apcu.php` and a `includes/cache.php` wrapper that prefers APCu then file-cache.

If you want, I can:
- Apply a small `includes/cache.php` into the repo and wire it into homepage/partners (safe, reversible change).
- Add a short CI-time checklist to ensure OPcache settings are validated.
