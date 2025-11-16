Enable compression (mod_deflate) and caching headers (mod_expires) in Apache (XAMPP on Windows)

Overview

This guide helps you enable gzip/deflate compression and configure caching headers for static assets. It uses Apache modules `mod_deflate`, `mod_expires`, and `mod_headers`.

1) Ensure Apache modules are enabled

Open `C:\\xampp\\apache\\conf\\httpd.conf` and ensure the following lines are not commented out (remove the leading # if present):

LoadModule deflate_module modules/mod_deflate.so
LoadModule headers_module modules/mod_headers.so
LoadModule expires_module modules/mod_expires.so

After editing, save the file and restart Apache.

2) Use the project .htaccess (already added)

A project-level `.htaccess` has been added at `C:\\xampp\\htdocs\\nacos\\.htaccess` with reasonable defaults for compression and Expires headers. If `AllowOverride` is disabled by your Apache config, you will need to add the same rules to your vhost or server config.

3) Verify compression

Open your site in a browser and inspect network headers (Chrome DevTools > Network). Look for the response header `Content-Encoding: gzip` (or deflate) on HTML/CSS/JS responses.

Or check from PowerShell with curl:

Invoke-WebRequest -Uri http://localhost/nacos/ -Headers @{"Accept-Encoding" = "gzip, deflate"} -OutFile -

4) Verify caching headers

Inspect the `Cache-Control` and `Expires` headers for static assets in the network tab. For example, open `http://localhost/nacos/assets/css/public.css` and inspect headers.

5) Notes & safety

- If you run into 500 errors after adding `.htaccess`, temporarily rename `.htaccess` and check Apache's error log at `C:\\xampp\\apache\\logs\\error.log`.
- If the server ignores `.htaccess`, ensure `AllowOverride All` is set for the site's Directory block in Apache config.

If you'd like, I can:
- Add a small PHP header-fallback for `assets/` responses, but proper server-level headers are preferred.
- Run a brief test for several assets and return the exact response headers (I can use PowerShell / Invoke-WebRequest). Would you like me to run that now?